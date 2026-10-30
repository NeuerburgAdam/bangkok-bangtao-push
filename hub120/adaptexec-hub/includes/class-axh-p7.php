<?php
/**
 * Sales Hub 1.20.0: sequences and round-robin routing.
 *
 * - Sequences: a rep's own cadence of calls, emails, texts and meetings.
 *   Enrolling a deal schedules the first step as a follow-up; completing it
 *   schedules the next. Nothing is ever sent by itself: email and text steps
 *   open the rep's compose window with the words filled in. A sequence stops
 *   when the deal is won or lost (and, if chosen, when it changes stage).
 * - Round-robin: an AdaptExec form can share new enquiries between reps in
 *   turn. Someone already in the Hub stays with the rep who has them.
 *
 * Additive only: two new tables, two nullable columns on tasks, options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P7 {

	const DB    = '1';
	const NS    = 'axh/v1';
	const TYPES = array( 'call', 'email', 'text', 'meeting', 'todo' );

	/** Steps scheduled during this request, so the Hub can say so. */
	protected static $made = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 10 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'axh_task_done', array( __CLASS__, 'on_done' ), 20 );
		add_action( 'axh_stage_changed', array( __CLASS__, 'on_stage' ), 5, 3 ); // before stage automations, so they see the step closed
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'tell' ), 22, 3 );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'axh_p7_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$tasks = AXH_DB::table( 'tasks' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tasks ) ) !== $tasks ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cs = $wpdb->get_charset_collate();
		$sq = AXH_DB::table( 'sequences' );
		$rn = AXH_DB::table( 'seq_runs' );
		dbDelta(
			"CREATE TABLE {$sq} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rep_id bigint(20) unsigned NOT NULL,
			name varchar(120) NOT NULL DEFAULT '',
			steps longtext NULL,
			shared tinyint(1) NOT NULL DEFAULT 0,
			stop_on_stage tinyint(1) NOT NULL DEFAULT 1,
			archived_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY rep_id (rep_id)
			) {$cs};"
		);
		dbDelta(
			"CREATE TABLE {$rn} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			seq_id bigint(20) unsigned NOT NULL,
			lead_id bigint(20) unsigned NOT NULL,
			rep_id bigint(20) unsigned NOT NULL,
			step smallint(5) unsigned NOT NULL DEFAULT 0,
			status varchar(12) NOT NULL DEFAULT 'active',
			task_id bigint(20) unsigned NULL,
			started_at datetime NOT NULL,
			ended_at datetime NULL,
			ended_reason varchar(120) NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY seq_id (seq_id)
			) {$cs};"
		);
		$ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sq ) ) === $sq && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rn ) ) === $rn;
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$tasks}", 0 ); // phpcs:ignore
		foreach ( array( 'seq_run' => 'ADD COLUMN seq_run BIGINT UNSIGNED NULL', 'seq_step' => 'ADD COLUMN seq_step SMALLINT UNSIGNED NULL' ) as $col => $ddl ) {
			if ( ! in_array( $col, $have, true ) && false === $wpdb->query( "ALTER TABLE {$tasks} {$ddl}" ) ) { // phpcs:ignore
				$ok = false;
			}
		}
		if ( $ok ) {
			update_option( 'axh_p7_db', self::DB, false );
		}
	}

	public static function routes() {
		$use = array( 'AXH_Rest', 'can_use' );
		$r   = function ( $route, $method, $cb, $auth = null ) use ( $use ) {
			register_rest_route( self::NS, $route, array( 'methods' => $method, 'callback' => array( __CLASS__, $cb ), 'permission_callback' => $auth ? $auth : $use ) );
		};
		$r( '/p7/seq', 'GET', 'rest_list' );
		$r( '/p7/seq', 'POST', 'rest_save' );
		$r( '/p7/seq/(?P<id>\\d+)/archive', 'POST', 'rest_archive' );
		$r( '/p7/seq/(?P<id>\\d+)/enroll', 'POST', 'rest_enroll' );
		$r( '/p7/runs/(?P<id>\\d+)/stop', 'POST', 'rest_stop' );
		$r( '/p7/runs/(?P<id>\\d+)/resume', 'POST', 'rest_resume' );
		$r( '/p7/lead/(?P<id>\\d+)/runs', 'GET', 'rest_lead_runs' );
		$r( '/p7/forms/(?P<id>\\d+)/rotation', 'GET', 'rest_get_rotation', array( 'AXH_Rest', 'can_manage' ) );
		$r( '/p7/forms/(?P<id>\\d+)/rotation', 'POST', 'rest_save_rotation', array( 'AXH_Rest', 'can_manage' ) );
		$r( '/p7/discovery', 'GET', 'rest_discovery' );
	}

	/**
	 * Where each deal's discovery questionnaire stands, for the board and lists.
	 * Read-only; a rep sees their own deals, a manager everyone's.
	 */
	public static function rest_discovery( $req ) {
		global $wpdb;
		$d     = AXH_DB::table( 'discovery' );
		$l     = AXH_DB::table( 'leads' );
		$scope = axh_is_manager() ? '1=1' : $wpdb->prepare( 'l.rep_id = %d', get_current_user_id() );
		$rows  = (array) $wpdb->get_results( "SELECT d.lead_id, d.status, d.progress, d.token, d.opened_at, d.client_saved_at, d.submitted_at FROM {$d} d JOIN {$l} l ON l.id = d.lead_id WHERE {$scope} ORDER BY d.id DESC LIMIT 5000", ARRAY_A ); // phpcs:ignore
		$out   = array();
		foreach ( $rows as $x ) {
			$id = (int) $x['lead_id'];
			if ( isset( $out[ $id ] ) ) {
				continue;
			}
			$out[ $id ] = array(
				'status'    => (string) $x['status'],
				'progress'  => (int) $x['progress'],
				'sent'      => '' !== (string) $x['token'],
				'opened'    => $x['opened_at'] ? (string) $x['opened_at'] : '',
				'client'    => $x['client_saved_at'] ? (string) $x['client_saved_at'] : '',
				'submitted' => $x['submitted_at'] ? (string) $x['submitted_at'] : '',
			);
		}
		return rest_ensure_response( array( 'leads' => (object) $out ) );
	}

	protected static function url_id( $req ) {
		$u = $req->get_url_params();
		return isset( $u['id'] ) ? (int) $u['id'] : 0;
	}

	protected static function ready() {
		return (bool) get_option( 'axh_p7_db' );
	}

	/* ------------------------------------------------------------- sequences */

	public static function starters() {
		$sig = "\n\nThanks,\n{rep}";
		return array(
			array(
				'key'   => 'enquiry',
				'name'  => 'New enquiry',
				'about' => 'For a form fill or a referral: reach them the same day, then keep trying for two weeks.',
				'steps' => array(
					array( 'type' => 'call', 'day' => 0, 'title' => 'Call [name] about their enquiry' ),
					array( 'type' => 'email', 'day' => 0, 'title' => 'Thanks for reaching out', 'subject' => 'Thanks for reaching out', 'body' => "Hi [name],\n\nThanks for getting in touch about [company]. I tried you by phone and will try again, but if email is easier, reply with a good time for a 15-minute call and I will work around you." . $sig ),
					array( 'type' => 'call', 'day' => 2, 'title' => 'Second call to [name]' ),
					array( 'type' => 'text', 'day' => 4, 'title' => 'Text [name]', 'body' => 'Hi [name], it’s {rep} with AdaptExec, following up on your enquiry. Is there a good time this week for a quick call?' ),
					array( 'type' => 'email', 'day' => 7, 'title' => 'Still interested?', 'subject' => 'Still worth a conversation?', 'body' => "Hi [name],\n\nI haven’t been able to reach you, so one more note in case the timing is better now. If it isn’t, no problem at all; just let me know and I won’t keep following up." . $sig ),
					array( 'type' => 'call', 'day' => 14, 'title' => 'Last call to [name]' ),
				),
			),
			array(
				'key'   => 'met',
				'name'  => 'After a first conversation',
				'about' => 'Recap what you heard, then keep the deal moving toward discovery.',
				'steps' => array(
					array( 'type' => 'email', 'day' => 1, 'title' => 'Recap the conversation', 'subject' => 'Great talking today', 'body' => "Hi [name],\n\nThanks for the time. Here is what I heard about how things run at [company]:\n\n- \n- \n\nThe next step is a short discovery session so AdaptExec can put a written proposal together. Does [day] work?" . $sig ),
					array( 'type' => 'call', 'day' => 4, 'title' => 'Book discovery with [name]' ),
					array( 'type' => 'email', 'day' => 9, 'title' => 'Share an example', 'subject' => 'An example from a business like yours', 'body' => "Hi [name],\n\nI thought this would be useful: here is what a business like [company] looks like once the busywork is handled. Happy to walk you through it.\n\n[demo link]" . $sig ),
					array( 'type' => 'call', 'day' => 16, 'title' => 'Check in with [name]' ),
				),
			),
			array(
				'key'   => 'proposal',
				'name'  => 'Proposal follow-through',
				'about' => 'Once the proposal is out: answer questions quickly and get to a decision.',
				'steps' => array(
					array( 'type' => 'call', 'day' => 2, 'title' => 'Walk [name] through the proposal' ),
					array( 'type' => 'email', 'day' => 5, 'title' => 'Proposal questions', 'subject' => 'Any questions on the proposal?', 'body' => "Hi [name],\n\nChecking in on the proposal AdaptExec sent over. If anything needs adjusting before you decide, tell me and I will get it changed." . $sig ),
					array( 'type' => 'call', 'day' => 10, 'title' => 'Decision call with [name]' ),
					array( 'type' => 'email', 'day' => 15, 'title' => 'Close the loop', 'subject' => 'Should I close your file?', 'body' => "Hi [name],\n\nI haven’t heard back, so I’m guessing the timing isn’t right. Should I close your file for now? If you’d still like to go ahead, just reply and we’ll pick up where we left off." . $sig ),
				),
			),
		);
	}

	protected static function clean_steps( $in ) {
		$out = array();
		foreach ( array_slice( (array) $in, 0, 15 ) as $s ) {
			$type  = in_array( ( $s['type'] ?? '' ), self::TYPES, true ) ? $s['type'] : 'call';
			$step  = array(
				'type'  => $type,
				'day'   => max( 0, min( 365, (int) ( $s['day'] ?? 0 ) ) ),
				'title' => mb_substr( sanitize_text_field( (string) ( $s['title'] ?? '' ) ), 0, 150 ),
			);
			if ( 'email' === $type ) {
				$step['subject'] = mb_substr( sanitize_text_field( (string) ( $s['subject'] ?? '' ) ), 0, 180 );
			}
			if ( in_array( $type, array( 'email', 'text' ), true ) ) {
				$step['body'] = mb_substr( sanitize_textarea_field( (string) ( $s['body'] ?? '' ) ), 0, 5000 );
			}
			if ( '' === $step['title'] ) {
				$step['title'] = 'email' === $type && ! empty( $step['subject'] ) ? $step['subject'] : ucfirst( $type ) . ' [name]';
			}
			$out[] = $step;
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['day'] <=> $b['day'];
			}
		);
		return $out;
	}

	protected static function shape( $r ) {
		$st = json_decode( (string) $r['steps'], true );
		return array(
			'id'            => (int) $r['id'],
			'rep_id'        => (int) $r['rep_id'],
			'name'          => $r['name'],
			'steps'         => is_array( $st ) ? $st : array(),
			'shared'        => (bool) $r['shared'],
			'stop_on_stage' => (bool) $r['stop_on_stage'],
			'mine'          => (int) $r['rep_id'] === get_current_user_id(),
			'active'        => isset( $r['active'] ) ? (int) $r['active'] : 0,
			'done'          => isset( $r['done'] ) ? (int) $r['done'] : 0,
		);
	}

	/** A sequence this person may use: their own, or one AdaptExec shared. */
	protected static function usable( $id ) {
		global $wpdb;
		$t   = AXH_DB::table( 'sequences' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND archived_at IS NULL", (int) $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row || ( (int) $row['rep_id'] !== get_current_user_id() && ! (int) $row['shared'] && ! axh_is_manager() ) ) {
			return null;
		}
		return $row;
	}

	public static function rest_list( $req ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return rest_ensure_response( array( 'sequences' => array(), 'starters' => self::starters() ) );
		}
		$t     = AXH_DB::table( 'sequences' );
		$rn    = AXH_DB::table( 'seq_runs' );
		$me    = get_current_user_id();
		$where = axh_is_manager() ? 's.archived_at IS NULL' : $wpdb->prepare( 's.archived_at IS NULL AND ( s.rep_id = %d OR s.shared = 1 )', $me );
		$runs  = axh_is_manager() ? '' : $wpdb->prepare( ' AND r.rep_id = %d', $me );
		$rows  = (array) $wpdb->get_results( "SELECT s.*, SUM(r.status = 'active') AS active, SUM(r.status = 'finished') AS done FROM {$t} s LEFT JOIN {$rn} r ON r.seq_id = s.id{$runs} WHERE {$where} GROUP BY s.id ORDER BY s.rep_id = {$me} DESC, s.name ASC", ARRAY_A ); // phpcs:ignore
		return rest_ensure_response( array( 'sequences' => array_map( array( __CLASS__, 'shape' ), $rows ), 'starters' => self::starters() ) );
	}

	public static function rest_save( $req ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return new WP_Error( 'axh_not_ready', 'Not ready yet. Reload and try again.', array( 'status' => 503 ) );
		}
		$b     = (array) $req->get_json_params();
		$name  = mb_substr( sanitize_text_field( (string) ( $b['name'] ?? '' ) ), 0, 120 );
		$steps = self::clean_steps( $b['steps'] ?? array() );
		if ( '' === $name || ! $steps ) {
			return new WP_Error( 'axh_bad', 'Give the sequence a name and at least one step.', array( 'status' => 400 ) );
		}
		$t   = AXH_DB::table( 'sequences' );
		$row = array(
			'name'          => $name,
			'steps'         => wp_json_encode( $steps ),
			'shared'        => axh_is_manager() && ! empty( $b['shared'] ) ? 1 : 0,
			'stop_on_stage' => ! array_key_exists( 'stop_on_stage', $b ) || ! empty( $b['stop_on_stage'] ) ? 1 : 0,
			'updated_at'    => AXH_DB::now(),
		);
		$id = (int) ( $b['id'] ?? 0 );
		if ( $id ) {
			$cur = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND archived_at IS NULL", $id ), ARRAY_A ); // phpcs:ignore
			if ( ! $cur || ( (int) $cur['rep_id'] !== get_current_user_id() && ! axh_is_manager() ) ) {
				return new WP_Error( 'axh_forbidden', 'Only its owner can change this sequence. Duplicate it to make your own.', array( 'status' => 403 ) );
			}
			$wpdb->update( $t, $row, array( 'id' => $id ) );
		} else {
			$row['rep_id']     = get_current_user_id();
			$row['created_at'] = AXH_DB::now();
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
		}
		return rest_ensure_response( array( 'id' => $id ) );
	}

	/** Archived, never deleted; deals in it keep going until they finish or stop. */
	public static function rest_archive( $req ) {
		global $wpdb;
		$t   = AXH_DB::table( 'sequences' );
		$cur = self::ready() ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", self::url_id( $req ) ), ARRAY_A ) : null; // phpcs:ignore
		if ( ! $cur || ( (int) $cur['rep_id'] !== get_current_user_id() && ! axh_is_manager() ) ) {
			return new WP_Error( 'axh_not_found', 'Sequence not found.', array( 'status' => 404 ) );
		}
		$wpdb->update( $t, array( 'archived_at' => AXH_DB::now() ), array( 'id' => (int) $cur['id'] ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ---------------------------------------------------------------- runs */

	protected static function due_for( $run, $step ) {
		$start = strtotime( substr( (string) $run['started_at'], 0, 10 ) . ' 00:00:00' );
		$due   = $start + (int) $step['day'] * DAY_IN_SECONDS;
		$today = strtotime( substr( AXH_DB::now(), 0, 10 ) . ' 00:00:00' );
		return gmdate( 'Y-m-d', max( $due, $today ) ) . ' 00:00:00';
	}

	/** Put the run's current step on the deal as a follow-up. */
	protected static function schedule( $run, $seq ) {
		global $wpdb;
		$steps = json_decode( (string) $seq['steps'], true );
		$i     = (int) $run['step'];
		if ( ! is_array( $steps ) || ! isset( $steps[ $i ] ) ) {
			return 0;
		}
		$s     = $steps[ $i ];
		$l     = AXH_DB::table( 'leads' );
		$lead  = $wpdb->get_row( $wpdb->prepare( "SELECT id, rep_id, company, contact_name FROM {$l} WHERE id = %d", (int) $run['lead_id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $lead ) {
			return 0;
		}
		$first = $lead['contact_name'] ? strtok( $lead['contact_name'], ' ' ) : $lead['company'];
		$title = strtr( (string) $s['title'], array( '[name]' => $first, '[company]' => $lead['company'] ) );
		$notes = 'Step ' . ( $i + 1 ) . ' of ' . count( $steps ) . ' in “' . $seq['name'] . '”.';
		if ( 'email' === $s['type'] ) {
			$notes .= "\n\nSubject: " . ( $s['subject'] ?? '' ) . "\n\n" . ( $s['body'] ?? '' );
		} elseif ( 'text' === $s['type'] ) {
			$notes .= "\n\n" . ( $s['body'] ?? '' );
		}
		$ok = $wpdb->insert(
			AXH_DB::table( 'tasks' ),
			array(
				'lead_id'    => (int) $lead['id'],
				'rep_id'     => (int) $lead['rep_id'],
				'title'      => mb_substr( $title, 0, 191 ),
				'type'       => $s['type'],
				'due_at'     => self::due_for( $run, $s ),
				'timed'      => 0,
				'notes'      => $notes,
				'seq_run'    => (int) $run['id'],
				'seq_step'   => $i,
				'created_at' => AXH_DB::now(),
				'updated_at' => AXH_DB::now(),
			)
		);
		if ( ! $ok ) {
			return 0;
		}
		$tid = (int) $wpdb->insert_id;
		$wpdb->update( AXH_DB::table( 'seq_runs' ), array( 'task_id' => $tid ), array( 'id' => (int) $run['id'] ) );
		self::$made[] = array( 'title' => mb_substr( $title, 0, 191 ), 'due_at' => self::due_for( $run, $s ), 'seq' => $seq['name'] );
		return $tid;
	}

	public static function rest_enroll( $req ) {
		global $wpdb;
		$seq = self::ready() ? self::usable( self::url_id( $req ) ) : null;
		if ( ! $seq ) {
			return new WP_Error( 'axh_not_found', 'Sequence not found.', array( 'status' => 404 ) );
		}
		$b    = (array) $req->get_json_params();
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', array_slice( (array) ( $b['lead_ids'] ?? array() ), 0, 200 ) ) ) ) );
		$rn   = AXH_DB::table( 'seq_runs' );
		$done = 0;
		$skip = array();
		foreach ( $ids as $id ) {
			$lead = AXH_DB::get_lead( $id );
			if ( ! $lead ) {
				continue;
			}
			if ( in_array( $lead['stage'], array( 'won', 'lost' ), true ) || ! empty( $lead['archived_at'] ) ) {
				$skip[] = $lead['company'] . ' is closed';
				continue;
			}
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rn} WHERE lead_id = %d AND status = 'active' LIMIT 1", $id ) ) ) { // phpcs:ignore
				$skip[] = $lead['company'] . ' is already in a sequence';
				continue;
			}
			$wpdb->insert( $rn, array( 'seq_id' => (int) $seq['id'], 'lead_id' => $id, 'rep_id' => (int) $lead['rep_id'], 'step' => 0, 'status' => 'active', 'started_at' => AXH_DB::now() ) );
			$run = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rn} WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A ); // phpcs:ignore
			if ( $run && self::schedule( $run, $seq ) ) {
				AXH_DB::log( $id, 'note', 'Started the sequence “' . $seq['name'] . '”', count( (array) json_decode( (string) $seq['steps'], true ) ) . ' steps, each added as a follow-up when the one before is done.' );
				++$done;
			}
		}
		return rest_ensure_response( array( 'enrolled' => $done, 'skipped' => $skip ) );
	}

	/** Stop a run: its open step is closed (kept on record, not deleted). */
	protected static function stop( $run, $reason ) {
		global $wpdb;
		$rn = AXH_DB::table( 'seq_runs' );
		$wpdb->update( $rn, array( 'status' => 'stopped', 'ended_at' => AXH_DB::now(), 'ended_reason' => mb_substr( $reason, 0, 120 ) ), array( 'id' => (int) $run['id'] ) );
		if ( ! empty( $run['task_id'] ) ) {
			$t = AXH_DB::table( 'tasks' );
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET done_at = %s, outcome = %s WHERE id = %d AND done_at IS NULL", AXH_DB::now(), 'Sequence stopped', (int) $run['task_id'] ) ); // phpcs:ignore
		}
	}

	public static function rest_stop( $req ) {
		global $wpdb;
		$rn  = AXH_DB::table( 'seq_runs' );
		$run = self::ready() ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rn} WHERE id = %d", self::url_id( $req ) ), ARRAY_A ) : null; // phpcs:ignore
		if ( ! $run || ! AXH_DB::get_lead( (int) $run['lead_id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		if ( 'active' === $run['status'] ) {
			self::stop( $run, 'Stopped by ' . wp_get_current_user()->display_name );
			AXH_DB::log( (int) $run['lead_id'], 'note', 'Stopped the sequence', '' );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/** The open step was deleted: put it back so the sequence can carry on. */
	public static function rest_resume( $req ) {
		global $wpdb;
		$rn  = AXH_DB::table( 'seq_runs' );
		$run = self::ready() ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rn} WHERE id = %d AND status = 'active'", self::url_id( $req ) ), ARRAY_A ) : null; // phpcs:ignore
		if ( ! $run || ! AXH_DB::get_lead( (int) $run['lead_id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$t = AXH_DB::table( 'tasks' );
		if ( $run['task_id'] && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE id = %d AND done_at IS NULL", (int) $run['task_id'] ) ) ) { // phpcs:ignore
			return rest_ensure_response( array( 'ok' => true ) );
		}
		$sq  = AXH_DB::table( 'sequences' );
		$seq = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sq} WHERE id = %d", (int) $run['seq_id'] ), ARRAY_A ); // phpcs:ignore
		if ( $seq ) {
			self::schedule( $run, $seq );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function rest_lead_runs( $req ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( self::url_id( $req ) );
		if ( ! $lead || ! self::ready() ) {
			return rest_ensure_response( array( 'runs' => array() ) );
		}
		$rn   = AXH_DB::table( 'seq_runs' );
		$sq   = AXH_DB::table( 'sequences' );
		$t    = AXH_DB::table( 'tasks' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT r.*, s.name, s.steps, t.title AS task_title, t.due_at AS task_due, t.type AS task_type FROM {$rn} r JOIN {$sq} s ON s.id = r.seq_id LEFT JOIN {$t} t ON t.id = r.task_id WHERE r.lead_id = %d ORDER BY r.id DESC LIMIT 10", (int) $lead['id'] ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $r ) {
			$steps = json_decode( (string) $r['steps'], true );
			$out[] = array(
				'id'     => (int) $r['id'],
				'name'   => $r['name'],
				'status' => $r['status'],
				'step'   => (int) $r['step'],
				'steps'  => is_array( $steps ) ? array_map( function ( $s ) { return array( 'type' => $s['type'], 'day' => (int) $s['day'], 'title' => $s['title'] ); }, $steps ) : array(),
				'next'   => 'active' === $r['status'] && $r['task_title'] ? array( 'title' => $r['task_title'], 'due_at' => $r['task_due'], 'type' => $r['task_type'], 'id' => (int) $r['task_id'] ) : null,
				'stalled' => 'active' === $r['status'] && ! $r['task_title'],
				'started_at' => $r['started_at'],
				'ended_at'   => $r['ended_at'],
				'reason'     => $r['ended_reason'],
			);
		}
		return rest_ensure_response( array( 'runs' => $out ) );
	}

	/** A step was completed: schedule the next one, or finish. */
	public static function on_done( $task_id ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return;
		}
		$t    = AXH_DB::table( 'tasks' );
		$task = $wpdb->get_row( $wpdb->prepare( "SELECT id, seq_run FROM {$t} WHERE id = %d", (int) $task_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $task || empty( $task['seq_run'] ) ) {
			return;
		}
		$rn  = AXH_DB::table( 'seq_runs' );
		$run = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rn} WHERE id = %d AND status = 'active' AND task_id = %d", (int) $task['seq_run'], (int) $task['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $run ) {
			return;
		}
		$sq    = AXH_DB::table( 'sequences' );
		$seq   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sq} WHERE id = %d", (int) $run['seq_id'] ), ARRAY_A ); // phpcs:ignore
		$steps = $seq ? json_decode( (string) $seq['steps'], true ) : array();
		$next  = (int) $run['step'] + 1;
		if ( ! $seq || ! is_array( $steps ) || $next >= count( $steps ) ) {
			$wpdb->update( $rn, array( 'status' => 'finished', 'ended_at' => AXH_DB::now(), 'ended_reason' => 'All steps done', 'task_id' => null ), array( 'id' => (int) $run['id'] ) );
			AXH_DB::log( (int) $run['lead_id'], 'note', 'Finished the sequence “' . ( $seq ? $seq['name'] : '' ) . '”', '' );
			self::$made[] = array( 'finished' => true, 'seq' => $seq ? $seq['name'] : '' );
			return;
		}
		$wpdb->update( $rn, array( 'step' => $next ), array( 'id' => (int) $run['id'] ) );
		$run['step'] = $next;
		self::schedule( $run, $seq );
	}

	/** Won or lost always stops a sequence; any stage change does if the sequence says so. */
	public static function on_stage( $lead_id, $from, $to ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return;
		}
		$rn = AXH_DB::table( 'seq_runs' );
		$sq = AXH_DB::table( 'sequences' );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT r.*, s.stop_on_stage, s.name FROM {$rn} r JOIN {$sq} s ON s.id = r.seq_id WHERE r.lead_id = %d AND r.status = 'active'", (int) $lead_id ), ARRAY_A ) as $run ) { // phpcs:ignore
			if ( in_array( $to, array( 'won', 'lost' ), true ) || (int) $run['stop_on_stage'] ) {
				self::stop( $run, 'Deal moved to ' . AXH_DB::stage_label( $to ) );
				AXH_DB::log( (int) $lead_id, 'note', 'Sequence “' . $run['name'] . '” stopped', 'The deal moved to ' . AXH_DB::stage_label( $to ) . '.' );
			}
		}
	}

	public static function tell( $res, $server, $req ) {
		if ( self::$made && $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/' . self::NS . '/' ) ) {
			$data = $res->get_data();
			if ( is_array( $data ) && ! isset( $data['axh_seq'] ) ) {
				$data['axh_seq'] = array_slice( self::$made, 0, 20 );
				$res->set_data( $data );
			}
		}
		self::$made = array();
		return $res;
	}

	public static function delete_for_lead( $lead_id ) {
		global $wpdb;
		if ( self::ready() ) {
			$wpdb->delete( AXH_DB::table( 'seq_runs' ), array( 'lead_id' => (int) $lead_id ) );
		}
	}

	/* ------------------------------------------------------- round-robin */

	protected static function rotation( $form_id ) {
		$v = get_option( 'axh_rr_' . (int) $form_id, array() );
		return is_array( $v ) ? wp_parse_args( $v, array( 'on' => false, 'reps' => array(), 'next' => 0 ) ) : array( 'on' => false, 'reps' => array(), 'next' => 0 );
	}

	public static function rest_get_rotation( $req ) {
		$r = self::rotation( self::url_id( $req ) );
		return rest_ensure_response( array( 'on' => (bool) $r['on'], 'reps' => array_map( 'intval', (array) $r['reps'] ) ) );
	}

	public static function rest_save_rotation( $req ) {
		$id = self::url_id( $req );
		if ( ! AXH_DB::get_form( $id ) ) {
			return new WP_Error( 'axh_not_found', 'Form not found.', array( 'status' => 404 ) );
		}
		$b    = (array) $req->get_json_params();
		$reps = array();
		foreach ( array_slice( (array) ( $b['reps'] ?? array() ), 0, 50 ) as $u ) {
			$u = (int) $u;
			if ( $u && user_can( $u, 'axh_use_hub' ) && ! in_array( $u, $reps, true ) ) {
				$reps[] = $u;
			}
		}
		$cur = self::rotation( $id );
		update_option( 'axh_rr_' . $id, array( 'on' => ! empty( $b['on'] ) && count( $reps ) > 0, 'reps' => $reps, 'next' => (int) $cur['next'] % max( 1, count( $reps ) ) ), false );
		return self::rest_get_rotation( $req );
	}

	/**
	 * Who a new enquiry on this form goes to. Someone already in the Hub stays
	 * with the rep who has them; otherwise the next rep in turn.
	 */
	public static function route_owner( $form, $answers ) {
		global $wpdb;
		$r = self::rotation( (int) $form['id'] );
		if ( empty( $r['on'] ) || ! $r['reps'] ) {
			return (int) $form['rep_id'];
		}
		$l     = AXH_DB::table( 'leads' );
		$email = strtolower( trim( (string) ( $answers['email'] ?? '' ) ) );
		if ( $email && is_email( $email ) ) {
			$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT rep_id FROM {$l} WHERE LOWER(email) = %s AND archived_at IS NULL ORDER BY id DESC LIMIT 1", $email ) ); // phpcs:ignore
			if ( $owner && user_can( $owner, 'axh_use_hub' ) ) {
				return $owner;
			}
		}
		$reps = array_values( array_filter( array_map( 'intval', (array) $r['reps'] ), function ( $u ) { return user_can( $u, 'axh_use_hub' ); } ) );
		if ( ! $reps ) {
			return (int) $form['rep_id'];
		}
		$i = (int) $r['next'] % count( $reps );
		$r['next'] = ( $i + 1 ) % count( $reps );
		update_option( 'axh_rr_' . (int) $form['id'], $r, false );
		return $reps[ $i ];
	}
}

AXH_P7::init();
