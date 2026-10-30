<?php
/**
 * Sales Hub 1.9.0: shared-link analytics, form upgrades and presenting logs.
 *
 * - Shared links count a view only when the deck actually runs in a browser
 *   (a signed view key from the page, so link-preview robots, scripts and
 *   the rep checking their own link never count). Each visit is a row in
 *   wp_axh_share_views with time per slide, how far they got, whether they
 *   finished and whether they clicked the button.
 * - The rep hears about it: a bell (and email) when the prospect opens the
 *   link, comes back to it, or clicks the button, and the lead's timeline
 *   shows the same.
 * - Forms: call/text consent, an instant reply to the person who filled it
 *   in, a "pick a time" button for reps with a booking link, a follow-up
 *   task the moment an enquiry lands, and repeat enquiries attach to the
 *   existing lead instead of making a duplicate.
 *
 * Everything here is additive: one new table, one new nullable column on
 * the forms table, and new options/user meta. Nothing is dropped or reset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P1 {

	const DB = '1';
	const NS = 'axh/v1';

	/** Set by record_submission() when an enquiry joined an existing lead. */
	public static $repeat = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ---------------------------------------------------------------- schema */

	public static function maybe_upgrade() {
		if ( get_option( 'axh_p1_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$forms = AXH_DB::table( 'forms' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $forms ) ) !== $forms ) {
			return; // Core tables not created yet.
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$views   = AXH_DB::table( 'share_views' );
		dbDelta(
			"CREATE TABLE {$views} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			share_id bigint(20) unsigned NOT NULL,
			sid varchar(24) NOT NULL,
			vid varchar(24) NOT NULL DEFAULT '',
			device varchar(12) NOT NULL DEFAULT '',
			slides smallint(5) unsigned NOT NULL DEFAULT 0,
			furthest smallint(5) unsigned NOT NULL DEFAULT 0,
			seconds int(10) unsigned NOT NULL DEFAULT 0,
			per_slide text NULL,
			finished tinyint(1) NOT NULL DEFAULT 0,
			cta tinyint(1) NOT NULL DEFAULT 0,
			started_at datetime NOT NULL,
			last_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY share_sid (share_id,sid),
			KEY share_id (share_id),
			KEY started_at (started_at)
			) {$charset};"
		);
		$ok   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $views ) ) === $views;
		$have = $wpdb->get_col( "SHOW COLUMNS FROM {$forms}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! in_array( 'extra', (array) $have, true ) ) {
			if ( false === $wpdb->query( "ALTER TABLE {$forms} ADD COLUMN extra LONGTEXT NULL" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
				$ok = false;
			}
		}
		if ( $ok ) {
			update_option( 'axh_p1_db', self::DB, false );
		}
	}

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		register_rest_route( self::NS, '/p1/shares', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_shares' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/p1/shares/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_share' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/p1/lead/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_lead' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/p1/ramp', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_ramp' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/p1/submissions', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_submissions' ),
			'permission_callback' => $auth,
		) );
	}

	/* ------------------------------------------------------------ boot data */

	/** Extra values the Hub needs at start-up (added to /bootstrap). */
	public static function boot() {
		$me = get_current_user_id();
		return array(
			'bookingUrl' => (string) get_user_meta( $me, 'axh_booking_url', true ),
			'phone'      => (string) get_user_meta( $me, 'axh_phone', true ),
			'tiers'      => AXH_Install::commission_tiers(),
			'demoCount'  => count( (array) AXH_Portal::demo_sites() ),
			'consent'    => self::consent_text( '' ),
			'ramp'       => self::ramp( $me ),
			'facts'      => self::facts( $me ),
			'push'       => class_exists( 'AXH_App' ) && AXH_App::available(),
		);
	}

	/* ------------------------------------------------- first-week checklist */

	/** The rep's first-week progress, kept on their account (any device). */
	public static function ramp( $user_id ) {
		$r = get_user_meta( (int) $user_id, 'axh_ramp', true );
		$r = is_array( $r ) ? $r : array();
		return array(
			'kit'    => isset( $r['kit'] ) && is_array( $r['kit'] ) ? $r['kit'] : (object) array(),
			'seen'   => isset( $r['seen'] ) && is_array( $r['seen'] ) ? $r['seen'] : (object) array(),
			'hidden' => ! empty( $r['hidden'] ),
		);
	}

	/** What the rep has actually done, so checklist steps tick themselves. */
	public static function facts( $user_id ) {
		global $wpdb;
		$c = function ( $table, $extra = '' ) use ( $wpdb, $user_id ) {
			$t = AXH_DB::table( $table );
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE rep_id = %d {$extra}", (int) $user_id ) ); // phpcs:ignore
		};
		$disc = 0;
		$d    = AXH_DB::table( 'discovery' );
		$l    = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $d ) ) === $d ) {
			$disc = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$d} d JOIN {$l} l ON l.id = d.lead_id WHERE l.rep_id = %d", (int) $user_id ) ); // phpcs:ignore
		}
		return array(
			'forms'     => $c( 'forms' ),
			'shares'    => $c( 'shares' ),
			'presented' => $c( 'sessions' ),
			'discovery' => $disc,
		);
	}

	public static function rest_ramp( $req ) {
		$b   = (array) $req->get_json_params();
		$me  = get_current_user_id();
		$cur = get_user_meta( $me, 'axh_ramp', true );
		$cur = is_array( $cur ) ? $cur : array();
		if ( isset( $b['kit'] ) && is_array( $b['kit'] ) ) {
			$kit = array();
			foreach ( array_slice( $b['kit'], 0, 100, true ) as $k => $v ) {
				if ( $v && preg_match( '/^\d{1,2}-\d{1,2}$/', (string) $k ) ) {
					$kit[ $k ] = 1;
				}
			}
			$cur['kit'] = $kit;
		}
		if ( ! empty( $b['seen'] ) ) {
			$cur['seen'] = isset( $cur['seen'] ) && is_array( $cur['seen'] ) ? $cur['seen'] : array();
			$cur['seen'][ sanitize_key( (string) $b['seen'] ) ] = 1;
		}
		if ( isset( $b['hidden'] ) ) {
			$cur['hidden'] = (bool) $b['hidden'];
		}
		update_user_meta( $me, 'axh_ramp', $cur );
		return rest_ensure_response( self::ramp( $me ) );
	}

	/* ----------------------------------------------------------- form extras */

	public static function extra_defaults() {
		return array(
			'consent'    => true,
			'autoreply'  => false,
			'ar_subject' => 'Thanks, {first_name} — we got your details',
			'ar_body'    => "Hi {first_name},\n\nThanks for reaching out. I have your details and I'll be in touch shortly.\n\nIf it's easier, you can pick a time that suits you: {booking_link}\n\n{rep_name}\n{rep_phone}",
			'booking'    => false,
		);
	}

	/** A form's 1.9 settings, with defaults for forms saved before 1.9. */
	public static function form_extra( $form ) {
		$raw = isset( $form['extra'] ) ? $form['extra'] : '';
		$x   = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		return wp_parse_args( is_array( $x ) ? $x : array(), self::extra_defaults() );
	}

	public static function clean_extra( $in, $old = array() ) {
		$in  = is_array( $in ) ? $in : array();
		$out = wp_parse_args( is_array( $old ) ? $old : array(), self::extra_defaults() );
		foreach ( array( 'consent', 'autoreply', 'booking' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$out[ $k ] = ! empty( $in[ $k ] ) && 'false' !== $in[ $k ];
			}
		}
		if ( array_key_exists( 'ar_subject', $in ) ) {
			$out['ar_subject'] = mb_substr( sanitize_text_field( $in['ar_subject'] ), 0, 160 );
		}
		if ( array_key_exists( 'ar_body', $in ) ) {
			$out['ar_body'] = mb_substr( sanitize_textarea_field( $in['ar_body'] ), 0, 4000 );
		}
		return $out;
	}

	/** The words the visitor agrees to when they tick the call/text box. */
	public static function consent_text( $rep_name ) {
		$who = $rep_name ? 'AdaptExec and ' . $rep_name : 'AdaptExec and my representative';
		return 'I agree that ' . $who . ' may call or text me about my enquiry at the number I gave, including by automated means. Consent is not a condition of purchase. Message and data rates may apply. Reply STOP to stop texts.';
	}

	/** Public-form config additions (consent box, booking button). */
	public static function public_config( $form, $rep ) {
		$x        = self::form_extra( $form );
		$fields   = (array) $form['fields'];
		$phone_on = in_array( 'phone', $fields, true );
		$booking  = '';
		if ( ! empty( $x['booking'] ) && $rep ) {
			$booking = esc_url_raw( (string) get_user_meta( $rep->ID, 'axh_booking_url', true ) );
		}
		return array(
			'consent'      => $phone_on && ! empty( $x['consent'] ) ? self::consent_text( $rep ? $rep->display_name : '' ) : '',
			'booking'      => $booking,
			'bookingLabel' => $booking ? 'Pick a time with ' . ( $rep ? strtok( $rep->display_name, ' ' ) : 'us' ) : '',
		);
	}

	/* ------------------------------------------------------ submissions hooks */

	/**
	 * An existing, non-archived lead of the form owner's with the same email
	 * (or, failing that, the same 10-digit phone number). 0 when none.
	 */
	public static function duplicate_lead( $rep_id, $email, $phone ) {
		global $wpdb;
		$t     = AXH_DB::table( 'leads' );
		$email = strtolower( trim( (string) $email ) );
		if ( $email && is_email( $email ) ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE rep_id = %d AND LOWER(email) = %s AND archived_at IS NULL ORDER BY id DESC LIMIT 1", (int) $rep_id, $email ) ); // phpcs:ignore
			if ( $id ) {
				return $id;
			}
		}
		$digits = substr( preg_replace( '/\D/', '', (string) $phone ), -10 );
		if ( strlen( $digits ) === 10 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, phone FROM {$t} WHERE rep_id = %d AND archived_at IS NULL AND phone <> '' ORDER BY id DESC LIMIT 2000", (int) $rep_id ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				if ( substr( preg_replace( '/\D/', '', (string) $r['phone'] ), -10 ) === $digits ) {
					return (int) $r['id'];
				}
			}
		}
		return 0;
	}

	/**
	 * Fill in what the existing lead is missing from a repeat enquiry. Never
	 * overwrites a value the rep already has.
	 */
	public static function merge_into( $lead_id, $lead ) {
		global $wpdb;
		$t   = AXH_DB::table( 'leads' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return;
		}
		$set = array( 'updated_at' => AXH_DB::now() );
		foreach ( array( 'contact_name', 'email', 'phone', 'website', 'city', 'state' ) as $k ) {
			if ( '' === trim( (string) $row[ $k ] ) && ! empty( $lead[ $k ] ) ) {
				$set[ $k ] = $lead[ $k ];
			}
		}
		if ( '' === trim( (string) $row['next_action'] ) ) {
			$set['next_action'] = 'Reply to enquiry';
		}
		if ( empty( $row['style_picks'] ) && ! empty( $lead['style_picks'] ) ) {
			$set['style_picks'] = $lead['style_picks'];
			$set['style_at']    = $lead['style_at'];
		}
		if ( empty( $row['tool_results'] ) && ! empty( $lead['tool_results'] ) ) {
			$set['tool_results'] = $lead['tool_results'];
		}
		$wpdb->update( $t, $set, array( 'id' => (int) $lead_id ) );
	}

	/** Speed to lead: a high-priority follow-up the moment an enquiry lands. */
	public static function speed_task( $rep_id, $lead_id, $name, $phone ) {
		global $wpdb;
		if ( ! $rep_id || ! get_option( 'axh_flow_db' ) ) {
			return;
		}
		$t = AXH_DB::table( 'tasks' );
		// One open reply task per lead is enough.
		$open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE lead_id = %d AND done_at IS NULL AND title LIKE %s", (int) $lead_id, 'Reply to %' ) ); // phpcs:ignore
		if ( $open ) {
			return;
		}
		$due = gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS );
		if ( '00:00' === substr( $due, 11, 5 ) ) {
			$due = substr( $due, 0, 17 ) . '01';
		}
		$wpdb->insert(
			$t,
			array(
				'lead_id'    => (int) $lead_id,
				'rep_id'     => (int) $rep_id,
				'title'      => mb_substr( 'Reply to ' . ( $name ? $name : 'new enquiry' ), 0, 191 ),
				'due_at'     => $due,
				'type'       => $phone ? 'call' : 'email',
				'priority'   => 1,
				'timed'      => 1,
				'notes'      => 'New form enquiry. The first business to reply usually wins it.',
				'created_at' => AXH_DB::now(),
				'updated_at' => AXH_DB::now(),
			)
		);
	}

	/** Merge fields for the instant reply. */
	public static function merge( $text, $vars ) {
		return strtr(
			(string) $text,
			array(
				'{first_name}'   => $vars['first_name'],
				'{name}'         => $vars['name'],
				'{company}'      => $vars['company'],
				'{rep_name}'     => $vars['rep_name'],
				'{rep_first}'    => $vars['rep_first'],
				'{rep_phone}'    => $vars['rep_phone'],
				'{rep_email}'    => $vars['rep_email'],
				'{booking_link}' => $vars['booking_link'],
			)
		);
	}

	/**
	 * The instant reply to the person who filled in the form, from their rep.
	 * Returns whether it was sent.
	 */
	public static function autoreply( $form, $answers, $lead_id ) {
		$x = self::form_extra( $form );
		if ( empty( $x['autoreply'] ) ) {
			return false;
		}
		$to = sanitize_email( $answers['email'] ?? '' );
		if ( ! is_email( $to ) ) {
			return false;
		}
		$rep     = get_userdata( (int) $form['rep_id'] );
		$name    = trim( sanitize_text_field( $answers['name'] ?? '' ) );
		$booking = $rep ? esc_url_raw( (string) get_user_meta( $rep->ID, 'axh_booking_url', true ) ) : '';
		$c       = AXH_Mail::contact();
		$vars    = array(
			'first_name'   => $name ? strtok( $name, ' ' ) : 'there',
			'name'         => $name ? $name : 'there',
			'company'      => sanitize_text_field( $answers['company'] ?? '' ),
			'rep_name'     => $rep ? $rep->display_name : 'AdaptExec',
			'rep_first'    => $rep ? strtok( $rep->display_name, ' ' ) : 'AdaptExec',
			'rep_phone'    => $rep && get_user_meta( $rep->ID, 'axh_phone', true ) ? (string) get_user_meta( $rep->ID, 'axh_phone', true ) : $c['phone'],
			'rep_email'    => $rep ? $rep->user_email : $c['email'],
			'booking_link' => $booking,
		);
		$subject = trim( self::merge( $x['ar_subject'], $vars ) );
		$body    = self::merge( $x['ar_body'], $vars );
		if ( ! $booking ) {
			// No booking link set: drop the line that would have held it.
			$body = preg_replace( '/^[^\n]*\{booking_link\}[^\n]*\n?/m', '', $x['ar_body'] );
			$body = self::merge( $body, $vars );
		}
		$paras = array_values( array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", $body ) ) ) );
		$args  = array(
			'heading'   => '',
			'preheader' => $paras ? wp_trim_words( $paras[0], 18 ) : $subject,
			'paras'     => $paras,
			'tag'       => '',
			'contact'   => array( 'name' => $vars['rep_name'], 'phone' => $vars['rep_phone'], 'email' => $vars['rep_email'] ),
			'footer'    => 'You are getting this because you sent your details through an AdaptExec form. Reply to this email to reach ' . $vars['rep_first'] . ' directly.',
		);
		if ( $booking ) {
			$args['cta'] = array( 'Pick a time', $booking );
		}
		$headers = array();
		if ( $rep && is_email( $rep->user_email ) ) {
			$headers[] = 'Reply-To: ' . $rep->display_name . ' <' . $rep->user_email . '>';
		}
		$ok = AXH_Mail::send( $to, $subject ? $subject : 'Thanks — we got your details', $args, $headers );
		if ( $ok && $lead_id ) {
			AXH_DB::log( $lead_id, 'email', 'Instant reply sent: ' . mb_substr( $subject, 0, 150 ), 'To ' . $to . ' from the form\'s instant-reply settings.' );
		}
		return $ok;
	}

	/* ----------------------------------------------------- shared-link views */

	/** "<time>.<sig>": proof a view came from a deck page we served. */
	public static function view_key( $token ) {
		$t = time();
		return $t . '.' . substr( hash_hmac( 'sha256', 'vk|' . $token . '|' . $t, wp_salt( 'nonce' ) ), 0, 16 );
	}

	public static function check_view_key( $vk, $token ) {
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{16})$/', (string) $vk, $m ) ) {
			return false;
		}
		$t = (int) $m[1];
		if ( $t > time() + 300 || $t < time() - 2 * DAY_IN_SECONDS ) {
			return false;
		}
		return hash_equals( substr( hash_hmac( 'sha256', 'vk|' . $token . '|' . $t, wp_salt( 'nonce' ) ), 0, 16 ), $m[2] );
	}

	protected static function id24( $v ) {
		return substr( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $v ), 0, 24 );
	}

	/** Deck title and per-slide titles, for the link page and timeline. */
	public static function deck_info( $slug ) {
		$out = array( 'title' => $slug, 'slides' => array() );
		foreach ( (array) AXH_Portal::slide_decks() as $d ) {
			if ( $d['slug'] !== $slug ) {
				continue;
			}
			$out['title'] = $d['title'];
			$file         = ! empty( $d['file'] ) ? $d['file'] : AXH_DIR . 'slides/' . $slug . '.json';
			if ( is_readable( $file ) ) {
				$j = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				foreach ( (array) ( $j['slides'] ?? array() ) as $s ) {
					if ( ! is_array( $s ) || empty( $s['type'] ) ) {
						continue; // The player skips these too, so indexes line up.
					}
					$h = $s['h'] ?? ( $s['title'] ?? ( $s['name'] ?? '' ) );
					$h = trim( html_entity_decode( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), ' ', (string) $h ) ), ENT_QUOTES ) );
					$out['slides'][] = $h ? $h : ucfirst( (string) $s['type'] );
				}
			}
			break;
		}
		return $out;
	}

	/** Who the link is for, in words: the lead's company, else the prospect line. */
	protected static function who( $share ) {
		if ( ! empty( $share['lead_id'] ) ) {
			global $wpdb;
			$t  = AXH_DB::table( 'leads' );
			$co = $wpdb->get_var( $wpdb->prepare( "SELECT company FROM {$t} WHERE id = %d", (int) $share['lead_id'] ) ); // phpcs:ignore
			if ( $co ) {
				return $co;
			}
		}
		return $share['prospect'] ? $share['prospect'] : ( $share['title'] ? $share['title'] : 'Someone' );
	}

	/**
	 * Public endpoint for the deck player. Events: open, beat, finish, cta.
	 * Anything that does not carry a valid view key is ignored (but answered
	 * with ok, so the player never shows an error).
	 */
	public static function share_event( $data ) {
		global $wpdb;
		$share = AXH_DB::share_by_token( $data['token'] ?? '' );
		if ( ! $share ) {
			return array( 'ok' => true );
		}
		$event = sanitize_key( $data['event'] ?? '' );
		$sid   = self::id24( $data['sid'] ?? '' );

		// A deck page from before 1.9 (no session id): count as before.
		if ( '' === $sid ) {
			if ( in_array( $event, array( 'finish', 'cta' ), true ) ) {
				AXH_DB::count_share_event( $share['id'], $event );
			}
			return array( 'ok' => true );
		}
		if ( ! self::check_view_key( $data['vk'] ?? '', $share['token'] ) || AXH_Portal::is_preview_bot() ) {
			return array( 'ok' => true );
		}

		$t   = AXH_DB::table( 'share_views' );
		$s   = AXH_DB::table( 'shares' );
		$now = AXH_DB::now();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE share_id = %d AND sid = %s", $share['id'], $sid ), ARRAY_A ); // phpcs:ignore

		if ( 'open' === $event ) {
			if ( $row ) {
				return array( 'ok' => true );
			}
			// No more than 40 new visits an hour per link from one address.
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$key = 'axh_sv_' . md5( $ip . '|' . $share['id'] );
			$n   = (int) get_transient( $key );
			if ( $n >= 40 ) {
				return array( 'ok' => true );
			}
			set_transient( $key, $n + 1, HOUR_IN_SECONDS );

			$vid  = self::id24( $data['vid'] ?? '' );
			$prev = $wpdb->get_row( $wpdb->prepare( "SELECT last_at FROM {$t} WHERE share_id = %d ORDER BY last_at DESC LIMIT 1", $share['id'] ), ARRAY_A ); // phpcs:ignore
			$seen = $vid ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE share_id = %d AND vid = %s", $share['id'], $vid ) ) : 0; // phpcs:ignore
			$dev  = sanitize_key( $data['dev'] ?? '' );
			$wpdb->insert(
				$t,
				array(
					'share_id'   => $share['id'],
					'sid'        => $sid,
					'vid'        => $vid,
					'device'     => in_array( $dev, array( 'phone', 'tablet', 'desktop' ), true ) ? $dev : '',
					'slides'     => min( 999, max( 0, (int) ( $data['n'] ?? 0 ) ) ),
					'furthest'   => 1,
					'seconds'    => 0,
					'started_at' => $now,
					'last_at'    => $now,
				)
			);
			if ( ! $wpdb->insert_id ) {
				return array( 'ok' => true );
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$s} SET views = views + 1, last_viewed = %s WHERE id = %d", $now, $share['id'] ) ); // phpcs:ignore

			// Tell the rep on the first visit, and when they come back after a
			// break of three hours or more — not on every refresh.
			$first = ! $prev;
			$back  = $prev && strtotime( $prev['last_at'] ) < strtotime( $now ) - 3 * HOUR_IN_SECONDS;
			if ( $first || $back ) {
				$visits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE share_id = %d", $share['id'] ) ); // phpcs:ignore
				self::tell( $share, $first ? 'open' : 'back', array( 'visits' => $visits, 'device' => $dev, 'returning' => $seen > 0 ) );
			}
			return array( 'ok' => true );
		}

		if ( ! $row ) {
			return array( 'ok' => true );
		}
		$set = array( 'last_at' => $now );

		// Time per slide: the player sends running totals, so a lost or
		// repeated beat never double-counts. Capped at 20 minutes a slide.
		if ( isset( $data['t'] ) && is_array( $data['t'] ) ) {
			$per = json_decode( (string) $row['per_slide'], true );
			$per = is_array( $per ) ? $per : array();
			foreach ( array_slice( $data['t'], 0, 400, true ) as $i => $sec ) {
				$i = (int) $i;
				if ( $i < 0 || $i > 998 ) {
					continue;
				}
				$per[ $i ] = max( (int) ( $per[ $i ] ?? 0 ), min( 1200, max( 0, (int) $sec ) ) );
			}
			ksort( $per );
			$set['per_slide'] = wp_json_encode( $per );
			$set['seconds']   = min( 86400, array_sum( $per ) );
		}
		if ( isset( $data['far'] ) ) {
			$set['furthest'] = max( (int) $row['furthest'], min( 999, max( 1, (int) $data['far'] ) ) );
		}

		if ( 'finish' === $event && ! (int) $row['finished'] ) {
			$set['finished'] = 1;
			$wpdb->query( $wpdb->prepare( "UPDATE {$s} SET finishes = finishes + 1 WHERE id = %d", $share['id'] ) ); // phpcs:ignore
			$done_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE share_id = %d AND finished = 1", $share['id'] ) ); // phpcs:ignore
			if ( ! $done_before && ! empty( $share['lead_id'] ) ) {
				self::timeline( $share, 'Watched your “' . self::deck_info( $share['deck'] )['title'] . '” deck to the end' );
			}
		}
		if ( 'cta' === $event && ! (int) $row['cta'] ) {
			$set['cta'] = 1;
			$wpdb->query( $wpdb->prepare( "UPDATE {$s} SET cta_clicks = cta_clicks + 1 WHERE id = %d", $share['id'] ) ); // phpcs:ignore
			self::tell( $share, 'cta', array() );
		}
		$wpdb->update( $t, $set, array( 'id' => (int) $row['id'] ) );
		return array( 'ok' => true );
	}

	protected static function timeline( $share, $subject, $body = '' ) {
		if ( empty( $share['lead_id'] ) ) {
			return;
		}
		global $wpdb;
		$now = AXH_DB::now();
		$wpdb->insert(
			AXH_DB::table( 'activity' ),
			array(
				'lead_id'     => (int) $share['lead_id'],
				'rep_id'      => (int) $share['rep_id'],
				'type'        => 'share',
				'subject'     => mb_substr( $subject, 0, 191 ),
				'body'        => $body,
				'occurred_at' => $now,
				'created_at'  => $now,
			)
		);
		$wpdb->update( AXH_DB::table( 'leads' ), array( 'updated_at' => $now ), array( 'id' => (int) $share['lead_id'] ) );
	}

	/** Bell + email + timeline for a link event. */
	protected static function tell( $share, $what, $info ) {
		$who   = self::who( $share );
		$deck  = self::deck_info( $share['deck'] )['title'];
		$link  = '#/link/' . (int) $share['id'];
		$where = array( 'phone' => ' on a phone', 'tablet' => ' on a tablet', 'desktop' => ' on a computer' );
		$dev   = $where[ $info['device'] ?? '' ] ?? '';
		if ( 'open' === $what ) {
			$title = $who . ' opened your deck link';
			$body  = $who . ' just opened “' . $deck . '”' . $dev . '. A quick follow-up while it is fresh works best.';
			$type  = 'share_viewed';
			self::timeline( $share, 'Opened your “' . $deck . '” deck link' . $dev );
		} elseif ( 'back' === $what ) {
			$n     = (int) ( $info['visits'] ?? 2 );
			$title = $who . ' is looking at your deck again';
			$body  = $who . ' came back to “' . $deck . '”' . $dev . ' (visit ' . $n . '). Repeat visits are a strong buying signal.';
			$type  = 'share_viewed';
			self::timeline( $share, 'Came back to your “' . $deck . '” deck (visit ' . $n . ')' );
		} else {
			$title = $who . ' clicked the button on your deck';
			$body  = $who . ' clicked “' . ( $share['cta_label'] ? $share['cta_label'] : 'Tell us about your business' ) . '” on “' . $deck . '”. Their form answers, if they send them, arrive as a lead.';
			$type  = 'share_cta';
			self::timeline( $share, 'Clicked the button on your “' . $deck . '” deck' );
		}
		$rep = (int) $share['rep_id'];
		// The visitor is anonymous, so the "not about yourself" rule in
		// AXH_Notify::add() never applies; notify the link's owner only.
		AXH_Notify::add( $rep, $type, $title, $body, $link, $share['lead_id'] ? (int) $share['lead_id'] : null, array( 'cta' => array( 'See the visit', axh_portal_url( $link ) ) ) );
	}

	/* ------------------------------------------------------------- reports */

	/** Per-link numbers for the Shared links table. */
	public static function share_stats( $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$t    = AXH_DB::table( 'share_views' );
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results( "SELECT share_id, COUNT(*) AS visits, COUNT(DISTINCT NULLIF(vid,'')) AS people, SUM(finished) AS finished, SUM(cta) AS cta, ROUND(AVG(seconds)) AS avg_seconds, MAX(last_at) AS last_at, SUM(CASE WHEN last_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS day_visits FROM {$t} WHERE share_id IN ({$in}) GROUP BY share_id", ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$hot = ( (int) $r['day_visits'] >= 2 ) || ( (int) $r['cta'] > 0 && strtotime( $r['last_at'] ) > time() - 3 * DAY_IN_SECONDS );
			$out[ (int) $r['share_id'] ] = array(
				'visits'      => (int) $r['visits'],
				'people'      => (int) $r['people'],
				'finished'    => (int) $r['finished'],
				'cta'         => (int) $r['cta'],
				'avg_seconds' => (int) $r['avg_seconds'],
				'last_at'     => $r['last_at'],
				'hot'         => $hot,
			);
		}
		return $out;
	}

	public static function rest_shares( $req ) {
		$shares = AXH_DB::get_shares();
		$stats  = self::share_stats( wp_list_pluck( $shares, 'id' ) );
		$titles = array();
		foreach ( (array) AXH_Portal::slide_decks() as $d ) {
			$titles[ $d['slug'] ] = $d['title'];
		}
		foreach ( $shares as &$sh ) {
			$sh['deck_title'] = $titles[ $sh['deck'] ] ?? $sh['deck'];
			$sh['stats']      = $stats[ $sh['id'] ] ?? null;
			$sh['rep_name']   = axh_is_manager() ? ( get_userdata( $sh['rep_id'] ) ? get_userdata( $sh['rep_id'] )->display_name : '' ) : '';
		}
		return rest_ensure_response( array(
			'shares' => $shares,
			'decks'  => AXH_Portal::slide_decks(),
			'forms'  => AXH_DB::get_forms(),
		) );
	}

	public static function rest_share( $req ) {
		global $wpdb;
		$share = AXH_DB::get_share( (int) $req['id'] );
		if ( ! $share ) {
			return new WP_Error( 'axh_no_share', 'Not found.', array( 'status' => 404 ) );
		}
		$t      = AXH_DB::table( 'share_views' );
		$visits = $wpdb->get_results( $wpdb->prepare( "SELECT id, vid, device, slides, furthest, seconds, per_slide, finished, cta, started_at, last_at FROM {$t} WHERE share_id = %d ORDER BY started_at DESC LIMIT 200", $share['id'] ), ARRAY_A ); // phpcs:ignore
		$deck   = self::deck_info( $share['deck'] );
		$sum    = array();
		$reach  = array();
		$people = array();
		foreach ( $visits as &$v ) {
			$per = json_decode( (string) $v['per_slide'], true );
			$per = is_array( $per ) ? $per : array();
			foreach ( $per as $i => $sec ) {
				$sum[ (int) $i ] = ( $sum[ (int) $i ] ?? 0 ) + (int) $sec;
			}
			for ( $i = 0; $i < (int) $v['furthest']; $i++ ) {
				$reach[ $i ] = ( $reach[ $i ] ?? 0 ) + 1;
			}
			if ( $v['vid'] ) {
				$people[ $v['vid'] ] = true;
			}
			$v['per_slide'] = $per;
			$v['person']    = $v['vid'] ? array_search( $v['vid'], array_keys( $people ), true ) : null;
			unset( $v['vid'] );
		}
		unset( $v );
		$n      = count( $visits );
		$slides = array();
		$count  = max( count( $deck['slides'] ), $sum ? max( array_keys( $sum ) ) + 1 : 0 );
		for ( $i = 0; $i < $count; $i++ ) {
			$slides[] = array(
				'title'   => $deck['slides'][ $i ] ?? ( 'Slide ' . ( $i + 1 ) ),
				'seconds' => (int) ( $sum[ $i ] ?? 0 ),
				'avg'     => $n ? (int) round( ( $sum[ $i ] ?? 0 ) / $n ) : 0,
				'reached' => (int) ( $reach[ $i ] ?? 0 ),
			);
		}
		$lead = $share['lead_id'] ? AXH_DB::get_lead( $share['lead_id'] ) : null;
		$share['deck_title'] = $deck['title'];
		$share['lead']       = $lead ? array( 'id' => (int) $lead['id'], 'company' => $lead['company'], 'contact_name' => $lead['contact_name'], 'email' => $lead['email'], 'phone' => $lead['phone'] ) : null;
		$share['form']       = $share['form_id'] ? AXH_DB::form_public( $share['form_id'] ) : null;
		$stats               = self::share_stats( array( $share['id'] ) );
		return rest_ensure_response( array(
			'share'  => $share,
			'stats'  => $stats[ $share['id'] ] ?? null,
			'visits' => $visits,
			'slides' => $slides,
			'legacy' => max( 0, (int) $share['views'] - $n ),
		) );
	}

	/** Lead page extras: its links with numbers, consent, and what it pays. */
	public static function rest_lead( $req ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( (int) $req['id'] );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$s      = AXH_DB::table( 'shares' );
		$shares = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$s} WHERE lead_id = %d ORDER BY id DESC LIMIT 50", (int) $lead['id'] ), ARRAY_A ); // phpcs:ignore
		$stats  = self::share_stats( wp_list_pluck( (array) $shares, 'id' ) );
		$titles = array();
		foreach ( (array) AXH_Portal::slide_decks() as $d ) {
			$titles[ $d['slug'] ] = $d['title'];
		}
		$links = array();
		foreach ( (array) $shares as $r ) {
			$links[] = array(
				'id'         => (int) $r['id'],
				'deck'       => $r['deck'],
				'deck_title' => $titles[ $r['deck'] ] ?? $r['deck'],
				'url'        => home_url( '/s/' . $r['token'] . '/' ),
				'active'     => (int) $r['active'],
				'views'      => (int) $r['views'],
				'stats'      => $stats[ (int) $r['id'] ] ?? null,
				'created_at' => $r['created_at'],
			);
		}
		// Did they agree to calls/texts on the form they sent?
		$sub     = AXH_DB::table( 'submissions' );
		$consent = null;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT payload FROM {$sub} WHERE lead_id = %d ORDER BY id DESC LIMIT 20", (int) $lead['id'] ) ) as $payload ) { // phpcs:ignore
			$p = json_decode( (string) $payload, true );
			if ( is_array( $p ) && isset( $p['contact_consent'] ) ) {
				$consent = 0 === strpos( (string) $p['contact_consent'], 'Yes' ) ? 'yes' : 'no';
				break; // The most recent answer wins.
			}
		}
		return rest_ensure_response( array(
			'links'   => $links,
			'consent' => $consent,
			'pays'    => self::pays( $lead ),
			'files'   => class_exists( 'AXH_Files' ) && get_option( 'axh_files_db' ) ? AXH_Files::list_for( (int) $lead['id'] ) : array(),
		) );
	}

	/**
	 * What this deal pays its rep: the locked rate on a closed deal, else
	 * the rep's current rate (override, or the agreement schedule) applied
	 * to the estimated value.
	 */
	public static function pays( $lead ) {
		$value = (float) ( (float) $lead['contract_value'] ? $lead['contract_value'] : $lead['est_value'] );
		$rate  = (float) $lead['commission_rate'];
		$basis = 'locked';
		if ( ! $rate ) {
			$basis = 'estimate';
			$rate  = (float) AXH_Install::commission_rate( $value, (int) $lead['rep_id'] );
		}
		return array(
			'value'  => $value,
			'rate'   => $rate,
			'amount' => round( $value * $rate / 100, 2 ),
			'basis'  => $basis,
			'closed' => ! empty( $lead['closed_at'] ),
		);
	}

	/** Every submission the user may see, newest first, paged; or all as CSV. */
	public static function rest_submissions( $req ) {
		global $wpdb;
		$t     = AXH_DB::table( 'submissions' );
		$f     = AXH_DB::table( 'forms' );
		$where = array( axh_is_manager() ? '1=1' : $wpdb->prepare( 's.rep_id = %d', get_current_user_id() ) );
		$form  = (int) $req->get_param( 'form_id' );
		if ( $form ) {
			$where[] = $wpdb->prepare( 's.form_id = %d', $form );
		}
		$q = trim( (string) $req->get_param( 'q' ) );
		if ( '' !== $q ) {
			$where[] = $wpdb->prepare( 's.payload LIKE %s', '%' . $wpdb->esc_like( $q ) . '%' );
		}
		$csv   = (bool) $req->get_param( 'csv' );
		$page  = max( 1, (int) $req->get_param( 'page' ) );
		$per   = $csv ? 5000 : 50;
		$sql   = "SELECT s.id, s.form_id, s.lead_id, s.payload, s.source, s.referrer, s.created_at, f.name AS form_name, f.custom FROM {$t} s LEFT JOIN {$f} f ON f.id = s.form_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY s.id DESC LIMIT ' . (int) $per . ' OFFSET ' . (int) ( ( $page - 1 ) * $per );
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} s WHERE " . implode( ' AND ', $where ) ); // phpcs:ignore

		$labels = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			foreach ( $step['fields'] as $fl ) {
				$labels[ $fl['key'] ] = $fl['label'];
			}
		}
		$labels['style_picks']     = 'Website style picks';
		$labels['contact_consent'] = 'Call/text consent';
		$out = array();
		foreach ( (array) $rows as $r ) {
			$p   = json_decode( (string) $r['payload'], true );
			$p   = is_array( $p ) ? $p : array();
			$lab = $labels;
			foreach ( (array) json_decode( (string) $r['custom'], true ) as $i => $c ) {
				if ( ! empty( $c['label'] ) ) {
					$lab[ 'custom_' . $i ] = $c['label'];
				}
			}
			if ( ! empty( $p['_labels'] ) && is_array( $p['_labels'] ) ) {
				$lab = array_merge( $lab, $p['_labels'] );
			}
			unset( $p['_labels'], $p['tool_results'] );
			$ans = array();
			foreach ( $p as $k => $v ) {
				$v = is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v;
				if ( '' !== trim( $v ) ) {
					$ans[] = array( 'key' => $k, 'label' => $lab[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ), 'value' => $v );
				}
			}
			$out[] = array(
				'id'         => (int) $r['id'],
				'form_id'    => (int) $r['form_id'],
				'form_name'  => (string) $r['form_name'],
				'lead_id'    => $r['lead_id'] ? (int) $r['lead_id'] : null,
				'source'     => (string) $r['source'],
				'referrer'   => (string) $r['referrer'],
				'created_at' => $r['created_at'],
				'name'       => (string) ( $p['name'] ?? '' ),
				'email'      => (string) ( $p['email'] ?? '' ),
				'phone'      => (string) ( $p['phone'] ?? '' ),
				'company'    => (string) ( $p['company'] ?? '' ),
				'answers'    => $ans,
			);
		}
		if ( $csv ) {
			$cols = array();
			foreach ( $out as $o ) {
				foreach ( $o['answers'] as $a ) {
					$cols[ $a['label'] ] = true;
				}
			}
			$cols  = array_keys( $cols );
			$lines = array( array_merge( array( 'Submitted (UTC)', 'Form', 'Source' ), $cols ) );
			foreach ( $out as $o ) {
				$m = array();
				foreach ( $o['answers'] as $a ) {
					$m[ $a['label'] ] = $a['value'];
				}
				$line = array( $o['created_at'], $o['form_name'], $o['source'] );
				foreach ( $cols as $c ) {
					$line[] = $m[ $c ] ?? '';
				}
				$lines[] = $line;
			}
			$text = '';
			foreach ( $lines as $line ) {
				$text .= implode(
					',',
					array_map(
						function ( $v ) {
							$v = (string) $v;
							// Spreadsheet formula injection: neutralise leading = + - @.
							if ( '' !== $v && false !== strpos( '=+-@', $v[0] ) ) {
								$v = "'" . $v;
							}
							return '"' . str_replace( '"', '""', $v ) . '"';
						},
						$line
					)
				) . "\r\n";
			}
			return rest_ensure_response( array( 'csv' => $text, 'count' => count( $out ) ) );
		}
		return rest_ensure_response( array( 'submissions' => $out, 'total' => $total, 'page' => $page, 'pages' => (int) ceil( $total / $per ) ) );
	}

	/* ------------------------------------------------------------ presenting */

	/**
	 * A presentation to a lead just ended: put how far it went and how long
	 * it took on the timeline entry the start created.
	 */
	public static function presented( $session, $furthest, $seconds, $slides ) {
		if ( empty( $session['lead_id'] ) ) {
			return;
		}
		global $wpdb;
		$a     = AXH_DB::table( 'activity' );
		$deck  = self::deck_info( $session['deck'] )['title'];
		$mins  = (int) round( $seconds / 60 );
		$total = $slides ? (int) $slides : count( self::deck_info( $session['deck'] )['slides'] );
		$line  = 'Reached slide ' . (int) $furthest . ( $total ? ' of ' . $total : '' ) . ' · ' . ( $mins < 1 ? 'under a minute' : $mins . ' min' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$a} WHERE lead_id = %d AND type = 'presentation' AND created_at >= %s ORDER BY id ASC LIMIT 1", (int) $session['lead_id'], $session['started_at'] ) ); // phpcs:ignore
		if ( $id ) {
			$wpdb->update( $a, array( 'subject' => mb_substr( 'Presented “' . $deck . '”', 0, 191 ), 'body' => $line ), array( 'id' => $id ) );
		} else {
			AXH_DB::log( (int) $session['lead_id'], 'presentation', 'Presented “' . $deck . '”', $line );
		}
	}
}

AXH_P1::init();
