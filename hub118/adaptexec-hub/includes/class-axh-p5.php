<?php
/**
 * Sales Hub 1.18.0: repeating follow-ups and custom fields.
 *
 * - A follow-up can repeat (daily, weekly, every two weeks, monthly,
 *   quarterly). Completing it schedules the next one, once.
 * - AdaptExec can add its own fields to every deal (number of chairs,
 *   current software, contract renewal date…). Reps fill them in on the
 *   deal; they can show in the pipeline list, filter it, and export.
 *
 * Additive only: two nullable columns on tasks, one on leads, one option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_P5 {

	const DB = '1';
	const NS = 'axh/v1';

	const REPEATS = array(
		'daily'     => 'Every day',
		'weekdays'  => 'Every weekday',
		'weekly'    => 'Every week',
		'2weeks'    => 'Every two weeks',
		'monthly'   => 'Every month',
		'quarterly' => 'Every three months',
	);
	const TYPES   = array( 'text', 'number', 'money', 'date', 'select', 'yesno', 'url' );

	/** Next follow-ups made during this request, so the Hub can say so. */
	protected static $made = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 9 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'axh_task_done', array( __CLASS__, 'on_done' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'tell' ), 21, 3 );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'axh_p5_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$t = AXH_DB::table( 'tasks' );
		$l = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return;
		}
		$ok   = true;
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$t}", 0 ); // phpcs:ignore
		foreach ( array( 'repeat_every' => 'ADD COLUMN repeat_every VARCHAR(16) NULL', 'repeat_next' => 'ADD COLUMN repeat_next BIGINT UNSIGNED NULL' ) as $col => $ddl ) {
			if ( ! in_array( $col, $have, true ) && false === $wpdb->query( "ALTER TABLE {$t} {$ddl}" ) ) { // phpcs:ignore
				$ok = false;
			}
		}
		$have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$l}", 0 ); // phpcs:ignore
		if ( ! in_array( 'custom', $have, true ) && false === $wpdb->query( "ALTER TABLE {$l} ADD COLUMN custom LONGTEXT NULL" ) ) { // phpcs:ignore
			$ok = false;
		}
		if ( $ok ) {
			update_option( 'axh_p5_db', self::DB, false );
		}
	}

	public static function routes() {
		register_rest_route( self::NS, '/p5/fields', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_fields' ), 'permission_callback' => array( 'AXH_Rest', 'can_manage' ) ) );
		register_rest_route( self::NS, '/p5/lead/(?P<id>\\d+)/fields', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save_values' ), 'permission_callback' => array( 'AXH_Rest', 'can_use' ) ) );
	}

	public static function boot() {
		return array( 'fields' => self::fields(), 'repeats' => self::REPEATS, 'ready' => (bool) get_option( 'axh_p5_db' ) );
	}

	/* ------------------------------------------------------- repeating tasks */

	public static function clean_repeat( $v ) {
		$v = sanitize_key( (string) $v );
		return isset( self::REPEATS[ $v ] ) ? $v : null;
	}

	/** The next date for a repeating follow-up: one step on, and never in the past. */
	public static function next_due( $due, $every, $now = null ) {
		$now   = $now ? $now : time();
		$utc   = new DateTimeZone( 'UTC' );
		$d     = $due ? new DateTime( $due, $utc ) : new DateTime( gmdate( 'Y-m-d 00:00:00', $now ), $utc );
		$day   = (int) $d->format( 'j' );
		$today = gmdate( 'Y-m-d', $now );
		$step  = function ( DateTime $x ) use ( $every, $day ) {
			switch ( $every ) {
				case 'daily':
					$x->modify( '+1 day' );
					break;
				case 'weekdays':
					do {
						$x->modify( '+1 day' );
					} while ( (int) $x->format( 'N' ) >= 6 );
					break;
				case 'weekly':
					$x->modify( '+7 days' );
					break;
				case '2weeks':
					$x->modify( '+14 days' );
					break;
				default:
					// Same day of the month, or the month's last day when it is shorter (Jan 31 → Feb 28).
					$months = 'quarterly' === $every ? 3 : 1;
					$y      = (int) $x->format( 'Y' );
					$m      = (int) $x->format( 'n' ) + $months;
					while ( $m > 12 ) {
						$m -= 12;
						++$y;
					}
					$last = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
					$x->setDate( $y, $m, min( $day, $last ) );
			}
			return $x;
		};
		$guard = 0;
		do {
			$step( $d );
		} while ( $d->format( 'Y-m-d' ) < $today && ++$guard < 1000 );
		return $d->format( 'Y-m-d H:i:s' );
	}

	public static function on_done( $task_id ) {
		global $wpdb;
		if ( ! get_option( 'axh_p5_db' ) ) {
			return;
		}
		$t   = AXH_DB::table( 'tasks' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $task_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row || empty( $row['repeat_every'] ) || ! self::clean_repeat( $row['repeat_every'] ) ) {
			return;
		}
		// Once only: done, reopened and done again does not make a second one.
		if ( ! empty( $row['repeat_next'] ) && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE id = %d", (int) $row['repeat_next'] ) ) ) { // phpcs:ignore
			return;
		}
		$due = self::next_due( $row['due_at'], $row['repeat_every'] );
		if ( empty( $row['timed'] ) ) {
			$due = substr( $due, 0, 10 ) . ' 00:00:00';
		}
		$new = array(
			'lead_id'      => $row['lead_id'] ? (int) $row['lead_id'] : null,
			'rep_id'       => (int) $row['rep_id'],
			'title'        => $row['title'],
			'due_at'       => $due,
			'created_at'   => AXH_DB::now(),
			'repeat_every' => $row['repeat_every'],
		);
		foreach ( array( 'type', 'priority', 'timed', 'notes' ) as $k ) {
			if ( array_key_exists( $k, $row ) ) {
				$new[ $k ] = $row[ $k ];
			}
		}
		if ( array_key_exists( 'updated_at', $row ) ) {
			$new['updated_at'] = AXH_DB::now();
		}
		if ( false === $wpdb->insert( $t, $new ) ) {
			return;
		}
		$nid = (int) $wpdb->insert_id;
		$wpdb->update( $t, array( 'repeat_next' => $nid ), array( 'id' => (int) $row['id'] ) );
		self::$made[] = array( 'id' => $nid, 'title' => $row['title'], 'due_at' => $due, 'timed' => (int) ( $row['timed'] ?? 0 ) );
	}

	public static function tell( $res, $server, $req ) {
		if ( self::$made && $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/' . self::NS . '/' ) ) {
			$data = $res->get_data();
			if ( is_array( $data ) && ! isset( $data['axh_repeat'] ) ) {
				$data['axh_repeat'] = array_slice( self::$made, 0, 20 );
				$res->set_data( $data );
			}
		}
		self::$made = array();
		return $res;
	}

	/* ----------------------------------------------------------- custom fields */

	public static function fields() {
		$v = get_option( 'axh_custom_fields', array() );
		return is_array( $v ) ? array_values( $v ) : array();
	}

	public static function save_fields( $req ) {
		$b    = (array) $req->get_json_params();
		$out  = array();
		$seen = array();
		foreach ( array_slice( (array) ( $b['fields'] ?? array() ), 0, 20 ) as $f ) {
			$label = mb_substr( sanitize_text_field( (string) ( $f['label'] ?? '' ) ), 0, 60 );
			if ( '' === $label ) {
				continue;
			}
			$key = sanitize_key( (string) ( $f['key'] ?? '' ) );
			if ( '' === $key ) {
				$key = 'f_' . substr( sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) ), 0, 24 );
			}
			$base = $key;
			$i    = 2;
			while ( isset( $seen[ $key ] ) ) {
				$key = $base . '_' . $i++;
			}
			$seen[ $key ] = 1;
			$type         = in_array( ( $f['type'] ?? '' ), self::TYPES, true ) ? $f['type'] : 'text';
			$opts         = array();
			if ( 'select' === $type ) {
				foreach ( array_slice( (array) ( $f['options'] ?? array() ), 0, 30 ) as $o ) {
					$o = mb_substr( sanitize_text_field( (string) $o ), 0, 60 );
					if ( '' !== $o && ! in_array( $o, $opts, true ) ) {
						$opts[] = $o;
					}
				}
				if ( ! $opts ) {
					continue;
				}
			}
			$out[] = array(
				'key'     => $key,
				'label'   => $label,
				'type'    => $type,
				'options' => $opts,
				'list'    => ! empty( $f['list'] ),
				'help'    => mb_substr( sanitize_text_field( (string) ( $f['help'] ?? '' ) ), 0, 140 ),
			);
		}
		update_option( 'axh_custom_fields', $out, false );
		return rest_ensure_response( array( 'fields' => $out ) );
	}

	public static function values( $lead ) {
		$v = json_decode( (string) ( $lead['custom'] ?? '' ), true );
		return is_array( $v ) ? $v : array();
	}

	protected static function clean_value( $f, $v ) {
		if ( is_array( $v ) || is_object( $v ) ) {
			return null;
		}
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		switch ( $f['type'] ) {
			case 'number':
			case 'money':
				$n = str_replace( array( ',', '$', ' ' ), '', $v );
				return is_numeric( $n ) ? (string) ( 0 + $n ) : null;
			case 'date':
				return preg_match( '/^(\\d{4})-(\\d{2})-(\\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $v : null;
			case 'select':
				return in_array( $v, (array) $f['options'], true ) ? $v : null;
			case 'yesno':
				return in_array( $v, array( 'yes', 'no' ), true ) ? $v : null;
			case 'url':
				$u = esc_url_raw( preg_match( '#^https?://#i', $v ) ? $v : 'https://' . $v );
				return $u ? mb_substr( $u, 0, 255 ) : null;
			default:
				return mb_substr( sanitize_textarea_field( $v ), 0, 1000 );
		}
	}

	public static function save_values( $req ) {
		global $wpdb;
		$u    = $req->get_url_params();
		$lead = AXH_DB::get_lead( (int) ( $u['id'] ?? 0 ) );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		if ( ! get_option( 'axh_p5_db' ) ) {
			return new WP_Error( 'axh_not_ready', 'Not ready yet. Reload and try again.', array( 'status' => 503 ) );
		}
		$b      = (array) $req->get_json_params();
		$in     = (array) ( $b['values'] ?? array() );
		$cur    = self::values( $lead );
		$defs   = array();
		$change = array();
		foreach ( self::fields() as $f ) {
			$defs[ $f['key'] ] = $f;
		}
		foreach ( $in as $k => $v ) {
			if ( ! isset( $defs[ $k ] ) ) {
				continue;
			}
			$clean = self::clean_value( $defs[ $k ], $v );
			if ( null === $clean ) {
				return new WP_Error( 'axh_bad_value', 'Check “' . $defs[ $k ]['label'] . '” — that value does not fit.', array( 'status' => 400 ) );
			}
			$old = isset( $cur[ $k ] ) ? (string) $cur[ $k ] : '';
			if ( $old === $clean ) {
				continue;
			}
			if ( '' === $clean ) {
				unset( $cur[ $k ] );
			} else {
				$cur[ $k ] = $clean;
			}
			$change[] = $defs[ $k ]['label'] . ': ' . ( '' === $old ? '—' : $old ) . ' → ' . ( '' === $clean ? '—' : $clean );
		}
		if ( $change ) {
			$wpdb->update( AXH_DB::table( 'leads' ), array( 'custom' => $cur ? wp_json_encode( $cur ) : null ), array( 'id' => (int) $lead['id'] ) );
			AXH_DB::log( (int) $lead['id'], 'audit', 'Details updated', implode( "\n", array_slice( $change, 0, 20 ) ) );
		}
		return rest_ensure_response( array( 'custom' => $cur ? $cur : new stdClass(), 'changed' => count( $change ) ) );
	}
}

AXH_P5::init();
