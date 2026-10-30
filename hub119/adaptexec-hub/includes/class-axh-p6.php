<?php
/**
 * Sales Hub 1.19.0: what to do next, and how the business is trending.
 *
 * - Deal health: every open deal gets a 0–100 score from what the buyer is
 *   doing (deck visits, email opens and clicks, forms) and what the rep is
 *   doing (recent contact, a next step, time in stage, the close date), with
 *   the reasons in plain words.
 * - Focus: the handful of deals to act on now, each with why and what to do.
 * - Insights: won business, new deals, activity, conversion by stage, time
 *   in each stage, win rate, deal size, days to close and sales velocity.
 *
 * Read only: no tables, no columns, nothing written.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P6 {

	const NS = 'axh/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		register_rest_route( self::NS, '/p6/health', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_health' ), 'permission_callback' => $auth ) );
		register_rest_route( self::NS, '/p6/insights', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_insights' ), 'permission_callback' => $auth ) );
	}

	protected static function ts( $d ) {
		return $d ? strtotime( (string) $d ) : 0;
	}

	protected static function scope_ids( $rep, $lead_id = 0 ) {
		global $wpdb;
		$l     = AXH_DB::table( 'leads' );
		$where = array( 'archived_at IS NULL', "stage NOT IN ('won','lost')" );
		if ( ! axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'rep_id = %d', get_current_user_id() );
		} elseif ( $rep ) {
			$where[] = $wpdb->prepare( 'rep_id = %d', (int) $rep );
		}
		if ( $lead_id ) {
			$where[] = $wpdb->prepare( 'id = %d', (int) $lead_id );
		}
		return (array) $wpdb->get_results( "SELECT id, rep_id, company, contact_name, phone, email, stage, est_value, created_at, close_by FROM {$l} WHERE " . implode( ' AND ', $where ) . ' LIMIT 1000', ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Health for open deals, keyed by lead id.
	 *
	 * @return array{ leads: array, focus: array }
	 */
	public static function compute( $rep = 0, $lead_id = 0 ) {
		global $wpdb;
		$rows = self::scope_ids( $rep, $lead_id );
		if ( ! $rows ) {
			return array( 'leads' => array(), 'focus' => array() );
		}
		$ids  = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
		$now  = self::ts( AXH_DB::now() );
		$day  = DAY_IN_SECONDS;
		$a    = AXH_DB::table( 'activity' );
		$t    = AXH_DB::table( 'tasks' );
		$sig  = array();
		$push = function ( $id, $at, $kind, $text ) use ( &$sig ) {
			$id = (int) $id;
			$ts = self::ts( $at );
			if ( ! $ts ) {
				return;
			}
			if ( ! isset( $sig[ $id ] ) || $ts > $sig[ $id ]['ts'] ) {
				$sig[ $id ] = array( 'ts' => $ts, 'at' => $at, 'kind' => $kind, 'text' => $text, 'n' => isset( $sig[ $id ] ) ? $sig[ $id ]['n'] + 1 : 1, 'hot' => ! empty( $sig[ $id ]['hot'] ) );
			} else {
				++$sig[ $id ]['n'];
			}
		};
		$since = gmdate( 'Y-m-d H:i:s', $now - 14 * $day );

		// What the rep did: last real contact (not stage changes or system entries).
		$touch = array();
		foreach ( (array) $wpdb->get_results( "SELECT lead_id, MAX(occurred_at) AS at FROM {$a} WHERE lead_id IN ({$ids}) AND type IN ('call','email','text','meeting') GROUP BY lead_id", ARRAY_A ) as $r ) { // phpcs:ignore
			$touch[ (int) $r['lead_id'] ] = self::ts( $r['at'] );
		}
		$stage_at = array();
		foreach ( (array) $wpdb->get_results( "SELECT lead_id, MAX(occurred_at) AS at FROM {$a} WHERE lead_id IN ({$ids}) AND type = 'stage' GROUP BY lead_id", ARRAY_A ) as $r ) { // phpcs:ignore
			$stage_at[ (int) $r['lead_id'] ] = self::ts( $r['at'] );
		}
		// Next steps.
		$next = array();
		foreach ( (array) $wpdb->get_results( "SELECT lead_id, id, title, type, due_at, timed FROM {$t} WHERE lead_id IN ({$ids}) AND done_at IS NULL ORDER BY (due_at IS NULL), due_at ASC", ARRAY_A ) as $r ) { // phpcs:ignore
			$lid = (int) $r['lead_id'];
			if ( ! isset( $next[ $lid ] ) ) {
				$next[ $lid ] = $r;
			}
		}
		// What the buyer did.
		if ( get_option( 'axh_p1_db' ) ) {
			$v = AXH_DB::table( 'share_views' );
			$s = AXH_DB::table( 'shares' );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT s.lead_id, v.started_at, v.last_at, v.cta, v.finished, v.seconds FROM {$v} v JOIN {$s} s ON s.id = v.share_id WHERE s.lead_id IN ({$ids}) AND v.last_at >= %s", $since ), ARRAY_A ) as $r ) { // phpcs:ignore
				$push( $r['lead_id'], $r['last_at'], 'deck', (int) $r['cta'] ? 'clicked the button on your deck' : ( (int) $r['finished'] ? 'watched your deck to the end' : 'looked at your deck' ) );
				if ( (int) $r['cta'] ) {
					$sig[ (int) $r['lead_id'] ]['hot'] = true;
				}
			}
		}
		if ( get_option( 'axh_p4_db' ) ) {
			$e = AXH_DB::table( 'email_tracks' );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT lead_id, subject, opens, clicks, last_open_at, last_click_at FROM {$e} WHERE lead_id IN ({$ids}) AND ( last_open_at >= %s OR last_click_at >= %s )", $since, $since ), ARRAY_A ) as $r ) { // phpcs:ignore
				if ( $r['last_click_at'] && self::ts( $r['last_click_at'] ) >= self::ts( $since ) ) {
					$push( $r['lead_id'], $r['last_click_at'], 'click', 'clicked a link in your email' );
					$sig[ (int) $r['lead_id'] ]['hot'] = true;
				}
				if ( $r['last_open_at'] ) {
					$push( $r['lead_id'], $r['last_open_at'], 'open', (int) $r['opens'] > 1 ? 'opened your email ' . (int) $r['opens'] . ' times' : 'opened your email' );
				}
			}
		}
		$sub = AXH_DB::table( 'submissions' );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT lead_id, created_at FROM {$sub} WHERE lead_id IN ({$ids}) AND created_at >= %s", $since ), ARRAY_A ) as $r ) { // phpcs:ignore
			$push( $r['lead_id'], $r['created_at'], 'form', 'filled in your form' );
			$sig[ (int) $r['lead_id'] ]['hot'] = true;
		}

		$rot   = (int) apply_filters( 'axh_rot_days', 14 );
		$today = strtotime( gmdate( 'Y-m-d', $now ) );
		$out   = array();
		$focus = array();
		$ago   = function ( $ts ) use ( $now ) {
			$d = max( 0, $now - $ts );
			if ( $d < HOUR_IN_SECONDS ) {
				return 'just now';
			}
			if ( $d < DAY_IN_SECONDS ) {
				$h = (int) floor( $d / HOUR_IN_SECONDS );
				return $h . ' hour' . ( 1 === $h ? '' : 's' ) . ' ago';
			}
			$n = (int) floor( $d / DAY_IN_SECONDS );
			return 1 === $n ? 'yesterday' : $n . ' days ago';
		};
		foreach ( $rows as $l ) {
			$id      = (int) $l['id'];
			$created = self::ts( $l['created_at'] );
			$age     = (int) floor( ( $now - $created ) / $day );
			$score   = 50;
			$good    = array();
			$bad     = array();
			$s       = $sig[ $id ] ?? null;
			$sdays   = $s ? ( $now - $s['ts'] ) / $day : 99;
			$tch     = $touch[ $id ] ?? 0;
			$tdays   = $tch ? (int) floor( ( $now - $tch ) / $day ) : null;
			$nx      = $next[ $id ] ?? null;
			$in      = (int) floor( ( $now - ( $stage_at[ $id ] ?? $created ) ) / $day );

			if ( $s ) {
				$pts    = $sdays <= 3 ? 20 : ( $sdays <= 7 ? 12 : 6 );
				$score += $pts + ( ! empty( $s['hot'] ) ? 8 : 0 );
				$good[] = 'They ' . $s['text'] . ' ' . $ago( $s['ts'] );
			}
			if ( null === $tdays ) {
				$score -= $age > 3 ? 12 : 0;
				if ( $age > 3 ) {
					$bad[] = 'No call, email or meeting logged yet';
				}
			} elseif ( $tdays <= 3 ) {
				$score += 10;
				$good[] = 'You were in touch ' . $ago( $tch );
			} elseif ( $tdays <= 7 ) {
				$score += 5;
			} elseif ( $tdays > 30 ) {
				$score -= 25;
				$bad[] = 'No contact in ' . $tdays . ' days';
			} elseif ( $tdays > $rot ) {
				$score -= 15;
				$bad[] = 'No contact in ' . $tdays . ' days';
			}
			$overdue = 0;
			if ( $nx ) {
				$due = $nx['due_at'] ? self::ts( $nx['due_at'] ) : 0;
				if ( $due ) {
					$untimed = ! (int) $nx['timed'] && '00:00:00' === substr( (string) $nx['due_at'], 11 );
					$late    = $untimed ? $due < $today : $due < $now;
					if ( $late ) {
						$overdue = max( 1, (int) floor( ( $today - strtotime( gmdate( 'Y-m-d', $due ) ) ) / $day ) );
						$score  -= 10;
						$bad[]   = 'Next step overdue' . ( $overdue > 1 ? ' by ' . $overdue . ' days' : '' );
					} else {
						$score += 10;
						$good[] = 'Next step scheduled';
					}
				} else {
					$score += 4;
				}
			} else {
				$score -= 15;
				$bad[]  = 'No next step scheduled';
			}
			if ( $in >= 2 * $rot ) {
				$score -= 20;
				$bad[]  = $in . ' days in ' . AXH_DB::stage_label( $l['stage'] );
			} elseif ( $in >= $rot ) {
				$score -= 10;
				$bad[]  = $in . ' days in ' . AXH_DB::stage_label( $l['stage'] );
			}
			$close_late = 0;
			if ( ! empty( $l['close_by'] ) ) {
				$cb = strtotime( $l['close_by'] . ' 00:00:00' );
				if ( $cb < $today ) {
					$close_late = (int) floor( ( $today - $cb ) / $day );
					$score     -= 15;
					$bad[]      = 'Expected close date passed';
				} elseif ( $cb - $today <= 14 * $day && in_array( $l['stage'], array( 'scoping', 'proposal' ), true ) ) {
					$score += 5;
				}
			}
			$score += array( 'proposal' => 5, 'scoping' => 3 )[ $l['stage'] ] ?? 0;
			// A deal is not healthy while its next step is late or missing, or
			// while the buyer has been waiting on the rep for more than a day.
			if ( $overdue || ! $nx ) {
				$score = min( $score, 65 );
			}
			if ( $s && $sdays > 1 && ( ! $tch || $tch < $s['ts'] ) ) {
				$score = min( $score, 60 );
				array_unshift( $bad, 'They ' . $s['text'] . ' ' . $ago( $s['ts'] ) . ' and have not heard from you since' );
			}
			$score  = max( 0, min( 100, (int) round( $score ) ) );
			$label  = $age < 3 && ! $s && null === $tdays ? 'new' : ( $score >= 70 ? 'good' : ( $score >= 40 ? 'watch' : 'risk' ) );
			$out[ $id ] = array(
				'score'  => $score,
				'label'  => $label,
				'good'   => array_slice( $good, 0, 3 ),
				'bad'    => array_slice( $bad, 0, 3 ),
				'signal' => $s ? array( 'at' => $s['at'], 'kind' => $s['kind'], 'text' => $s['text'], 'count' => $s['n'], 'hot' => ! empty( $s['hot'] ) ) : null,
				'touch'  => $tch ? gmdate( 'Y-m-d H:i:s', $tch ) : null,
			);

			// Focus: the single most useful thing to do on this deal, if anything.
			$who  = $l['contact_name'] ? $l['contact_name'] : $l['company'];
			$item = null;
			if ( $s && $sdays <= 2 && ( ! $tch || $tch < $s['ts'] ) ) {
				$item = array( 'p' => 100 - $sdays * 10 + ( ! empty( $s['hot'] ) ? 15 : 0 ), 'kind' => 'signal', 'why' => ucfirst( $who ) . ' ' . $s['text'] . ' ' . $ago( $s['ts'] ) . '.', 'do' => 'Call while it is fresh' );
			} elseif ( $overdue ) {
				$item = array( 'p' => 70 + min( 20, $overdue * 2 ), 'kind' => 'overdue', 'why' => '“' . $nx['title'] . '” is ' . ( $overdue > 1 ? $overdue . ' days ' : '' ) . 'overdue.', 'do' => 'Do it or move it' );
			} elseif ( 'proposal' === $l['stage'] && ( null === $tdays || $tdays >= 5 ) ) {
				$item = array( 'p' => 65, 'kind' => 'proposal', 'why' => 'Proposal is out' . ( null !== $tdays ? ' and you last spoke ' . $tdays . ' days ago' : '' ) . '.', 'do' => 'Check in on the proposal' );
			} elseif ( $close_late ) {
				$item = array( 'p' => 55, 'kind' => 'close', 'why' => 'Expected close was ' . $close_late . ' day' . ( 1 === $close_late ? '' : 's' ) . ' ago.', 'do' => 'Update the date or the stage' );
			} elseif ( ! $nx ) {
				$item = array( 'p' => 45 + min( 15, (int) ( (float) $l['est_value'] / 2000 ) ), 'kind' => 'nonext', 'why' => 'Nothing scheduled with ' . $who . '.', 'do' => 'Schedule the next step' );
			} elseif ( null !== $tdays && $tdays > $rot ) {
				$item = array( 'p' => 40, 'kind' => 'cold', 'why' => 'Quiet for ' . $tdays . ' days.', 'do' => 'Re-engage' );
			}
			if ( $item ) {
				$focus[] = array_merge(
					$item,
					array( 'lead_id' => $id, 'company' => $l['company'], 'contact' => $l['contact_name'], 'phone' => $l['phone'], 'email' => $l['email'], 'stage' => $l['stage'], 'value' => (float) $l['est_value'], 'score' => $score, 'rep_id' => (int) $l['rep_id'] )
				);
			}
		}
		usort(
			$focus,
			function ( $x, $y ) {
				return $y['p'] <=> $x['p'] ?: $y['value'] <=> $x['value'];
			}
		);
		return array( 'leads' => $out, 'focus' => array_slice( $focus, 0, 12 ) );
	}

	public static function rest_health( $req ) {
		$r = self::compute( (int) $req->get_param( 'rep' ), (int) $req->get_param( 'lead' ) );
		return rest_ensure_response( array( 'leads' => (object) $r['leads'], 'focus' => $r['focus'] ) );
	}

	/* -------------------------------------------------------------- insights */

	public static function rest_insights( $req ) {
		global $wpdb;
		$rep   = axh_is_manager() ? (int) $req->get_param( 'rep' ) : get_current_user_id();
		$l     = AXH_DB::table( 'leads' );
		$a     = AXH_DB::table( 'activity' );
		$lw    = $rep ? $wpdb->prepare( 'rep_id = %d', $rep ) : '1=1';
		$now   = self::ts( AXH_DB::now() );
		$m0    = strtotime( gmdate( 'Y-m-01', $now ) );
		$start = strtotime( '-11 months', $m0 );
		$from  = gmdate( 'Y-m-d H:i:s', $start );

		$months = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$k            = gmdate( 'Y-m', strtotime( '+' . $i . ' months', $start ) );
			$months[ $k ] = array( 'm' => $k, 'won' => 0, 'won_value' => 0.0, 'created' => 0, 'lost' => 0 );
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(closed_at, '%%Y-%%m') AS m, stage, COUNT(*) AS n, SUM(contract_value) AS v FROM {$l} WHERE {$lw} AND stage IN ('won','lost') AND closed_at >= %s GROUP BY m, stage", $from ), ARRAY_A ) as $r ) { // phpcs:ignore
			if ( isset( $months[ $r['m'] ] ) ) {
				if ( 'won' === $r['stage'] ) {
					$months[ $r['m'] ]['won']       = (int) $r['n'];
					$months[ $r['m'] ]['won_value'] = (float) $r['v'];
				} else {
					$months[ $r['m'] ]['lost'] = (int) $r['n'];
				}
			}
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS m, COUNT(*) AS n FROM {$l} WHERE {$lw} AND created_at >= %s GROUP BY m", $from ), ARRAY_A ) as $r ) { // phpcs:ignore
			if ( isset( $months[ $r['m'] ] ) ) {
				$months[ $r['m'] ]['created'] = (int) $r['n'];
			}
		}

		// Activity by week (who did the work, not who owns the deal).
		$w0    = strtotime( 'monday this week', $now );
		$wfrom = strtotime( '-11 weeks', $w0 );
		$weeks = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$k           = gmdate( 'Y-m-d', strtotime( '+' . $i . ' weeks', $wfrom ) );
			$weeks[ $k ] = array( 'w' => $k, 'call' => 0, 'email' => 0, 'text' => 0, 'meeting' => 0 );
		}
		$aw = $rep ? $wpdb->prepare( 'rep_id = %d', $rep ) : '1=1';
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT type, occurred_at FROM {$a} WHERE {$aw} AND type IN ('call','email','text','meeting') AND occurred_at >= %s LIMIT 20000", gmdate( 'Y-m-d H:i:s', $wfrom ) ), ARRAY_A ) as $r ) { // phpcs:ignore
			$k = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $r['occurred_at'] ) ) );
			if ( isset( $weeks[ $k ] ) ) {
				++$weeks[ $k ][ $r['type'] ];
			}
		}

		// Stage journey for deals created in the last 12 months.
		$keys  = array_values( array_diff( AXH_Install::stage_keys(), array( 'lost' ) ) );
		$order = array_flip( $keys );
		$label = array();
		foreach ( AXH_Install::stages() as $s ) {
			$label[ $s['label'] ] = $s['key'];
		}
		$leads = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, stage, created_at, closed_at, contract_value, est_value FROM {$l} WHERE {$lw} AND created_at >= %s LIMIT 5000", $from ), ARRAY_A ); // phpcs:ignore
		$reach = array_fill_keys( $keys, 0 );
		$dwell = array_fill_keys( $keys, array( 0, 0 ) );
		$byid  = array();
		foreach ( $leads as $r ) {
			$byid[ (int) $r['id'] ] = array( 'lead' => $r, 'ev' => array() );
		}
		if ( $byid ) {
			$ids = implode( ',', array_keys( $byid ) );
			foreach ( (array) $wpdb->get_results( "SELECT lead_id, subject, occurred_at FROM {$a} WHERE lead_id IN ({$ids}) AND type = 'stage' ORDER BY occurred_at ASC, id ASC", ARRAY_A ) as $e ) { // phpcs:ignore
				if ( preg_match( '/^Stage: (.+?) → (.+)$/u', (string) $e['subject'], $m ) ) {
					$byid[ (int) $e['lead_id'] ]['ev'][] = array( $label[ $m[1] ] ?? null, $label[ $m[2] ] ?? null, self::ts( $e['occurred_at'] ) );
				}
			}
		}
		foreach ( $byid as $x ) {
			$lead = $x['lead'];
			$max  = 0;
			$cur  = $x['ev'] ? $x['ev'][0][0] : $lead['stage'];
			$at   = self::ts( $lead['created_at'] );
			foreach ( $x['ev'] as $e ) {
				if ( $cur && isset( $dwell[ $cur ] ) && $e[2] >= $at ) {
					$dwell[ $cur ][0] += ( $e[2] - $at ) / DAY_IN_SECONDS;
					++$dwell[ $cur ][1];
				}
				foreach ( array( $e[0], $e[1] ) as $k ) {
					if ( $k && isset( $order[ $k ] ) ) {
						$max = max( $max, $order[ $k ] );
					}
				}
				$cur = $e[1];
				$at  = $e[2];
			}
			if ( isset( $order[ $lead['stage'] ] ) ) {
				$max = max( $max, $order[ $lead['stage'] ] );
			}
			for ( $i = 0; $i <= $max; $i++ ) {
				++$reach[ $keys[ $i ] ];
			}
		}
		$funnel = array();
		foreach ( $keys as $i => $k ) {
			$funnel[] = array(
				'stage' => $k,
				'label' => AXH_DB::stage_label( $k ),
				'n'     => $reach[ $k ],
				'days'  => $dwell[ $k ][1] ? round( $dwell[ $k ][0] / $dwell[ $k ][1], 1 ) : null,
			);
		}

		// The headline numbers, over the last 12 months.
		$won   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT contract_value, created_at, closed_at FROM {$l} WHERE {$lw} AND stage = 'won' AND closed_at >= %s", $from ), ARRAY_A ); // phpcs:ignore
		$lost  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$l} WHERE {$lw} AND stage = 'lost' AND closed_at >= %s", $from ) ); // phpcs:ignore
		$wn    = count( $won );
		$wv    = array_sum( array_map( 'floatval', wp_list_pluck( $won, 'contract_value' ) ) );
		$cycle = $wn ? array_sum( array_map( function ( $r ) { return max( 0, ( self::ts( $r['closed_at'] ) - self::ts( $r['created_at'] ) ) / DAY_IN_SECONDS ); }, $won ) ) / $wn : null;
		$open  = (array) $wpdb->get_row( "SELECT COUNT(*) AS n, SUM(est_value) AS v FROM {$l} WHERE {$lw} AND archived_at IS NULL AND stage NOT IN ('won','lost')", ARRAY_A ); // phpcs:ignore
		$rate  = ( $wn + $lost ) ? $wn / ( $wn + $lost ) : null;
		$size  = $wn ? $wv / $wn : null;
		$vel   = ( $rate && $size && null !== $cycle ) ? (int) $open['n'] * $rate * $size / max( 1, $cycle ) : null;

		return rest_ensure_response(
			array(
				'months'  => array_values( $months ),
				'weeks'   => array_values( $weeks ),
				'funnel'  => $funnel,
				'summary' => array(
					'won'       => $wn,
					'won_value' => $wv,
					'lost'      => $lost,
					'win_rate'  => null === $rate ? null : round( $rate * 100 ),
					'deal_size' => $size,
					'cycle'     => null === $cycle ? null : round( $cycle ),
					'open'      => (int) $open['n'],
					'open_value' => (float) $open['v'],
					'velocity'  => $vel,
				),
				'rep'     => $rep,
			)
		);
	}
}

AXH_P6::init();
