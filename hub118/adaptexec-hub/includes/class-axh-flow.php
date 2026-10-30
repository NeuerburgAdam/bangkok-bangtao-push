<?php
/**
 * Follow-ups and pipeline intelligence (1.8.0).
 *
 * Adds what a modern CRM is expected to have on top of the original tasks
 * table — follow-up types, due times, priority, notes, outcomes — plus the
 * per-deal signals the pipeline board needs: days in stage, last activity
 * and the next scheduled step. Kept in its own file so the core Hub classes
 * stay untouched; every column it adds is additive and nullable/defaulted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_Flow {

	const DB = '1';
	const NS = 'axh/v1';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function types() {
		return array( 'call', 'email', 'text', 'meeting', 'todo' );
	}

	/* ---------------------------------------------------------------- schema */

	public static function maybe_upgrade() {
		if ( get_option( 'axh_flow_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$t = AXH_DB::table( 'tasks' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return; // Core tables not created yet; try again on a later request.
		}
		$have = $wpdb->get_col( "SHOW COLUMNS FROM {$t}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL
		$add  = array(
			'type'       => "ADD COLUMN type VARCHAR(16) NOT NULL DEFAULT 'todo'",
			'priority'   => 'ADD COLUMN priority TINYINT(1) NOT NULL DEFAULT 0',
			'timed'      => 'ADD COLUMN timed TINYINT(1) NOT NULL DEFAULT 0',
			'notes'      => 'ADD COLUMN notes TEXT NULL',
			'outcome'    => 'ADD COLUMN outcome VARCHAR(191) NULL',
			'updated_at' => 'ADD COLUMN updated_at DATETIME NULL',
		);
		$ok = true;
		foreach ( $add as $col => $ddl ) {
			if ( ! in_array( $col, (array) $have, true ) ) {
				if ( false === $wpdb->query( "ALTER TABLE {$t} {$ddl}" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
					$ok = false;
				}
			}
		}
		if ( $ok ) {
			update_option( 'axh_flow_db', self::DB, false );
		}
	}

	/* ---------------------------------------------------------------- access */

	private static function me() {
		return get_current_user_id();
	}

	private static function mgr() {
		return function_exists( 'axh_is_manager' ) && axh_is_manager();
	}

	private static function lead( $id ) {
		global $wpdb;
		$t = AXH_DB::table( 'leads' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, rep_id, company, stage FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	private static function can_lead( $id ) {
		$l = self::lead( $id );
		return $l && ( self::mgr() || (int) $l['rep_id'] === self::me() );
	}

	private static function task( $id ) {
		global $wpdb;
		$t = AXH_DB::table( 'tasks' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row || ( ! self::mgr() && (int) $row['rep_id'] !== self::me() ) ) {
			return null;
		}
		return $row;
	}

	/* ------------------------------------------------------------- cleaning */

	/** A due value is either a calendar day (Y-m-d) or a UTC instant (Y-m-d H:i:s). */
	private static function due( $v, $timed ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return null;
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})$/', $v, $m ) ) {
			return $m[1] . ' 00:00:00';
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/', $v, $m ) ) {
			$s = isset( $m[4] ) ? $m[4] : '00';
			// A timed follow-up at exactly UTC midnight would read as a bare day.
			if ( $timed && '00' === $m[2] && '00' === $m[3] && '00' === $s ) {
				$s = '01';
			}
			return $m[1] . ' ' . $m[2] . ':' . $m[3] . ':' . $s;
		}
		return null;
	}

	private static function fields( $p, $creating ) {
		$f = array();
		if ( $creating || isset( $p['title'] ) ) {
			$f['title'] = mb_substr( sanitize_text_field( isset( $p['title'] ) ? $p['title'] : '' ), 0, 191 );
		}
		if ( isset( $p['type'] ) ) {
			$f['type'] = in_array( $p['type'], self::types(), true ) ? $p['type'] : 'todo';
		}
		if ( isset( $p['priority'] ) ) {
			$f['priority'] = empty( $p['priority'] ) ? 0 : 1;
		}
		if ( isset( $p['notes'] ) ) {
			$f['notes'] = sanitize_textarea_field( $p['notes'] );
		}
		if ( isset( $p['due_at'] ) || isset( $p['timed'] ) ) {
			$timed       = ! empty( $p['timed'] ) ? 1 : 0;
			$f['due_at'] = self::due( isset( $p['due_at'] ) ? $p['due_at'] : '', $timed );
			$f['timed']  = $f['due_at'] ? $timed : 0;
		}
		if ( array_key_exists( 'lead_id', $p ) ) {
			$lid          = (int) $p['lead_id'];
			$f['lead_id'] = $lid && self::can_lead( $lid ) ? $lid : null;
		}
		if ( isset( $p['rep_id'] ) && self::mgr() && (int) $p['rep_id'] > 0 ) {
			$f['rep_id'] = (int) $p['rep_id'];
		}
		// 1.18.0: repeating follow-ups.
		if ( array_key_exists( 'repeat', $p ) && class_exists( 'AXH_P5' ) && get_option( 'axh_p5_db' ) ) {
			$f['repeat_every'] = AXH_P5::clean_repeat( $p['repeat'] );
		}
		return $f;
	}

	/* ----------------------------------------------------------------- reads */

	private static function shape( $r ) {
		$r['id']       = (int) $r['id'];
		$r['lead_id']  = $r['lead_id'] ? (int) $r['lead_id'] : null;
		$r['rep_id']   = (int) $r['rep_id'];
		$r['priority'] = isset( $r['priority'] ) ? (int) $r['priority'] : 0;
		$r['timed']    = isset( $r['timed'] ) ? (int) $r['timed'] : 0;
		$r['type']     = ! empty( $r['type'] ) ? $r['type'] : 'todo';
		return $r;
	}

	public static function get_tasks( $req ) {
		global $wpdb;
		$t      = AXH_DB::table( 'tasks' );
		$l      = AXH_DB::table( 'leads' );
		$where  = array( self::mgr() ? '1=1' : $wpdb->prepare( 't.rep_id = %d', self::me() ) );
		$status = (string) $req->get_param( 'status' );
		if ( 'done' === $status ) {
			$where[] = 't.done_at IS NOT NULL';
			$order   = 't.done_at DESC';
			$limit   = 150;
		} else {
			$where[] = 't.done_at IS NULL';
			$order   = '(t.due_at IS NULL), t.due_at ASC, t.priority DESC';
			$limit   = 600;
		}
		$rep = (int) $req->get_param( 'rep_id' );
		if ( $rep && self::mgr() ) {
			$where[] = $wpdb->prepare( 't.rep_id = %d', $rep );
		}
		$sql  = "SELECT t.*, l.company, l.contact_name, l.email, l.phone, l.stage, l.est_value
			FROM {$t} t LEFT JOIN {$l} l ON l.id = t.lead_id
			WHERE " . implode( ' AND ', $where ) . " ORDER BY {$order} LIMIT {$limit}";
		$rows = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		$names = array();
		foreach ( $rows as &$r ) {
			$r   = self::shape( $r );
			$rid = $r['rep_id'];
			if ( ! isset( $names[ $rid ] ) ) {
				$u             = get_userdata( $rid );
				$names[ $rid ] = $u ? $u->display_name : '';
			}
			$r['rep_name'] = $names[ $rid ];
		}
		unset( $r );

		// Done this week, for the scoreboard.
		$week = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$done = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT COUNT(*) FROM {$t} WHERE done_at IS NOT NULL AND done_at >= '" . esc_sql( $week ) . "' AND " .
			( self::mgr() ? '1=1' : $wpdb->prepare( 'rep_id = %d', self::me() ) )
		);

		return rest_ensure_response( array( 'tasks' => $rows, 'done_week' => $done, 'types' => self::types() ) );
	}

	/* ---------------------------------------------------------------- writes */

	public static function add_task( $req ) {
		global $wpdb;
		$p = (array) $req->get_json_params();
		$f = self::fields( $p, true );
		if ( '' === $f['title'] ) {
			return new WP_Error( 'axh_title', 'Give the follow-up a name.', array( 'status' => 400 ) );
		}
		if ( ! empty( $p['lead_id'] ) && empty( $f['lead_id'] ) ) {
			return new WP_Error( 'axh_lead', 'That lead is not yours.', array( 'status' => 403 ) );
		}
		$f = array_merge(
			array( 'rep_id' => self::me(), 'type' => 'todo', 'priority' => 0, 'timed' => 0 ),
			$f,
			array( 'created_at' => AXH_DB::now(), 'updated_at' => AXH_DB::now() )
		);
		if ( false === $wpdb->insert( AXH_DB::table( 'tasks' ), $f ) ) {
			return new WP_Error( 'axh_db', 'Could not save the follow-up.', array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'id' => (int) $wpdb->insert_id ) );
	}

	public static function update_task( $req ) {
		global $wpdb;
		$row = self::task( $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'axh_nf', 'Follow-up not found.', array( 'status' => 404 ) );
		}
		$p = (array) $req->get_json_params();
		$f = self::fields( $p, false );
		if ( isset( $f['title'] ) && '' === $f['title'] ) {
			unset( $f['title'] );
		}
		if ( array_key_exists( 'done', $p ) ) {
			$f['done_at'] = $p['done'] ? AXH_DB::now() : null;
		}
		if ( isset( $p['outcome'] ) ) {
			$f['outcome'] = mb_substr( sanitize_text_field( $p['outcome'] ), 0, 191 );
		}
		$f['updated_at'] = AXH_DB::now();
		$wpdb->update( AXH_DB::table( 'tasks' ), $f, array( 'id' => (int) $row['id'] ) );
		if ( ! empty( $p['done'] ) && empty( $row['done_at'] ) ) {
			do_action( 'axh_task_done', (int) $row['id'] ); // 1.18.0: repeating follow-ups
		}

		// Completing with an outcome writes it to the deal's timeline.
		if ( ! empty( $p['done'] ) && $row['lead_id'] && ( ! empty( $p['outcome'] ) || ! empty( $p['log'] ) ) ) {
			$type  = ! empty( $f['type'] ) ? $f['type'] : ( ! empty( $row['type'] ) ? $row['type'] : 'todo' );
			$label = array( 'call' => 'Call', 'email' => 'Email', 'text' => 'Text', 'meeting' => 'Meeting', 'todo' => 'Follow-up' );
			$subj  = $label[ $type ] . ': ' . $row['title'] . ( ! empty( $p['outcome'] ) ? ' — ' . sanitize_text_field( $p['outcome'] ) : '' );
			AXH_DB::log( (int) $row['lead_id'], 'call' === $type || 'meeting' === $type || 'email' === $type ? $type : 'note', mb_substr( $subj, 0, 191 ), isset( $p['note'] ) ? sanitize_textarea_field( $p['note'] ) : '' );
		}
		return rest_ensure_response( array( 'ok' => true, 'task' => self::shape( self::task( $row['id'] ) ) ) );
	}

	public static function bulk( $req ) {
		global $wpdb;
		$p      = (array) $req->get_json_params();
		$ids    = array_slice( array_filter( array_map( 'intval', (array) ( isset( $p['ids'] ) ? $p['ids'] : array() ) ) ), 0, 300 );
		$action = isset( $p['action'] ) ? (string) $p['action'] : '';
		$t      = AXH_DB::table( 'tasks' );
		$n      = 0;
		foreach ( $ids as $id ) {
			if ( ! self::task( $id ) ) {
				continue;
			}
			if ( 'done' === $action ) {
				$was = self::task( $id );
				$n  += (int) $wpdb->update( $t, array( 'done_at' => AXH_DB::now(), 'updated_at' => AXH_DB::now() ), array( 'id' => $id ) );
				if ( $was && empty( $was['done_at'] ) ) {
					do_action( 'axh_task_done', (int) $id );
				}
			} elseif ( 'snooze' === $action ) {
				$timed = ! empty( $p['timed'] ) ? 1 : 0;
				$due   = self::due( isset( $p['due_at'] ) ? $p['due_at'] : '', $timed );
				if ( $due ) {
					$n += (int) $wpdb->update( $t, array( 'due_at' => $due, 'timed' => $timed, 'updated_at' => AXH_DB::now() ), array( 'id' => $id ) );
				}
			} elseif ( 'delete' === $action ) {
				$n += (int) $wpdb->delete( $t, array( 'id' => $id ) );
			}
		}
		return rest_ensure_response( array( 'ok' => true, 'count' => $n ) );
	}

	/** A note or call straight onto a deal's timeline (lost reasons, quick logs). */
	public static function log( $req ) {
		$p   = (array) $req->get_json_params();
		$lid = isset( $p['lead_id'] ) ? (int) $p['lead_id'] : 0;
		if ( ! $lid || ! self::can_lead( $lid ) ) {
			return new WP_Error( 'axh_lead', 'Lead not found.', array( 'status' => 404 ) );
		}
		$type = isset( $p['type'] ) && in_array( $p['type'], array( 'note', 'call', 'email', 'meeting', 'lost' ), true ) ? $p['type'] : 'note';
		AXH_DB::log( $lid, $type, mb_substr( sanitize_text_field( isset( $p['subject'] ) ? $p['subject'] : '' ), 0, 191 ), sanitize_textarea_field( isset( $p['body'] ) ? $p['body'] : '' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* --------------------------------------------------------- pipeline meta */

	public static function board( $req ) {
		global $wpdb;
		$l     = AXH_DB::table( 'leads' );
		$a     = AXH_DB::table( 'activity' );
		$t     = AXH_DB::table( 'tasks' );
		$scope = self::mgr() ? '1=1' : $wpdb->prepare( 'l.rep_id = %d', self::me() );

		// 1.14.0: one grouped pass instead of two lookups per deal.
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT l.id, l.created_at,
				MAX(CASE WHEN a.type = 'stage' THEN a.occurred_at END) AS stage_at,
				MAX(a.occurred_at) AS last_at
			FROM {$l} l LEFT JOIN {$a} a ON a.lead_id = l.id
			WHERE {$scope} AND l.archived_at IS NULL
			GROUP BY l.id, l.created_at",
			ARRAY_A
		);
		$tasks = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT t.id, t.lead_id, t.title, t.type, t.due_at, t.timed, t.priority FROM {$t} t
			JOIN {$l} l ON l.id = t.lead_id
			WHERE t.done_at IS NULL AND {$scope}
			ORDER BY (t.due_at IS NULL), t.due_at ASC",
			ARRAY_A
		);

		$meta = array();
		foreach ( $rows as $r ) {
			$meta[ (int) $r['id'] ] = array(
				'stage_at' => $r['stage_at'] ? $r['stage_at'] : $r['created_at'],
				'last_at'  => $r['last_at'] ? $r['last_at'] : $r['created_at'],
				'open'     => 0,
				'next'     => null,
			);
		}
		foreach ( $tasks as $x ) {
			$id = (int) $x['lead_id'];
			if ( ! isset( $meta[ $id ] ) ) {
				continue;
			}
			$meta[ $id ]['open']++;
			if ( ! $meta[ $id ]['next'] ) {
				$meta[ $id ]['next'] = array(
					'id'     => (int) $x['id'],
					'title'  => $x['title'],
					'type'   => $x['type'] ? $x['type'] : 'todo',
					'due_at' => $x['due_at'],
					'timed'  => (int) $x['timed'],
				);
			}
		}

		$prob = apply_filters(
			'axh_stage_probability',
			array( 'new' => 10, 'contacted' => 20, 'discovery' => 35, 'scoping' => 50, 'proposal' => 70, 'won' => 100, 'lost' => 0 )
		);

		return rest_ensure_response( array( 'meta' => (object) $meta, 'probability' => $prob, 'rot_days' => (int) apply_filters( 'axh_rot_days', 14 ) ) );
	}

	/* ---------------------------------------------------------------- routes */

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		register_rest_route( self::NS, '/flow/tasks', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_tasks' ), 'permission_callback' => $auth ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'add_task' ), 'permission_callback' => $auth ),
		) );
		register_rest_route( self::NS, '/flow/tasks/bulk', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'bulk' ), 'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/flow/tasks/(?P<id>\d+)', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'update_task' ), 'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/flow/log', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'log' ), 'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/flow/board', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'board' ), 'permission_callback' => $auth,
		) );
	}
}

AXH_Flow::init();
