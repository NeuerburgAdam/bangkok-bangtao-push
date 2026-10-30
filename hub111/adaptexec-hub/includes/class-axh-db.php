<?php
/**
 * Data access. Every read is scoped to the requesting user unless they manage all.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_DB {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'axh_' . $name;
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	/**
	 * Trim a value to a column's length. wpdb refuses (rather than truncates)
	 * any value longer than its column, so an over-long company name used to
	 * make the whole insert fail silently.
	 */
	/** "12,000", "$12,000.50" or "12000" all mean the same amount (1.11.0). */
	public static function money( $v ) {
		if ( is_int( $v ) || is_float( $v ) ) {
			return (float) $v;
		}
		$s = preg_replace( '/[^0-9.]/', '', (string) $v );
		if ( substr_count( $s, '.' ) > 1 ) {
			$s = str_replace( '.', '', substr( $s, 0, strrpos( $s, '.' ) ) ) . substr( $s, strrpos( $s, '.' ) );
		}
		return round( (float) $s, 2 );
	}

	protected static function clip( $value, $len ) {
		return mb_substr( (string) $value, 0, (int) $len );
	}

	/** Column lengths for the free-text lead fields. */
	protected static function lead_lengths() {
		return array(
			'company'    => 191,
			'contact_name' => 191,
			'title'      => 191,
			'email'      => 191,
			'phone'      => 64,
			'website'    => 191,
			'industry'   => 64,
			'city'       => 96,
			'state'      => 32,
			'source'     => 96,
			'next_action'  => 191,
		);
	}

	/**
	 * Everyone a lead can belong to: the sales reps and the AdaptExec managers.
	 *
	 * @return array id => display name.
	 */
	public static function assignees() {
		$out = array();
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $u ) {
			if ( user_can( $u, 'axh_use_hub' ) ) {
				$out[ (int) $u->ID ] = $u->display_name;
			}
		}
		return $out;
	}

	protected static function user_name( $id ) {
		$u = $id ? get_userdata( (int) $id ) : null;
		return $u ? $u->display_name : 'Unassigned';
	}

	/**
	 * Write an entry on a lead's timeline without the rep-scoping that
	 * add_activity() applies — for manager actions and system events, which
	 * must be recorded even when the lead belongs to someone else.
	 */
	public static function log( $lead_id, $type, $subject, $body = '' ) {
		global $wpdb;
		$now = self::now();
		$wpdb->insert(
			self::table( 'activity' ),
			array(
				'lead_id'     => (int) $lead_id,
				'rep_id'      => get_current_user_id(),
				'type'        => sanitize_key( $type ),
				'subject'     => self::clip( sanitize_text_field( $subject ), 191 ),
				'body'        => sanitize_textarea_field( $body ),
				'occurred_at' => $now,
				'created_at'  => $now,
			)
		);
	}

	/**
	 * A WHERE fragment restricting rows to the current user unless they are a manager.
	 */
	protected static function scope( $column = 'rep_id' ) {
		global $wpdb;
		if ( axh_is_manager() ) {
			return '1=1';
		}
		return $wpdb->prepare( "{$column} = %d", get_current_user_id() );
	}

	/* ------------------------------------------------------------------ leads */

	public static function get_leads( $args = array() ) {
		global $wpdb;
		$t = self::table( 'leads' );

		$where = array( self::scope() );

		// Archived leads stay out of the pipeline unless asked for.
		$archived = isset( $args['archived'] ) ? (string) $args['archived'] : '';
		if ( 'all' === $archived ) {
			// both
		} elseif ( $archived && '0' !== $archived ) {
			$where[] = 'archived_at IS NOT NULL';
		} else {
			$where[] = 'archived_at IS NULL';
		}
		if ( ! empty( $args['win_requested'] ) ) {
			$where[] = "win_requested_at IS NOT NULL AND stage <> 'won'";
		}

		if ( ! empty( $args['stage'] ) ) {
			$where[] = $wpdb->prepare( 'stage = %s', sanitize_key( $args['stage'] ) );
		}
		if ( ! empty( $args['reg_status'] ) ) {
			$where[] = $wpdb->prepare( 'reg_status = %s', sanitize_key( $args['reg_status'] ) );
		}
		if ( ! empty( $args['rep_id'] ) && axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'rep_id = %d', (int) $args['rep_id'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$digits  = preg_replace( '/\D/', '', (string) $args['search'] );
			$phone   = strlen( $digits ) >= 4
				? $wpdb->prepare( " OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'-',''),' ',''),'(',''),')',''),'.',''),'+','') LIKE %s", '%' . $wpdb->esc_like( $digits ) . '%' )
				: '';
			$where[] = $wpdb->prepare( '(company LIKE %s OR contact_name LIKE %s OR email LIKE %s OR city LIKE %s OR phone LIKE %s', $like, $like, $like, $like, $like ) . $phone . ')';
		}

		$limit = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 500;
		$sql   = 'SELECT * FROM ' . $t . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT ' . $limit;

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		// A manager sees everyone's leads, so each one says whose it is.
		if ( $rows && axh_is_manager() ) {
			$names = array();
			foreach ( $rows as &$row ) {
				$rid = (int) $row['rep_id'];
				if ( ! isset( $names[ $rid ] ) ) {
					$names[ $rid ] = self::user_name( $rid );
				}
				$row['rep_name'] = $names[ $rid ];
			}
			unset( $row );
		}
		// The pipeline list stays light: tool results are only sent with one lead.
		foreach ( $rows as &$row ) {
			$row['has_tool_results'] = ! empty( $row['tool_results'] );
			unset( $row['tool_results'] );
		}
		unset( $row );
		return $rows;
	}

	/**
	 * A lead with no ownership check.
	 *
	 * Only for paths whose credential is not a login — a client's discovery
	 * token reaches exactly one lead, and nobody is signed in when they open
	 * it, so get_lead()'s rep scoping would refuse every request. Never call
	 * this from anything a rep can reach.
	 */
	protected static function lead_unscoped( $id ) {
		global $wpdb;
		$t = self::table( 'leads' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	public static function get_lead( $id ) {
		global $wpdb;
		$t   = self::table( 'leads' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( ! axh_is_manager() && (int) $row['rep_id'] !== get_current_user_id() ) {
			return null;
		}
		return $row;
	}

	public static function insert_lead( $data ) {
		global $wpdb;
		$now = self::now();

		$row = array(
			'rep_id'       => axh_is_manager() && ! empty( $data['rep_id'] ) && user_can( (int) $data['rep_id'], 'axh_use_hub' ) ? (int) $data['rep_id'] : get_current_user_id(),
			'company'      => sanitize_text_field( $data['company'] ?? '' ),
			'contact_name' => sanitize_text_field( $data['contact_name'] ?? '' ),
			'title'        => sanitize_text_field( $data['title'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'website'      => esc_url_raw( $data['website'] ?? '' ),
			'industry'     => sanitize_text_field( $data['industry'] ?? '' ),
			'city'         => sanitize_text_field( $data['city'] ?? '' ),
			'state'        => sanitize_text_field( $data['state'] ?? '' ),
			'stage'        => self::clean_stage( $data['stage'] ?? 'new' ),
			'reg_status'   => 'draft',
			'est_value'    => max( 0, self::money( $data['est_value'] ?? 0 ) ),
			'source'       => sanitize_text_field( $data['source'] ?? '' ),
			'notes'        => wp_kses_post( $data['notes'] ?? '' ),
			'next_action'  => sanitize_text_field( $data['next_action'] ?? '' ),
			'created_at'   => $now,
			'updated_at'   => $now,
		);

		if ( ! empty( $data['next_action_at'] ) ) {
			$row['next_action_at'] = self::clean_datetime( $data['next_action_at'] );
		}
		// Closed Won is AdaptExec's decision (it sets commission): a rep cannot
		// create a lead already won. A deal created lost or won gets a close date.
		if ( 'won' === $row['stage'] && ! axh_is_manager() ) {
			$row['stage'] = 'new';
		}
		if ( in_array( $row['stage'], array( 'won', 'lost' ), true ) ) {
			$row['closed_at'] = $now;
		}
		if ( $row['website'] && ! preg_match( '#^https?://#i', $row['website'] ) ) {
			$row['website'] = esc_url_raw( 'https://' . ltrim( (string) ( $data['website'] ?? '' ), '/' ) );
		}
		foreach ( self::lead_lengths() as $col => $len ) {
			if ( isset( $row[ $col ] ) ) {
				$row[ $col ] = self::clip( $row[ $col ], $len );
			}
		}

		if ( ! $wpdb->insert( self::table( 'leads' ), $row ) ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	public static function update_lead( $id, $data ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead ) {
			return false;
		}
		$manager = axh_is_manager();

		$fields = array();
		$text   = array( 'company', 'contact_name', 'title', 'phone', 'industry', 'city', 'state', 'source', 'next_action' );
		foreach ( $text as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = sanitize_text_field( $data[ $key ] );
			}
		}
		if ( isset( $data['email'] ) ) {
			$fields['email'] = sanitize_email( $data['email'] );
		}
		if ( isset( $data['website'] ) ) {
			$fields['website'] = esc_url_raw( $data['website'] );
		}
		if ( isset( $data['notes'] ) ) {
			$fields['notes'] = wp_kses_post( $data['notes'] );
		}
		if ( isset( $fields['company'] ) && '' === trim( $fields['company'] ) ) {
			return new WP_Error( 'axh_no_company', 'A company name is required.', array( 'status' => 400 ) );
		}

		/*
		 * Closed Won and the contract value decide commission, so only an
		 * AdaptExec manager sets them. A rep asks for it with request_win().
		 * Once a deal is won its stage is locked for reps too — moving it
		 * back out would quietly remove earned commission from Earnings.
		 */
		if ( isset( $data['stage'] ) ) {
			$stage = self::clean_stage( $data['stage'] );
			if ( $stage !== $lead['stage'] ) {
				if ( ! $manager && 'won' === $stage ) {
					return new WP_Error( 'axh_won_admin', 'Only AdaptExec can mark a deal Closed Won. Use "Request Closed Won" and it will be confirmed for you.', array( 'status' => 403 ) );
				}
				if ( ! $manager && 'won' === $lead['stage'] ) {
					return new WP_Error( 'axh_won_locked', 'This deal is Closed Won and locked. Ask AdaptExec if something needs to change.', array( 'status' => 403 ) );
				}
				$fields['stage'] = $stage;
				// The close date is when it closed this time: a deal lost,
				// reopened and later won is dated by the win.
				if ( 'won' === $stage || ( 'lost' === $stage && ! in_array( $lead['stage'], array( 'won', 'lost' ), true ) ) ) {
					$fields['closed_at'] = self::now();
				}
			}
		}
		if ( isset( $data['est_value'] ) ) {
			$fields['est_value'] = max( 0, self::money( $data['est_value'] ) );
		}
		if ( isset( $data['contract_value'] ) && $manager ) {
			$fields['contract_value'] = max( 0, self::money( $data['contract_value'] ) );
		}
		$new_stage = $fields['stage'] ?? $lead['stage'];
		if ( 'won' === $new_stage ) {
			$value = $fields['contract_value'] ?? $lead['contract_value'];
			// Lock the rate when the deal is won; re-tier only when a manager
			// corrects the contract value itself.
			if ( empty( $lead['commission_rate'] ) || 'won' !== $lead['stage'] || isset( $fields['contract_value'] ) ) {
				$fields['commission_rate'] = AXH_Install::commission_rate( $value, $lead['rep_id'] );
			}
			$fields['win_requested_at'] = null;
		}
		if ( array_key_exists( 'next_action_at', $data ) ) {
			$fields['next_action_at'] = $data['next_action_at'] ? self::clean_datetime( $data['next_action_at'] ) : null;
		}

		// Registration decisions are manager-only.
		if ( isset( $data['reg_status'] ) && $manager ) {
			$status = sanitize_key( $data['reg_status'] );
			if ( in_array( $status, AXH_Install::reg_statuses(), true ) && $status !== $lead['reg_status'] ) {
				$fields['reg_status']    = $status;
				$fields['decided_at']    = self::now();
				$fields['decided_by']    = get_current_user_id();
				$fields['decision_note'] = sanitize_textarea_field( $data['decision_note'] ?? '' );
				if ( empty( $lead['registered_at'] ) && 'draft' !== $status ) {
					$fields['registered_at'] = self::now();
				}
			}
		}

		if ( empty( $fields ) ) {
			return true;
		}
		foreach ( self::lead_lengths() as $col => $len ) {
			if ( isset( $fields[ $col ] ) ) {
				$fields[ $col ] = self::clip( $fields[ $col ], $len );
			}
		}
		$fields['updated_at'] = self::now();

		if ( false === $wpdb->update( self::table( 'leads' ), $fields, array( 'id' => (int) $id ) ) ) {
			return false;
		}

		// The audit trail: who moved what, and when.
		if ( isset( $fields['stage'] ) ) {
			self::log( $id, 'stage', 'Stage: ' . self::stage_label( $lead['stage'] ) . ' → ' . self::stage_label( $fields['stage'] ), 'By ' . self::user_name( get_current_user_id() ) );
		}
		if ( isset( $fields['contract_value'] ) && (float) $fields['contract_value'] !== (float) $lead['contract_value'] ) {
			self::log(
				$id,
				'audit',
				'Contract value: $' . number_format( (float) $lead['contract_value'], 2 ) . ' → $' . number_format( (float) $fields['contract_value'], 2 ),
				'By ' . self::user_name( get_current_user_id() ) . ( isset( $fields['commission_rate'] ) ? ' · commission rate ' . rtrim( rtrim( number_format( (float) $fields['commission_rate'], 2 ), '0' ), '.' ) . '%' : '' )
			);
		}
		if ( isset( $fields['reg_status'] ) ) {
			$words = array( 'registered' => 'Registration approved', 'house' => 'Flagged as a house account', 'declined' => 'Registration declined', 'pending' => 'Registration reopened', 'draft' => 'Registration reset to draft' );
			self::log( $id, 'registration', $words[ $fields['reg_status'] ] ?? 'Registration updated', trim( 'By ' . self::user_name( get_current_user_id() ) . ( $fields['decision_note'] ? "\n" . $fields['decision_note'] : '' ) ) );
		}
		return true;
	}

	public static function stage_label( $key ) {
		foreach ( AXH_Install::stages() as $st ) {
			if ( $st['key'] === $key ) {
				return $st['label'];
			}
		}
		return (string) $key;
	}

	/**
	 * A rep tells AdaptExec the client signed. Nothing reaches Earnings until a
	 * manager confirms it with close_won().
	 */
	public static function request_win( $id, $value, $note = '' ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		if ( 'won' === $lead['stage'] ) {
			return new WP_Error( 'axh_already_won', 'This deal is already Closed Won.', array( 'status' => 400 ) );
		}
		$value = self::money( $value );
		if ( $value <= 0 ) {
			return new WP_Error( 'axh_bad_value', 'Enter the signed contract value.', array( 'status' => 400 ) );
		}
		$wpdb->update(
			self::table( 'leads' ),
			array(
				'win_requested_at'  => self::now(),
				'win_requested_by'  => get_current_user_id(),
				'win_request_value' => $value,
				'win_request_note'  => sanitize_textarea_field( mb_substr( (string) $note, 0, 2000 ) ),
				'updated_at'        => self::now(),
			),
			array( 'id' => (int) $id )
		);
		self::log( $id, 'audit', 'Closed Won requested at $' . number_format( $value, 2 ), trim( 'By ' . self::user_name( get_current_user_id() ) . ( $note ? "\n" . sanitize_textarea_field( $note ) : '' ) ) );
		return self::get_lead( $id );
	}

	/**
	 * Manager: mark a deal Closed Won at a contract value (or correct the value
	 * of one already won). The commission rate is set from the value here.
	 */
	public static function close_won( $id, $value ) {
		$lead = self::get_lead( $id );
		if ( ! $lead || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$value = self::money( $value );
		if ( $value <= 0 ) {
			return new WP_Error( 'axh_bad_value', 'Enter the signed contract value.', array( 'status' => 400 ) );
		}
		$was_won = 'won' === $lead['stage'];
		$ok      = self::update_lead( $id, array( 'stage' => 'won', 'contract_value' => $value ) );
		if ( is_wp_error( $ok ) || ! $ok ) {
			return $ok ? $ok : new WP_Error( 'axh_update_failed', 'Could not close that deal.', array( 'status' => 400 ) );
		}
		global $wpdb;
		$wpdb->update( self::table( 'leads' ), array( 'win_requested_at' => null ), array( 'id' => (int) $id ) );
		$after = self::get_lead( $id );
		if ( ! $was_won ) {
			self::log( $id, 'audit', 'Closed Won approved — contract $' . number_format( $value, 2 ) . ' at ' . rtrim( rtrim( number_format( (float) $after['commission_rate'], 2 ), '0' ), '.' ) . '%', 'By ' . self::user_name( get_current_user_id() ) );
		}
		return $after;
	}

	/** Manager: turn a rep's Closed Won request down, with a reason. */
	public static function decline_win( $id, $note = '' ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$wpdb->update( self::table( 'leads' ), array( 'win_requested_at' => null, 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
		self::log( $id, 'audit', 'Closed Won request not approved yet', trim( 'By ' . self::user_name( get_current_user_id() ) . ( $note ? "\n" . sanitize_textarea_field( $note ) : '' ) ) );
		return self::get_lead( $id );
	}

	/**
	 * Manager: give a lead to another rep. Its discovery and open follow-ups
	 * travel with it, so the new owner has the whole picture.
	 */
	public static function assign_lead( $id, $rep_id ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$rep_id = (int) $rep_id;
		$user   = $rep_id ? get_userdata( $rep_id ) : null;
		if ( ! $user || ! user_can( $user, 'axh_use_hub' ) ) {
			return new WP_Error( 'axh_bad_rep', 'Choose a representative with Hub access.', array( 'status' => 400 ) );
		}
		if ( (int) $lead['rep_id'] === $rep_id ) {
			return $lead;
		}
		$wpdb->update( self::table( 'leads' ), array( 'rep_id' => $rep_id, 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
		$wpdb->update( self::table( 'discovery' ), array( 'rep_id' => $rep_id ), array( 'lead_id' => (int) $id ) );
		$t = self::table( 'tasks' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET rep_id = %d WHERE lead_id = %d AND done_at IS NULL", $rep_id, (int) $id ) ); // phpcs:ignore
		// 1.11.0: deck links made for this deal and the form answers that
		// opened it go with it too, so the old owner stops seeing its
		// analytics and contact details, and the new owner gets the alerts.
		$wpdb->update( self::table( 'shares' ), array( 'rep_id' => $rep_id ), array( 'lead_id' => (int) $id ) );
		$wpdb->update( self::table( 'submissions' ), array( 'rep_id' => $rep_id ), array( 'lead_id' => (int) $id ) );
		self::log( $id, 'audit', 'Reassigned: ' . self::user_name( $lead['rep_id'] ) . ' → ' . $user->display_name, 'By ' . self::user_name( get_current_user_id() ) );
		// Billing customers and documents follow the lead (1.7.0).
		do_action( 'axh_lead_assigned', (int) $id, $rep_id );
		return self::get_lead( $id );
	}

	/** Archive (or restore) a lead: out of the pipeline, never destroyed. */
	public static function archive_lead( $id, $on = true ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$wpdb->update( self::table( 'leads' ), array( 'archived_at' => $on ? self::now() : null, 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
		self::log( $id, 'audit', $on ? 'Archived' : 'Restored from the archive', 'By ' . self::user_name( get_current_user_id() ) );
		return self::get_lead( $id );
	}

	/**
	 * Rep submits a lead for registration under Section 2.4 of the agreement.
	 */
	/**
	 * Normalise a company name so "Summit Ridge Automotive, LLC" and
	 * "summit ridge automotive" collide.
	 */
	public static function company_key( $name ) {
		$k = strtolower( (string) $name );
		$k = preg_replace( '/&/', ' and ', $k );
		$k = preg_replace( '/[^a-z0-9 ]+/', ' ', $k );
		$k = preg_replace( '/\b(the|a|an|llc|l l c|inc|incorporated|co|company|corp|corporation|ltd|limited|pllc|pc|lp|llp|group|holdings)\b/', ' ', $k );
		return trim( preg_replace( '/\s+/', ' ', $k ) );
	}

	/** The registrable domain from a website or email address, if there is one. */
	public static function domain_key( $value ) {
		$v = strtolower( trim( (string) $value ) );
		if ( ! $v ) {
			return '';
		}
		if ( strpos( $v, '@' ) !== false ) {
			$v = substr( $v, strpos( $v, '@' ) + 1 );
		}
		$v = preg_replace( '#^https?://#', '', $v );
		$v = preg_replace( '#^www\.#', '', $v );
		$v = explode( '/', $v )[0];
		// Free mailboxes say nothing about which company a lead belongs to.
		$free = array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com',
			'icloud.com', 'me.com', 'live.com', 'msn.com', 'proton.me', 'protonmail.com' );
		return in_array( $v, $free, true ) ? '' : $v;
	}

	/**
	 * Prospects already claimed by someone else. Section 2.4 protects the first
	 * rep to register a company, so the second one has to be told before they
	 * spend a week on it — not after.
	 */
	public static function conflicts( $lead ) {
		global $wpdb;
		$t = self::table( 'leads' );

		$name_key = self::company_key( $lead['company'] );
		$domain   = self::domain_key( $lead['website'] );
		if ( ! $domain ) {
			$domain = self::domain_key( $lead['email'] );
		}
		if ( ! $name_key && ! $domain ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, rep_id, company, website, email, reg_status, registered_at, stage
				 FROM {$t}
				 WHERE id <> %d
				   AND rep_id <> %d
				   AND reg_status IN ('pending','registered')
				 ORDER BY registered_at ASC
				 LIMIT 20000",
				(int) $lead['id'],
				(int) $lead['rep_id']
			),
			ARRAY_A
		);

		$hits = array();
		foreach ( (array) $rows as $r ) {
			$why = '';
			if ( $name_key && self::company_key( $r['company'] ) === $name_key ) {
				$why = 'same company name';
			} else {
				$other = self::domain_key( $r['website'] );
				if ( ! $other ) {
					$other = self::domain_key( $r['email'] );
				}
				if ( $domain && $other === $domain ) {
					$why = 'same web domain (' . $domain . ')';
				}
			}
			if ( $why ) {
				$rep    = get_userdata( (int) $r['rep_id'] );
				$hits[] = array(
					'lead_id'       => (int) $r['id'],
					'company'       => $r['company'],
					'rep'           => $rep ? $rep->display_name : 'another representative',
					'reg_status'    => $r['reg_status'],
					'registered_at' => $r['registered_at'],
					'why'           => $why,
				);
			}
		}
		return $hits;
	}

	public static function register_lead( $id ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead || 'draft' !== $lead['reg_status'] ) {
			return false;
		}

		$conflicts = self::conflicts( $lead );
		if ( $conflicts ) {
			// Still submit it — the decision is AdaptExec's, not the software's —
			// but record the clash so nobody is surprised later.
			self::add_activity(
				array(
					'lead_id' => $id,
					'type'    => 'registration',
					'subject' => 'Possible conflict flagged on submission',
					'body'    => sprintf(
						'%s is already registered by %s (%s).',
						$conflicts[0]['company'],
						$conflicts[0]['rep'],
						$conflicts[0]['why']
					),
				)
			);
		}

		$wpdb->update(
			self::table( 'leads' ),
			array(
				'reg_status'    => 'pending',
				'registered_at' => self::now(),
				'updated_at'    => self::now(),
			),
			array( 'id' => (int) $id )
		);
		self::add_activity(
			array(
				'lead_id' => $id,
				'type'    => 'registration',
				'subject' => 'Submitted for registration',
				'body'    => 'Awaiting confirmation from AdaptExec.',
			)
		);
		return array( 'ok' => true, 'conflicts' => $conflicts );
	}

	public static function delete_lead( $id ) {
		global $wpdb;
		$lead = self::get_lead( $id );
		if ( ! $lead ) {
			return false;
		}
		// Once a prospect has been submitted for registration it is part of the
		// record (Section 2.4 depends on who claimed it first), so a rep can only
		// delete their own drafts. Everything else is archived instead.
		if ( ! axh_is_manager() && ( 'draft' !== $lead['reg_status'] || 'won' === $lead['stage'] ) ) {
			return new WP_Error( 'axh_cannot_delete', 'Only draft leads can be deleted. Archive this one instead — it leaves your pipeline but stays on record.', array( 'status' => 403 ) );
		}
		$wpdb->delete( self::table( 'activity' ), array( 'lead_id' => (int) $id ) );
		$wpdb->delete( self::table( 'tasks' ), array( 'lead_id' => (int) $id ) );
		$wpdb->delete( self::table( 'discovery' ), array( 'lead_id' => (int) $id ) );
		// 1.7.2: nothing that names a deleted lead stays behind — the form
		// submission that opened it, everyone's notifications about it, the
		// deck links made for it and the presentation sessions logged on it.
		$wpdb->delete( self::table( 'submissions' ), array( 'lead_id' => (int) $id ) );
		$wpdb->delete( self::table( 'notifications' ), array( 'lead_id' => (int) $id ) );
		// 1.11.0: the visits recorded on its deck links, and its files.
		if ( get_option( 'axh_p1_db' ) ) {
			$sv = self::table( 'share_views' );
			$sh = self::table( 'shares' );
			$wpdb->query( $wpdb->prepare( "DELETE v FROM {$sv} v JOIN {$sh} s ON s.id = v.share_id WHERE s.lead_id = %d", (int) $id ) ); // phpcs:ignore
		}
		if ( class_exists( 'AXH_Files' ) ) {
			AXH_Files::delete_for_lead( (int) $id );
		}
		$wpdb->delete( self::table( 'shares' ), array( 'lead_id' => (int) $id ) );
		$wpdb->delete( self::table( 'sessions' ), array( 'lead_id' => (int) $id ) );
		// Money is never deleted: a billing customer (and its documents) made
		// from this lead is kept and simply no longer points at it.
		foreach ( array( 'customers', 'bdocs' ) as $bt ) {
			$t = self::table( $bt );
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET lead_id = NULL WHERE lead_id = %d", (int) $id ) ); // phpcs:ignore
		}
		return (bool) $wpdb->delete( self::table( 'leads' ), array( 'id' => (int) $id ) );
	}

	/**
	 * One-time clean-up (DB 1.12.0): form submissions and notifications whose
	 * lead was deleted before delete_lead() cascaded to them. Only rows that
	 * point at a lead id that no longer exists — never rows without a lead.
	 * Returns the ids removed (no content), for the record.
	 */
	public static function purge_orphans() {
		global $wpdb;
		$l   = self::table( 'leads' );
		$out = array();
		foreach ( array( 'submissions', 'notifications' ) as $name ) {
			$t   = self::table( $name );
			$ids = $wpdb->get_col( "SELECT x.id FROM {$t} x LEFT JOIN {$l} l ON l.id = x.lead_id WHERE x.lead_id IS NOT NULL AND x.lead_id > 0 AND l.id IS NULL ORDER BY x.id" ); // phpcs:ignore
			$ids = array_map( 'intval', (array) $ids );
			foreach ( $ids as $rid ) {
				$wpdb->delete( $t, array( 'id' => $rid ) );
			}
			$out[ $name ] = $ids;
		}
		return $out;
	}

	/**
	 * Hub admins only: permanently remove a stored form submission. The lead
	 * it opened (if any) is not touched. The audit entry records who and
	 * which number, never what was in it.
	 */
	public static function delete_submission( $id ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only Hub admins can delete submissions.', array( 'status' => 403 ) );
		}
		$t  = self::table( 'submissions' );
		$id = (int) $id;
		if ( ! $id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE id = %d", $id ) ) ) { // phpcs:ignore
			return false;
		}
		if ( ! $wpdb->delete( $t, array( 'id' => $id ) ) ) {
			return false;
		}
		$u = wp_get_current_user();
		AXH_Onboard::audit( 0, 'submission_deleted', sprintf( 'Submission #%d deleted by %s', $id, $u && $u->ID ? $u->display_name : 'System' ) );
		return true;
	}

	/* --------------------------------------------------------------- activity */

	public static function get_activity( $lead_id, $limit = 100 ) {
		global $wpdb;
		if ( ! self::get_lead( $lead_id ) ) {
			return array();
		}
		$t = self::table( 'activity' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE lead_id = %d ORDER BY occurred_at DESC, id DESC LIMIT %d", (int) $lead_id, (int) $limit ),
			ARRAY_A
		);
	}

	public static function add_activity( $data ) {
		global $wpdb;
		$lead = self::get_lead( $data['lead_id'] ?? 0 );
		if ( ! $lead ) {
			return false;
		}
		$now = self::now();
		$wpdb->insert(
			self::table( 'activity' ),
			array(
				'lead_id'     => (int) $data['lead_id'],
				'rep_id'      => get_current_user_id(),
				'type'        => sanitize_key( $data['type'] ?? 'note' ),
				'subject'     => self::clip( sanitize_text_field( $data['subject'] ?? '' ), 191 ),
				'body'        => sanitize_textarea_field( $data['body'] ?? '' ),
				'occurred_at' => ! empty( $data['occurred_at'] ) ? self::clean_datetime( $data['occurred_at'] ) : $now,
				'created_at'  => $now,
			)
		);
		$wpdb->update( self::table( 'leads' ), array( 'updated_at' => $now ), array( 'id' => (int) $data['lead_id'] ) );
		return (int) $wpdb->insert_id;
	}

	/* ------------------------------------------------------------------ tasks */

	public static function get_tasks( $args = array() ) {
		global $wpdb;
		$t     = self::table( 'tasks' );
		$where = array( self::scope() );
		if ( ! empty( $args['lead_id'] ) ) {
			$where[] = $wpdb->prepare( 'lead_id = %d', (int) $args['lead_id'] );
		}
		if ( ! empty( $args['open'] ) ) {
			$where[] = 'done_at IS NULL';
		}
		$sql = 'SELECT * FROM ' . $t . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY (due_at IS NULL), due_at ASC LIMIT 200';
		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function add_task( $data ) {
		global $wpdb;
		if ( ! empty( $data['lead_id'] ) && ! self::get_lead( (int) $data['lead_id'] ) ) {
			return 0;
		}
		$wpdb->insert(
			self::table( 'tasks' ),
			array(
				'lead_id'    => ! empty( $data['lead_id'] ) ? (int) $data['lead_id'] : null,
				'rep_id'     => get_current_user_id(),
				'title'      => self::clip( sanitize_text_field( $data['title'] ?? '' ), 191 ),
				'due_at'     => ! empty( $data['due_at'] ) ? self::clean_datetime( $data['due_at'] ) : null,
				'created_at' => self::now(),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function toggle_task( $id, $done ) {
		global $wpdb;
		$t   = self::table( 'tasks' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row || ( ! axh_is_manager() && (int) $row['rep_id'] !== get_current_user_id() ) ) {
			return false;
		}
		return false !== $wpdb->update( $t, array( 'done_at' => $done ? self::now() : null ), array( 'id' => (int) $id ) );
	}

	public static function delete_task( $id ) {
		global $wpdb;
		$t   = self::table( 'tasks' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row || ( ! axh_is_manager() && (int) $row['rep_id'] !== get_current_user_id() ) ) {
			return false;
		}
		return (bool) $wpdb->delete( $t, array( 'id' => (int) $id ) );
	}

	/* -------------------------------------------------------------- discovery */

	public static function get_discovery( $lead_id ) {
		global $wpdb;
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return null;
		}
		$t   = self::table( 'discovery' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE lead_id = %d", (int) $lead_id ), ARRAY_A );
		if ( ! $row ) {
			return array(
				'lead_id'   => (int) $lead_id,
				'payload'   => new stdClass(),
				'modules'   => new stdClass(),
				'config'    => new stdClass(),
				'sources'   => new stdClass(),
				'status'    => 'draft',
				'progress'  => 0,
				'token'     => '',
				'share_url' => '',
				'readiness' => axh_discovery_readiness( axh_discovery_visible( axh_discovery_schema( $lead['industry'] ?? '' ) ), array() ),
			);
		}
		$row['payload']   = axh_discovery_migrate_payload( json_decode( $row['payload'] ? $row['payload'] : '{}', true ) );
		$row['modules']   = json_decode( $row['modules'] ? $row['modules'] : '{}', true );
		$row['config']    = json_decode( $row['config'] ? $row['config'] : '{}', true );
		$row['sources']   = json_decode( ! empty( $row['sources'] ) ? $row['sources'] : '{}', true );
		$row['share_url'] = $row['token'] ? axh_discovery_url( $row['token'] ) : '';
		$row['readiness'] = axh_discovery_readiness(
			axh_discovery_visible( axh_discovery_schema( $lead['industry'] ?? '' ), (array) $row['config'] ),
			(array) $row['payload'],
			(array) $row['modules']
		);
		$row['quote_by_name'] = ! empty( $row['quote_by'] ) ? self::user_name( $row['quote_by'] ) : '';
		foreach ( array( 'payload', 'modules', 'config', 'sources' ) as $k ) {
			if ( empty( $row[ $k ] ) ) {
				$row[ $k ] = new stdClass();
			}
		}
		return $row;
	}

	/**
	 * The link a client fills in, minted once and stable thereafter.
	 *
	 * A rep who has already texted the link should not break it by opening the
	 * share panel again, so an existing token is returned unchanged.
	 */
	public static function discovery_token( $lead_id ) {
		global $wpdb;
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return '';
		}
		$t     = self::table( 'discovery' );
		$now   = self::now();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, token FROM {$t} WHERE lead_id = %d", (int) $lead_id ), ARRAY_A );
		$token = $row && $row['token'] ? $row['token'] : '';

		if ( ! $token ) {
			// 24 hex characters: guessable only by brute force, and the page
			// behind it is noindex and carries no other lead's data.
			$token = substr( bin2hex( random_bytes( 16 ) ), 0, 24 );
			if ( $row ) {
				$wpdb->update( $t, array( 'token' => $token, 'sent_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) );
			} else {
				$wpdb->insert(
					$t,
					array(
						'lead_id'    => (int) $lead_id,
						'rep_id'     => (int) $lead['rep_id'],
						'token'      => $token,
						'status'     => 'draft',
						'sent_at'    => $now,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);
			}
		}

		return $token;
	}

	/**
	 * The discovery behind a client link, with the lead it belongs to.
	 */
	public static function get_discovery_by_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^a-f0-9]/', '', (string) $token );
		if ( strlen( $token ) < 16 ) {
			return null;
		}
		$t   = self::table( 'discovery' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE token = %s", $token ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		// The token is the credential here; nobody is signed in.
		$lead = self::lead_unscoped( (int) $row['lead_id'] );
		if ( ! $lead ) {
			return null;
		}
		$row['payload'] = axh_discovery_migrate_payload( json_decode( $row['payload'] ? $row['payload'] : '{}', true ) );
		$row['modules'] = json_decode( $row['modules'] ? $row['modules'] : '{}', true );
		$row['config']  = json_decode( $row['config'] ? $row['config'] : '{}', true );
		$row['sources_raw'] = (string) ( $row['sources'] ?? '' );
		$row['lead']    = $lead;
		return $row;
	}

	/**
	 * Marks a client link as opened, once, for the rep's benefit.
	 */
	public static function discovery_opened( $token ) {
		global $wpdb;
		$t = self::table( 'discovery' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET opened_at = %s WHERE token = %s AND opened_at IS NULL",
				self::now(),
				preg_replace( '/[^a-f0-9]/', '', (string) $token )
			)
		);
	}

	/**
	 * Who typed each answer (1.7.1): the rep in the Hub or the client on
	 * their link. Only answers that changed are re-credited.
	 *
	 * @return array [sources, number of answers changed]
	 */
	protected static function credit_answers( $sources, $old, $new, $by, $uid ) {
		$sources = is_array( $sources ) ? $sources : array();
		$old     = is_array( $old ) ? $old : array();
		$changed = 0;
		$now     = self::now();
		foreach ( (array) $new as $sec => $fields ) {
			foreach ( (array) $fields as $key => $value ) {
				$before = $old[ $sec ][ $key ] ?? '';
				if ( wp_json_encode( $before ) === wp_json_encode( $value ) ) {
					continue;
				}
				$id    = $sec . '.' . $key;
				$empty = is_array( $value ) ? ! array_filter( $value ) : '' === trim( (string) $value );
				if ( $empty ) {
					unset( $sources[ $id ] );
					if ( '' !== $before && array() !== $before ) {
						$changed++;
					}
					continue;
				}
				$sources[ $id ] = array( 'by' => $by, 'uid' => (int) $uid, 'at' => $now );
				$changed++;
			}
		}
		return array( $sources, $changed );
	}

	/**
	 * A client's own answers, saved against their link.
	 *
	 * Only the answers move. The rep's trim, the status and the review are not
	 * the client's to change, and nothing here trusts the lead id in the body —
	 * it comes from the token. Pressing "I'm finished" marks it client_done
	 * for the rep (1.7.1); only the rep's "Request a quote" hands it to AdaptExec.
	 */
	public static function save_discovery_by_token( $token, $payload, $submit = false ) {
		global $wpdb;
		$row = self::get_discovery_by_token( $token );
		if ( ! $row ) {
			return null;
		}

		$clean = self::clean_payload( $payload );
		// The client never sees the rep's internal pages, so keep those answers.
		foreach ( axh_discovery_schema( $row['lead']['industry'] ?? '' ) as $sec ) {
			if ( isset( $sec['audience'] ) && 'internal' === $sec['audience'] && isset( $row['payload'][ $sec['key'] ] ) ) {
				$clean[ $sec['key'] ] = $row['payload'][ $sec['key'] ];
			}
		}
		$credit = self::credit_answers( json_decode( (string) ( $row['sources_raw'] ?? '' ), true ), $row['payload'], $clean, 'client', 0 );
		$now    = self::now();
		$data   = array(
			'payload'         => wp_json_encode( $clean ),
			'sources'         => wp_json_encode( $credit[0] ? $credit[0] : new stdClass() ),
			// Against the trimmed form the client was actually sent.
			'progress'        => self::progress(
				$clean,
				$row['modules'],
				$row['lead']['industry'] ?? '',
				(array) $row['config']
			),
			'client_saved_at' => $now,
			'updated_at'      => $now,
		);

		$first = $submit && ! in_array( $row['status'], array( 'client_done', 'submitted', 'reviewed' ), true );
		if ( $first ) {
			$data['status'] = 'client_done';
		}

		$wpdb->update( self::table( 'discovery' ), $data, array( 'id' => (int) $row['id'] ) );

		if ( $submit ) {
			// Not add_activity(): that is rep-scoped and nobody is signed in
			// here, so it would quietly record nothing. Credit the lead's rep.
			$wpdb->insert(
				self::table( 'activity' ),
				array(
					'lead_id'     => (int) $row['lead_id'],
					'rep_id'      => (int) $row['lead']['rep_id'],
					'type'        => 'discovery',
					'subject'     => 'Client completed the discovery form',
					'body'        => 'Filled in by the client from the link they were sent. Review it, fill any gaps, then request a quote.',
					'occurred_at' => $now,
					'created_at'  => $now,
				)
			);
			$wpdb->update( self::table( 'leads' ), array( 'updated_at' => $now ), array( 'id' => (int) $row['lead_id'] ) );
			do_action( 'axh_discovery_client_done', (int) $row['lead_id'] );
		}

		return self::get_discovery_by_token( $token );
	}

	/**
	 * The rep's trim: which sections and questions this client is asked.
	 */
	public static function save_discovery_config( $lead_id, $config ) {
		global $wpdb;
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return null;
		}

		$clean = array( 'sections' => array(), 'questions' => array() );
		foreach ( array( 'sections', 'questions' ) as $k ) {
			if ( ! empty( $config[ $k ] ) && is_array( $config[ $k ] ) ) {
				foreach ( $config[ $k ] as $key => $on ) {
					$clean[ $k ][ sanitize_key( $key ) ] = (bool) $on;
				}
			}
		}

		$t   = self::table( 'discovery' );
		$now = self::now();
		$id  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE lead_id = %d", (int) $lead_id ) );

		if ( $id ) {
			$wpdb->update( $t, array( 'config' => wp_json_encode( $clean ), 'updated_at' => $now ), array( 'id' => (int) $id ) );
		} else {
			$wpdb->insert(
				$t,
				array(
					'lead_id'    => (int) $lead_id,
					'rep_id'     => (int) $lead['rep_id'],
					'config'     => wp_json_encode( $clean ),
					'status'     => 'draft',
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
		}

		return self::get_discovery( $lead_id );
	}

	/**
	 * Saves the questionnaire. Values are stored as JSON so the schema can grow
	 * without a migration; everything is sanitized on the way in.
	 *
	 * With $submit the rep requests a quote (1.7.1): the questionnaire goes to
	 * AdaptExec, who writes the proposal from it without a discovery call. It
	 * needs the quote-readiness threshold, or a note saying why not.
	 *
	 * @return array|false|WP_Error
	 */
	public static function save_discovery( $lead_id, $payload, $modules, $submit = false, $note = '' ) {
		global $wpdb;
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return false;
		}

		$clean_payload = self::clean_payload( $payload );
		$clean_modules = self::clean_modules( $modules );
		$note          = mb_substr( sanitize_textarea_field( (string) $note ), 0, 2000 );

		$t        = self::table( 'discovery' );
		$now      = self::now();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, config, payload, sources, status FROM {$t} WHERE lead_id = %d", (int) $lead_id ), ARRAY_A );

		// Progress is measured against the form this client was actually sent,
		// not the whole packet — otherwise a trimmed form can never reach 100%.
		$config    = $existing && $existing['config'] ? json_decode( $existing['config'], true ) : array();
		$progress  = self::progress( $clean_payload, $clean_modules, $lead['industry'] ?? '', (array) $config );
		$readiness = axh_discovery_readiness( axh_discovery_visible( axh_discovery_schema( $lead['industry'] ?? '' ), (array) $config ), $clean_payload, $clean_modules );

		if ( $submit && ! $readiness['ready'] && mb_strlen( trim( $note ) ) < 10 ) {
			return new WP_Error(
				'axh_not_ready',
				sprintf( 'The questionnaire is %d%% ready for a quote (it unlocks at %d%%). Add a short note for Adam explaining what is missing and why, or keep filling it in.', $readiness['pct'], $readiness['threshold'] ),
				array( 'status' => 400, 'readiness' => $readiness )
			);
		}

		$old    = $existing && $existing['payload'] ? axh_discovery_migrate_payload( json_decode( $existing['payload'], true ) ) : array();
		$credit = self::credit_answers( $existing && ! empty( $existing['sources'] ) ? json_decode( $existing['sources'], true ) : array(), $old, $clean_payload, 'rep', get_current_user_id() );

		$row = array(
			'lead_id'    => (int) $lead_id,
			'rep_id'     => (int) $lead['rep_id'],
			'payload'    => wp_json_encode( $clean_payload ),
			'modules'    => wp_json_encode( $clean_modules ),
			'sources'    => wp_json_encode( $credit[0] ? $credit[0] : new stdClass() ),
			'progress'   => $progress,
			'updated_at' => $now,
		);

		if ( $submit ) {
			$row['status']       = 'submitted';
			$row['submitted_at'] = $now;
			$row['quote_note']   = $note;
			$row['quote_by']     = get_current_user_id();
			$row['quote_ready']  = (int) $readiness['pct'];
		}

		if ( $existing ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $existing['id'] ) );
		} else {
			$row['status']     = $submit ? 'submitted' : 'draft';
			$row['created_at'] = $now;
			$wpdb->insert( $t, $row );
		}

		// Answers a rep types are theirs on the record, so the client's own
		// words and the rep's notes are never confused in the brief.
		if ( $credit[1] > 0 ) {
			self::add_activity(
				array(
					'lead_id' => $lead_id,
					'type'    => 'note',
					'subject' => 'Questionnaire: ' . $credit[1] . ' answer' . ( 1 === $credit[1] ? '' : 's' ) . ' entered by the rep',
					'body'    => 'Entered in the Hub by ' . self::user_name( get_current_user_id() ) . ' on the prospect’s behalf.',
				)
			);
		}

		if ( $submit ) {
			self::add_activity(
				array(
					'lead_id' => $lead_id,
					'type'    => 'discovery',
					'subject' => 'Quote requested',
					'body'    => 'Questionnaire handed to AdaptExec for a written proposal (quote readiness ' . (int) $readiness['pct'] . '%).' . ( '' !== $note ? "\n\nNote for Adam: " . $note : '' ),
				)
			);
			$wpdb->update( self::table( 'leads' ), array( 'stage' => 'scoping', 'updated_at' => $now ), array( 'id' => (int) $lead_id ) );
			// Adam quotes in Billing, so the customer is waiting for him there.
			if ( class_exists( 'AXH_Bill' ) && method_exists( 'AXH_Bill', 'customer_from_lead' ) ) {
				AXH_Bill::customer_from_lead( $lead_id );
			}
			do_action( 'axh_discovery_submitted', $lead_id );
		}

		return self::get_discovery( $lead_id );
	}

	/**
	 * Manager review note on a submitted discovery.
	 */
	public static function review_discovery( $lead_id, $note, $status = 'reviewed' ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return false;
		}
		return false !== $wpdb->update(
			self::table( 'discovery' ),
			array(
				'status'      => sanitize_key( $status ),
				'review_note' => sanitize_textarea_field( $note ),
				'reviewed_at' => self::now(),
				'reviewed_by' => get_current_user_id(),
			),
			array( 'lead_id' => (int) $lead_id )
		);
	}

	protected static function clean_payload( $payload ) {
		$out = array();
		if ( ! is_array( $payload ) ) {
			return $out;
		}
		foreach ( $payload as $section => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}
			$section = sanitize_key( $section );
			foreach ( $fields as $key => $value ) {
				$key = sanitize_key( $key );
				if ( is_array( $value ) ) {
					$clean = array();
					foreach ( $value as $item ) {
						if ( is_array( $item ) ) {
							$sub = array();
							foreach ( $item as $ik => $iv ) {
								$sub[ sanitize_key( $ik ) ] = sanitize_text_field( is_scalar( $iv ) ? $iv : '' );
							}
							$clean[] = $sub;
						} else {
							$clean[] = sanitize_text_field( $item );
						}
					}
					$out[ $section ][ $key ] = $clean;
				} else {
					$out[ $section ][ $key ] = sanitize_textarea_field( (string) $value );
				}
			}
		}
		return $out;
	}

	protected static function clean_modules( $modules ) {
		$valid = array_flip( axh_module_names() );
		$out   = array();
		if ( ! is_array( $modules ) ) {
			return $out;
		}
		foreach ( $modules as $name => $level ) {
			$name = wp_unslash( (string) $name );
			if ( ! isset( $valid[ $name ] ) ) {
				continue;
			}
			$level = sanitize_key( $level );
			if ( in_array( $level, array( 'must', 'nice' ), true ) ) {
				$out[ $name ] = $level;
			}
		}
		return $out;
	}

	/**
	 * Rough completion percentage, used for the progress ring in the portal.
	 */
	protected static function progress( $payload, $modules, $industry = '', $config = array() ) {
		$total  = 0;
		$filled = 0;
		$schema = axh_discovery_visible( axh_discovery_schema( $industry ), $config );
		foreach ( $schema as $section ) {
			// The on-site study is an optional add-on, not a gap (1.7.1).
			if ( ! empty( $section['optional'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field ) {
				$total++;
				if ( 'modules' === $field['type'] ) {
					if ( ! empty( $modules ) ) {
						$filled++;
					}
					continue;
				}
				$value = $payload[ $section['key'] ][ $field['key'] ] ?? '';
				if ( is_array( $value ) ? count( $value ) : '' !== trim( (string) $value ) ) {
					$filled++;
				}
			}
		}
		return $total ? (int) round( $filled / $total * 100 ) : 0;
	}

	/* --------------------------------------------------------------- sessions */

	public static function start_session( $deck, $lead_id = null ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'sessions' ),
			array(
				'rep_id'     => get_current_user_id(),
				'lead_id'    => $lead_id && self::get_lead( (int) $lead_id ) ? (int) $lead_id : null,
				'deck'       => sanitize_key( $deck ),
				'started_at' => self::now(),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $lead_id && self::get_lead( $lead_id ) ) {
			self::add_activity(
				array(
					'lead_id' => $lead_id,
					'type'    => 'presentation',
					'subject' => 'Presenting “' . AXH_P1::deck_info( sanitize_key( $deck ) )['title'] . '”',
				)
			);
		}
		return $id;
	}

	public static function end_session( $id, $furthest, $seconds, $slides = 0 ) {
		global $wpdb;
		$t   = self::table( 'sessions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row || (int) $row['rep_id'] !== get_current_user_id() ) {
			return false;
		}
		$ok = false !== $wpdb->update(
			$t,
			array(
				'furthest_page' => max( 0, (int) $furthest ),
				'seconds'       => max( 0, (int) $seconds ),
				'ended_at'      => self::now(),
			),
			array( 'id' => (int) $id )
		);
		if ( $ok && empty( $row['ended_at'] ) ) {
			AXH_P1::presented( $row, max( 1, (int) $furthest ), max( 0, (int) $seconds ), (int) $slides );
		}
		return $ok;
	}

	/* ---------------------------------------------------------------- reports */

	/**
	 * Headline numbers for whoever is asking: a rep sees their own, a manager sees everyone's.
	 */
	public static function summary( $rep_id = 0 ) {
		global $wpdb;
		$leads = self::table( 'leads' );

		$where = array( self::scope() );
		if ( $rep_id && axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'rep_id = %d', (int) $rep_id );
		}
		$w = implode( ' AND ', $where );

		$open_stages = "'new','contacted','discovery','scoping','proposal'";
		$live        = 'archived_at IS NULL';

		$out = array(
			'open_count'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE {$w} AND {$live} AND stage IN ({$open_stages})" ), // phpcs:ignore
			'pipeline'      => (float) $wpdb->get_var( "SELECT COALESCE(SUM(est_value),0) FROM {$leads} WHERE {$w} AND {$live} AND stage IN ({$open_stages})" ), // phpcs:ignore
			'won_count'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE {$w} AND stage = 'won'" ), // phpcs:ignore
			'won_value'     => (float) $wpdb->get_var( "SELECT COALESCE(SUM(contract_value),0) FROM {$leads} WHERE {$w} AND stage = 'won'" ), // phpcs:ignore
			'pending_reg'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE {$w} AND {$live} AND reg_status = 'pending'" ), // phpcs:ignore
			'pending_win'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE {$w} AND {$live} AND win_requested_at IS NOT NULL AND stage <> 'won'" ), // phpcs:ignore
			'archived'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE {$w} AND archived_at IS NOT NULL" ), // phpcs:ignore
			'by_stage'      => array(),
		);

		$rows = $wpdb->get_results( "SELECT stage, COUNT(*) c, COALESCE(SUM(est_value),0) v FROM {$leads} WHERE {$w} AND {$live} GROUP BY stage", ARRAY_A ); // phpcs:ignore
		foreach ( $rows as $r ) {
			$out['by_stage'][ $r['stage'] ] = array( 'count' => (int) $r['c'], 'value' => (float) $r['v'] );
		}

		return $out;
	}

	/**
	 * Per-rep roll-up for the manager overview.
	 */
	public static function rep_rollup() {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return array();
		}
		$leads = self::table( 'leads' );
		$act   = self::table( 'activity' );

		$out = array();

		// Everyone on the team, including people whose access was removed —
		// their leads and commission still belong to them.
		foreach ( AXH_Team::roster() as $member ) {
			$id  = (int) $member['id'];
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) total,
						SUM(archived_at IS NULL AND stage IN ('new','contacted','discovery','scoping','proposal')) open_count,
						COALESCE(SUM(CASE WHEN archived_at IS NULL AND stage IN ('new','contacted','discovery','scoping','proposal') THEN est_value ELSE 0 END),0) pipeline,
						SUM(stage = 'won') won_count,
						COALESCE(SUM(CASE WHEN stage = 'won' THEN contract_value ELSE 0 END),0) won_value,
						SUM(reg_status = 'pending') pending_reg
					FROM {$leads} WHERE rep_id = %d",
					$id
				),
				ARRAY_A
			);

			$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(occurred_at) FROM {$act} WHERE rep_id = %d", $id ) );

			$out[] = array_merge( $member, array(
				'id'            => $id,
				'total'         => (int) ( $row['total'] ?? 0 ),
				'open_count'    => (int) ( $row['open_count'] ?? 0 ),
				'pipeline'      => (float) ( $row['pipeline'] ?? 0 ),
				'won_count'     => (int) ( $row['won_count'] ?? 0 ),
				'won_value'     => (float) ( $row['won_value'] ?? 0 ),
				'pending_reg'   => (int) ( $row['pending_reg'] ?? 0 ),
				'last_activity' => $last,
			) );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ forms */

	public static function get_forms() {
		global $wpdb;
		$t   = self::table( 'forms' );
		$sql = 'SELECT * FROM ' . $t . ' WHERE ' . self::scope() . ' ORDER BY created_at DESC LIMIT 200';
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as &$row ) {
			$row['fields']   = json_decode( $row['fields'] ? $row['fields'] : '[]', true );
			$row['custom']   = json_decode( $row['custom'] ? $row['custom'] : '[]', true );
			$row['url']      = home_url( '/f/' . $row['slug'] . '/' );
			$row['rep_name'] = self::user_name( $row['rep_id'] );
			$row['extra']    = AXH_P1::form_extra( $row );
		}
		return $rows;
	}

	public static function get_form( $id ) {
		global $wpdb;
		$t   = self::table( 'forms' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( ! axh_is_manager() && (int) $row['rep_id'] !== get_current_user_id() ) {
			return null;
		}
		$row['fields']   = json_decode( $row['fields'] ? $row['fields'] : '[]', true );
		$row['custom']   = json_decode( $row['custom'] ? $row['custom'] : '[]', true );
		$row['url']      = home_url( '/f/' . $row['slug'] . '/' );
		$row['rep_name'] = self::user_name( $row['rep_id'] );
		$row['extra']    = AXH_P1::form_extra( $row );
		return $row;
	}

	/** Public lookup by slug — no ownership check, this is the live form. */
	public static function form_by_slug( $slug ) {
		global $wpdb;
		$t   = self::table( 'forms' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s AND active = 1", sanitize_title( $slug ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$row['fields'] = json_decode( $row['fields'] ? $row['fields'] : '[]', true );
		$row['custom'] = json_decode( $row['custom'] ? $row['custom'] : '[]', true );
		return $row;
	}

	protected static function unique_slug( $base, $ignore_id = 0 ) {
		global $wpdb;
		$t    = self::table( 'forms' );
		$slug = sanitize_title( $base );
		if ( ! $slug ) {
			$slug = 'form';
		}
		$try = $slug;
		$n   = 2;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE slug = %s AND id <> %d", $try, (int) $ignore_id ) ) ) {
			$try = $slug . '-' . $n;
			$n++;
		}
		return $try;
	}

	protected static function clean_form( $data, $existing = null ) {
		$catalog = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			foreach ( $step['fields'] as $f ) {
				$catalog[ $f['key'] ] = true;
			}
		}

		$fields = array();
		foreach ( (array) ( $data['fields'] ?? array() ) as $k ) {
			$k = sanitize_key( $k );
			if ( isset( $catalog[ $k ] ) ) {
				$fields[] = $k;
			}
		}
		if ( ! in_array( 'name', $fields, true ) ) {
			array_unshift( $fields, 'name' );
		}
		if ( ! in_array( 'email', $fields, true ) ) {
			$fields[] = 'email';
		}

		$custom = array();
		foreach ( array_slice( (array) ( $data['custom'] ?? array() ), 0, 6 ) as $c ) {
			$label = sanitize_text_field( $c['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$type = in_array( ( $c['type'] ?? 'text' ), array( 'text', 'textarea', 'select' ), true ) ? $c['type'] : 'text';
			$opts = array();
			if ( 'select' === $type ) {
				$raw  = is_array( $c['options'] ?? null ) ? $c['options'] : preg_split( '/[\r\n,]+/', (string) ( $c['options'] ?? '' ) );
				$opts = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', (array) $raw ) ), 'strlen' ) ), 0, 20 );
				if ( ! $opts ) {
					$type = 'text';
				}
			}
			$item = array(
				'label'    => mb_substr( $label, 0, 160 ),
				'type'     => $type,
				'required' => ! empty( $c['required'] ) && 'false' !== $c['required'],
			);
			if ( $opts ) {
				$item['options'] = $opts;
			}
			$custom[] = $item;
		}

		$accent = sanitize_hex_color( $data['accent'] ?? '' );

		return array(
			'name'            => sanitize_text_field( $data['name'] ?? 'Untitled form' ),
			'headline'        => sanitize_text_field( $data['headline'] ?? '' ),
			'subhead'         => sanitize_textarea_field( $data['subhead'] ?? '' ),
			'intro'           => sanitize_textarea_field( $data['intro'] ?? '' ),
			'accent'          => $accent ? $accent : '#EF5D0F',
			'industry'        => sanitize_text_field( $data['industry'] ?? '' ),
			'source_tag'      => sanitize_text_field( $data['source_tag'] ?? '' ),
			'fields'          => wp_json_encode( $fields ),
			'custom'          => wp_json_encode( $custom ),
			'thanks_headline' => sanitize_text_field( $data['thanks_headline'] ?? '' ),
			'thanks_body'     => sanitize_textarea_field( $data['thanks_body'] ?? '' ),
			'redirect_url'    => esc_url_raw( $data['redirect_url'] ?? '' ),
			'active'          => empty( $data['active'] ) ? 0 : 1,
			'style_picker'    => empty( $data['style_picker'] ) ? 0 : 1,
		);
	}

	public static function insert_form( $data ) {
		global $wpdb;
		$row               = self::clean_form( $data );
		$row['rep_id']     = get_current_user_id();
		$row['slug']       = self::unique_slug( $data['slug'] ?? $row['name'] );
		if ( get_option( 'axh_p1_db' ) ) {
			$row['extra'] = wp_json_encode( AXH_P1::clean_extra( $data['extra'] ?? array() ) );
		}
		$row['created_at'] = self::now();
		$row['updated_at'] = self::now();
		$wpdb->insert( self::table( 'forms' ), $row );
		return (int) $wpdb->insert_id;
	}

	public static function update_form( $id, $data ) {
		global $wpdb;
		$existing = self::get_form( $id );
		if ( ! $existing ) {
			return false;
		}
		$data = (array) $data;
		// 1.9.0: a save only changes what it sends. Anything left out (custom
		// questions, the redirect, the style step...) keeps its saved value
		// instead of being wiped.
		$merged = $data;
		foreach ( array( 'name', 'headline', 'subhead', 'intro', 'accent', 'industry', 'source_tag', 'fields', 'custom', 'thanks_headline', 'thanks_body', 'redirect_url', 'active', 'style_picker' ) as $k ) {
			if ( ! array_key_exists( $k, $merged ) ) {
				$merged[ $k ] = $existing[ $k ];
			}
		}
		$row = self::clean_form( $merged );
		if ( array_key_exists( 'extra', $data ) && get_option( 'axh_p1_db' ) ) {
			$row['extra'] = wp_json_encode( AXH_P1::clean_extra( $data['extra'], $existing['extra'] ?? array() ) );
		}
		if ( ! empty( $data['slug'] ) && $data['slug'] !== $existing['slug'] ) {
			$row['slug'] = self::unique_slug( $data['slug'], $id );
		}
		// Who a form's leads go to. Only a manager can hand a form to someone
		// else — this is how website enquiries get routed to a rep.
		if ( ! empty( $data['rep_id'] ) && axh_is_manager() && (int) $data['rep_id'] !== (int) $existing['rep_id'] ) {
			$owner = (int) $data['rep_id'];
			if ( ! user_can( $owner, 'axh_use_hub' ) ) {
				return false;
			}
			$row['rep_id'] = $owner;
		}
		$row['updated_at'] = self::now();
		return false !== $wpdb->update( self::table( 'forms' ), $row, array( 'id' => (int) $id ) );
	}

	public static function delete_form( $id ) {
		global $wpdb;
		if ( ! self::get_form( $id ) ) {
			return false;
		}
		return (bool) $wpdb->delete( self::table( 'forms' ), array( 'id' => (int) $id ) );
	}

	public static function count_form_view( $id ) {
		global $wpdb;
		$t = self::table( 'forms' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET views = views + 1 WHERE id = %d", (int) $id ) ); // phpcs:ignore
	}

	public static function get_submissions( $form_id = 0, $limit = 100 ) {
		global $wpdb;
		$t     = self::table( 'submissions' );
		$where = array( self::scope() );
		if ( $form_id ) {
			$where[] = $wpdb->prepare( 'form_id = %d', (int) $form_id );
		}
		$sql  = 'SELECT * FROM ' . $t . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as &$r ) {
			$r['payload'] = json_decode( $r['payload'] ? $r['payload'] : '{}', true );
		}
		return $rows;
	}

	/**
	 * Records a public submission and opens a lead for the owning rep.
	 * Runs unauthenticated, so everything here is defensive.
	 */
	public static function record_submission( $form, $answers, $meta = array() ) {
		global $wpdb;
		$now = self::now();

		// Optional structured results from a lead-capture tool (answers.tool_results):
		// kept apart from the plain answers, cleaned, and stored on the lead.
		$tool = axh_tool_results_clean( $answers['tool_results'] ?? null );
		unset( $answers['tool_results'] );

		// 1.9.0: call/text consent is recorded as evidence (who, when, the
		// exact words), not as a loose answer; internal keys never come in.
		// Only forms that actually showed the box record a yes or no; the
		// website's own pages (contact page, calculators) never ask.
		$consented = ! empty( $answers['sms_consent'] ) && 'false' !== $answers['sms_consent'];
		$asked     = ! empty( $answers['consent_shown'] );
		unset( $answers['sms_consent'], $answers['consent_shown'], $answers['_labels'], $answers['contact_consent'] );
		AXH_P1::$repeat = false;

		$clean = array();
		foreach ( (array) $answers as $k => $v ) {
			$k = sanitize_key( $k );
			if ( is_array( $v ) ) {
				$clean[ $k ] = array_slice( array_map( 'sanitize_text_field', array_filter( $v, 'is_scalar' ) ), 0, 30 );
			} else {
				// Free-text answers keep their line breaks. The "biggest headache"
				// box also carries the summaries our online tools send, so it may
				// run to a few thousand characters; everything else stays short.
				$clean[ $k ] = sanitize_textarea_field( mb_substr( (string) $v, 0, axh_answer_limit( $k ) ) );
			}
		}

		$source = $form['source_tag'] ? $form['source_tag'] : $form['name'];
		if ( ! empty( $meta['src'] ) ) {
			$source .= ' · ' . sanitize_text_field( $meta['src'] );
		}

		$lead = array(
			'rep_id'       => (int) $form['rep_id'],
			'company'      => $clean['company'] ?? ( $clean['name'] ?? 'Website enquiry' ),
			'contact_name' => $clean['name'] ?? '',
			'email'        => sanitize_email( $clean['email'] ?? '' ),
			'phone'        => $clean['phone'] ?? '',
			'website'      => $clean['website'] ?? '',
			'industry'     => $clean['industry'] ?? $form['industry'],
			'city'         => $clean['city'] ?? '',
			'state'        => $clean['state'] ?? '',
			'stage'        => 'new',
			'reg_status'   => 'draft',
			'source'       => mb_substr( $source, 0, 96 ),
			'next_action'  => 'Reply to enquiry',
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		if ( $lead['website'] && ! preg_match( '#^https?://#i', $lead['website'] ) ) {
			$lead['website'] = 'https://' . $lead['website'];
		}
		$lead['website'] = esc_url_raw( $lead['website'] );
		foreach ( self::lead_lengths() as $col => $len ) {
			if ( isset( $lead[ $col ] ) ) {
				$lead[ $col ] = self::clip( $lead[ $col ], $len );
			}
		}

		// Optional: the visitor's website style picks (forms with the style
		// step turned on, or any page that sends answers.style_picks).
		$style = null;
		if ( ! empty( $answers['style_picks'] ) || ! empty( $answers['style'] ) ) {
			$style = axh_style_clean( ! empty( $answers['style_picks'] ) ? $answers['style_picks'] : $answers['style'], 'form' );
			if ( $style ) {
				$style['at']    = $now;
				$style['by']    = $lead['contact_name'] ? $lead['contact_name'] : 'Website visitor';
				$lead['style_picks'] = wp_json_encode( $style );
				$lead['style_at']    = $now;
				$clean['style_picks'] = axh_style_names( $style );
				unset( $clean['style'] );
			}
		}

		if ( $tool ) {
			$lead['tool_results'] = wp_json_encode( $tool );
		}

		if ( ! empty( $clean['phone'] ) && $asked ) {
			$owner = get_userdata( (int) $form['rep_id'] );
			$clean['contact_consent'] = $consented
				? 'Yes — ' . gmdate( 'M j, Y g:i a' ) . ' UTC, for ' . $clean['phone'] . ': "' . AXH_P1::consent_text( $owner ? $owner->display_name : '' ) . '"'
				: 'No — did not tick the call/text consent box';
		}
		// The question wording at the time, so later edits to the form never
		// relabel old answers.
		$qlabels = array();
		foreach ( (array) $form['custom'] as $i => $c ) {
			if ( isset( $clean[ 'custom_' . $i ] ) && ! empty( $c['label'] ) ) {
				$qlabels[ 'custom_' . $i ] = $c['label'];
			}
		}
		if ( $qlabels ) {
			$clean['_labels'] = $qlabels;
		}

		// 1.9.0: the same person again (same email or phone, owner's open
		// leads) joins their existing lead instead of making a duplicate.
		$lead_id = AXH_P1::duplicate_lead( (int) $form['rep_id'], $lead['email'], $lead['phone'] );
		if ( $lead_id ) {
			AXH_P1::$repeat = true;
			AXH_P1::merge_into( $lead_id, $lead );
		} else {
			$wpdb->insert( self::table( 'leads' ), $lead );
			$lead_id = (int) $wpdb->insert_id;
		}
		AXH_P1::speed_task( (int) $form['rep_id'], $lead_id, $lead['contact_name'], $lead['phone'] );

		$wpdb->insert(
			self::table( 'submissions' ),
			array(
				'form_id'    => (int) $form['id'],
				'rep_id'     => (int) $form['rep_id'],
				'lead_id'    => $lead_id,
				'payload'    => wp_json_encode( $tool ? array_merge( $clean, array( 'tool_results' => $tool ) ) : $clean ),
				'source'     => mb_substr( $source, 0, 96 ),
				'referrer'   => esc_url_raw( mb_substr( (string) ( $meta['referrer'] ?? '' ), 0, 191 ) ),
				'ip_hash'    => hash( 'sha256', ( $meta['ip'] ?? '' ) . wp_salt() ),
				'created_at' => $now,
			)
		);

		$t = self::table( 'forms' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET submissions = submissions + 1 WHERE id = %d", (int) $form['id'] ) ); // phpcs:ignore

		// Human-readable transcript on the lead's timeline.
		$lines = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			foreach ( $step['fields'] as $f ) {
				if ( ! isset( $clean[ $f['key'] ] ) || '' === $clean[ $f['key'] ] ) {
					continue;
				}
				$val     = is_array( $clean[ $f['key'] ] ) ? implode( ', ', $clean[ $f['key'] ] ) : $clean[ $f['key'] ];
				$lines[] = $f['label'] . ':' . ( false !== strpos( $val, "\n" ) ? "\n" . $val : ' ' . $val );
			}
		}
		foreach ( (array) $form['custom'] as $i => $c ) {
			$k = 'custom_' . $i;
			if ( ! empty( $clean[ $k ] ) ) {
				$lines[] = $c['label'] . ': ' . $clean[ $k ];
			}
		}
		if ( isset( $clean['contact_consent'] ) ) {
			$lines[] = 'Call/text consent: ' . ( $consented ? 'Yes (' . gmdate( 'M j, Y g:i a' ) . ' UTC)' : 'No' );
		}
		if ( $style ) {
			$lines[] = 'Website style picks: ' . axh_style_names( $style );
		}
		if ( $tool ) {
			$lines[] = 'Tool results: ' . count( axh_tool_results_lines( $tool ) ) . ' items (see the Tool results card)';
		}

		$wpdb->insert(
			self::table( 'activity' ),
			array(
				'lead_id'     => $lead_id,
				'rep_id'      => (int) $form['rep_id'],
				'type'        => 'form',
				'subject'     => ( AXH_P1::$repeat ? 'Sent the form again — ' : 'Form submission — ' ) . $form['name'],
				'body'        => implode( "\n", $lines ),
				'occurred_at' => $now,
				'created_at'  => $now,
			)
		);

		return $lead_id;
	}

	/** Simple per-IP throttle for the public endpoint. */
	public static function throttled( $ip ) {
		$key   = 'axh_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/* ---------------------------------------------------------------- helpers */

	protected static function clean_stage( $stage ) {
		$stage = sanitize_key( $stage );
		return in_array( $stage, AXH_Install::stage_keys(), true ) ? $stage : 'new';
	}

	/* --------------------------------------------------------------- earnings */

	/**
	 * Commission on closed business.
	 *
	 * Half falls due when the customer's deposit lands and half on their final
	 * payment, each payable within 30 days — the arrangement in section 3.3 of
	 * the agreement. The rate is whatever was locked onto the lead when it
	 * closed, so re-tiering later cannot rewrite history.
	 */
	public static function earnings( $unscoped = false, $rep_id = 0 ) {
		global $wpdb;
		$t     = self::table( 'leads' );
		$where = $unscoped ? '1=1' : self::scope( 'rep_id' );
		if ( $rep_id && ( $unscoped || axh_is_manager() ) ) {
			$where .= $wpdb->prepare( ' AND rep_id = %d', (int) $rep_id );
		}
		// Won deals, plus any deal that was paid commission and later moved out
		// of Closed Won (refund, cancellation): the money already paid stays on
		// the record so it can be recovered (agreement 3.6) and so year-end
		// totals match the checks actually written.
		$pt0  = self::table( 'payouts' );
		$rows = $wpdb->get_results(
			"SELECT id, rep_id, company, stage, contract_value, commission_rate, closed_at,
			        deposit_at, final_at, deposit_paid_at, final_paid_at
			 FROM {$t}
			 WHERE ( stage = 'won' OR id IN ( SELECT lead_id FROM {$pt0} WHERE voided_at IS NULL ) ) AND {$where}
			 ORDER BY closed_at DESC, id DESC",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL

		// The payment record behind each "paid", so a rep can see how and when.
		$records = array();
		if ( $rows ) {
			$ids = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
			$pt  = self::table( 'payouts' );
			foreach ( (array) $wpdb->get_results( "SELECT id, lead_id, leg, amount, paid_at, method, reference FROM {$pt} WHERE voided_at IS NULL AND lead_id IN ({$ids}) ORDER BY id ASC", ARRAY_A ) as $p ) { // phpcs:ignore
				$records[ $p['lead_id'] . ':' . $p['leg'] ] = $p;
			}
		}

		$days  = AXH_Install::payout_days();
		$out   = array();
		$total = array( 'earned' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'upcoming' => 0.0, 'contracts' => 0.0, 'overpaid' => 0.0 );

		foreach ( (array) $rows as $r ) {
			$reversed = 'won' !== $r['stage'];
			$value    = $reversed ? 0.0 : (float) $r['contract_value'];
			$rate     = (float) $r['commission_rate'];
			if ( ! $rate ) {
				$rate = AXH_Install::commission_rate( $value, $r['rep_id'] );
			}
			$commission = round( $value * $rate / 100, 2 );
			$half       = round( $commission / 2, 2 );

			// What was really paid on this deal. A paid half shows the amount of
			// its payment record, not a figure recomputed from today's contract
			// value; an unpaid half is capped at what is still owed.
			$paid_sum = 0.0;
			foreach ( array( 'deposit', 'final' ) as $k ) {
				if ( ! empty( $r[ $k . '_paid_at' ] ) && isset( $records[ $r['id'] . ':' . $k ] ) ) {
					$paid_sum += (float) $records[ $r['id'] . ':' . $k ]['amount'];
				}
			}
			$any_paid  = $paid_sum > 0;
			$remaining = round( $commission - $paid_sum, 2 );

			$legs = array();
			foreach ( array(
				array( 'deposit', 'Customer deposit', $r['deposit_at'], $r['deposit_paid_at'] ),
				array( 'final', 'Customer final payment', $r['final_at'], $r['final_paid_at'] ),
			) as $leg ) {
				list( $key, $label, $trigger, $paid ) = $leg;
				$amount = 'final' === $key ? round( $commission - $half, 2 ) : $half;
				$state  = 'waiting';
				$due    = null;
				$rec    = $records[ $r['id'] . ':' . $key ] ?? null;
				if ( $paid ) {
					$state = 'paid';
					if ( $rec ) {
						$amount = round( (float) $rec['amount'], 2 );
					}
				} else {
					if ( $any_paid ) {
						$amount = max( 0.0, min( $amount, $remaining ) );
					}
					if ( $reversed || $amount <= 0 ) {
						$amount = 0.0;
						$state  = 'cancelled';
					} elseif ( $trigger ) {
						$due   = gmdate( 'Y-m-d H:i:s', strtotime( $trigger ) + $days * DAY_IN_SECONDS );
						$state = strtotime( $due ) < time() ? 'overdue' : 'due';
					}
				}
				$legs[] = array(
					'key'       => $key,
					'label'     => $label,
					'amount'    => $amount,
					'state'     => $state,
					'trigger'   => $trigger,
					'due_at'    => $due,
					'paid_at'   => $paid,
					'payout_id' => $rec ? (int) $rec['id'] : 0,
					'method'    => $rec ? $rec['method'] : '',
					'reference' => $rec ? $rec['reference'] : '',
				);
				if ( 'paid' === $state ) {
					$total['paid'] += $amount;
				} elseif ( 'waiting' === $state ) {
					$total['upcoming'] += $amount;
				} elseif ( 'cancelled' !== $state ) {
					$total['due'] += $amount;
				}
			}
			$overpaid = max( 0.0, round( $paid_sum - $commission, 2 ) );

			$total['earned']    += $commission;
			$total['contracts'] += $value;
			$total['overpaid']  += $overpaid;

			$out[] = array(
				'lead_id'    => (int) $r['id'],
				'rep_id'     => (int) $r['rep_id'],
				'company'    => $r['company'],
				'rep_name'   => ( $unscoped || axh_is_manager() ) ? self::user_name( $r['rep_id'] ) : '',
				'value'      => $value,
				'rate'       => $rate,
				'commission' => $commission,
				'closed_at'  => $r['closed_at'],
				'reversed'   => $reversed,
				'overpaid'   => $overpaid,
				'legs'       => $legs,
			);
		}

		foreach ( $total as $k => $v ) {
			$total[ $k ] = round( $v, 2 );
		}
		return array( 'deals' => $out, 'totals' => $total, 'tiers' => AXH_Install::commission_tiers() );
	}

	/**
	 * Record when the customer paid, or when we paid the rep. Managers only —
	 * a rep marking their own commission paid would be nonsense.
	 */
	public static function set_payment( $lead_id, $field, $when ) {
		global $wpdb;
		$allowed = array( 'deposit_at', 'final_at', 'deposit_paid_at', 'final_paid_at' );
		if ( ! in_array( $field, $allowed, true ) ) {
			return null;
		}
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return null;
		}
		$value = $when ? self::clean_datetime( $when ) : null;
		// 1.11.0: saving the dialog sends every date; only real changes count.
		$had = ! empty( $lead[ $field ] ) ? substr( (string) $lead[ $field ], 0, 10 ) : '';
		if ( ( $value ? substr( $value, 0, 10 ) : '' ) === $had ) {
			return $lead;
		}
		// Paying the rep is a payout record, not just a date.
		if ( in_array( $field, array( 'deposit_paid_at', 'final_paid_at' ), true ) ) {
			$leg = 'deposit_paid_at' === $field ? 'deposit' : 'final';
			if ( $value && empty( $lead[ $field ] ) ) {
				$r = self::record_payout( $lead_id, $leg, array( 'paid_at' => $value ) );
				return is_wp_error( $r ) ? null : self::get_lead( $lead_id );
			}
			if ( ! $value && ! empty( $lead[ $field ] ) ) {
				$active = self::active_payout( $lead_id, $leg );
				if ( $active ) {
					self::void_payout( $active['id'], 'Cleared from Record a payment' );
					return self::get_lead( $lead_id );
				}
			}
		}
		$wpdb->update(
			self::table( 'leads' ),
			array( $field => $value, 'updated_at' => self::now() ),
			array( 'id' => (int) $lead_id )
		);

		$label = array(
			'deposit_at'      => 'Customer deposit received',
			'final_at'        => 'Customer final payment received',
			'deposit_paid_at' => 'Commission paid — deposit half',
			'final_paid_at'   => 'Commission paid — final half',
		);
		self::add_activity(
			array(
				'lead_id' => $lead_id,
				'type'    => 'payment',
				'subject' => $label[ $field ] . ( $value ? '' : ' (cleared)' ),
				'body'    => $value ? gmdate( 'j M Y', strtotime( $value ) ) : '',
			)
		);
		return self::get_lead( $lead_id );
	}

	/* ----------------------------------------------------------------- styles */

	/** A lead's saved website style picks, or null. */
	public static function style_value( $lead ) {
		if ( empty( $lead['style_picks'] ) ) {
			return null;
		}
		$v = json_decode( $lead['style_picks'], true );
		if ( ! is_array( $v ) ) {
			return null;
		}
		$v['answers'] = (object) ( $v['answers'] ?? array() );
		$v['names']   = axh_style_names( $v );
		return $v;
	}

	/**
	 * Save (or, with empty picks, clear) a lead's style picks.
	 *
	 * @param array  $lead   The lead row (already checked by the caller).
	 * @param mixed  $raw    Picks as sent.
	 * @param string $source rep | client | discovery | form.
	 * @param string $by     Who chose them, for the timeline.
	 * @return array|null The saved value, or null when cleared.
	 */
	protected static function write_style( $lead, $raw, $source, $by ) {
		global $wpdb;
		$v   = axh_style_clean( $raw, $source );
		$now = self::now();
		if ( $v ) {
			$v['at'] = $now;
			$v['by'] = mb_substr( sanitize_text_field( $by ), 0, 96 );
		}
		$wpdb->update(
			self::table( 'leads' ),
			array( 'style_picks' => $v ? wp_json_encode( $v ) : null, 'style_at' => $v ? $now : null, 'updated_at' => $now ),
			array( 'id' => (int) $lead['id'] )
		);
		$where = array( 'rep' => 'in the Hub', 'client' => 'on their style link', 'discovery' => 'in the discovery questionnaire', 'form' => 'on a lead form' );
		$wpdb->insert(
			self::table( 'activity' ),
			array(
				'lead_id'     => (int) $lead['id'],
				'rep_id'      => get_current_user_id() ? get_current_user_id() : (int) $lead['rep_id'],
				'type'        => 'style',
				'subject'     => self::clip( $v ? 'Website style picks: ' . axh_style_names( $v ) : 'Website style picks cleared', 191 ),
				'body'        => $v ? trim( 'By ' . $v['by'] . ' ' . ( $where[ $source ] ?? '' ) . ( $v['top'] ? "\nTop matches: " . axh_style_names( array( 'top' => $v['top'] ), true ) : '' ) . ( $v['notes'] ? "\nNotes: " . $v['notes'] : '' ) ) : 'By ' . self::user_name( get_current_user_id() ),
				'occurred_at' => $now,
				'created_at'  => $now,
			)
		);
		return $v;
	}

	/** Rep or manager: save picks on a lead they can see. */
	public static function save_style_picks( $lead_id, $raw ) {
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		return array( 'style' => self::write_style( $lead, $raw, 'rep', self::user_name( get_current_user_id() ) ) );
	}

	/** The client's own style link for a lead, made once and reused. */
	public static function style_token( $lead_id ) {
		global $wpdb;
		$lead = self::get_lead( $lead_id );
		if ( ! $lead ) {
			return '';
		}
		if ( ! empty( $lead['style_token'] ) ) {
			return $lead['style_token'];
		}
		$token = bin2hex( random_bytes( 12 ) );
		$wpdb->update( self::table( 'leads' ), array( 'style_token' => $token ), array( 'id' => (int) $lead_id ) );
		self::log( $lead_id, 'style', 'Style link created for the client', 'By ' . self::user_name( get_current_user_id() ) );
		return $token;
	}

	/** Public: the lead behind a style link. The token is the credential. */
	public static function lead_by_style_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $token ) );
		if ( strlen( $token ) < 16 ) {
			return null;
		}
		$t = self::table( 'leads' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE style_token = %s AND archived_at IS NULL", $token ), ARRAY_A );
	}

	/** Public: a client saving picks from their style link or questionnaire. */
	public static function save_style_public( $lead, $raw, $source ) {
		$who = $lead['contact_name'] ? $lead['contact_name'] : ( $lead['company'] ? $lead['company'] : 'The client' );
		return self::write_style( $lead, $raw, $source, $who );
	}

	/* ------------------------------------------------------- tool results */

	/** A lead's stored tool results (decoded), or null. */
	public static function tool_results( $lead ) {
		if ( empty( $lead['tool_results'] ) ) {
			return null;
		}
		$v = json_decode( $lead['tool_results'], true );
		return is_array( $v ) && $v ? $v : null;
	}

	/**
	 * The form submission that opened this lead: what they answered, which
	 * form or tool it came from, and when. Null for leads added by hand.
	 */
	public static function lead_submission( $lead_id ) {
		global $wpdb;
		$s   = self::table( 'submissions' );
		$f   = self::table( 'forms' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT s.payload, s.source, s.referrer, s.created_at, f.name AS form_name, f.custom FROM {$s} s LEFT JOIN {$f} f ON f.id = s.form_id WHERE s.lead_id = %d ORDER BY s.id DESC LIMIT 1", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		$answers = json_decode( (string) $row['payload'], true );
		$answers = is_array( $answers ) ? $answers : array();
		unset( $answers['tool_results'] );
		$labels = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			foreach ( $step['fields'] as $fl ) {
				$labels[ $fl['key'] ] = $fl['label'];
			}
		}
		foreach ( (array) json_decode( (string) $row['custom'], true ) as $i => $c ) {
			if ( ! empty( $c['label'] ) ) {
				$labels[ 'custom_' . $i ] = $c['label'];
			}
		}
		$labels['style_picks']     = 'Website style picks';
		$labels['contact_consent'] = 'Call/text consent';
		if ( ! empty( $answers['_labels'] ) && is_array( $answers['_labels'] ) ) {
			$labels = array_merge( $labels, $answers['_labels'] );
		}
		unset( $answers['_labels'] );
		$list = array();
		foreach ( $answers as $k => $v ) {
			$v = is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v;
			if ( '' === trim( $v ) ) {
				continue;
			}
			$list[] = array( 'key' => $k, 'label' => $labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ), 'value' => $v );
		}
		return array(
			'form'     => $row['form_name'] ? $row['form_name'] : '',
			'source'   => $row['source'],
			'referrer' => $row['referrer'],
			'at'       => $row['created_at'],
			'answers'  => $list,
		);
	}

	/** Public: the lead behind a discovery token (for its style step). */
	public static function lead_by_discovery_token( $token ) {
		$disc = self::get_discovery_by_token( $token );
		return $disc ? self::lead_unscoped( $disc['lead_id'] ) : null;
	}

	/* ---------------------------------------------------------------- payouts */

	public static function active_payout( $lead_id, $leg ) {
		global $wpdb;
		$t = self::table( 'payouts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE lead_id = %d AND leg = %s AND voided_at IS NULL ORDER BY id DESC LIMIT 1", (int) $lead_id, $leg ), ARRAY_A );
	}

	/** The commission leg as Earnings works it out, for one won lead. */
	protected static function leg_of( $lead_id, $leg ) {
		$e = self::earnings( true );
		foreach ( $e['deals'] as $d ) {
			if ( (int) $d['lead_id'] === (int) $lead_id ) {
				foreach ( $d['legs'] as $l ) {
					if ( $l['key'] === $leg ) {
						return array_merge( $l, array( 'rep_id' => $d['rep_id'], 'company' => $d['company'] ) );
					}
				}
			}
		}
		return null;
	}

	/**
	 * Manager: record that a half of a deal's commission was paid to the rep.
	 *
	 * @param array $args paid_at, method, reference, note, amount (defaults to the half).
	 */
	public static function record_payout( $lead_id, $leg, $args = array() ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec records payouts.', array( 'status' => 403 ) );
		}
		if ( ! in_array( $leg, array( 'deposit', 'final' ), true ) ) {
			return new WP_Error( 'axh_bad_leg', 'Choose the deposit half or the final half.', array( 'status' => 400 ) );
		}
		$lead = self::get_lead( $lead_id );
		if ( ! $lead || 'won' !== $lead['stage'] ) {
			return new WP_Error( 'axh_not_won', 'Commission is only paid on Closed Won deals.', array( 'status' => 400 ) );
		}
		if ( self::active_payout( $lead_id, $leg ) ) {
			return new WP_Error( 'axh_already_paid', 'That half is already recorded as paid. Void it first to change it.', array( 'status' => 400 ) );
		}
		// A half on hold (Billing → Commissions: dispute, chargeback) can't be paid.
		$block = apply_filters( 'axh_payout_block', null, (int) $lead_id, $leg );
		if ( is_wp_error( $block ) ) {
			return $block;
		}
		$info    = self::leg_of( $lead_id, $leg );
		$amount  = isset( $args['amount'] ) && '' !== $args['amount'] ? round( (float) $args['amount'], 2 ) : (float) ( $info['amount'] ?? 0 );
		$paid_at = ! empty( $args['paid_at'] ) ? self::clean_datetime( $args['paid_at'] ) : self::now();
		if ( $amount <= 0 ) {
			return new WP_Error( 'axh_bad_amount', 'Enter the amount paid.', array( 'status' => 400 ) );
		}
		if ( ! $paid_at ) {
			return new WP_Error( 'axh_bad_date', 'Enter the date it was paid.', array( 'status' => 400 ) );
		}
		$wpdb->insert(
			self::table( 'payouts' ),
			array(
				'lead_id'     => (int) $lead_id,
				'rep_id'      => (int) $lead['rep_id'],
				'leg'         => $leg,
				'amount'      => $amount,
				'paid_at'     => $paid_at,
				'method'      => self::clip( sanitize_text_field( $args['method'] ?? '' ), 64 ),
				'reference'   => self::clip( sanitize_text_field( $args['reference'] ?? '' ), 191 ),
				'note'        => sanitize_textarea_field( $args['note'] ?? '' ),
				'recorded_by' => get_current_user_id(),
				'created_at'  => self::now(),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'axh_failed', 'Could not record that payment.', array( 'status' => 500 ) );
		}
		$wpdb->update( self::table( 'leads' ), array( $leg . '_paid_at' => $paid_at, 'updated_at' => self::now() ), array( 'id' => (int) $lead_id ) );
		$how = trim( ( $args['method'] ?? '' ) . ( ! empty( $args['reference'] ) ? ' · ref ' . $args['reference'] : '' ) );
		self::log( $lead_id, 'payment', 'Commission paid — ' . $leg . ' half, $' . number_format( $amount, 2 ), trim( gmdate( 'j M Y', strtotime( $paid_at ) ) . ( $how ? ' · ' . $how : '' ) . ' · by ' . self::user_name( get_current_user_id() ) ) );
		return self::get_payout( $id );
	}

	public static function get_payout( $id ) {
		global $wpdb;
		$t = self::table( 'payouts' );
		$l = self::table( 'leads' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT p.*, l.company FROM {$t} p LEFT JOIN {$l} l ON l.id = p.lead_id WHERE p.id = %d", (int) $id ), ARRAY_A );
	}

	/** Manager: undo a payout recorded in error. The row is kept, marked void. */
	public static function void_payout( $id, $reason = '' ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec changes payouts.', array( 'status' => 403 ) );
		}
		$p = self::get_payout( $id );
		if ( ! $p || $p['voided_at'] ) {
			return new WP_Error( 'axh_not_found', 'That payment is not on record.', array( 'status' => 404 ) );
		}
		$wpdb->update( self::table( 'payouts' ), array( 'voided_at' => self::now(), 'note' => trim( $p['note'] . "\nVoided: " . sanitize_text_field( $reason ) ) ), array( 'id' => (int) $id ) );
		if ( ! self::active_payout( $p['lead_id'], $p['leg'] ) ) {
			$wpdb->update( self::table( 'leads' ), array( $p['leg'] . '_paid_at' => null, 'updated_at' => self::now() ), array( 'id' => (int) $p['lead_id'] ) );
		}
		self::log( $p['lead_id'], 'payment', 'Commission payment voided — ' . $p['leg'] . ' half, $' . number_format( (float) $p['amount'], 2 ), trim( 'By ' . self::user_name( get_current_user_id() ) . ( $reason ? ' · ' . sanitize_text_field( $reason ) : '' ) ) );
		return self::get_payout( $id );
	}

	/** Payout history, newest first. Reps see their own; managers see everyone's. */
	public static function payouts( $args = array() ) {
		global $wpdb;
		$t     = self::table( 'payouts' );
		$l     = self::table( 'leads' );
		$where = array( axh_is_manager() ? '1=1' : $wpdb->prepare( 'p.rep_id = %d', get_current_user_id() ) );
		if ( ! empty( $args['rep_id'] ) && axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'p.rep_id = %d', (int) $args['rep_id'] );
		}
		if ( empty( $args['with_void'] ) ) {
			$where[] = 'p.voided_at IS NULL';
		}
		$rows = $wpdb->get_results( "SELECT p.*, l.company FROM {$t} p LEFT JOIN {$l} l ON l.id = p.lead_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY p.paid_at DESC, p.id DESC LIMIT 500', ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$r['rep_name'] = self::user_name( $r['rep_id'] );
			$r['amount']   = (float) $r['amount'];
		}
		return $rows;
	}

	/** What is owed right now (due or overdue), across the whole team. */
	public static function payouts_owed_total() {
		$e = self::earnings( true );
		return (float) $e['totals']['due'];
	}

	/* ------------------------------------------------------------ team reports */

	/** How likely each open stage is to close — a rough forecast, not a promise. */
	public static function stage_weights() {
		return apply_filters( 'axh_stage_weights', array( 'new' => 0.1, 'contacted' => 0.2, 'discovery' => 0.35, 'scoping' => 0.5, 'proposal' => 0.7 ) );
	}

	/**
	 * Closed (and about-to-close) deals across the team, for Deals closed.
	 *
	 * @param array $args status (won|lost|pending|all), rep_id, from, to, search.
	 */
	public static function deals( $args = array() ) {
		global $wpdb;
		$t      = self::table( 'leads' );
		$status = sanitize_key( $args['status'] ?? 'won' );
		$where  = array( self::scope() );
		$date   = 'closed_at';
		if ( 'pending' === $status ) {
			$where[] = "win_requested_at IS NOT NULL AND stage <> 'won'";
			$date    = 'win_requested_at';
		} elseif ( 'lost' === $status ) {
			$where[] = "stage = 'lost'";
		} elseif ( 'all' === $status ) {
			$where[] = "(stage IN ('won','lost') OR (win_requested_at IS NOT NULL AND stage <> 'won'))";
			$date    = 'COALESCE(closed_at, win_requested_at)';
		} else {
			$where[] = "stage = 'won'";
		}
		if ( ! empty( $args['rep_id'] ) && axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'rep_id = %d', (int) $args['rep_id'] );
		}
		if ( ! empty( $args['from'] ) && strtotime( $args['from'] ) ) {
			$where[] = $wpdb->prepare( "{$date} >= %s", gmdate( 'Y-m-d 00:00:00', strtotime( $args['from'] ) ) );
		}
		if ( ! empty( $args['to'] ) && strtotime( $args['to'] ) ) {
			$where[] = $wpdb->prepare( "{$date} <= %s", gmdate( 'Y-m-d 23:59:59', strtotime( $args['to'] ) ) );
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(company LIKE %s OR contact_name LIKE %s)', $like, $like );
		}
		$rows = $wpdb->get_results(
			"SELECT id, rep_id, company, contact_name, industry, city, state, source, stage, est_value, contract_value, commission_rate,
			        closed_at, win_requested_at, win_request_value, deposit_at, final_at, deposit_paid_at, final_paid_at, archived_at, style_picks
			 FROM {$t} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$date} DESC, id DESC LIMIT 1000",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL

		$tot = array( 'count' => 0, 'contracts' => 0.0, 'commission' => 0.0 );
		foreach ( $rows as &$r ) {
			$r['rep_name']   = self::user_name( $r['rep_id'] );
			$r['styles']     = $r['style_picks'] ? axh_style_names( json_decode( $r['style_picks'], true ) ) : '';
			unset( $r['style_picks'] );
			$value           = 'won' === $r['stage'] ? (float) $r['contract_value'] : (float) ( 'lost' === $r['stage'] ? $r['est_value'] : $r['win_request_value'] );
			$rate            = 'won' === $r['stage'] ? (float) $r['commission_rate'] : 0.0;
			$r['value']      = $value;
			$r['commission'] = round( $value * $rate / 100, 2 );
			$tot['count']++;
			$tot['contracts']  += 'won' === $r['stage'] ? $value : 0;
			$tot['commission'] += $r['commission'];
		}
		unset( $r );
		$tot['contracts']  = round( $tot['contracts'], 2 );
		$tot['commission'] = round( $tot['commission'], 2 );
		return array( 'deals' => $rows, 'totals' => $tot );
	}

	/**
	 * The team dashboard: leaderboard, stage totals, win rate and a weighted
	 * forecast. Manager only.
	 */
	public static function team_dashboard() {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return array();
		}
		$t       = self::table( 'leads' );
		$weights = self::stage_weights();
		$q_start = gmdate( 'Y-m-d 00:00:00', mktime( 0, 0, 0, (int) ( ceil( gmdate( 'n' ) / 3 ) * 3 - 2 ), 1, (int) gmdate( 'Y' ) ) );
		$m_start = gmdate( 'Y-m-01 00:00:00' );

		$by_stage = array();
		$forecast = 0.0;
		foreach ( $wpdb->get_results( "SELECT stage, COUNT(*) c, COALESCE(SUM(est_value),0) v FROM {$t} WHERE archived_at IS NULL GROUP BY stage", ARRAY_A ) as $r ) { // phpcs:ignore
			$by_stage[ $r['stage'] ] = array( 'count' => (int) $r['c'], 'value' => (float) $r['v'] );
			if ( isset( $weights[ $r['stage'] ] ) ) {
				$forecast += (float) $r['v'] * $weights[ $r['stage'] ];
			}
		}
		$won  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE stage = 'won'" ); // phpcs:ignore
		$lost = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE stage = 'lost'" ); // phpcs:ignore

		$board = array();
		$names = array();
		foreach ( AXH_Team::roster() as $r ) {
			$names[ $r['id'] ] = $r;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rep_id,
					SUM(stage = 'won') won_count,
					COALESCE(SUM(CASE WHEN stage = 'won' THEN contract_value ELSE 0 END),0) won_value,
					COALESCE(SUM(CASE WHEN stage = 'won' AND closed_at >= %s THEN contract_value ELSE 0 END),0) won_quarter,
					COALESCE(SUM(CASE WHEN stage = 'won' AND closed_at >= %s THEN contract_value ELSE 0 END),0) won_month,
					SUM(stage = 'lost') lost_count,
					SUM(archived_at IS NULL AND stage IN ('new','contacted','discovery','scoping','proposal')) open_count,
					COALESCE(SUM(CASE WHEN archived_at IS NULL AND stage IN ('new','contacted','discovery','scoping','proposal') THEN est_value ELSE 0 END),0) pipeline,
					COALESCE(SUM(CASE WHEN archived_at IS NULL AND stage = 'new' THEN est_value * %f WHEN archived_at IS NULL AND stage = 'contacted' THEN est_value * %f WHEN archived_at IS NULL AND stage = 'discovery' THEN est_value * %f WHEN archived_at IS NULL AND stage = 'scoping' THEN est_value * %f WHEN archived_at IS NULL AND stage = 'proposal' THEN est_value * %f ELSE 0 END),0) forecast
				 FROM {$t} GROUP BY rep_id",
				$q_start,
				$m_start,
				$weights['new'] ?? 0,
				$weights['contacted'] ?? 0,
				$weights['discovery'] ?? 0,
				$weights['scoping'] ?? 0,
				$weights['proposal'] ?? 0
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as $r ) {
			$closed  = (int) $r['won_count'] + (int) $r['lost_count'];
			$board[] = array(
				'rep_id'      => (int) $r['rep_id'],
				'name'        => self::user_name( $r['rep_id'] ),
				'active'      => isset( $names[ $r['rep_id'] ] ) ? (bool) $names[ $r['rep_id'] ]['active'] : user_can( (int) $r['rep_id'], 'axh_use_hub' ),
				'won_count'   => (int) $r['won_count'],
				'won_value'   => round( (float) $r['won_value'], 2 ),
				'won_quarter' => round( (float) $r['won_quarter'], 2 ),
				'won_month'   => round( (float) $r['won_month'], 2 ),
				'open_count'  => (int) $r['open_count'],
				'pipeline'    => round( (float) $r['pipeline'], 2 ),
				'forecast'    => round( (float) $r['forecast'], 2 ),
				'win_rate'    => $closed ? round( (int) $r['won_count'] / $closed * 100 ) : null,
			);
		}
		usort( $board, function ( $a, $b ) { return $b['won_value'] <=> $a['won_value'] ?: $b['pipeline'] <=> $a['pipeline']; } );

		$e = self::earnings( true );
		return array(
			'by_stage'    => $by_stage,
			'forecast'    => round( $forecast, 2 ),
			'weights'     => $weights,
			'win_rate'    => ( $won + $lost ) ? round( $won / ( $won + $lost ) * 100 ) : null,
			'won_quarter' => round( (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(contract_value),0) FROM {$t} WHERE stage = 'won' AND closed_at >= %s", $q_start ) ), 2 ),
			'won_month'   => round( (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(contract_value),0) FROM {$t} WHERE stage = 'won' AND closed_at >= %s", $m_start ) ), 2 ),
			'new_month'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $m_start ) ),
			'leaderboard' => $board,
			'commission'  => $e['totals'],
		);
	}

	/** One rep's recent activity across all their leads. */
	public static function rep_activity( $rep_id, $limit = 30 ) {
		global $wpdb;
		$a = self::table( 'activity' );
		$l = self::table( 'leads' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT a.id, a.lead_id, a.type, a.subject, a.occurred_at, l.company FROM {$a} a LEFT JOIN {$l} l ON l.id = a.lead_id WHERE a.rep_id = %d ORDER BY a.occurred_at DESC, a.id DESC LIMIT %d", (int) $rep_id, (int) $limit ),
			ARRAY_A
		);
	}

	/* ----------------------------------------------------------------- shares */

	/**
	 * A share is a deck plus the form its closing slide points at, reachable
	 * on a public link. Reads are scoped like everything else: a rep sees
	 * their own, a manager sees all of them.
	 */
	public static function get_shares() {
		global $wpdb;
		$t = self::table( 'shares' );
		$f = self::table( 'forms' );
		$l = self::table( 'leads' );
		$rows = $wpdb->get_results(
			"SELECT s.*, f.name AS form_name, f.slug AS form_slug, l.company AS lead_company
			 FROM {$t} s
			 LEFT JOIN {$f} f ON f.id = s.form_id
			 LEFT JOIN {$l} l ON l.id = s.lead_id
			 WHERE " . self::scope( 's.rep_id' ) . ' ORDER BY s.created_at DESC',
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'shape_share' ), $rows ? $rows : array() );
	}

	public static function get_share( $id ) {
		global $wpdb;
		$t   = self::table( 'shares' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND " . self::scope( 'rep_id' ), (int) $id ),
			ARRAY_A
		);
		return $row ? self::shape_share( $row ) : null;
	}

	/** Public lookup: no scoping, and inactive links stay dark. */
	public static function share_by_token( $token ) {
		global $wpdb;
		$t   = self::table( 'shares' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$t} WHERE token = %s AND active = 1", self::clean_token( $token ) ),
			ARRAY_A
		);
		return $row ? self::shape_share( $row ) : null;
	}

	protected static function shape_share( $row ) {
		$row['id']         = (int) $row['id'];
		$row['rep_id']     = (int) $row['rep_id'];
		$row['lead_id']    = $row['lead_id'] ? (int) $row['lead_id'] : null;
		$row['form_id']    = $row['form_id'] ? (int) $row['form_id'] : null;
		$row['active']     = (int) $row['active'];
		$row['views']      = (int) $row['views'];
		$row['finishes']   = (int) $row['finishes'];
		$row['cta_clicks'] = (int) $row['cta_clicks'];
		$row['url']        = home_url( '/s/' . $row['token'] . '/' );
		return $row;
	}

	protected static function clean_token( $token ) {
		return substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $token ) ), 0, 32 );
	}

	protected static function new_token() {
		global $wpdb;
		$t = self::table( 'shares' );
		do {
			$token = strtolower( wp_generate_password( 10, false, false ) );
			$taken = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE token = %s", $token ) );
		} while ( $taken );
		return $token;
	}

	public static function insert_share( $data ) {
		global $wpdb;
		$now = self::now();
		$row = array(
			'rep_id'     => get_current_user_id(),
			'lead_id'    => ! empty( $data['lead_id'] ) && self::get_lead( (int) $data['lead_id'] ) ? (int) $data['lead_id'] : null,
			'token'      => self::new_token(),
			'deck'       => sanitize_key( $data['deck'] ?? '' ),
			'form_id'    => ! empty( $data['form_id'] ) && self::get_form( (int) $data['form_id'] ) ? (int) $data['form_id'] : null,
			'title'      => self::clip( sanitize_text_field( $data['title'] ?? '' ), 191 ),
			'prospect'   => sanitize_text_field( $data['prospect'] ?? '' ),
			'cta_label'  => sanitize_text_field( $data['cta_label'] ?? '' ),
			'note'       => sanitize_textarea_field( $data['note'] ?? '' ),
			'active'     => 1,
			'created_at' => $now,
			'updated_at' => $now,
		);
		$wpdb->insert( self::table( 'shares' ), $row );
		return self::get_share( $wpdb->insert_id );
	}

	public static function update_share( $id, $data ) {
		global $wpdb;
		$share = self::get_share( $id );
		if ( ! $share ) {
			return null;
		}
		$row = array( 'updated_at' => self::now() );
		foreach ( array( 'title', 'prospect', 'cta_label' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$row[ $k ] = sanitize_text_field( $data[ $k ] );
			}
		}
		if ( isset( $data['note'] ) ) {
			$row['note'] = sanitize_textarea_field( $data['note'] );
		}
		if ( isset( $data['deck'] ) ) {
			$row['deck'] = sanitize_key( $data['deck'] );
		}
		if ( array_key_exists( 'form_id', $data ) ) {
			$row['form_id'] = $data['form_id'] && self::get_form( (int) $data['form_id'] ) ? (int) $data['form_id'] : null;
		}
		if ( array_key_exists( 'lead_id', $data ) ) {
			$row['lead_id'] = $data['lead_id'] && self::get_lead( (int) $data['lead_id'] ) ? (int) $data['lead_id'] : null;
		}
		if ( isset( $data['active'] ) ) {
			$row['active'] = $data['active'] ? 1 : 0;
		}
		$wpdb->update( self::table( 'shares' ), $row, array( 'id' => (int) $id ) );
		return self::get_share( $id );
	}

	public static function delete_share( $id ) {
		global $wpdb;
		if ( ! self::get_share( $id ) ) {
			return false;
		}
		$wpdb->delete( self::table( 'shares' ), array( 'id' => (int) $id ) );
		return true;
	}

	/**
	 * Just enough of a form for a public deck's closing slide to link to it.
	 * Unscoped on purpose — the share link is already public — and it returns
	 * no submissions, no field definitions and no owner details.
	 */
	public static function form_public( $id ) {
		global $wpdb;
		$t   = self::table( 'forms' );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, slug, name, headline, active FROM {$t} WHERE id = %d", (int) $id ),
			ARRAY_A
		);
		if ( ! $row || ! (int) $row['active'] ) {
			return null;
		}
		return array(
			'id'   => (int) $row['id'],
			'slug' => $row['slug'],
			'name' => $row['name'],
			'url'  => home_url( '/f/' . $row['slug'] . '/' ),
		);
	}

	/** Counters for the public viewer. No identity is recorded, only tallies. */
	public static function count_share_view( $id ) {
		global $wpdb;
		$t = self::table( 'shares' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET views = views + 1, last_viewed = %s WHERE id = %d", self::now(), (int) $id ) );
	}

	public static function count_share_event( $id, $event ) {
		global $wpdb;
		$col = 'finish' === $event ? 'finishes' : 'cta_clicks';
		$t   = self::table( 'shares' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET {$col} = {$col} + 1 WHERE id = %d", (int) $id ) );
	}


	protected static function clean_datetime( $value ) {
		$ts = strtotime( (string) $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
