<?php
/**
 * Sales Hub 1.16.0: cleaner records, faster hands.
 *
 * - Merge two deals that are the same business. Everything on the one
 *   merged away (timeline, follow-ups, deck links, form entries, files,
 *   people) moves to the one kept; the other is archived and points to it,
 *   never deleted, and the merge can be undone.
 * - Saved message templates, per person, for email and text.
 * - Mentions: tell AdaptExec (or the deal's rep) about a note, with an
 *   alert and an email.
 *
 * Additive only: one new table, one nullable column on leads, user meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P3 {

	const DB = '1';
	const NS = 'axh/v1';

	/** Lead fields a person may take from the deal being merged away. */
	const FIELDS = array( 'company', 'contact_name', 'title', 'email', 'phone', 'website', 'industry', 'city', 'state', 'est_value', 'source', 'stage' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 7 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'axh_p3_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$leads = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads ) ) !== $leads ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$m = AXH_DB::table( 'merges' );
		dbDelta(
			"CREATE TABLE {$m} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keep_id bigint(20) unsigned NOT NULL,
			gone_id bigint(20) unsigned NOT NULL,
			by_user bigint(20) unsigned NOT NULL DEFAULT 0,
			payload longtext NULL,
			created_at datetime NOT NULL,
			undone_at datetime NULL,
			PRIMARY KEY  (id),
			KEY keep_id (keep_id),
			KEY gone_id (gone_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		$ok   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $m ) ) === $m;
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$leads}", 0 ); // phpcs:ignore
		if ( ! in_array( 'merged_into', $have, true ) && false === $wpdb->query( "ALTER TABLE {$leads} ADD COLUMN merged_into BIGINT UNSIGNED NULL" ) ) { // phpcs:ignore
			$ok = false;
		}
		if ( $ok ) {
			update_option( 'axh_p3_db', self::DB, false );
		}
	}

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		$r    = function ( $route, $method, $cb ) use ( $auth ) {
			register_rest_route( self::NS, $route, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $auth ) );
		};
		$r( '/p3/merge', 'GET', 'rest_preview' );
		$r( '/p3/merge', 'POST', 'rest_merge' );
		$r( '/p3/merge/(?P<id>\\d+)/undo', 'POST', 'rest_undo' );
		$r( '/p3/templates', 'GET', 'get_templates' );
		$r( '/p3/templates', 'POST', 'save_templates' );
		$r( '/p3/people', 'GET', 'mentionable' );
		$r( '/p3/mention', 'POST', 'rest_mention' );
	}

	protected static function url_id( $req ) {
		$u = $req->get_url_params();
		return isset( $u['id'] ) ? (int) $u['id'] : 0;
	}

	/* ---------------------------------------------------------------- merge */

	/** Lead tables whose rows simply follow the deal. */
	protected static function movable() {
		$t = array( 'activity', 'tasks', 'shares', 'submissions', 'sessions' );
		if ( get_option( 'axh_files_db' ) ) {
			$t[] = 'lead_files';
		}
		if ( get_option( 'axh_p2_db' ) ) {
			$t[] = 'lead_contacts';
			$t[] = 'activity_bin';
		}
		return $t;
	}

	protected static function count_rows( $lead_id ) {
		global $wpdb;
		$out = array();
		foreach ( self::movable() as $name ) {
			$t            = AXH_DB::table( $name );
			$extra        = 'tasks' === $name ? ' AND done_at IS NULL' : '';
			$out[ $name ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE lead_id = %d{$extra}", (int) $lead_id ) ); // phpcs:ignore
		}
		$d                = AXH_DB::table( 'discovery' );
		$out['discovery'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$d} WHERE lead_id = %d", (int) $lead_id ) ); // phpcs:ignore
		return $out;
	}

	/**
	 * Why this pair cannot be merged this way round, or ''. The deal merged
	 * away must not carry anything that is decided per deal: a win, money,
	 * or an earlier registration claim (Section 2.4).
	 */
	protected static function problem( $keep, $gone ) {
		global $wpdb;
		if ( (int) $keep['id'] === (int) $gone['id'] ) {
			return 'Pick two different deals.';
		}
		if ( ! empty( $gone['merged_into'] ) ) {
			return $gone['company'] . ' was already merged into another deal.';
		}
		if ( ! empty( $keep['merged_into'] ) ) {
			return $keep['company'] . ' was merged into another deal. Open that one instead.';
		}
		if ( 'won' === $gone['stage'] ) {
			return 'Keep ' . $gone['company'] . ' — it is Closed Won, so its record stays as it is.';
		}
		if ( ! empty( $gone['win_requested_at'] ) ) {
			return 'Keep ' . $gone['company'] . ' — it has a Closed Won request waiting.';
		}
		$pay = AXH_DB::table( 'payouts' );
		if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pay} WHERE lead_id = %d", (int) $gone['id'] ) ) ) { // phpcs:ignore
			return 'Keep ' . $gone['company'] . ' — it has commission payments recorded.';
		}
		foreach ( array( 'customers', 'bdocs' ) as $bt ) {
			$t = AXH_DB::table( $bt );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t && (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE lead_id = %d", (int) $gone['id'] ) ) ) { // phpcs:ignore
				return 'Keep ' . $gone['company'] . ' — it has billing records.';
			}
		}
		$claimed = function ( $l ) {
			return 'draft' !== $l['reg_status'] && ! empty( $l['registered_at'] );
		};
		if ( $claimed( $gone ) && ( ! $claimed( $keep ) || strtotime( $gone['registered_at'] ) < strtotime( $keep['registered_at'] ) ) ) {
			return 'Keep ' . $gone['company'] . ' — it was registered first, and the first registration is the one that counts.';
		}
		return '';
	}

	protected static function summary( $l ) {
		$u = get_userdata( (int) $l['rep_id'] );
		$o = array( 'id' => (int) $l['id'], 'rep' => $u ? $u->display_name : '', 'tags' => (string) ( $l['tags'] ?? '' ), 'reg_status' => $l['reg_status'], 'registered_at' => $l['registered_at'], 'created_at' => $l['created_at'], 'archived' => ! empty( $l['archived_at'] ) );
		foreach ( self::FIELDS as $f ) {
			$o[ $f ] = (string) $l[ $f ];
		}
		$o['stage_label'] = AXH_DB::stage_label( $l['stage'] );
		return $o;
	}

	public static function rest_preview( $req ) {
		$keep = AXH_DB::get_lead( (int) $req->get_param( 'keep' ) );
		$gone = AXH_DB::get_lead( (int) $req->get_param( 'gone' ) );
		if ( ! $keep || ! $gone ) {
			return new WP_Error( 'axh_not_found', 'Deal not found.', array( 'status' => 404 ) );
		}
		$p    = self::problem( $keep, $gone );
		$swap = $p ? self::problem( $gone, $keep ) : 'x';
		return rest_ensure_response(
			array(
				'keep'    => self::summary( $keep ),
				'gone'    => self::summary( $gone ),
				'counts'  => self::count_rows( (int) $gone['id'] ),
				'problem' => $p,
				'swap'    => $p && '' === $swap,
			)
		);
	}

	public static function rest_merge( $req ) {
		global $wpdb;
		if ( ! get_option( 'axh_p3_db' ) ) {
			return new WP_Error( 'axh_not_ready', 'Merging is not ready yet. Reload and try again.', array( 'status' => 503 ) );
		}
		$b    = (array) $req->get_json_params();
		$keep = AXH_DB::get_lead( (int) ( $b['keep'] ?? 0 ) );
		$gone = AXH_DB::get_lead( (int) ( $b['gone'] ?? 0 ) );
		if ( ! $keep || ! $gone ) {
			return new WP_Error( 'axh_not_found', 'Deal not found.', array( 'status' => 404 ) );
		}
		$p = self::problem( $keep, $gone );
		if ( $p ) {
			return new WP_Error( 'axh_merge', $p, array( 'status' => 400 ) );
		}
		$kid  = (int) $keep['id'];
		$gid  = (int) $gone['id'];
		$take = array_values( array_intersect( self::FIELDS, array_map( 'sanitize_key', (array) ( $b['take'] ?? array() ) ) ) );
		$now  = AXH_DB::now();
		$log  = array( 'moved' => array(), 'before' => array(), 'gone_before' => array( 'archived_at' => $gone['archived_at'] ) );

		// 1. Rows that follow the deal.
		foreach ( self::movable() as $name ) {
			$t   = AXH_DB::table( $name );
			$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE lead_id = %d", $gid ) ) ); // phpcs:ignore
			if ( $ids ) {
				$wpdb->query( "UPDATE {$t} SET lead_id = {$kid} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
				$log['moved'][ $name ] = $ids;
			}
		}
		$d = AXH_DB::table( 'discovery' );
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$d} WHERE lead_id = %d", $kid ) ) ) { // phpcs:ignore
			$did = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$d} WHERE lead_id = %d", $gid ) ); // phpcs:ignore
			if ( $did ) {
				$wpdb->update( $d, array( 'lead_id' => $kid ), array( 'id' => $did ) );
				$log['moved']['discovery'] = array( $did );
			}
		}

		// Different owners (only AdaptExec can see both): what moved goes to the
		// kept deal's rep, as a reassignment would do it.
		if ( (int) $gone['rep_id'] !== (int) $keep['rep_id'] ) {
			$log['rep_from'] = (int) $gone['rep_id'];
			foreach ( array( 'tasks', 'shares', 'submissions', 'discovery' ) as $name ) {
				if ( ! empty( $log['moved'][ $name ] ) ) {
					$t     = AXH_DB::table( $name );
					$extra = 'tasks' === $name ? ' AND done_at IS NULL' : '';
					$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET rep_id = %d WHERE id IN (" . implode( ',', array_map( 'intval', $log['moved'][ $name ] ) ) . "){$extra}", (int) $keep['rep_id'] ) ); // phpcs:ignore
				}
			}
		}

		// 2. The kept deal's details: what they picked, then anything still blank.
		$set = array();
		foreach ( self::FIELDS as $f ) {
			if ( 'stage' === $f ) {
				continue;
			}
			$empty = 'est_value' === $f ? ! (float) $keep[ $f ] : '' === trim( (string) $keep[ $f ] );
			$has   = 'est_value' === $f ? (float) $gone[ $f ] > 0 : '' !== trim( (string) $gone[ $f ] );
			if ( $has && ( $empty || in_array( $f, $take, true ) ) && (string) $gone[ $f ] !== (string) $keep[ $f ] ) {
				$set[ $f ] = $gone[ $f ];
			}
		}
		foreach ( array( array( 'style_picks', 'style_at' ), array( 'tool_results' ) ) as $grp ) {
			if ( empty( $keep[ $grp[0] ] ) && ! empty( $gone[ $grp[0] ] ) ) {
				foreach ( $grp as $f ) {
					$set[ $f ] = $gone[ $f ];
				}
			}
		}
		if ( trim( (string) $gone['notes'] ) ) {
			$set['notes'] = trim( trim( (string) $keep['notes'] ) . "\n\n— From " . $gone['company'] . " (merged in):\n" . trim( (string) $gone['notes'] ) );
		}
		if ( get_option( 'axh_p2_db' ) && class_exists( 'AXH_P2' ) ) {
			$tags = AXH_P2::clean_tags( array_merge( AXH_P2::clean_tags( (string) ( $keep['tags'] ?? '' ) ), AXH_P2::clean_tags( (string) ( $gone['tags'] ?? '' ) ) ) );
			$str  = mb_substr( implode( ', ', $tags ), 0, 255 );
			if ( $str !== (string) ( $keep['tags'] ?? '' ) ) {
				$set['tags'] = $str ? $str : null;
			}
		}
		foreach ( array_keys( $set ) as $f ) {
			$log['before'][ $f ] = $keep[ $f ];
		}
		if ( $set ) {
			$set['updated_at'] = $now;
			$wpdb->update( AXH_DB::table( 'leads' ), $set, array( 'id' => $kid ) );
		}

		// 3. Their main contact, if it is someone else, joins the kept deal's people.
		$same = function ( $a, $b ) {
			return '' !== trim( (string) $a ) && strtolower( trim( (string) $a ) ) === strtolower( trim( (string) $b ) );
		};
		$after = array_merge( $keep, $set );
		if ( get_option( 'axh_p2_db' ) && ( $gone['contact_name'] || $gone['email'] || $gone['phone'] ) &&
			! $same( $gone['email'], $after['email'] ) && ! $same( $gone['contact_name'], $after['contact_name'] ) &&
			! ( $gone['phone'] && substr( preg_replace( '/\D/', '', $gone['phone'] ), -10 ) === substr( preg_replace( '/\D/', '', (string) $after['phone'] ), -10 ) ) ) {
			$wpdb->insert(
				AXH_DB::table( 'lead_contacts' ),
				array(
					'lead_id'    => $kid,
					'name'       => mb_substr( (string) $gone['contact_name'], 0, 191 ),
					'role'       => mb_substr( (string) $gone['title'], 0, 120 ),
					'email'      => (string) $gone['email'],
					'phone'      => mb_substr( (string) $gone['phone'], 0, 64 ),
					'notes'      => 'Main contact on ' . $gone['company'] . ' before the merge.',
					'created_at' => $now,
				)
			);
			$log['added_contact'] = (int) $wpdb->insert_id;
		}

		// 4. The deal merged away stays on record: archived, pointing here.
		$wpdb->update( AXH_DB::table( 'leads' ), array( 'archived_at' => $gone['archived_at'] ? $gone['archived_at'] : $now, 'merged_into' => $kid, 'updated_at' => $now ), array( 'id' => $gid ) );

		$wpdb->insert( AXH_DB::table( 'merges' ), array( 'keep_id' => $kid, 'gone_id' => $gid, 'by_user' => get_current_user_id(), 'payload' => wp_json_encode( $log ), 'created_at' => $now ) );
		$mid = (int) $wpdb->insert_id;

		$moved = array();
		$words = array( 'activity' => array( 'timeline entry', 'timeline entries' ), 'tasks' => array( 'follow-up', 'follow-ups' ), 'shares' => array( 'deck link', 'deck links' ), 'submissions' => array( 'form entry', 'form entries' ), 'sessions' => array( 'presentation', 'presentations' ), 'lead_files' => array( 'file', 'files' ), 'lead_contacts' => array( 'person', 'people' ), 'discovery' => array( 'discovery questionnaire', 'discovery questionnaires' ) );
		foreach ( $log['moved'] as $name => $ids ) {
			if ( isset( $words[ $name ] ) ) {
				$moved[] = count( $ids ) . ' ' . $words[ $name ][ 1 === count( $ids ) ? 0 : 1 ];
			}
		}
		AXH_DB::log( $kid, 'audit', 'Merged in ' . $gone['company'] . ' (#' . $gid . ')', 'By ' . wp_get_current_user()->display_name . ( $moved ? "\nMoved here: " . implode( ', ', $moved ) . '.' : '' ) );
		AXH_DB::log( $gid, 'audit', 'Merged into ' . ( $set['company'] ?? $keep['company'] ) . ' (#' . $kid . ')', 'By ' . wp_get_current_user()->display_name . '. Archived, kept on record.' );

		// 5. Their stage, if that is the one they picked (through the usual rules).
		if ( in_array( 'stage', $take, true ) && $gone['stage'] !== $keep['stage'] ) {
			AXH_DB::update_lead( $kid, array( 'stage' => $gone['stage'] ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'id' => $kid, 'merge' => $mid, 'moved' => $moved ) );
	}

	public static function rest_undo( $req ) {
		global $wpdb;
		$m   = AXH_DB::table( 'merges' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$m} WHERE id = %d", self::url_id( $req ) ), ARRAY_A ); // phpcs:ignore
		if ( ! $row || ! AXH_DB::get_lead( (int) $row['keep_id'] ) || ! AXH_DB::get_lead( (int) $row['gone_id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Merge not found.', array( 'status' => 404 ) );
		}
		if ( $row['undone_at'] ) {
			return new WP_Error( 'axh_done', 'That merge was already undone.', array( 'status' => 400 ) );
		}
		$kid = (int) $row['keep_id'];
		$gid = (int) $row['gone_id'];
		$log = json_decode( (string) $row['payload'], true );
		$log = is_array( $log ) ? $log : array();
		foreach ( (array) ( $log['moved'] ?? array() ) as $name => $ids ) {
			$ids = array_filter( array_map( 'intval', (array) $ids ) );
			if ( $ids && ( 'discovery' === $name || in_array( $name, self::movable(), true ) ) ) {
				$t = AXH_DB::table( $name );
				$wpdb->query( "UPDATE {$t} SET lead_id = {$gid} WHERE lead_id = {$kid} AND id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
			}
		}
		if ( ! empty( $log['rep_from'] ) ) {
			foreach ( array( 'tasks', 'shares', 'submissions', 'discovery' ) as $name ) {
				$ids = array_filter( array_map( 'intval', (array) ( $log['moved'][ $name ] ?? array() ) ) );
				if ( $ids ) {
					$t = AXH_DB::table( $name );
					$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET rep_id = %d WHERE id IN (" . implode( ',', $ids ) . ')', (int) $log['rep_from'] ) ); // phpcs:ignore
				}
			}
		}
		if ( ! empty( $log['added_contact'] ) ) {
			$wpdb->delete( AXH_DB::table( 'lead_contacts' ), array( 'id' => (int) $log['added_contact'], 'lead_id' => $kid ) );
		}
		$before = array_intersect_key( (array) ( $log['before'] ?? array() ), array_flip( array_merge( self::FIELDS, array( 'notes', 'tags', 'style_picks', 'style_at', 'tool_results' ) ) ) );
		unset( $before['stage'] );
		if ( $before ) {
			$wpdb->update( AXH_DB::table( 'leads' ), $before, array( 'id' => $kid ) );
		}
		$wpdb->update( AXH_DB::table( 'leads' ), array( 'archived_at' => $log['gone_before']['archived_at'] ?? null, 'merged_into' => null, 'updated_at' => AXH_DB::now() ), array( 'id' => $gid ) );
		$wpdb->update( $m, array( 'undone_at' => AXH_DB::now() ), array( 'id' => (int) $row['id'] ) );
		$gname = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT company FROM ' . AXH_DB::table( 'leads' ) . ' WHERE id = %d', $gid ) ); // phpcs:ignore
		AXH_DB::log( $kid, 'audit', 'Merge undone: ' . $gname . ' is its own deal again', 'By ' . wp_get_current_user()->display_name );
		AXH_DB::log( $gid, 'audit', 'Merge undone: back to its own deal', 'By ' . wp_get_current_user()->display_name );
		return rest_ensure_response( array( 'ok' => true, 'keep' => $kid, 'gone' => $gid ) );
	}

	/** Recent merges touching a deal, for the deal page. */
	public static function merges_for( $lead_id ) {
		global $wpdb;
		if ( ! get_option( 'axh_p3_db' ) ) {
			return array();
		}
		$m    = AXH_DB::table( 'merges' );
		$l    = AXH_DB::table( 'leads' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.id, m.keep_id, m.gone_id, m.created_at, k.company AS keep_company, g.company AS gone_company FROM {$m} m LEFT JOIN {$l} k ON k.id = m.keep_id LEFT JOIN {$l} g ON g.id = m.gone_id WHERE m.undone_at IS NULL AND ( m.keep_id = %d OR m.gone_id = %d ) ORDER BY m.id DESC LIMIT 10", (int) $lead_id, (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		return $rows;
	}

	/* ------------------------------------------------------------ templates */

	public static function get_templates( $req ) {
		$v = get_user_meta( get_current_user_id(), 'axh_tpls', true );
		return rest_ensure_response( array( 'templates' => is_array( $v ) ? $v : array() ) );
	}

	public static function save_templates( $req ) {
		$b   = (array) $req->get_json_params();
		$out = array();
		foreach ( array_slice( (array) ( $b['templates'] ?? array() ), 0, 40 ) as $t ) {
			$name = mb_substr( sanitize_text_field( (string) ( $t['name'] ?? '' ) ), 0, 60 );
			$body = mb_substr( sanitize_textarea_field( (string) ( $t['body'] ?? '' ) ), 0, 6000 );
			if ( '' === $name || '' === trim( $body ) ) {
				continue;
			}
			$out[] = array(
				'id'      => substr( sanitize_key( (string) ( $t['id'] ?? '' ) ), 0, 20 ) ?: 't' . substr( md5( $name . wp_rand() ), 0, 8 ),
				'kind'    => 'text' === ( $t['kind'] ?? '' ) ? 'text' : 'email',
				'name'    => $name,
				'subject' => mb_substr( sanitize_text_field( (string) ( $t['subject'] ?? '' ) ), 0, 191 ),
				'body'    => $body,
			);
		}
		update_user_meta( get_current_user_id(), 'axh_tpls', $out );
		return rest_ensure_response( array( 'templates' => $out ) );
	}

	/* ------------------------------------------------------------- mentions */

	/** Who can be told about a note on this deal: AdaptExec, and the deal's rep. */
	protected static function people_for( $lead ) {
		$me  = get_current_user_id();
		$out = array();
		foreach ( AXH_Notify::managers() as $u ) {
			if ( (int) $u->ID !== $me ) {
				$out[ (int) $u->ID ] = array( 'id' => (int) $u->ID, 'name' => $u->display_name, 'role' => 'AdaptExec' );
			}
		}
		if ( (int) $lead['rep_id'] !== $me && ! isset( $out[ (int) $lead['rep_id'] ] ) ) {
			$u = get_userdata( (int) $lead['rep_id'] );
			if ( $u ) {
				$out[ (int) $u->ID ] = array( 'id' => (int) $u->ID, 'name' => $u->display_name, 'role' => 'Owns this deal' );
			}
		}
		return array_values( $out );
	}

	public static function mentionable( $req ) {
		$lead = AXH_DB::get_lead( (int) $req->get_param( 'lead' ) );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Deal not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'people' => self::people_for( $lead ) ) );
	}

	public static function rest_mention( $req ) {
		$b    = (array) $req->get_json_params();
		$lead = AXH_DB::get_lead( (int) ( $b['lead_id'] ?? 0 ) );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Deal not found.', array( 'status' => 404 ) );
		}
		$allowed = wp_list_pluck( self::people_for( $lead ), 'id' );
		$ids     = array_values( array_intersect( array_map( 'intval', array_slice( (array) ( $b['ids'] ?? array() ), 0, 10 ) ), $allowed ) );
		$text    = mb_substr( sanitize_textarea_field( trim( (string) ( $b['subject'] ?? '' ) . ( ! empty( $b['body'] ) ? "\n\n" . (string) $b['body'] : '' ) ) ), 0, 1500 );
		$me      = wp_get_current_user()->display_name;
		$n       = 0;
		foreach ( $ids as $uid ) {
			// The rep already hears about AdaptExec's notes on their deals.
			if ( axh_is_manager() && (int) $uid === (int) $lead['rep_id'] && ! empty( $b['logged'] ) ) {
				continue;
			}
			if ( AXH_Notify::add( $uid, 'mention', $me . ' mentioned you on ' . $lead['company'], $text, '#/lead/' . (int) $lead['id'], (int) $lead['id'] ) ) {
				++$n;
			}
		}
		return rest_ensure_response( array( 'told' => $n ) );
	}
}

AXH_P3::init();
