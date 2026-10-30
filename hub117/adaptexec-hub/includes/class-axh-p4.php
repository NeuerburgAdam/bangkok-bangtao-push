<?php
/**
 * Sales Hub 1.17.0: know what is closing, and who read your email.
 *
 * - An expected close date on each deal, so the pipeline can be forecast
 *   by month and slipping deals stand out.
 * - Emails sent from the Hub can say when they are opened and when a link
 *   in them is clicked. The rep's own copy is sent separately, untracked,
 *   so reading it never counts as the prospect opening it.
 *
 * Additive only: one new table and one nullable column on leads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P4 {

	const DB = '1';
	const NS = 'axh/v1';

	/** Opens and clicks in the first seconds after sending are mail scanners, not people. */
	const QUIET = 20;

	/** The message being sent right now, while its HTML passes through wp_mail. */
	protected static $live = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 8 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'axh_p4_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$leads = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads ) ) !== $leads ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = AXH_DB::table( 'email_tracks' );
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(32) NOT NULL,
			lead_id bigint(20) unsigned NOT NULL,
			rep_id bigint(20) unsigned NOT NULL,
			activity_id bigint(20) unsigned NULL,
			to_email varchar(191) NOT NULL DEFAULT '',
			subject varchar(191) NOT NULL DEFAULT '',
			sent_at datetime NOT NULL,
			opens int(10) unsigned NOT NULL DEFAULT 0,
			first_open_at datetime NULL,
			last_open_at datetime NULL,
			clicks int(10) unsigned NOT NULL DEFAULT 0,
			last_click_at datetime NULL,
			last_url varchar(255) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY lead_id (lead_id),
			KEY rep_id (rep_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		$ok   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$leads}", 0 ); // phpcs:ignore
		if ( ! in_array( 'close_by', $have, true ) && false === $wpdb->query( "ALTER TABLE {$leads} ADD COLUMN close_by DATE NULL" ) ) { // phpcs:ignore
			$ok = false;
		}
		if ( $ok ) {
			update_option( 'axh_p4_db', self::DB, false );
		}
	}

	public static function routes() {
		register_rest_route( self::NS, '/p4/lead/(?P<id>\\d+)/close-by', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_close_by' ), 'permission_callback' => array( 'AXH_Rest', 'can_use' ) ) );
		// Public: the open pixel and the click redirect a prospect's mail app reaches.
		register_rest_route( self::NS, '/t/o/(?P<token>[a-f0-9]{32})', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'pixel' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/t/c/(?P<token>[a-f0-9]{32})', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'click' ), 'permission_callback' => '__return_true' ) );
	}

	/* --------------------------------------------------- expected close date */

	public static function rest_close_by( $req ) {
		global $wpdb;
		$u    = $req->get_url_params();
		$lead = AXH_DB::get_lead( (int) ( $u['id'] ?? 0 ) );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		if ( ! get_option( 'axh_p4_db' ) ) {
			return new WP_Error( 'axh_not_ready', 'Not ready yet. Reload and try again.', array( 'status' => 503 ) );
		}
		$b    = (array) $req->get_json_params();
		$date = trim( (string) ( $b['date'] ?? '' ) );
		if ( '' !== $date && ! preg_match( '/^(\\d{4})-(\\d{2})-(\\d{2})$/', $date, $m ) ) {
			return new WP_Error( 'axh_bad_date', 'Pick a date.', array( 'status' => 400 ) );
		}
		if ( '' !== $date && ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return new WP_Error( 'axh_bad_date', 'That is not a real date.', array( 'status' => 400 ) );
		}
		$old = (string) ( $lead['close_by'] ?? '' );
		$wpdb->update( AXH_DB::table( 'leads' ), array( 'close_by' => '' === $date ? null : $date ), array( 'id' => (int) $lead['id'] ) );
		if ( $old !== $date ) {
			$fmt = function ( $d ) {
				return $d ? wp_date( 'M j, Y', strtotime( $d . ' 12:00:00' ) ) : 'none';
			};
			AXH_DB::log( (int) $lead['id'], 'note', 'Expected close: ' . $fmt( $old ) . ' → ' . $fmt( $date ) );
		}
		return rest_ensure_response( array( 'close_by' => '' === $date ? null : $date ) );
	}

	/* --------------------------------------------------- email open tracking */

	protected static function sign( $token, $url ) {
		return substr( hash_hmac( 'sha256', 'c|' . $token . '|' . $url, wp_salt( 'auth' ) ), 0, 20 );
	}

	/**
	 * Called by the Hub's email sender before it sends: makes the record and
	 * puts the pixel and link redirects into the HTML on its way to wp_mail.
	 */
	public static function begin( $lead, $to, $subject ) {
		global $wpdb;
		self::$live = null;
		if ( ! get_option( 'axh_p4_db' ) ) {
			return false;
		}
		$token = bin2hex( random_bytes( 16 ) );
		$ok    = $wpdb->insert(
			AXH_DB::table( 'email_tracks' ),
			array(
				'token'    => $token,
				'lead_id'  => (int) $lead['id'],
				'rep_id'   => get_current_user_id(),
				'to_email' => mb_substr( (string) $to, 0, 191 ),
				'subject'  => mb_substr( (string) $subject, 0, 191 ),
				'sent_at'  => AXH_DB::now(),
			)
		);
		if ( ! $ok ) {
			return false;
		}
		self::$live = array( 'id' => (int) $wpdb->insert_id, 'token' => $token );
		add_filter( 'wp_mail', array( __CLASS__, 'inject' ), 99 );
		return true;
	}

	/** After sending: link the record to the timeline entry, or drop it if the send failed. */
	public static function end( $activity_id, $sent ) {
		global $wpdb;
		remove_filter( 'wp_mail', array( __CLASS__, 'inject' ), 99 );
		if ( ! self::$live ) {
			return;
		}
		if ( $sent ) {
			$wpdb->update( AXH_DB::table( 'email_tracks' ), array( 'activity_id' => $activity_id ? (int) $activity_id : null ), array( 'id' => self::$live['id'] ) );
		} else {
			$wpdb->delete( AXH_DB::table( 'email_tracks' ), array( 'id' => self::$live['id'] ) );
		}
		self::$live = null;
	}

	public static function inject( $args ) {
		if ( ! self::$live || empty( $args['message'] ) || false === stripos( (string) $args['message'], '</body>' ) ) {
			return $args;
		}
		$token = self::$live['token'];
		$html  = (string) $args['message'];
		// Links typed in the message become tracked links (only in the message's own paragraphs).
		$html = preg_replace_callback(
			'#(<p [^>]*>)(.*?)(</p>)#s',
			function ( $p ) use ( $token ) {
				$inner = preg_replace_callback(
					'#\\bhttps?://[^\\s<>"]+#i',
					function ( $m ) use ( $token ) {
						$shown = $m[0];
						$trail = '';
						while ( '' !== $shown && false !== strpos( '.,;:!?)', substr( $shown, -1 ) ) ) {
							$trail = substr( $shown, -1 ) . $trail;
							$shown = substr( $shown, 0, -1 );
						}
						$url = html_entity_decode( $shown, ENT_QUOTES, 'UTF-8' );
						if ( ! wp_http_validate_url( $url ) ) {
							return $m[0];
						}
						$go = add_query_arg( array( 'u' => rawurlencode( $url ), 's' => self::sign( $token, $url ) ), rest_url( self::NS . '/t/c/' . $token ) );
						return '<a href="' . esc_url( $go ) . '" style="color:#C8490A;">' . $shown . '</a>' . $trail;
					},
					$p[2]
				);
				return $p[1] . $inner . $p[3];
			},
			$html
		);
		$pixel        = '<img src="' . esc_url( rest_url( self::NS . '/t/o/' . $token ) ) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;opacity:0;">';
		$pos          = strripos( $html, '</body>' );
		$args['message'] = substr( $html, 0, $pos ) . $pixel . substr( $html, $pos );
		return $args;
	}

	protected static function row( $token ) {
		global $wpdb;
		$t = AXH_DB::table( 'email_tracks' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE token = %s", (string) $token ), ARRAY_A ); // phpcs:ignore
	}

	protected static function no_cache() {
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Referrer-Policy: no-referrer' );
		do_action( 'litespeed_control_set_nocache', 'axh email tracking' );
	}

	protected static function early( $row ) {
		return ( time() - strtotime( $row['sent_at'] . ' UTC' ) ) < self::QUIET;
	}

	public static function pixel( $req ) {
		global $wpdb;
		$row = get_option( 'axh_p4_db' ) ? self::row( $req['token'] ) : null;
		if ( $row && ! self::early( $row ) ) {
			$now = AXH_DB::now();
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . AXH_DB::table( 'email_tracks' ) . ' SET opens = opens + 1, last_open_at = %s, first_open_at = COALESCE(first_open_at, %s) WHERE id = %d', $now, $now, (int) $row['id'] ) ); // phpcs:ignore
			if ( ! (int) $row['opens'] ) {
				self::tell( $row, 'opened' );
			}
		}
		self::no_cache();
		header( 'Content-Type: image/gif' );
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ); // phpcs:ignore
		exit;
	}

	public static function click( $req ) {
		global $wpdb;
		$url = (string) $req->get_param( 'u' );
		$sig = (string) $req->get_param( 's' );
		$row = get_option( 'axh_p4_db' ) ? self::row( $req['token'] ) : null;
		if ( ! $row || ! hash_equals( self::sign( $req['token'], $url ), $sig ) || ! wp_http_validate_url( $url ) ) {
			self::no_cache();
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( ! self::early( $row ) ) {
			$now = AXH_DB::now();
			// A click means it was opened, whether or not the mail app loaded images.
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . AXH_DB::table( 'email_tracks' ) . ' SET clicks = clicks + 1, last_click_at = %s, last_url = %s, opens = GREATEST(opens, 1), first_open_at = COALESCE(first_open_at, %s), last_open_at = COALESCE(last_open_at, %s) WHERE id = %d', $now, mb_substr( $url, 0, 255 ), $now, $now, (int) $row['id'] ) ); // phpcs:ignore
			if ( ! (int) $row['clicks'] ) {
				self::tell( $row, 'clicked', $url );
			}
		}
		self::no_cache();
		header( 'Location: ' . esc_url_raw( $url ), true, 302 );
		exit;
	}

	protected static function tell( $row, $what, $url = '' ) {
		$lead = AXH_DB::table( 'leads' );
		global $wpdb;
		$l = $wpdb->get_row( $wpdb->prepare( "SELECT id, company, contact_name FROM {$lead} WHERE id = %d", (int) $row['lead_id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $l ) {
			return;
		}
		$who   = $l['contact_name'] ? $l['contact_name'] . ' (' . $l['company'] . ')' : $l['company'];
		$title = 'opened' === $what ? $who . ' opened your email' : $who . ' clicked a link in your email';
		$body  = '“' . $row['subject'] . '”' . ( $url ? "\n\n" . $url : '' );
		// No one is signed in here, so add() would skip nobody; it still respects their settings.
		AXH_Notify::add( (int) $row['rep_id'], 'email_opened', $title, $body, '#/lead/' . (int) $l['id'], (int) $l['id'] );
	}

	/** Tracking on a deal's emails, for its timeline. */
	public static function for_lead( $lead_id ) {
		global $wpdb;
		if ( ! get_option( 'axh_p4_db' ) ) {
			return array();
		}
		$t = AXH_DB::table( 'email_tracks' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT activity_id, to_email, sent_at, opens, first_open_at, last_open_at, clicks, last_click_at, last_url FROM {$t} WHERE lead_id = %d AND activity_id IS NOT NULL ORDER BY id DESC LIMIT 100", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
	}
}

AXH_P4::init();
