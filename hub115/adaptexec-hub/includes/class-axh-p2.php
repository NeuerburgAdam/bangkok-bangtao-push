<?php
/**
 * Sales Hub 1.15.0: the deal record, deeper.
 *
 * - More than one person per deal (owner, office manager, whoever signs),
 *   each with their own role, email and phone.
 * - Tags on deals, for whatever a rep wants to group by.
 * - Notes and logged calls can be corrected or removed by whoever wrote
 *   them, and one can be pinned to the top of the deal.
 * - Stage automations: moving a deal to a stage can add the follow-up that
 *   always comes next, so the next step is never forgotten.
 *
 * Additive only: two new tables (people on a deal, and a keep-copy of any
 * note that is corrected or removed, so nothing is ever truly lost), one
 * nullable column on leads and one on activity, and user meta for each
 * person's automations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P2 {

	const DB = '1';
	const NS = 'axh/v1';

	/** Activity a person logs themselves, and may therefore correct. */
	const EDITABLE = array( 'note', 'call', 'email', 'text', 'meeting' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'axh_stage_changed', array( __CLASS__, 'on_stage' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'tell_added' ), 20, 3 );
	}

	/** Follow-ups an automation added during this request, so the Hub can say so. */
	protected static $added = array();

	public static function tell_added( $res, $server, $req ) {
		if ( self::$added && $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/' . self::NS . '/' ) ) {
			$data = $res->get_data();
			if ( is_array( $data ) && ! isset( $data['axh_auto'] ) && ( ! $data || array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) ) {
				$data['axh_auto'] = array_slice( self::$added, 0, 20 );
				$res->set_data( $data );
			}
		}
		self::$added = array();
		return $res;
	}

	public static function maybe_upgrade() {
		if ( get_option( 'axh_p2_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$leads = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads ) ) !== $leads ) {
			return;
		}
		$ok = true;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = AXH_DB::table( 'lead_contacts' );
		dbDelta(
			"CREATE TABLE {$c} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL DEFAULT '',
			role varchar(120) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(64) NOT NULL DEFAULT '',
			notes text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		$bin = AXH_DB::table( 'activity_bin' );
		dbDelta(
			"CREATE TABLE {$bin} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_id bigint(20) unsigned NOT NULL,
			lead_id bigint(20) unsigned NOT NULL,
			kind varchar(16) NOT NULL DEFAULT 'removed',
			payload longtext NULL,
			by_user bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		foreach ( array( $c, $bin ) as $tb ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tb ) ) !== $tb ) {
				$ok = false;
			}
		}
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$leads}", 0 ); // phpcs:ignore
		if ( ! in_array( 'tags', $have, true ) && false === $wpdb->query( "ALTER TABLE {$leads} ADD COLUMN tags VARCHAR(255) NULL" ) ) { // phpcs:ignore
			$ok = false;
		}
		$a    = AXH_DB::table( 'activity' );
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$a}", 0 ); // phpcs:ignore
		if ( ! in_array( 'pinned', $have, true ) && false === $wpdb->query( "ALTER TABLE {$a} ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0" ) ) { // phpcs:ignore
			$ok = false;
		}
		if ( $ok ) {
			update_option( 'axh_p2_db', self::DB, false );
		}
	}

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		$r    = function ( $route, $method, $cb ) use ( $auth ) {
			register_rest_route( self::NS, $route, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $auth ) );
		};
		$r( '/p2/lead/(?P<id>\\d+)/contacts', 'POST', 'save_contact' );
		$r( '/p2/contacts/(?P<id>\\d+)', 'DELETE', 'delete_contact' );
		$r( '/p2/lead/(?P<id>\\d+)/tags', 'POST', 'save_tags' );
		$r( '/p2/activity/(?P<id>\\d+)', 'POST', 'edit_activity' );
		$r( '/p2/activity/(?P<id>\\d+)', 'DELETE', 'delete_activity' );
		$r( '/p2/automations', 'GET', 'get_automations' );
		$r( '/p2/automations', 'POST', 'save_automations' );
		$r( '/p2/tags/bulk', 'POST', 'bulk_tags' );
		$r( '/p2/index', 'GET', 'contact_index' );
	}

	/** The id in the address, never one from the body (JSON params win in $req['id']). */
	protected static function url_id( $req ) {
		$u = $req->get_url_params();
		return isset( $u['id'] ) ? (int) $u['id'] : 0;
	}

	/* ------------------------------------------------------------ contacts */

	public static function contacts( $lead_id ) {
		global $wpdb;
		if ( ! get_option( 'axh_p2_db' ) ) {
			return array();
		}
		$t = AXH_DB::table( 'lead_contacts' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, role, email, phone, notes FROM {$t} WHERE lead_id = %d ORDER BY id ASC LIMIT 50", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
	}

	public static function save_contact( $req ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( self::url_id( $req ) );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$b   = (array) $req->get_json_params();
		$row = array(
			'name'  => mb_substr( sanitize_text_field( (string) ( $b['name'] ?? '' ) ), 0, 191 ),
			'role'  => mb_substr( sanitize_text_field( (string) ( $b['role'] ?? '' ) ), 0, 120 ),
			'email' => sanitize_email( (string) ( $b['email'] ?? '' ) ),
			'phone' => mb_substr( sanitize_text_field( (string) ( $b['phone'] ?? '' ) ), 0, 64 ),
			'notes' => mb_substr( sanitize_textarea_field( (string) ( $b['notes'] ?? '' ) ), 0, 2000 ),
		);
		if ( '' === $row['name'] && '' === $row['email'] && '' === $row['phone'] ) {
			return new WP_Error( 'axh_empty', 'Add a name, email or phone.', array( 'status' => 400 ) );
		}
		$t   = AXH_DB::table( 'lead_contacts' );
		$cid = (int) ( $b['id'] ?? 0 );
		if ( $cid ) {
			$wpdb->update( $t, $row, array( 'id' => $cid, 'lead_id' => (int) $lead['id'] ) );
		} else {
			$dupe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE lead_id = %d AND ( ( email <> '' AND email = %s ) OR ( name <> '' AND name = %s AND phone = %s ) )", (int) $lead['id'], $row['email'], $row['name'], $row['phone'] ) ); // phpcs:ignore
			if ( $dupe ) {
				return new WP_Error( 'axh_dupe', 'That person is already on this deal.', array( 'status' => 400 ) );
			}
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE lead_id = %d", (int) $lead['id'] ) ); // phpcs:ignore
			if ( $n >= 50 ) {
				return new WP_Error( 'axh_many', 'That is the most people one deal can hold.', array( 'status' => 400 ) );
			}
			$row['lead_id']    = (int) $lead['id'];
			$row['created_at'] = AXH_DB::now();
			$wpdb->insert( $t, $row );
			AXH_DB::log( (int) $lead['id'], 'note', 'Added a contact: ' . ( $row['name'] ? $row['name'] : $row['email'] ) . ( $row['role'] ? ' (' . $row['role'] . ')' : '' ) );
		}
		return rest_ensure_response( array( 'contacts' => self::contacts( (int) $lead['id'] ) ) );
	}

	public static function delete_contact( $req ) {
		global $wpdb;
		$t   = AXH_DB::table( 'lead_contacts' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", self::url_id( $req ) ), ARRAY_A ); // phpcs:ignore
		if ( ! $row || ! AXH_DB::get_lead( (int) $row['lead_id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Contact not found.', array( 'status' => 404 ) );
		}
		$wpdb->delete( $t, array( 'id' => (int) $row['id'] ) );
		return rest_ensure_response( array( 'contacts' => self::contacts( (int) $row['lead_id'] ) ) );
	}

	/* ---------------------------------------------------------------- tags */

	public static function clean_tags( $tags ) {
		$tags = is_array( $tags ) ? $tags : preg_split( '/\\s*,\\s*/', (string) $tags );
		$out  = array();
		foreach ( (array) $tags as $t ) {
			$t = trim( mb_substr( preg_replace( '/[,\\s]+/', ' ', sanitize_text_field( (string) $t ) ), 0, 30 ) );
			if ( '' !== $t && ! in_array( mb_strtolower( $t ), array_map( 'mb_strtolower', $out ), true ) ) {
				$out[] = $t;
			}
		}
		return array_slice( $out, 0, 8 );
	}

	/** Add (or take off) one tag across many deals at once. */
	public static function bulk_tags( $req ) {
		global $wpdb;
		$b    = (array) $req->get_json_params();
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', array_slice( (array) ( $b['ids'] ?? array() ), 0, 300 ) ) ) ) );
		$add  = self::clean_tags( array( (string) ( $b['add'] ?? '' ) ) );
		$drop = mb_strtolower( trim( sanitize_text_field( (string) ( $b['remove'] ?? '' ) ) ) );
		if ( ! $ids || ( ! $add && '' === $drop ) ) {
			return new WP_Error( 'axh_bad', 'Pick some deals and a tag.', array( 'status' => 400 ) );
		}
		$n = 0;
		foreach ( $ids as $id ) {
			$lead = AXH_DB::get_lead( $id );
			if ( ! $lead ) {
				continue;
			}
			$tags = self::clean_tags( (string) ( $lead['tags'] ?? '' ) );
			if ( $add ) {
				$tags = self::clean_tags( array_merge( $tags, $add ) );
			}
			if ( '' !== $drop ) {
				$tags = array_values( array_filter( $tags, function ( $t ) use ( $drop ) { return mb_strtolower( $t ) !== $drop; } ) );
			}
			$str = mb_substr( implode( ', ', $tags ), 0, 255 );
			if ( (string) ( $lead['tags'] ?? '' ) !== $str ) {
				$wpdb->update( AXH_DB::table( 'leads' ), array( 'tags' => $str ? $str : null ), array( 'id' => (int) $id ) );
				++$n;
			}
		}
		return rest_ensure_response( array( 'count' => $n ) );
	}

	public static function save_tags( $req ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( self::url_id( $req ) );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$b    = (array) $req->get_json_params();
		$tags = self::clean_tags( $b['tags'] ?? array() );
		$str  = mb_substr( implode( ', ', $tags ), 0, 255 );
		$wpdb->update( AXH_DB::table( 'leads' ), array( 'tags' => $str ? $str : null ), array( 'id' => (int) $lead['id'] ) );
		return rest_ensure_response( array( 'tags' => $tags ) );
	}

	/* ----------------------------------------------------- activity entries */

	protected static function own_activity( $id ) {
		global $wpdb;
		$a   = AXH_DB::table( 'activity' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$a} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row || ! AXH_DB::get_lead( (int) $row['lead_id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Entry not found.', array( 'status' => 404 ) );
		}
		return $row;
	}

	/** Correct a note or logged call (author only), or pin/unpin any entry. */
	public static function edit_activity( $req ) {
		global $wpdb;
		$row = self::own_activity( self::url_id( $req ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$b   = (array) $req->get_json_params();
		$set = array();
		if ( array_key_exists( 'pinned', $b ) && get_option( 'axh_p2_db' ) ) {
			$a = AXH_DB::table( 'activity' );
			if ( ! empty( $b['pinned'] ) ) {
				// One pinned entry per deal keeps the top of the timeline meaningful.
				$wpdb->query( $wpdb->prepare( "UPDATE {$a} SET pinned = 0 WHERE lead_id = %d", (int) $row['lead_id'] ) ); // phpcs:ignore
			}
			$set['pinned'] = empty( $b['pinned'] ) ? 0 : 1;
		}
		if ( isset( $b['subject'] ) || isset( $b['body'] ) ) {
			if ( (int) $row['rep_id'] !== get_current_user_id() || ! in_array( $row['type'], self::EDITABLE, true ) ) {
				return new WP_Error( 'axh_forbidden', 'Only the person who logged this can change it.', array( 'status' => 403 ) );
			}
			if ( isset( $b['subject'] ) ) {
				$set['subject'] = mb_substr( sanitize_text_field( (string) $b['subject'] ), 0, 191 );
			}
			if ( isset( $b['body'] ) ) {
				$set['body'] = sanitize_textarea_field( (string) $b['body'] );
			}
		}
		if ( $set ) {
			if ( isset( $set['subject'] ) || isset( $set['body'] ) ) {
				self::keep( $row, 'edited' );
			}
			$wpdb->update( AXH_DB::table( 'activity' ), $set, array( 'id' => (int) $row['id'] ) );
		}
		return rest_ensure_response( array( 'activity' => AXH_DB::get_activity( (int) $row['lead_id'] ) ) );
	}

	public static function delete_activity( $req ) {
		global $wpdb;
		$row = self::own_activity( self::url_id( $req ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( (int) $row['rep_id'] !== get_current_user_id() || ! in_array( $row['type'], self::EDITABLE, true ) ) {
			return new WP_Error( 'axh_forbidden', 'Only notes and calls you logged yourself can be removed. Stage changes, form entries and other records stay on the deal.', array( 'status' => 403 ) );
		}
		if ( ! self::keep( $row, 'removed' ) ) {
			return new WP_Error( 'axh_db', 'Could not remove that entry. Try again.', array( 'status' => 500 ) );
		}
		$wpdb->delete( AXH_DB::table( 'activity' ), array( 'id' => (int) $row['id'] ) );
		return rest_ensure_response( array( 'activity' => AXH_DB::get_activity( (int) $row['lead_id'] ) ) );
	}

	/**
	 * A copy of an entry as it was, before it is corrected or removed, so a
	 * slip of the finger never loses a record. Kept with the deal.
	 */
	protected static function keep( $row, $kind ) {
		global $wpdb;
		if ( ! get_option( 'axh_p2_db' ) ) {
			return false;
		}
		return false !== $wpdb->insert(
			AXH_DB::table( 'activity_bin' ),
			array(
				'activity_id' => (int) $row['id'],
				'lead_id'     => (int) $row['lead_id'],
				'kind'        => $kind,
				'payload'     => wp_json_encode( $row ),
				'by_user'     => get_current_user_id(),
				'created_at'  => AXH_DB::now(),
			)
		);
	}

	/** delete_lead() removes everything that names a lead; these too. */
	public static function delete_for_lead( $lead_id ) {
		global $wpdb;
		if ( ! get_option( 'axh_p2_db' ) ) {
			return;
		}
		$wpdb->delete( AXH_DB::table( 'lead_contacts' ), array( 'lead_id' => (int) $lead_id ) );
		$wpdb->delete( AXH_DB::table( 'activity_bin' ), array( 'lead_id' => (int) $lead_id ) );
	}

	/** Everyone on the deals this person can see, for search. */
	public static function contact_index( $req ) {
		global $wpdb;
		if ( ! get_option( 'axh_p2_db' ) ) {
			return rest_ensure_response( array( 'people' => array() ) );
		}
		$c     = AXH_DB::table( 'lead_contacts' );
		$l     = AXH_DB::table( 'leads' );
		$scope = axh_is_manager() ? '1=1' : $wpdb->prepare( 'l.rep_id = %d', get_current_user_id() );
		$rows  = (array) $wpdb->get_results( "SELECT c.lead_id, c.name, c.role, c.email, c.phone FROM {$c} c JOIN {$l} l ON l.id = c.lead_id WHERE {$scope} ORDER BY c.id DESC LIMIT 3000", ARRAY_A ); // phpcs:ignore
		return rest_ensure_response( array( 'people' => $rows ) );
	}

	/* --------------------------------------------------------- automations */

	public static function automation_defaults() {
		return array(
			array( 'stage' => 'contacted', 'on' => true, 'type' => 'call', 'title' => 'Follow up with {contact}', 'days' => 2 ),
			array( 'stage' => 'discovery', 'on' => true, 'type' => 'meeting', 'title' => 'Finish discovery with {contact}', 'days' => 3 ),
			array( 'stage' => 'scoping', 'on' => false, 'type' => 'todo', 'title' => 'Request a quote for {company}', 'days' => 2 ),
			array( 'stage' => 'proposal', 'on' => true, 'type' => 'call', 'title' => 'Check in on the proposal with {contact}', 'days' => 3 ),
			array( 'stage' => 'won', 'on' => false, 'type' => 'call', 'title' => 'Kick-off call with {contact}', 'days' => 1 ),
		);
	}

	public static function automations( $user_id ) {
		$v = get_user_meta( (int) $user_id, 'axh_automations', true );
		return is_array( $v ) && $v ? $v : self::automation_defaults();
	}

	public static function get_automations( $req ) {
		return rest_ensure_response( array( 'rules' => self::automations( get_current_user_id() ), 'defaults' => self::automation_defaults() ) );
	}

	public static function save_automations( $req ) {
		$b     = (array) $req->get_json_params();
		$keys  = AXH_Install::stage_keys();
		$out   = array();
		$types = array( 'call', 'email', 'text', 'meeting', 'todo' );
		foreach ( array_slice( (array) ( $b['rules'] ?? array() ), 0, 12 ) as $r ) {
			$st = sanitize_key( (string) ( $r['stage'] ?? '' ) );
			if ( ! in_array( $st, $keys, true ) || 'lost' === $st ) {
				continue;
			}
			$out[] = array(
				'stage' => $st,
				'on'    => ! empty( $r['on'] ),
				'type'  => in_array( ( $r['type'] ?? '' ), $types, true ) ? $r['type'] : 'call',
				'title' => mb_substr( sanitize_text_field( (string) ( $r['title'] ?? '' ) ), 0, 150 ),
				'days'  => max( 0, min( 60, (int) ( $r['days'] ?? 2 ) ) ),
			);
		}
		update_user_meta( get_current_user_id(), 'axh_automations', $out );
		return rest_ensure_response( array( 'rules' => self::automations( get_current_user_id() ) ) );
	}

	/**
	 * A deal just moved stage: add the follow-up the owner's automation
	 * says comes next, unless the deal already has one open.
	 */
	public static function on_stage( $lead_id, $from, $to ) {
		global $wpdb;
		$t    = AXH_DB::table( 'tasks' );
		$l    = AXH_DB::table( 'leads' );
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT id, rep_id, company, contact_name FROM {$l} WHERE id = %d", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $lead || ! get_option( 'axh_flow_db' ) ) {
			return;
		}
		foreach ( self::automations( (int) $lead['rep_id'] ) as $r ) {
			if ( $r['stage'] !== $to || empty( $r['on'] ) || '' === trim( $r['title'] ) ) {
				continue;
			}
			$open = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE lead_id = %d AND done_at IS NULL", (int) $lead_id ) ); // phpcs:ignore
			if ( $open ) {
				return; // They already have a next step; do not pile on.
			}
			$title = strtr( $r['title'], array( '{contact}' => $lead['contact_name'] ? $lead['contact_name'] : $lead['company'], '{company}' => $lead['company'] ) );
			$wpdb->insert(
				$t,
				array(
					'lead_id'    => (int) $lead_id,
					'rep_id'     => (int) $lead['rep_id'],
					'title'      => mb_substr( $title, 0, 191 ),
					'type'       => $r['type'],
					'due_at'     => gmdate( 'Y-m-d 00:00:00', time() + (int) $r['days'] * DAY_IN_SECONDS ),
					'timed'      => 0,
					'notes'      => 'Added automatically when the deal moved to ' . AXH_DB::stage_label( $to ) . '.',
					'created_at' => AXH_DB::now(),
					'updated_at' => AXH_DB::now(),
				)
			);
			self::$added[] = array( 'lead_id' => (int) $lead_id, 'title' => mb_substr( $title, 0, 191 ), 'days' => (int) $r['days'] );
			return;
		}
	}
}

AXH_P2::init();
