<?php
/**
 * REST API for the portal app. Every route requires an authenticated hub user.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_Rest {

	const NS = 'axh/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_use() {
		return is_user_logged_in() && current_user_can( 'axh_use_hub' );
	}

	public static function can_manage() {
		return is_user_logged_in() && current_user_can( 'axh_manage_all' );
	}

	public static function routes() {
		$auth   = array( __CLASS__, 'can_use' );
		$manage = array( __CLASS__, 'can_manage' );

		register_rest_route( self::NS, '/bootstrap', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'bootstrap' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/leads', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_leads' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_lead' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_lead' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_lead' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_lead' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/register', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'register_lead' ),
			'permission_callback' => $auth,
		) );

		// Archive / restore. Reps archive anything that is not a draft; drafts
		// can still be deleted outright.
		register_rest_route( self::NS, '/leads/(?P<id>\d+)/archive', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'archive_lead' ),
			'permission_callback' => $auth,
		) );

		// A rep reports a signed deal; a manager confirms it.
		register_rest_route( self::NS, '/leads/(?P<id>\d+)/win-request', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'request_win' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/close', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'close_lead' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/assign', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'assign_lead' ),
			'permission_callback' => $manage,
		) );

		// Website style picks on a lead, and the client's own style link.
		register_rest_route( self::NS, '/leads/(?P<id>\d+)/styles', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save_styles' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/styles/link', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'style_link' ),
			'permission_callback' => $auth,
		) );

		// Public: a client choosing their style on their own link, or in the
		// discovery questionnaire. The token decides the lead.
		register_rest_route( self::NS, '/public/styles/(?P<token>[a-f0-9]{16,32})', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'public_save_styles' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/public/discovery/(?P<token>[a-f0-9]{16,32})/styles', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'public_save_styles' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/activity', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'add_activity' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/discovery', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_discovery' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_discovery' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/tasks', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_tasks' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_task' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/tasks/(?P<id>\d+)', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_task' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_task' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/sessions', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'start_session' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/sessions/(?P<id>\d+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'end_session' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/forms', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_forms' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_form' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/forms/(?P<id>\\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_form' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_form' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_form' ),
				'permission_callback' => $auth,
			),
		) );

		// Hub admins only (1.7.2): permanently delete stored form submissions,
		// one at a time or several at once. The leads they opened are kept.
		register_rest_route( self::NS, '/forms/submissions/(?P<id>\\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'delete_submission' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/forms/submissions/delete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'delete_submissions' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/shares', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_shares' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_share' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( self::NS, '/shares/(?P<id>\\d+)', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_share' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_share' ),
				'permission_callback' => $auth,
			),
		) );

		// Public: the shared deck reports that it was finished or its CTA clicked.
		// It only ever increments a counter on a link that is already public.
		register_rest_route( self::NS, '/public/share-event', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'public_share_event' ),
			'permission_callback' => '__return_true',
		) );

		// Public: a client saving their own discovery form. The token in the URL
		// is the credential and decides which lead is written — nothing in the
		// body can point the write somewhere else.
		register_rest_route( self::NS, '/public/discovery/(?P<token>[a-f0-9]{16,32})', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'public_save_discovery' ),
			'permission_callback' => '__return_true',
		) );

		// The rep's trim, and the link to hand the client.
		register_rest_route( self::NS, '/leads/(?P<id>\d+)/discovery/config', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save_discovery_config' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/discovery/share', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'share_discovery' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/earnings', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_earnings' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/leads/(?P<id>\d+)/payment', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'set_payment' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/manage/brief/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_brief' ),
			'permission_callback' => $manage,
		) );

		// Public: the live form posts here. Guarded by nonce, honeypot and throttle.
		register_rest_route( self::NS, '/public/submit', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'public_submit' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/manage/overview', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'manage_overview' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/manage/discovery', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'manage_discovery' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/manage/discovery/(?P<id>\d+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'review_discovery' ),
			'permission_callback' => $manage,
		) );

		// Team accounts, so a manager never has to go to wp-admin to put a new
		// rep to work. Manager-only, and every one of these refuses to act on
		// an account that is not a Hub rep.
		register_rest_route( self::NS, '/manage/team', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'create_rep' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/manage/team/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_rep' ),
				'permission_callback' => $manage,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_rep' ),
				'permission_callback' => $manage,
			),
		) );

		register_rest_route( self::NS, '/manage/team', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_team' ),
			'permission_callback' => $manage,
		) );

		// Deals closed, commission owed and paid, across the whole team.
		register_rest_route( self::NS, '/manage/deals', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'manage_deals' ),
			'permission_callback' => $manage,
		) );

		register_rest_route( self::NS, '/manage/payouts', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'manage_payouts' ),
				'permission_callback' => $manage,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'record_payout' ),
				'permission_callback' => $manage,
			),
		) );

		register_rest_route( self::NS, '/manage/payouts/(?P<id>\d+)/void', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'void_payout' ),
			'permission_callback' => $manage,
		) );

		// Notifications and the signed-in person's own settings.
		register_rest_route( self::NS, '/notifications', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_notifications' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/notifications/read', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'read_notifications' ),
			'permission_callback' => $auth,
		) );

		// Each person deletes their own notifications (1.7.2): one, or every read one.
		register_rest_route( self::NS, '/notifications/(?P<id>\\d+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'delete_notification' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/notifications/clear', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'clear_notifications' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::NS, '/me', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_me' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_me' ),
				'permission_callback' => $auth,
			),
		) );
	}

	/**
	 * Only ever a sales rep, never an administrator — otherwise this would be
	 * a way for a manager account to reset the site owner's password.
	 */
	protected static function rep_user( $id ) {
		$user = get_user_by( 'id', (int) $id );
		if ( ! $user || ! AXH_Team::is_rep_account( $user ) ) {
			return null;
		}
		return $user;
	}

	public static function create_rep( $req ) {
		$body  = (array) $req->get_json_params();
		$name  = sanitize_text_field( $body['name'] ?? '' );
		$email = sanitize_email( $body['email'] ?? '' );
		$pass  = (string) ( $body['password'] ?? '' );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'axh_bad_email', 'Enter a valid email address.', array( 'status' => 400 ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'axh_exists', 'That email already belongs to an account.', array( 'status' => 400 ) );
		}
		if ( $pass && strlen( $pass ) < 12 ) {
			return new WP_Error( 'axh_weak', 'Use at least 12 characters, or leave it blank to email them a link.', array( 'status' => 400 ) );
		}

		$login = sanitize_user( current( explode( '@', $email ) ), true );
		$base  = $login;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			$i++;
		}

		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass ? $pass : wp_generate_password( 20, true ),
				'display_name' => $name ? $name : $login,
				'first_name'   => $name,
				'role'         => AXH_ROLE,
			)
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'axh_failed', $id->get_error_message(), array( 'status' => 400 ) );
		}
		update_user_meta( $id, 'axh_was_rep', 1 );
		$user = get_userdata( $id );
		AXH_Team::save_profile( $user, array_intersect_key( $body, array_flip( array( 'phone', 'territory', 'industries', 'rate_override' ) ) ) );

		// Invite unless the manager said not to. The invitation always carries
		// a set-password link, so a typed password is optional either way.
		$out = array( 'ok' => true, 'id' => (int) $id, 'emailed' => false, 'invited' => false );
		if ( ! isset( $body['invite'] ) || ! empty( $body['invite'] ) ) {
			$r              = AXH_Team::send_invite( $user, 'invite' );
			$out['invited'] = true;
			$out['emailed'] = ! empty( $r['emailed'] );
			if ( ! $out['emailed'] && ! empty( $r['link'] ) ) {
				$out['link'] = $r['link']; // Mail failed: hand the link to the manager instead.
			}
		}
		$out['reps'] = AXH_DB::rep_rollup();
		return rest_ensure_response( $out );
	}

	public static function get_team( $req ) {
		return rest_ensure_response( array( 'reps' => AXH_DB::rep_rollup(), 'industries' => AXH_Install::industries() ) );
	}

	/** One rep: profile, account status, pipeline, activity and commission. */
	public static function get_rep( $req ) {
		$user = self::rep_user( $req['id'] );
		if ( ! $user ) {
			return new WP_Error( 'axh_not_rep', 'That is not a sales representative account.', array( 'status' => 404 ) );
		}
		$rep = null;
		foreach ( AXH_DB::rep_rollup() as $r ) {
			if ( (int) $r['id'] === (int) $user->ID ) {
				$rep = $r;
			}
		}
		$e = AXH_DB::earnings( false, $user->ID );
		return rest_ensure_response( array(
			'rep'        => $rep,
			'leads'      => AXH_DB::get_leads( array( 'rep_id' => $user->ID, 'archived' => 'all' ) ),
			'activity'   => AXH_DB::rep_activity( $user->ID, 30 ),
			'earnings'   => array( 'totals' => $e['totals'], 'deals' => $e['deals'] ),
			'payouts'    => AXH_DB::payouts( array( 'rep_id' => $user->ID ) ),
			'industries' => AXH_Install::industries(),
		) );
	}

	/**
	 * Set a password, mint a sign-in link, or take Hub access away.
	 */
	public static function update_rep( $req ) {
		$body = (array) $req->get_json_params();
		$user = self::rep_user( $req['id'] );
		if ( ! $user ) {
			return new WP_Error( 'axh_not_rep', 'That is not a sales representative account.', array( 'status' => 404 ) );
		}
		$do  = sanitize_key( $body['do'] ?? '' );
		$out = array( 'ok' => true );

		if ( in_array( $do, array( 'invite', 'signin' ), true ) ) {
			if ( ! AXH_Team::is_active( $user ) ) {
				return new WP_Error( 'axh_inactive', 'Reactivate this account first.', array( 'status' => 400 ) );
			}
			$mode = in_array( $body['mode'] ?? 'send', array( 'preview', 'test' ), true ) ? $body['mode'] : 'send';
			$r    = AXH_Team::send_invite( $user, $do, $mode );
			if ( empty( $r['ok'] ) && 'send' === $mode ) {
				return new WP_Error( 'axh_failed', $r['message'] ?? 'Could not send that.', array( 'status' => 400 ) );
			}
			$out = array_merge( $out, $r );
			if ( 'send' === $mode && ! empty( $r['emailed'] ) ) {
				unset( $out['link'] ); // Emailed: no need to show the secret link too.
			}
		} elseif ( 'edit' === $do ) {
			$r = AXH_Team::save_profile( $user, $body );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		} elseif ( 'deactivate' === $do || 'revoke' === $do ) {
			AXH_Team::deactivate( $user );
		} elseif ( 'reactivate' === $do ) {
			AXH_Team::reactivate( $user );
		} elseif ( 'password' === $do ) {
			$pass = (string) ( $body['password'] ?? '' );
			if ( strlen( $pass ) < 12 ) {
				return new WP_Error( 'axh_weak', 'Use at least 12 characters.', array( 'status' => 400 ) );
			}
			wp_set_password( $pass, $user->ID );
		} elseif ( 'link' === $do ) {
			// Copy-able link, for texting it yourself. Treated as an invitation
			// (a week to use it) until the rep first signs in.
			if ( ! get_user_meta( $user->ID, 'axh_activated_at', true ) ) {
				update_user_meta( $user->ID, 'axh_invite_open', 1 );
			}
			$out['link'] = AXH_Team::password_link( $user );
			if ( ! $out['link'] ) {
				return new WP_Error( 'axh_failed', 'Could not create the link.', array( 'status' => 400 ) );
			}
			$out['days'] = get_user_meta( $user->ID, 'axh_invite_open', true ) ? AXH_Team::invite_days() : 1;
		} else {
			return new WP_Error( 'axh_bad_action', 'Unknown action.', array( 'status' => 400 ) );
		}

		$out['reps'] = AXH_DB::rep_rollup();
		return rest_ensure_response( $out );
	}

	/* ------------------------------------------------------------- responses */

	public static function bootstrap( $req ) {
		$user = wp_get_current_user();

		return rest_ensure_response( array(
			'user' => array(
				'id'      => $user->ID,
				'name'    => $user->display_name,
				'email'   => $user->user_email,
				'initials'=> self::initials( $user->display_name ),
				'manager' => axh_is_manager(),
			),
			'stages'     => AXH_Install::stages(),
			'industries' => AXH_Install::industries(),
			'modules'    => axh_module_library(),
			'schema'     => axh_discovery_schema(),
			'decks'      => AXH_Portal::decks(),
			'slideDecks' => AXH_Portal::slide_decks(),
			'demoSites'  => AXH_Portal::demo_sites(),
			'deckBase'   => home_url( '/deck/' ),
			'summary'    => AXH_DB::summary(),
			'logoutUrl'  => wp_logout_url( axh_portal_url() ),
			'siteName'   => get_bloginfo( 'name' ),
			// How commission is paid: the agreement link and the payout note
			// are set by AdaptExec under AdaptExec Hub → Settings.
			'agreementUrl' => esc_url_raw( (string) get_option( 'axh_agreement_url', '' ) ),
			'payoutNote'   => sanitize_textarea_field( (string) get_option( 'axh_payout_note', '' ) ),
			'payoutDays'   => axh_is_manager() ? AXH_Install::payout_days() : null,
			'assignees'    => axh_is_manager() ? self::assignee_list() : array(),
			'unread'       => AXH_Notify::unread(),
			'lostPasswordUrl' => wp_lostpassword_url( axh_portal_url() ),
			'contact'      => AXH_Mail::contact(),
			'rateOverride' => (float) get_user_meta( $user->ID, 'axh_rate_override', true ),
			'onboarding'   => AXH_Onboard::boot( $user ),
			'p1'           => AXH_P1::boot(),
		) );
	}

	public static function get_leads( $req ) {
		return rest_ensure_response( array(
			'leads'   => AXH_DB::get_leads( array(
				'stage'      => $req->get_param( 'stage' ),
				'reg_status' => $req->get_param( 'reg_status' ),
				'rep_id'     => $req->get_param( 'rep_id' ),
				'search'     => $req->get_param( 'search' ),
				'archived'   => $req->get_param( 'archived' ),
			) ),
			'summary' => AXH_DB::summary(),
		) );
	}

	public static function create_lead( $req ) {
		$data = $req->get_json_params();
		if ( empty( $data['company'] ) ) {
			return new WP_Error( 'axh_no_company', 'A company name is required.', array( 'status' => 400 ) );
		}
		$id = AXH_DB::insert_lead( $data );
		if ( ! $id ) {
			return new WP_Error( 'axh_insert_failed', 'Could not save that lead. Please try again.', array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'lead' => AXH_DB::get_lead( $id ) ) );
	}

	public static function get_lead( $req ) {
		$id   = (int) $req['id'];
		$lead = AXH_DB::get_lead( $id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array(
			'lead'      => $lead,
			'activity'  => AXH_DB::get_activity( $id ),
			'tasks'     => AXH_DB::get_tasks( array( 'lead_id' => $id ) ),
			'discovery' => AXH_DB::get_discovery( $id ),
			'rep'       => self::rep_name( $lead['rep_id'] ),
			'conflicts' => AXH_DB::conflicts( $lead ),
			'style'     => AXH_DB::style_value( $lead ),
			'style_url' => ! empty( $lead['style_token'] ) ? axh_style_url( $lead['style_token'] ) : '',
			'tool_results' => AXH_DB::tool_results( $lead ),
			'submission'   => AXH_DB::lead_submission( $id ),
		)
			// 1.7.1: the questionnaire screen gets the lead's own trade packet, so
			// the rep fills in the same questions the client link and readiness use.
			+ ( $req->get_param( 'schema' ) ? array( 'schema' => axh_discovery_schema( $lead['industry'] ), 'packet' => (bool) ( axh_discovery_packets()[ $lead['industry'] ] ?? false ) ) : array() ) );
	}

	public static function save_styles( $req ) {
		$b = (array) $req->get_json_params();
		$r = AXH_DB::save_style_picks( (int) $req['id'], $b['picks'] ?? array() );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$lead = AXH_DB::get_lead( (int) $req['id'] );
		return rest_ensure_response( array( 'style' => AXH_DB::style_value( $lead ), 'lead' => $lead ) );
	}

	public static function style_link( $req ) {
		$token = AXH_DB::style_token( (int) $req['id'] );
		if ( ! $token ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'token' => $token, 'url' => axh_style_url( $token ) ) );
	}

	/** A client saving their picks: on their style link, or in the questionnaire. */
	public static function public_save_styles( $req ) {
		$token     = (string) $req['token'];
		$discovery = false !== strpos( (string) $req->get_route(), '/public/discovery/' );
		$lead      = $discovery ? AXH_DB::lead_by_discovery_token( $token ) : AXH_DB::lead_by_style_token( $token );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'That link is no longer active.', array( 'status' => 404 ) );
		}
		// A generous per-link limit: picks are saved with a button, not per click.
		$key = 'axh_styl_' . md5( $token );
		$n   = (int) get_transient( $key );
		if ( $n >= 20 ) {
			return new WP_Error( 'axh_throttled', 'Too many saves. Try again in a few minutes.', array( 'status' => 429 ) );
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

		$b = (array) $req->get_json_params();
		$v = AXH_DB::save_style_public( $lead, $b['picks'] ?? array(), $discovery ? 'discovery' : 'client' );
		if ( $v && $lead['rep_id'] ) {
			$company = $lead['company'] ? $lead['company'] : 'A client';
			AXH_Notify::add(
				(int) $lead['rep_id'],
				'style_picks',
				$company . ' picked their website style',
				$company . ' shortlisted: ' . ( axh_style_names( $v ) ? axh_style_names( $v ) : 'no styles yet' ) . '.' . ( $v['notes'] ? "\n\nTheir notes: " . $v['notes'] : '' ),
				'#/lead/' . (int) $lead['id'],
				(int) $lead['id'],
				array( 'rows' => array_filter( array(
					'Shortlist'   => axh_style_names( array( 'shortlist' => $v['shortlist'] ) ),
					'Top matches' => axh_style_names( array( 'top' => $v['top'] ), true ),
					'Where'       => $discovery ? 'Discovery questionnaire' : 'Their style link',
				) ) )
			);
		}
		return rest_ensure_response( array( 'ok' => true, 'saved' => (bool) $v, 'names' => $v ? axh_style_names( $v ) : '' ) );
	}

	public static function update_lead( $req ) {
		$id     = (int) $req['id'];
		$before = AXH_DB::get_lead( $id );
		$result = AXH_DB::update_lead( $id, (array) $req->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'axh_update_failed', 'Could not update that lead.', array( 'status' => 400 ) );
		}
		$lead = AXH_DB::get_lead( $id );
		if ( $before && $lead && $before['reg_status'] !== $lead['reg_status'] && in_array( $lead['reg_status'], array( 'registered', 'house', 'declined' ), true ) ) {
			self::notify_registration_decision( $lead );
		}
		return rest_ensure_response( array( 'lead' => $lead ) );
	}

	public static function delete_lead( $req ) {
		$result = AXH_DB::delete_lead( (int) $req['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'deleted' => (bool) $result ) );
	}

	public static function archive_lead( $req ) {
		$body   = (array) $req->get_json_params();
		$result = AXH_DB::archive_lead( (int) $req['id'], ! isset( $body['archived'] ) || ! empty( $body['archived'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'lead' => $result ) );
	}

	/** Rep: "the client signed" — goes to AdaptExec to confirm. */
	public static function request_win( $req ) {
		$body   = (array) $req->get_json_params();
		$result = AXH_DB::request_win( (int) $req['id'], $body['contract_value'] ?? 0, (string) ( $body['note'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		AXH_Notify::managers_add(
			'win_request',
			'Closed Won to confirm: ' . $result['company'],
			sprintf(
				'%s reports that %s has signed, for $%s.%s',
				wp_get_current_user()->display_name,
				$result['company'],
				number_format( (float) $result['win_request_value'], 2 ),
				$result['win_request_note'] ? "\n\nNote: " . $result['win_request_note'] : ''
			) . "\n\nApprove it, or correct the value, under Approvals.",
			'#/manage/registrations',
			(int) $result['id']
		);
		return rest_ensure_response( array( 'lead' => $result ) );
	}

	/** Manager: approve Closed Won at a value, correct the value, or decline the request. */
	public static function close_lead( $req ) {
		$body = (array) $req->get_json_params();
		$id   = (int) $req['id'];
		if ( ! empty( $body['decline'] ) ) {
			$result = AXH_DB::decline_win( $id, (string) ( $body['note'] ?? '' ) );
			if ( ! is_wp_error( $result ) ) {
				self::notify_rep(
					$result,
					'win_decision',
					'Not marked won yet: ' . $result['company'],
					"AdaptExec has not marked {$result['company']} Closed Won yet." . ( ! empty( $body['note'] ) ? "\n\nNote from AdaptExec: " . sanitize_textarea_field( $body['note'] ) : '' ),
					'#/lead/' . $id
				);
			}
			return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'lead' => $result ) );
		}
		$before = AXH_DB::get_lead( $id );
		$result = AXH_DB::close_won( $id, $body['contract_value'] ?? 0 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $before && 'won' !== $before['stage'] ) {
			$com = round( (float) $result['contract_value'] * (float) $result['commission_rate'] / 100, 2 );
			self::notify_rep(
				$result,
				'win_decision',
				'Closed Won: ' . $result['company'],
				sprintf(
					"Congratulations — %s is confirmed Closed Won.\n\nCommission: $%s, earned in two halves as the client's deposit and final payment fully clear, and paid by check after the funds clear. Track it under Earnings.",
					$result['company'],
					number_format( $com, 2 )
				),
				'#/earnings',
				array(
					'rows' => array(
						'Contract value'  => '$' . number_format( (float) $result['contract_value'], 2 ),
						'Commission rate' => rtrim( rtrim( number_format( (float) $result['commission_rate'], 2 ), '0' ), '.' ) . '%',
						'Commission'      => '$' . number_format( $com, 2 ),
					),
				)
			);
		}
		return rest_ensure_response( array( 'lead' => $result ) );
	}

	/** Manager: give a lead to another rep, and tell them. */
	public static function assign_lead( $req ) {
		$body   = (array) $req->get_json_params();
		$before = AXH_DB::get_lead( (int) $req['id'] );
		$result = AXH_DB::assign_lead( (int) $req['id'], $body['rep_id'] ?? 0 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $before && (int) $before['rep_id'] !== (int) $result['rep_id'] ) {
			self::notify_rep(
				$result,
				'lead_assigned',
				'New lead assigned to you: ' . $result['company'],
				wp_get_current_user()->display_name . ' has assigned you a lead. Register it before your first real conversation.',
				'#/lead/' . (int) $result['id'],
				array(
					'rows' => array(
						'Company' => $result['company'],
						'Contact' => $result['contact_name'] ? $result['contact_name'] : '—',
						'Email'   => $result['email'] ? $result['email'] : '—',
						'Phone'   => $result['phone'] ? $result['phone'] : '—',
						'Source'  => $result['source'] ? $result['source'] : '—',
					),
				)
			);
		}
		return rest_ensure_response( array( 'lead' => $result ) );
	}

	public static function register_lead( $req ) {
		$id     = (int) $req['id'];
		$result = AXH_DB::register_lead( $id );
		if ( ! $result ) {
			return new WP_Error( 'axh_register_failed', 'That lead has already been submitted.', array( 'status' => 400 ) );
		}
		$conflicts = is_array( $result ) ? $result['conflicts'] : array();
		$reg_lead = AXH_DB::get_lead( $id );
		AXH_Notify::managers_add(
			'registration_request',
			'Registration to decide: ' . $reg_lead['company'],
			sprintf(
				'%s submitted %s%s for registration. Approve it, decline it or flag it as a house account under Approvals.',
				wp_get_current_user()->display_name,
				$reg_lead['company'],
				$reg_lead['contact_name'] ? ' (' . $reg_lead['contact_name'] . ')' : ''
			) . ( $conflicts ? sprintf(
				"\n\nHeads up: this looks like a conflict with %s, already registered by %s (%s).",
				$conflicts[0]['company'],
				$conflicts[0]['rep'],
				$conflicts[0]['why']
			) : '' ),
			'#/manage/registrations',
			$id
		);
		return rest_ensure_response( array( 'lead' => AXH_DB::get_lead( $id ), 'conflicts' => $conflicts ) );
	}

	public static function add_activity( $req ) {
		$id   = (int) $req['id'];
		$data = (array) $req->get_json_params();
		$data['lead_id'] = $id;
		if ( ! AXH_DB::add_activity( $data ) ) {
			return new WP_Error( 'axh_activity_failed', 'Could not log that.', array( 'status' => 400 ) );
		}
		// AdaptExec writing on a rep's lead: tell the rep.
		$lead = AXH_DB::get_lead( $id );
		if ( $lead && axh_is_manager() && (int) $lead['rep_id'] !== get_current_user_id() ) {
			$what = trim( ( $data['subject'] ?? '' ) . ( ! empty( $data['body'] ) ? "\n\n" . $data['body'] : '' ) );
			AXH_Notify::add(
				$lead['rep_id'],
				'note_on_lead',
				wp_get_current_user()->display_name . ' added a note on ' . $lead['company'],
				mb_substr( sanitize_textarea_field( $what ), 0, 1500 ),
				'#/lead/' . $id,
				$id
			);
		}
		return rest_ensure_response( array( 'activity' => AXH_DB::get_activity( $id ) ) );
	}

	public static function get_discovery( $req ) {
		return rest_ensure_response( array( 'discovery' => AXH_DB::get_discovery( (int) $req['id'] ) ) );
	}

	/**
	 * The sections and questions this client will actually be asked.
	 */
	public static function save_discovery_config( $req ) {
		$body   = (array) $req->get_json_params();
		$result = AXH_DB::save_discovery_config( (int) $req['id'], (array) ( $body['config'] ?? array() ) );
		if ( ! $result ) {
			return new WP_Error( 'axh_discovery_failed', 'Could not save the form setup.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'discovery' => $result ) );
	}

	/**
	 * Mint (or return) the client's link for this lead.
	 */
	public static function share_discovery( $req ) {
		$id = (int) $req['id'];
		if ( ! AXH_DB::get_lead( $id ) ) {
			return new WP_Error( 'axh_no_lead', 'Lead not found.', array( 'status' => 404 ) );
		}
		$token = AXH_DB::discovery_token( $id );
		if ( ! $token ) {
			return new WP_Error( 'axh_discovery_failed', 'Could not create the link.', array( 'status' => 400 ) );
		}
		return rest_ensure_response(
			array(
				'token'     => $token,
				'share_url' => axh_discovery_url( $token ),
				'discovery' => AXH_DB::get_discovery( $id ),
			)
		);
	}

	/**
	 * A client saving their own answers. Public, and deliberately narrow: the
	 * token decides the lead, and only the answers can be written.
	 */
	public static function public_save_discovery( $req ) {
		$body   = (array) $req->get_json_params();
		$result = AXH_DB::save_discovery_by_token(
			(string) $req['token'],
			(array) ( $body['payload'] ?? array() ),
			! empty( $body['submit'] )
		);
		if ( ! $result ) {
			return new WP_Error( 'axh_not_found', 'That link is no longer active.', array( 'status' => 404 ) );
		}
		if ( ! empty( $body['submit'] ) && 'client_done' === $result['status'] ) {
			self::notify_rep_of_discovery( $result );
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'status'   => $result['status'],
				'progress' => (int) $result['progress'],
			)
		);
	}

	/**
	 * Tell the rep their client finished it, rather than making them check.
	 */
	protected static function notify_rep_of_discovery( $disc ) {
		$rep = ! empty( $disc['lead']['rep_id'] ) ? get_user_by( 'id', (int) $disc['lead']['rep_id'] ) : null;
		if ( ! $rep || ! $rep->user_email ) {
			return;
		}
		$company = $disc['lead']['company'] ? $disc['lead']['company'] : 'A client';
		AXH_Notify::add(
			$rep->ID,
			'discovery_completed',
			$company . ' completed their discovery form',
			$company . ' has filled in the discovery form you sent. Review it, fill any gaps from your meetings, then press Request a quote so Adam can write the proposal.',
			'#/discovery/' . (int) $disc['lead_id'],
			(int) $disc['lead_id']
		);
	}

	public static function save_discovery( $req ) {
		$id     = (int) $req['id'];
		$body   = (array) $req->get_json_params();
		$submit = ! empty( $body['submit'] );
		$result = AXH_DB::save_discovery(
			$id,
			$body['payload'] ?? array(),
			$body['modules'] ?? array(),
			$submit,
			(string) ( $body['note'] ?? '' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'axh_discovery_failed', 'Could not save the questionnaire.', array( 'status' => 400 ) );
		}
		if ( $submit ) {
			self::notify_quote_request( $id, $result );
		}
		return rest_ensure_response( array( 'discovery' => $result, 'lead' => AXH_DB::get_lead( $id ) ) );
	}

	/**
	 * The hand-off (1.7.1): Adam hears about it in the Hub and by email at the
	 * notification address, with the facts he prices from and a link to the
	 * printable brief.
	 */
	protected static function notify_quote_request( $id, $disc ) {
		$lead = AXH_DB::get_lead( $id );
		$p    = (array) $disc['payload'];
		$r    = (array) $disc['readiness'];
		$pick = function ( $sec, $key ) use ( $p ) {
			$v = $p[ $sec ][ $key ] ?? '';
			$v = is_array( $v ) ? implode( ', ', array_filter( array_map( function ( $x ) { return is_array( $x ) ? ( $x['name'] ?? '' ) : html_entity_decode( (string) $x ); }, $v ) ) ) : (string) $v;
			return '' !== trim( $v ) ? mb_substr( $v, 0, 300 ) : '—';
		};
		$onsite = $pick( 'onsite', 'interest' );
		if ( in_array( $onsite, array( 'Yes', 'Maybe' ), true ) ) {
			$onsite .= ' · ' . $pick( 'onsite', 'days' ) . ' · ' . $pick( 'onsite', 'location' );
		}
		$rows = array(
			'Company'          => $lead['company'] . ( $lead['city'] ? ' · ' . $lead['city'] . ( $lead['state'] ? ', ' . $lead['state'] : '' ) : '' ),
			'Rep'              => wp_get_current_user()->display_name,
			'Quote readiness'  => (int) $r['pct'] . '% of key questions (' . (int) $r['filled'] . ' of ' . (int) $r['total'] . ')',
			'Questionnaire'    => (int) $disc['progress'] . '% complete',
			'Looking for'      => $pick( 'goals', 'need' ),
			'Budget range'     => $pick( 'decision', 'budget_range' ),
			'Timeline'         => $pick( 'decision', 'go_live' ),
			'On-site study'    => $onsite,
		);
		if ( ! empty( $disc['quote_note'] ) ) {
			$rows['Rep note'] = mb_substr( (string) $disc['quote_note'], 0, 500 );
		}
		AXH_Notify::managers_add(
			'discovery_submitted',
			'Quote requested: ' . $lead['company'],
			sprintf( "%s finished discovery with %s and is asking you for a quote. The brief has everything they gathered, ready to print; the billing customer is set up for the proposal.\n\nNo discovery call is booked. If you need one, ask the rep.", wp_get_current_user()->display_name, $lead['company'] ),
			'#/brief/' . $id,
			$id,
			array( 'rows' => $rows )
		);
	}

	public static function get_tasks( $req ) {
		return rest_ensure_response( array( 'tasks' => AXH_DB::get_tasks( array( 'open' => $req->get_param( 'open' ) ) ) ) );
	}

	public static function add_task( $req ) {
		$data = (array) $req->get_json_params();
		if ( empty( $data['title'] ) ) {
			return new WP_Error( 'axh_no_title', 'Give the task a name.', array( 'status' => 400 ) );
		}
		if ( ! AXH_DB::add_task( $data ) ) {
			return new WP_Error( 'axh_task_failed', 'Could not add that follow-up.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'tasks' => AXH_DB::get_tasks( array( 'lead_id' => $data['lead_id'] ?? 0 ) ) ) );
	}

	public static function update_task( $req ) {
		$data = (array) $req->get_json_params();
		if ( ! AXH_DB::toggle_task( (int) $req['id'], ! empty( $data['done'] ) ) ) {
			return new WP_Error( 'axh_not_found', 'Follow-up not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function delete_task( $req ) {
		return rest_ensure_response( array( 'deleted' => AXH_DB::delete_task( (int) $req['id'] ) ) );
	}

	public static function start_session( $req ) {
		$data = (array) $req->get_json_params();
		$id   = AXH_DB::start_session( $data['deck'] ?? '', $data['lead_id'] ?? null );
		return rest_ensure_response( array( 'id' => $id ) );
	}

	public static function end_session( $req ) {
		$data = (array) $req->get_json_params();
		if ( ! AXH_DB::end_session( (int) $req['id'], $data['furthest'] ?? 0, $data['seconds'] ?? 0, $data['slides'] ?? 0 ) ) {
			return new WP_Error( 'axh_not_found', 'Session not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function get_forms( $req ) {
		return rest_ensure_response( array(
			'forms'       => AXH_DB::get_forms(),
			'catalog'     => AXH_Install::form_fields(),
			'defaults'    => AXH_Install::default_form_fields(),
			'submissions' => AXH_DB::get_submissions( 0, 40 ),
		) );
	}

	public static function create_form( $req ) {
		$data = (array) $req->get_json_params();
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'axh_no_name', 'Give the form a name.', array( 'status' => 400 ) );
		}
		$id = AXH_DB::insert_form( $data );
		return rest_ensure_response( array( 'form' => AXH_DB::get_form( $id ) ) );
	}

	public static function get_form( $req ) {
		$form = AXH_DB::get_form( (int) $req['id'] );
		if ( ! $form ) {
			return new WP_Error( 'axh_not_found', 'Form not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array(
			'form'        => $form,
			'submissions' => AXH_DB::get_submissions( (int) $req['id'] ),
		) );
	}

	public static function update_form( $req ) {
		$id = (int) $req['id'];
		if ( ! AXH_DB::update_form( $id, (array) $req->get_json_params() ) ) {
			return new WP_Error( 'axh_update_failed', 'Could not save that form.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'form' => AXH_DB::get_form( $id ) ) );
	}

	public static function delete_form( $req ) {
		return rest_ensure_response( array( 'deleted' => AXH_DB::delete_form( (int) $req['id'] ) ) );
	}

	public static function delete_submission( $req ) {
		$r = AXH_DB::delete_submission( (int) $req['id'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'Submission not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function delete_submissions( $req ) {
		$b   = (array) $req->get_json_params();
		$ids = array_values( array_unique( array_filter( array_map( 'intval', array_slice( (array) ( $b['ids'] ?? array() ), 0, 200 ) ) ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'axh_no_ids', 'Pick at least one submission.', array( 'status' => 400 ) );
		}
		$done = array();
		foreach ( $ids as $id ) {
			$r = AXH_DB::delete_submission( $id );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( $r ) {
				$done[] = $id;
			}
		}
		return rest_ensure_response( array( 'deleted' => $done ) );
	}

	/**
	 * Unauthenticated endpoint. Order matters: cheap rejections first.
	 */
	public static function public_submit( $req ) {
		$body = (array) $req->get_json_params();

		if ( ! empty( $body['website_url'] ) ) {                       // honeypot
			return rest_ensure_response( array( 'ok' => true ) );      // silent success for bots
		}
		// The signed render stamp is the proof this request came from a form we
		// served. It is not tied to a login, so it works the same for anonymous
		// visitors and for signed-in staff (whose WP nonce is minted for their
		// user but verified here as user 0, since page scripts send no X-WP-Nonce).
		$t       = (string) ( $body['t'] ?? '' );
		$started = self::check_stamp( $t, (string) ( $body['slug'] ?? '' ) );
		if ( ! $started && ctype_digit( $t ) && self::public_nonce_ok( (string) ( $body['nonce'] ?? '' ) ) ) {
			// A page rendered before signed stamps existed: plain timestamp plus a valid nonce.
			$started = self::stamp_age_ok( (int) $t ) ? (int) $t : 0;
		}
		if ( ! $started ) {
			return new WP_Error( 'axh_bad_nonce', 'This form expired. Please refresh and try again.', array( 'status' => 403 ) );
		}
		$elapsed = time() - $started;
		if ( $elapsed < 3 ) {                                          // filled impossibly fast
			return new WP_Error( 'axh_too_fast', 'Please take a moment and try again.', array( 'status' => 429 ) );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( AXH_DB::throttled( $ip ) ) {
			return new WP_Error( 'axh_throttled', 'Too many submissions. Try again shortly.', array( 'status' => 429 ) );
		}

		$form = AXH_DB::form_by_slug( $body['slug'] ?? '' );
		if ( ! $form ) {
			return new WP_Error( 'axh_no_form', 'That form is no longer available.', array( 'status' => 404 ) );
		}

		// 1.11.0: a form whose owner has left sends its leads to AdaptExec.
		$form['rep_id'] = AXH_P1::active_owner( (int) $form['rep_id'] );

		$answers = (array) ( $body['answers'] ?? array() );
		if ( empty( $answers['email'] ) || ! is_email( $answers['email'] ) ) {
			return new WP_Error( 'axh_bad_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}

		$lead_id = AXH_DB::record_submission(
			$form,
			$answers,
			array(
				'ip'       => $ip,
				'referrer' => $body['referrer'] ?? '',
				'src'      => $body['src'] ?? '',
			)
		);

		self::notify_form( $form, $answers, $lead_id );
		AXH_P1::autoreply( $form, $answers, $lead_id );

		$extra = AXH_P1::public_config( $form, get_userdata( (int) $form['rep_id'] ) );
		return rest_ensure_response( array(
			'ok'           => true,
			'redirect'     => $form['redirect_url'],
			'headline'     => $form['thanks_headline'] ? $form['thanks_headline'] : 'Thank you — we have got it.',
			'body'         => $form['thanks_body'] ? $form['thanks_body'] : 'Someone will be in touch shortly.',
			'booking'      => $extra['booking'],
			'bookingLabel' => $extra['bookingLabel'],
		) );
	}

	/** Alert the owning rep (and the notification address) about a new enquiry. */
	protected static function notify_form( $form, $answers, $lead_id ) {
		$name  = sanitize_text_field( $answers['name'] ?? '' );
		$name  = $name ? $name : 'Someone';
		$title = ( AXH_P1::$repeat ? 'Returning enquiry: ' : 'New enquiry: ' ) . $name . ( ! empty( $answers['company'] ) ? ' (' . sanitize_text_field( $answers['company'] ) . ')' : '' );
		$body  = AXH_P1::$repeat
			? $name . ' sent "' . $form['name'] . '" again. It is on their existing lead, so nothing was duplicated. Reply quickly — they are actively looking.'
			: $name . ' just submitted "' . $form['name'] . '". Reply quickly — first to respond usually wins.';
		$picked = ! empty( $answers['style_picks'] ) ? axh_style_clean( $answers['style_picks'], 'form' ) : null;
		$mail  = array(
			'rows' => array(
				'Website style' => $picked ? axh_style_names( $picked ) : '',
				'Name'    => $name,
				'Email'   => sanitize_email( $answers['email'] ?? '' ),
				'Phone'   => sanitize_text_field( $answers['phone'] ?? '' ) ? sanitize_text_field( $answers['phone'] ) : '—',
				'Company' => sanitize_text_field( $answers['company'] ?? '' ) ? sanitize_text_field( $answers['company'] ) : '—',
				'Form'    => $form['name'],
			),
		);
		// Every other answer, in full. Short ones as rows; long free text (the
		// headache box, which our tools also use for their results summary)
		// as its own section so line breaks and bullets survive.
		$shown    = array( 'name', 'email', 'phone', 'company', 'style_picks', 'style', 'tool_results', 'sms_consent', 'consent_shown', '_labels' );
		$labels   = array();
		$sections = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			foreach ( $step['fields'] as $fl ) {
				$labels[ $fl['key'] ] = $fl['label'];
			}
		}
		foreach ( (array) $form['custom'] as $i => $c ) {
			if ( ! empty( $c['label'] ) ) {
				$labels[ 'custom_' . $i ] = $c['label'];
			}
		}
		foreach ( $answers as $k => $v ) {
			$k = sanitize_key( $k );
			if ( in_array( $k, $shown, true ) || ! isset( $labels[ $k ] ) ) {
				continue;
			}
			$v = is_array( $v ) ? implode( ', ', array_map( 'sanitize_text_field', array_filter( $v, 'is_scalar' ) ) ) : sanitize_textarea_field( mb_substr( (string) $v, 0, axh_answer_limit( $k ) ) );
			if ( '' === trim( $v ) ) {
				continue;
			}
			if ( mb_strlen( $v ) > 160 || false !== strpos( $v, "\n" ) ) {
				$sections[] = array( 'title' => $labels[ $k ], 'paras' => array_values( array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", $v ) ) ) ) );
			} else {
				$mail['rows'][ $labels[ $k ] ] = $v;
			}
		}
		$tool = axh_tool_results_clean( $answers['tool_results'] ?? null );
		if ( $tool ) {
			$sections[] = array( 'title' => 'Tool results', 'list' => array_slice( axh_tool_results_lines( $tool ), 0, 200 ) );
		}
		if ( $sections ) {
			$mail['sections'] = $sections;
		}
		if ( ! empty( $answers['phone'] ) && ! empty( $answers['consent_shown'] ) ) {
			$mail['rows']['Call/text consent'] = ! empty( $answers['sms_consent'] ) ? 'Yes — ticked the consent box' : 'Not given — email them first';
		}
		$mail['rows'] = array_filter( $mail['rows'], 'strlen' );
		if ( $picked ) {
			$body .= ' They picked a website style: ' . axh_style_names( $picked ) . '.';
		}
		$owner = (int) $form['rep_id'];
		$skip  = array();
		if ( $owner && ! axh_is_manager( $owner ) ) {
			AXH_Notify::add( $owner, 'form_submission', $title, $body, '#/lead/' . $lead_id, $lead_id, $mail );
		} elseif ( $owner ) {
			// A manager's own form: they hear about it through the manager path.
			$skip = array();
		}
		AXH_Notify::managers_add( 'form_submission', $title, $body, '#/lead/' . $lead_id, $lead_id, $mail, $skip );
	}

	public static function manage_overview( $req ) {
		// 1.11.0: each waiting registration carries any clash, so it can be
		// decided from the list without opening every lead.
		$regs = AXH_DB::get_leads( array( 'reg_status' => 'pending' ) );
		foreach ( $regs as &$r ) {
			$r['conflicts'] = AXH_DB::conflicts( $r );
		}
		unset( $r );
		return rest_ensure_response( array(
			'summary'       => AXH_DB::summary(),
			'reps'          => AXH_DB::rep_rollup(),
			'registrations' => $regs,
			'win_requests'  => AXH_DB::get_leads( array( 'win_requested' => 1 ) ),
			'dashboard'     => AXH_DB::team_dashboard(),
		) );
	}

	public static function manage_deals( $req ) {
		return rest_ensure_response( AXH_DB::deals( array(
			'status' => $req->get_param( 'status' ),
			'rep_id' => $req->get_param( 'rep_id' ),
			'from'   => $req->get_param( 'from' ),
			'to'     => $req->get_param( 'to' ),
			'search' => $req->get_param( 'search' ),
		) ) );
	}

	/** What is owed to each rep, every unpaid half with its due date, and the payment history. */
	public static function manage_payouts( $req ) {
		$e     = AXH_DB::earnings();
		$owed  = array();
		$legs  = array();
		foreach ( $e['deals'] as $d ) {
			foreach ( $d['legs'] as $leg ) {
				$rid = (int) $d['rep_id'];
				if ( ! isset( $owed[ $rid ] ) ) {
					$owed[ $rid ] = array( 'rep_id' => $rid, 'name' => $d['rep_name'], 'due' => 0.0, 'overdue' => 0.0, 'upcoming' => 0.0, 'paid' => 0.0, 'overpaid' => 0.0, 'next_due' => null );
				}
				if ( 'cancelled' === $leg['state'] ) {
					continue; // Nothing left to pay on this half (deal reversed or already covered).
				}
				$bucket = 'paid' === $leg['state'] ? 'paid' : ( 'waiting' === $leg['state'] ? 'upcoming' : $leg['state'] );
				$owed[ $rid ][ $bucket ] += $leg['amount'];
				if ( in_array( $leg['state'], array( 'due', 'overdue' ), true ) && ( ! $owed[ $rid ]['next_due'] || $leg['due_at'] < $owed[ $rid ]['next_due'] ) ) {
					$owed[ $rid ]['next_due'] = $leg['due_at'];
				}
				$legs[] = array_merge( $leg, array(
					'lead_id'   => $d['lead_id'],
					'company'   => $d['company'],
					'rep_id'    => $rid,
					'rep_name'  => $d['rep_name'],
					'value'     => $d['value'],
					'rate'      => $d['rate'],
					'closed_at' => $d['closed_at'],
					'hold'      => class_exists( 'AXH_Bill' ) ? AXH_Bill::hold_for( $d['lead_id'], $leg['key'] ) : null,
				) );
			}
		}
		foreach ( $e['deals'] as $d ) {
			if ( ! empty( $d['overpaid'] ) && isset( $owed[ (int) $d['rep_id'] ] ) ) {
				$owed[ (int) $d['rep_id'] ]['overpaid'] += $d['overpaid'];
			}
		}
		foreach ( $owed as &$o ) {
			foreach ( array( 'due', 'overdue', 'upcoming', 'paid', 'overpaid' ) as $k ) {
				$o[ $k ] = round( $o[ $k ], 2 );
			}
		}
		unset( $o );
		// Who each check goes to, and whether the rep's paperwork is in order.
		$cards = array();
		foreach ( array_unique( array_merge( wp_list_pluck( $legs, 'rep_id' ), array_keys( $owed ) ) ) as $rid ) {
			$cards[ (int) $rid ] = array( 'payee' => AXH_Onboard::payee_card( (int) $rid ), 'readiness' => AXH_Onboard::readiness( (int) $rid ) );
		}
		foreach ( $legs as &$lg ) {
			$lg['payee']     = $cards[ (int) $lg['rep_id'] ]['payee'];
			$lg['readiness'] = $cards[ (int) $lg['rep_id'] ]['readiness'];
		}
		unset( $lg );
		foreach ( $owed as &$o ) {
			$o['payee']     = $cards[ (int) $o['rep_id'] ]['payee'];
			$o['readiness'] = $cards[ (int) $o['rep_id'] ]['readiness'];
		}
		unset( $o );
		return rest_ensure_response( array(
			'owed'    => array_values( $owed ),
			'legs'    => $legs,
			'history' => AXH_DB::payouts( array( 'with_void' => true ) ),
			'totals'  => $e['totals'],
			'methods' => array( 'Check', 'ACH / direct deposit', 'Zelle', 'PayPal', 'Wire', 'Other' ),
		) );
	}

	/**
	 * Paying a rep whose W-9, agreement or (verified) payout address is missing
	 * needs an explicit override, and the override is recorded on their audit log.
	 */
	protected static function payout_gate( $lead_id, $b ) {
		$lead = AXH_DB::get_lead( (int) $lead_id );
		if ( ! $lead || empty( $lead['rep_id'] ) || axh_is_manager( (int) $lead['rep_id'] ) ) {
			return true;
		}
		$r = AXH_Onboard::readiness( (int) $lead['rep_id'] );
		if ( $r['ready'] ) {
			return true;
		}
		if ( empty( $b['override'] ) ) {
			return new WP_Error(
				'axh_onboarding_incomplete',
				'Hold on — this rep\'s onboarding is missing: ' . implode( ', ', $r['missing'] ) . '. Pay anyway only if you have it some other way.',
				array( 'status' => 409, 'missing' => $r['missing'] )
			);
		}
		AXH_Onboard::audit( (int) $lead['rep_id'], 'payout_override', 'Payment recorded for ' . $lead['company'] . ' with onboarding incomplete (missing: ' . implode( ', ', $r['missing'] ) . ')' );
		return true;
	}

	public static function record_payout( $req ) {
		$b    = (array) $req->get_json_params();
		$gate = self::payout_gate( (int) ( $b['lead_id'] ?? 0 ), $b );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$p = AXH_DB::record_payout( (int) ( $b['lead_id'] ?? 0 ), sanitize_key( $b['leg'] ?? '' ), $b );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		AXH_Notify::add(
			(int) $p['rep_id'],
			'payout_recorded',
			'Commission paid: $' . number_format( (float) $p['amount'], 2 ) . ' for ' . $p['company'],
			sprintf(
				'AdaptExec recorded a commission payment to you — the %s half for %s.',
				'deposit' === $p['leg'] ? 'deposit' : 'final',
				$p['company']
			),
			'#/earnings',
			(int) $p['lead_id'],
			array(
				'rows' => array_filter( array(
					'Amount'    => '$' . number_format( (float) $p['amount'], 2 ),
					'Paid on'   => mysql2date( 'F j, Y', $p['paid_at'] ),
					'Method'    => $p['method'],
					'Reference' => $p['reference'],
				) ),
			)
		);
		return rest_ensure_response( array( 'payout' => $p ) );
	}

	public static function void_payout( $req ) {
		$b = (array) $req->get_json_params();
		$p = AXH_DB::void_payout( (int) $req['id'], (string) ( $b['reason'] ?? '' ) );
		return is_wp_error( $p ) ? $p : rest_ensure_response( array( 'payout' => $p ) );
	}

	public static function get_notifications( $req ) {
		return rest_ensure_response( array( 'items' => AXH_Notify::latest( 50 ), 'unread' => AXH_Notify::unread() ) );
	}

	public static function read_notifications( $req ) {
		$b = (array) $req->get_json_params();
		AXH_Notify::mark_read( (array) ( $b['ids'] ?? array() ), ! empty( $b['all'] ) );
		return rest_ensure_response( array( 'unread' => AXH_Notify::unread() ) );
	}

	public static function delete_notification( $req ) {
		if ( ! AXH_Notify::remove( (int) $req['id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Notification not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true, 'unread' => AXH_Notify::unread() ) );
	}

	public static function clear_notifications( $req ) {
		$n = AXH_Notify::clear_read();
		return rest_ensure_response( array( 'cleared' => $n, 'unread' => AXH_Notify::unread() ) );
	}

	/** The signed-in person: their profile and notification settings. */
	public static function get_me( $req ) {
		$u = wp_get_current_user();
		return rest_ensure_response( array(
			'name'    => $u->display_name,
			'email'   => $u->user_email,
			'login'   => $u->user_login,
			'phone'   => (string) get_user_meta( $u->ID, 'axh_phone', true ),
			'booking' => (string) get_user_meta( $u->ID, 'axh_booking_url', true ),
			'prefs'   => AXH_Notify::prefs( $u->ID ),
			'manager' => axh_is_manager(),
			'cron'    => array(
				'next'     => wp_next_scheduled( 'axh_daily' ) ? wp_date( 'Y-m-d H:i', wp_next_scheduled( 'axh_daily' ) ) : null,
				'disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'last'     => get_option( 'axh_daily_ran', '' ),
			),
		) );
	}

	public static function save_me( $req ) {
		$b = (array) $req->get_json_params();
		$u = wp_get_current_user();
		if ( isset( $b['name'] ) ) {
			$name = sanitize_text_field( $b['name'] );
			if ( '' === trim( $name ) ) {
				return new WP_Error( 'axh_no_name', 'Your name cannot be blank.', array( 'status' => 400 ) );
			}
			wp_update_user( array( 'ID' => $u->ID, 'display_name' => $name ) );
		}
		if ( isset( $b['phone'] ) ) {
			update_user_meta( $u->ID, 'axh_phone', mb_substr( sanitize_text_field( $b['phone'] ), 0, 40 ) );
		}
		if ( isset( $b['booking'] ) ) {
			$url = trim( (string) $b['booking'] );
			if ( '' !== $url && ! preg_match( '#^https?://#i', $url ) ) {
				$url = 'https://' . $url;
			}
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( '' !== trim( (string) $b['booking'] ) && '' === $url ) {
				return new WP_Error( 'axh_bad_url', 'That booking link does not look like a web address.', array( 'status' => 400 ) );
			}
			update_user_meta( $u->ID, 'axh_booking_url', mb_substr( $url, 0, 300 ) );
		}
		if ( isset( $b['prefs'] ) ) {
			AXH_Notify::save_prefs( $u->ID, (array) $b['prefs'] );
		}
		return self::get_me( $req );
	}

	public static function manage_discovery( $req ) {
		global $wpdb;
		$d = AXH_DB::table( 'discovery' );
		$l = AXH_DB::table( 'leads' );
		$rows = $wpdb->get_results(
			"SELECT d.id, d.lead_id, d.rep_id, d.status, d.progress, d.submitted_at, d.review_note, d.quote_note, d.quote_ready, d.quote_by,
			        l.company, l.industry, l.city, l.state, l.est_value
			 FROM {$d} d INNER JOIN {$l} l ON l.id = d.lead_id
			 WHERE d.status IN ('submitted','reviewed')
			 ORDER BY d.submitted_at DESC LIMIT 200",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as &$row ) {
			$row['rep'] = self::rep_name( $row['rep_id'] );
		}
		return rest_ensure_response( array( 'briefs' => $rows ) );
	}

	public static function review_discovery( $req ) {
		$data = (array) $req->get_json_params();
		AXH_DB::review_discovery( (int) $req['id'], $data['note'] ?? '', $data['status'] ?? 'reviewed' );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/* ---------------------------------------------------------------- helpers */

	/** "<unix time>.<signature>" — the public form's render time, tamper-evident. */
	public static function form_stamp( $slug ) {
		$t = time();
		return $t . '.' . substr( hash_hmac( 'sha256', $t . '|' . sanitize_title( $slug ), wp_salt( 'nonce' ) ), 0, 12 );
	}

	/** The render time from a form stamp, or 0 if it was not issued by us. */
	public static function check_stamp( $stamp, $slug ) {
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{12})$/', $stamp, $m ) ) {
			return 0;
		}
		$want = substr( hash_hmac( 'sha256', $m[1] . '|' . sanitize_title( $slug ), wp_salt( 'nonce' ) ), 0, 12 );
		return ( hash_equals( $want, $m[2] ) && self::stamp_age_ok( (int) $m[1] ) ) ? (int) $m[1] : 0;
	}

	/** Issued within the last three days, and not in the future. */
	protected static function stamp_age_ok( $t ) {
		$now = time();
		return $t > 0 && $t <= $now + 300 && $t >= $now - 3 * DAY_IN_SECONDS;
	}

	/**
	 * The public form nonce, checked for an anonymous visitor and, failing that,
	 * for whoever the login cookie belongs to (the nonce was minted for them).
	 */
	protected static function public_nonce_ok( $nonce ) {
		if ( '' === $nonce ) {
			return false;
		}
		if ( wp_verify_nonce( $nonce, 'axh_public_form' ) ) {
			return true;
		}
		$cookie_user = (int) wp_validate_auth_cookie( '', 'logged_in' );
		if ( ! $cookie_user || get_current_user_id() === $cookie_user ) {
			return false;
		}
		$was = get_current_user_id();
		wp_set_current_user( $cookie_user );
		$ok = (bool) wp_verify_nonce( $nonce, 'axh_public_form' );
		wp_set_current_user( $was );
		return $ok;
	}

	/** Everyone a lead or a form can be given to. */
	protected static function assignee_list() {
		$out = array();
		foreach ( AXH_DB::assignees() as $id => $name ) {
			$out[] = array( 'id' => (int) $id, 'name' => $name, 'manager' => user_can( (int) $id, 'axh_manage_all' ), 'rate' => (float) get_user_meta( (int) $id, 'axh_rate_override', true ) );
		}
		return $out;
	}

	/**
	 * Email the rep who owns a lead. Skipped when they are the one acting —
	 * nobody needs an email about what they just did.
	 */
	protected static function notify_rep( $lead, $type, $subject, $message, $link = '', $mail = array() ) {
		if ( empty( $lead['rep_id'] ) ) {
			return;
		}
		AXH_Notify::add( (int) $lead['rep_id'], $type, $subject, $message, $link ? $link : '#/lead/' . (int) $lead['id'], (int) $lead['id'], $mail );
	}

	protected static function notify_registration_decision( $lead ) {
		$note = $lead['decision_note'] ? "\n\nNote from AdaptExec: " . $lead['decision_note'] : '';
		if ( 'registered' === $lead['reg_status'] ) {
			self::notify_rep( $lead, 'registration_decision', 'Registration approved: ' . $lead['company'], $lead['company'] . " is registered to you. You're clear to work it — your commission on this prospect is protected under your agreement." . $note );
		} elseif ( 'house' === $lead['reg_status'] ) {
			self::notify_rep( $lead, 'registration_decision', 'House account: ' . $lead['company'], $lead['company'] . ' is an existing AdaptExec (house) account, so it cannot be registered to you. Please check with AdaptExec before contacting them.' . $note );
		} else {
			self::notify_rep( $lead, 'registration_decision', 'Registration declined: ' . $lead['company'], 'AdaptExec could not register ' . $lead['company'] . ' to you.' . $note );
		}
	}

	protected static function rep_name( $id ) {
		$user = get_userdata( (int) $id );
		return $user ? $user->display_name : 'Unassigned';
	}

	protected static function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		$out   = '';
		foreach ( array_slice( $parts, 0, 2 ) as $p ) {
			$out .= strtoupper( substr( $p, 0, 1 ) );
		}
		return $out ? $out : 'AX';
	}

	/**
	 * Email the notification address for registrations and submitted briefs.
	 */
	protected static function notify_manager( $subject, $message, $type = 'registration_request' ) {
		AXH_Notify::managers_add( $type, $subject, $message, '#/manage' );
	}

	/* ----------------------------------------------------------------- shares */

	public static function get_shares( $req ) {
		return rest_ensure_response( array(
			'shares' => AXH_DB::get_shares(),
			'decks'  => AXH_Portal::slide_decks(),
			'forms'  => AXH_DB::get_forms(),
		) );
	}

	public static function create_share( $req ) {
		$data = (array) $req->get_json_params();
		if ( empty( $data['deck'] ) ) {
			return new WP_Error( 'axh_no_deck', 'Choose a deck to share.', array( 'status' => 400 ) );
		}
		$decks = wp_list_pluck( AXH_Portal::slide_decks(), 'slug' );
		if ( ! in_array( sanitize_key( $data['deck'] ), $decks, true ) ) {
			return new WP_Error( 'axh_bad_deck', 'That deck does not exist.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'share' => AXH_DB::insert_share( $data ) ) );
	}

	public static function update_share( $req ) {
		$share = AXH_DB::update_share( (int) $req['id'], (array) $req->get_json_params() );
		if ( ! $share ) {
			return new WP_Error( 'axh_no_share', 'Not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'share' => $share ) );
	}

	public static function delete_share( $req ) {
		if ( ! AXH_DB::delete_share( (int) $req['id'] ) ) {
			return new WP_Error( 'axh_no_share', 'Not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function public_share_event( $req ) {
		// 1.9.0: views, time per slide, finishes and clicks per visit.
		return rest_ensure_response( AXH_P1::share_event( (array) $req->get_json_params() ) );
	}

	/* --------------------------------------------------------------- earnings */

	public static function get_earnings( $req ) {
		$data          = AXH_DB::earnings();
		$data['manager'] = axh_is_manager();
		if ( ! $data['manager'] ) {
			// Commission is paid within a reasonable time after the client's
			// payment clears (agreement 3.4), so a rep is never shown a pay date.
			foreach ( $data['deals'] as &$deal ) {
				foreach ( $deal['legs'] as &$leg ) {
					unset( $leg['due_at'] );
				}
				unset( $leg );
			}
			unset( $deal );
		}
		if ( axh_is_manager() ) {
			$data['reps'] = AXH_DB::rep_rollup();
		}
		return rest_ensure_response( $data );
	}

	public static function set_payment( $req ) {
		$data = (array) $req->get_json_params();
		if ( in_array( $data['field'] ?? '', array( 'deposit_paid_at', 'final_paid_at' ), true ) && ! empty( $data['when'] ) ) {
			$before = AXH_DB::get_lead( (int) $req['id'] );
			if ( $before && empty( $before[ $data['field'] ] ) ) {
				$gate = self::payout_gate( (int) $req['id'], $data );
				if ( is_wp_error( $gate ) ) {
					return $gate;
				}
			}
		}
		$lead = AXH_DB::set_payment( (int) $req['id'], $data['field'] ?? '', $data['when'] ?? '' );
		if ( ! $lead ) {
			return new WP_Error( 'axh_bad_payment', 'Could not record that.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'lead' => $lead, 'earnings' => AXH_DB::earnings() ) );
	}

	/**
	 * A submitted questionnaire in a form a developer can read: the lead, every
	 * answer against its question, and the module selection split into
	 * must-have and nice-to-have.
	 */
	public static function get_brief( $req ) {
		$lead = AXH_DB::get_lead( (int) $req['id'] );
		if ( ! $lead ) {
			return new WP_Error( 'axh_no_lead', 'Not found.', array( 'status' => 404 ) );
		}
		$disc = AXH_DB::get_discovery( (int) $req['id'] );
		// The questionnaire follows the lead's trade, so it comes from here
		// rather than from bootstrap — one rep works several industries in a day.
		return rest_ensure_response( array(
			'lead'      => $lead,
			'discovery' => $disc,
			'schema'    => axh_discovery_schema( $lead['industry'] ),
			'packet'    => (bool) ( axh_discovery_packets()[ $lead['industry'] ] ?? false ),
			'modules'   => axh_module_library(),
			'rep'       => self::rep_name( $lead['rep_id'] ),
		) );
	}
}
