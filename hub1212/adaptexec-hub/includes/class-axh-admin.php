<?php
/**
 * A small wp-admin surface: settings, rep creation, and a link into the Hub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_axh_add_rep', array( __CLASS__, 'add_rep' ) );
		add_action( 'admin_post_axh_set_pass', array( __CLASS__, 'set_pass' ) );
		add_action( 'admin_post_axh_rep_access', array( __CLASS__, 'rep_access' ) );
		add_action( 'admin_post_axh_reset_link', array( __CLASS__, 'reset_link' ) );
		add_action( 'admin_post_axh_invite', array( __CLASS__, 'invite' ) );
	}

	/** Send (or resend) a rep's invitation from this screen. */
	public static function invite() {
		check_admin_referer( 'axh_invite' );
		$user = self::rep_or_die( $_POST['user_id'] ?? 0 );
		$r    = AXH_Team::send_invite( $user, 'invite' );
		if ( empty( $r['emailed'] ) && ! empty( $r['link'] ) ) {
			set_transient( 'axh_reset_link_' . get_current_user_id(), $r['link'], 5 * MINUTE_IN_SECONDS );
			self::back( 'invite_failed', array( 'axh_who' => rawurlencode( $user->display_name ) ) );
		}
		self::back( 'invited', array( 'axh_who' => rawurlencode( $user->display_name ) ) );
	}

	/**
	 * Only ever act on a Hub rep, never on an administrator.
	 *
	 * This screen is a convenience for onboarding sales staff. Letting it set
	 * any user's password would make it a way to take over the site, so it is
	 * limited to accounts holding the rep role.
	 */
	protected static function rep_or_die( $user_id ) {
		if ( ! current_user_can( 'axh_manage_all' ) ) {
			wp_die( 'Not allowed.' );
		}
		$user = get_user_by( 'id', (int) $user_id );
		if ( ! $user || ! in_array( AXH_ROLE, (array) $user->roles, true ) ) {
			wp_die( 'That is not a sales representative account.' );
		}
		return $user;
	}

	protected static function back( $notice, $extra = array() ) {
		$args = array_merge( array( 'page' => 'axh-hub', 'axh_notice' => $notice ), $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Set a rep's password directly.
	 *
	 * Email is the usual route, but a shop's mail is not always configured on
	 * day one and a rep who cannot sign in is a rep who cannot work.
	 */
	public static function set_pass() {
		check_admin_referer( 'axh_set_pass' );
		$user = self::rep_or_die( $_POST['user_id'] ?? 0 );
		$pass = (string) ( $_POST['new_pass'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( strlen( $pass ) < 12 ) {
			self::back( 'weak' );
		}
		wp_set_password( $pass, $user->ID );
		self::back( 'passed', array( 'axh_who' => rawurlencode( $user->display_name ) ) );
	}

	/**
	 * A password-set link to hand over when email is not working yet.
	 */
	public static function reset_link() {
		check_admin_referer( 'axh_reset_link' );
		$user = self::rep_or_die( $_POST['user_id'] ?? 0 );
		$key  = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			self::back( 'error' );
		}
		$url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
		set_transient( 'axh_reset_link_' . get_current_user_id(), $url, 5 * MINUTE_IN_SECONDS );
		self::back( 'link', array( 'axh_who' => rawurlencode( $user->display_name ) ) );
	}

	/**
	 * Take Hub access away without deleting the person or their leads.
	 */
	public static function rep_access() {
		check_admin_referer( 'axh_rep_access' );
		$user = self::rep_or_die( $_POST['user_id'] ?? 0 );
		AXH_Team::deactivate( $user );
		self::back( 'revoked', array( 'axh_who' => rawurlencode( $user->display_name ) ) );
	}

	public static function menu() {
		add_menu_page(
			'AdaptExec Hub',
			'AdaptExec Hub',
			'axh_manage_all',
			'axh-hub',
			array( __CLASS__, 'page' ),
			'dashicons-chart-line',
			30
		);
	}

	public static function settings() {
		register_setting( 'axh_settings', 'axh_portal_slug', array( 'sanitize_callback' => 'sanitize_title' ) );
		register_setting( 'axh_settings', 'axh_notify_email', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'axh_settings', 'axh_agreement_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'axh_settings', 'axh_payout_note', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'axh_settings', 'axh_contact_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'axh_settings', 'axh_contact_email', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'axh_settings', 'axh_contact_phone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public static function add_rep() {
		if ( ! current_user_can( 'axh_manage_all' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'axh_add_rep' );

		$email = sanitize_email( wp_unslash( $_POST['rep_email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['rep_name'] ?? '' ) );

		$notice = 'error';
		if ( is_email( $email ) && ! email_exists( $email ) ) {
			$login = sanitize_user( current( explode( '@', $email ) ), true );
			$i     = 1;
			$base  = $login;
			while ( username_exists( $login ) ) {
				$login = $base . $i;
				$i++;
			}
			// A password typed here is used as-is; otherwise WordPress emails
			// them a link. Mail is not always working on day one, so being
			// able to set one and read it out is the difference between a rep
			// who can work this afternoon and one who cannot.
			$chosen = (string) ( $_POST['rep_pass'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$set_it = strlen( $chosen ) >= 12;

			$user_id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => $set_it ? $chosen : wp_generate_password( 20, true ),
					'display_name' => $name ? $name : $login,
					'first_name'   => $name,
					'role'         => AXH_ROLE,
				)
			);
			if ( ! is_wp_error( $user_id ) ) {
				update_user_meta( $user_id, 'axh_was_rep', 1 );
				$notice = $set_it ? 'added_pass' : 'added';
				if ( ! empty( $_POST['rep_invite'] ) || ! $set_it ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$r = AXH_Team::send_invite( get_userdata( $user_id ), 'invite' );
					if ( empty( $r['emailed'] ) && ! empty( $r['link'] ) ) {
						set_transient( 'axh_reset_link_' . get_current_user_id(), $r['link'], 5 * MINUTE_IN_SECONDS );
						wp_safe_redirect( admin_url( 'admin.php?page=axh-hub&axh_notice=invite_failed&axh_who=' . rawurlencode( $name ? $name : $login ) ) );
						exit;
					}
					$notice = 'added';
				}
			}
		} elseif ( email_exists( $email ) ) {
			$notice = 'exists';
		}

		wp_safe_redirect( admin_url( 'admin.php?page=axh-hub&axh_notice=' . $notice ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'axh_manage_all' ) ) {
			return;
		}
		$reps   = get_users( array( 'role__in' => array( AXH_ROLE ), 'orderby' => 'display_name' ) );
		$notice = isset( $_GET['axh_notice'] ) ? sanitize_key( wp_unslash( $_GET['axh_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1>AdaptExec Sales Hub</h1>

			<?php
			$axh_who  = isset( $_GET['axh_who'] ) ? sanitize_text_field( wp_unslash( $_GET['axh_who'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$axh_link = get_transient( 'axh_reset_link_' . get_current_user_id() );
			?>
			<?php if ( 'added' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p>Representative added and invited. They have an email with a link to set their password (good for <?php echo (int) AXH_Team::invite_days(); ?> days), the Hub address and their first-week checklist.</p></div>
			<?php elseif ( 'invited' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p>Invitation sent to <strong><?php echo esc_html( $axh_who ); ?></strong>.</p></div>
			<?php elseif ( 'invite_failed' === $notice ) : ?>
				<div class="notice notice-error">
					<p><strong>The invitation email to <?php echo esc_html( $axh_who ); ?> could not be sent</strong> — your mail server refused it (see WP Mail SMTP → Tools → Debug Events). Send them this link yourself; it sets their password and lasts <?php echo (int) AXH_Team::invite_days(); ?> days.</p>
					<?php if ( $axh_link ) : ?>
						<p><input type="text" readonly class="large-text code" onclick="this.select()" value="<?php echo esc_url( $axh_link ); ?>"></p>
						<?php delete_transient( 'axh_reset_link_' . get_current_user_id() ); ?>
					<?php endif; ?>
				</div>
			<?php elseif ( 'added_pass' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p>Representative added with the password you set. Give it to them along with the Hub address &mdash; no email is sent.</p></div>
			<?php elseif ( 'passed' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p>New password set for <strong><?php echo esc_html( $axh_who ); ?></strong>. It takes effect immediately and signs out their other sessions.</p></div>
			<?php elseif ( 'weak' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p>Use at least 12 characters. Nothing was changed.</p></div>
			<?php elseif ( 'revoked' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p>Hub access removed for <strong><?php echo esc_html( $axh_who ); ?></strong>. Their account and their leads are untouched.</p></div>
			<?php elseif ( 'link' === $notice && $axh_link ) : ?>
				<div class="notice notice-info">
					<p><strong>One-time sign-in link for <?php echo esc_html( $axh_who ); ?>.</strong>
						Send it to them; it sets their password and expires in 24 hours.</p>
					<p><input type="text" readonly class="large-text code" onclick="this.select()"
						value="<?php echo esc_url( $axh_link ); ?>"></p>
				</div>
				<?php delete_transient( 'axh_reset_link_' . get_current_user_id() ); ?>
			<?php elseif ( 'exists' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p>That email already belongs to a user account.</p></div>
			<?php elseif ( 'error' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p>Enter a valid email address.</p></div>
			<?php endif; ?>

			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( axh_portal_url() ); ?>">Open the Hub &rarr;</a>
			</p>

			<h2>Settings</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'axh_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="axh_portal_slug">Portal address</label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
							<input name="axh_portal_slug" id="axh_portal_slug" type="text" class="regular-text" style="width:12rem"
								value="<?php echo esc_attr( get_option( 'axh_portal_slug', 'hub' ) ); ?>">
							<p class="description">After changing this, visit <strong>Settings &rarr; Permalinks</strong> once to refresh the rules.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="axh_notify_email">Notification email</label></th>
						<td>
							<input name="axh_notify_email" id="axh_notify_email" type="email" class="regular-text"
								value="<?php echo esc_attr( get_option( 'axh_notify_email', get_option( 'admin_email' ) ) ); ?>">
							<p class="description">Where lead registrations, Closed Won requests and submitted questionnaires are sent.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="axh_agreement_url">Rep agreement link</label></th>
						<td>
							<input name="axh_agreement_url" id="axh_agreement_url" type="url" class="regular-text" placeholder="https://drive.google.com/…"
								value="<?php echo esc_attr( get_option( 'axh_agreement_url', '' ) ); ?>">
							<p class="description">Optional, and only for an older agreement signed outside the Hub. Since 1.6.0 reps read and e-sign the Independent Contractor Agreement in the Hub (Onboarding), and their signed copy is linked there instead.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="axh_payout_note">How commission is paid</label></th>
						<td>
							<textarea name="axh_payout_note" id="axh_payout_note" class="large-text" rows="3"
								placeholder="e.g. By check, mailed after the client's payment clears."><?php echo esc_textarea( get_option( 'axh_payout_note', '' ) ); ?></textarea>
							<p class="description">Optional. Shown to reps in "How you get paid". Leave blank to say: by check, mailed to the payout address they add in the Hub. Don't promise a pay date here — the agreement deliberately has none.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Rep contact</th>
						<td>
							<?php $axh_c = AXH_Mail::contact(); ?>
							<input name="axh_contact_name" type="text" class="regular-text" style="width:10rem" placeholder="Adam" aria-label="Contact name" value="<?php echo esc_attr( get_option( 'axh_contact_name', '' ) ); ?>">
							<input name="axh_contact_phone" type="text" class="regular-text" style="width:11rem" placeholder="(602) 584-0614" aria-label="Contact phone" value="<?php echo esc_attr( get_option( 'axh_contact_phone', '' ) ); ?>">
							<input name="axh_contact_email" type="email" class="regular-text" style="width:16rem" placeholder="adam@adaptexec.com" aria-label="Contact email" value="<?php echo esc_attr( get_option( 'axh_contact_email', '' ) ); ?>">
							<p class="description">Who reps call with questions. Shown in invitations and at the foot of every Hub email (now: <?php echo esc_html( $axh_c['name'] . ' · ' . $axh_c['phone'] . ' · ' . $axh_c['email'] ); ?>).</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2>Sales representatives</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="axh_add_rep">
				<?php wp_nonce_field( 'axh_add_rep' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rep_name">Name</label></th>
						<td><input name="rep_name" id="rep_name" type="text" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="rep_email">Email</label></th>
						<td><input name="rep_email" id="rep_email" type="email" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="rep_pass">Password</label></th>
						<td>
							<input name="rep_pass" id="rep_pass" type="text" class="regular-text" autocomplete="off"
								placeholder="Leave blank to email them a link">
							<p class="description">Optional. Leave it blank and they choose their own from the invitation email.</p>
							<p><label><input type="checkbox" name="rep_invite" value="1" checked> Email them an invitation (set-password link, Hub address, first-week checklist, how they get paid)</label></p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Add representative', 'secondary' ); ?>
			</form>

			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Leads</th><th>Last activity</th><th style="width:40%">Sign-in</th></tr></thead>
				<tbody>
				<?php if ( empty( $reps ) ) : ?>
					<tr><td colspan="6">No representatives yet.</td></tr>
				<?php else : ?>
					<?php foreach ( AXH_DB::rep_rollup() as $rep ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $rep['name'] ); ?></strong></td>
							<td><?php echo esc_html( $rep['email'] ); ?></td>
							<td><?php
							$axh_st = array(
								'active'        => 'Active' . ( $rep['last_login'] ? ' · last sign-in ' . mysql2date( 'M j', $rep['last_login'] ) : '' ),
								'invited'       => 'Invited ' . ( $rep['invited_at'] ? mysql2date( 'M j', $rep['invited_at'] ) : '' ) . ' · not signed in yet',
								'invite_failed' => 'Invitation email failed',
								'password_set'  => 'Password set · not signed in yet',
								'not_invited'   => 'Not invited yet',
								'deactivated'   => 'Access removed',
							);
							echo esc_html( $axh_st[ $rep['status'] ] ?? $rep['status'] );
							?></td>
							<td><?php echo (int) $rep['total']; ?> (<?php echo (int) $rep['open_count']; ?> open)</td>
							<td><?php echo $rep['last_activity'] ? esc_html( mysql2date( 'M j, Y', $rep['last_activity'] ) ) : '&mdash;'; ?></td>
							<td>
								<?php if ( $rep['active'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:6px">
									<input type="hidden" name="action" value="axh_set_pass">
									<input type="hidden" name="user_id" value="<?php echo (int) $rep['id']; ?>">
									<?php wp_nonce_field( 'axh_set_pass' ); ?>
									<input name="new_pass" type="text" autocomplete="off" style="width:15rem"
										placeholder="New password (12+)"
										aria-label="New password for <?php echo esc_attr( $rep['name'] ); ?>">
									<button class="button">Set password</button>
								</form>
								<div style="display:flex;gap:6px">
									<?php if ( $rep['active'] && 'active' !== $rep['status'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="axh_invite">
										<input type="hidden" name="user_id" value="<?php echo (int) $rep['id']; ?>">
										<?php wp_nonce_field( 'axh_invite' ); ?>
										<button class="button button-primary button-small"><?php echo $rep['invite_count'] ? 'Resend invitation' : 'Send invitation'; ?></button>
									</form>
									<?php endif; ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="axh_reset_link">
										<input type="hidden" name="user_id" value="<?php echo (int) $rep['id']; ?>">
										<?php wp_nonce_field( 'axh_reset_link' ); ?>
										<button class="button button-link">Get sign-in link</button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
										onsubmit="return confirm('Remove Hub access for <?php echo esc_js( $rep['name'] ); ?>? Their leads stay where they are.');">
										<input type="hidden" name="action" value="axh_rep_access">
										<input type="hidden" name="user_id" value="<?php echo (int) $rep['id']; ?>">
										<?php wp_nonce_field( 'axh_rep_access' ); ?>
										<button class="button button-link delete">Remove access</button>
									</form>
								</div>
								<?php else : ?>
									<p class="description">Access removed<?php echo $rep['deactivated_at'] ? ' ' . esc_html( mysql2date( 'M j, Y', $rep['deactivated_at'] ) ) : ''; ?>. Reactivate from the Hub under Team.</p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h2>Onboarding</h2>
			<p>Company legal name, signer, the agreement text and reminders are set in the Hub under <a href="<?php echo esc_url( axh_portal_url( '#/manage/onboarding/settings' ) ); ?>">Administration &rarr; Onboarding &rarr; Settings</a>.
				Completed W-9s, signatures and signed agreements are encrypted in a private folder inside wp-content/uploads; deleting the plugin does not delete that folder.</p>

			<h2>Where your reps sign in</h2>
			<p>Send them <code><?php echo esc_html( axh_portal_url() ); ?></code>. It has its own
				sign-in screen, so they never see wp-admin. Their data lives in the Hub's own
				tables, which survive the plugin being deleted and installed again.</p>
		</div>
		<?php
	}
}

/* =========================================================================
   Email, notifications and the team.

   These live here rather than in files of their own so the whole plugin can
   still be maintained from the WordPress plugin editor, which can change
   files but not add them.
   ========================================================================= */

/**
 * Branded email. Every message the Hub sends goes through send(): an HTML
 * body in the AdaptExec look plus a plain-text alternative for clients that
 * prefer it (and for spam filters, which like to see both).
 */
class AXH_Mail {

	/** Plain-text body for the message being sent right now. */
	protected static $alt = '';

	/** Who reps should contact. Set under AdaptExec Hub → Settings. */
	public static function contact() {
		$name  = trim( (string) get_option( 'axh_contact_name', '' ) );
		$email = trim( (string) get_option( 'axh_contact_email', '' ) );
		$phone = trim( (string) get_option( 'axh_contact_phone', '' ) );
		return array(
			'name'  => $name ? $name : 'Adam',
			'email' => is_email( $email ) ? $email : 'adam@adaptexec.com',
			'phone' => $phone ? $phone : '(602) 584-0614',
		);
	}

	/**
	 * Build the HTML and text versions of a message.
	 *
	 * @param array $a {
	 *     heading   string  Big title.
	 *     preheader string  Inbox preview line.
	 *     paras     array   Paragraphs (plain text).
	 *     rows      array   label => value pairs, shown as a small table.
	 *     cta       array   array( label, url ).
	 *     cta_note  string  Small print under the button.
	 *     sections  array   Each array( 'title' => , 'paras' => array, 'list' => array, 'steps' => array ).
	 *     footer    string  Why they got it / how to change it.
	 * }
	 */
	public static function render( $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'heading'   => '',
				'preheader' => '',
				'paras'     => array(),
				'rows'      => array(),
				'cta'       => array(),
				'cta_note'  => '',
				'sections'  => array(),
				'footer'    => '',
				'cta_first' => false,
				'tag'       => 'Sales Hub', // 1.9.0: '' for client-facing mail
				'contact'   => null,        // 1.9.0: array( name, phone, email ) to override the footer contact
			)
		);
		$e    = 'esc_html';
		$font = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
		$p    = 'margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;' . $font;

		$html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e( $a['heading'] ) . '</title></head>';
		$html .= '<body style="margin:0;padding:0;background:#F6F5F3;">';
		$html .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $e( $a['preheader'] ) . '</div>';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F5F3;"><tr><td align="center" style="padding:24px 12px;">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:12px;overflow:hidden;border:1px solid #E4E2DD;">';
		$html .= '<tr><td style="background:#0D0E10;padding:22px 28px;"><span style="' . $font . 'font-size:22px;font-weight:800;letter-spacing:.06em;color:#FFFFFF;">ADAPT<span style="color:#EF5D0F;">EXEC</span></span>'
			. ( '' !== (string) $a['tag'] ? '<span style="' . $font . 'font-size:12px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#9AA3AE;padding-left:10px;">' . $e( $a['tag'] ) . '</span>' : '' ) . '</td></tr>';
		$html .= '<tr><td style="height:4px;background:#EF5D0F;line-height:4px;font-size:0;">&nbsp;</td></tr>';
		$html .= '<tr><td style="padding:30px 28px 8px;">';
		if ( $a['heading'] ) {
			$html .= '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#14181E;' . $font . '">' . $e( $a['heading'] ) . '</h1>';
		}
		foreach ( (array) $a['paras'] as $para ) {
			$html .= '<p style="' . $p . '">' . nl2br( $e( $para ) ) . '</p>';
		}
		$rows_html = '';
		$cta_html  = '';
		if ( $a['rows'] ) {
			$rows_html .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:4px 0 18px;border:1px solid #E4E2DD;border-radius:8px;">';
			foreach ( $a['rows'] as $label => $value ) {
				$rows_html .= '<tr><td style="padding:9px 12px;border-bottom:1px solid #F1EFEA;font-size:13px;color:#69727D;width:38%;' . $font . '">' . $e( $label ) . '</td>'
					. '<td style="padding:9px 12px;border-bottom:1px solid #F1EFEA;font-size:14px;color:#14181E;' . $font . '">' . nl2br( $e( (string) $value ) ) . '</td></tr>';
			}
			$rows_html .= '</table>';
		}
		if ( ! empty( $a['cta'][0] ) && ! empty( $a['cta'][1] ) ) {
			$cta_html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 10px;"><tr><td style="border-radius:8px;background:#EF5D0F;">'
				. '<a href="' . esc_url( $a['cta'][1] ) . '" style="display:inline-block;padding:13px 24px;font-size:15px;font-weight:700;color:#FFFFFF;text-decoration:none;' . $font . '">' . $e( $a['cta'][0] ) . '</a></td></tr></table>';
			$cta_html .= '<p style="margin:0 0 18px;font-size:12px;line-height:1.5;color:#69727D;word-break:break-all;' . $font . '">'
				. ( $a['cta_note'] ? $e( $a['cta_note'] ) . '<br>' : '' ) . 'Or paste this into your browser: ' . $e( $a['cta'][1] ) . '</p>';
		}
		$html .= ! empty( $a['cta_first'] ) ? $cta_html . $rows_html : $rows_html . $cta_html;
		foreach ( (array) $a['sections'] as $sec ) {
			$html .= '<h2 style="margin:22px 0 10px;font-size:16px;letter-spacing:.04em;text-transform:uppercase;color:#14181E;' . $font . '">' . $e( $sec['title'] ?? '' ) . '</h2>';
			foreach ( (array) ( $sec['paras'] ?? array() ) as $para ) {
				$html .= '<p style="' . $p . '">' . nl2br( $e( $para ) ) . '</p>';
			}
			foreach ( array( 'steps' => 'ol', 'list' => 'ul' ) as $key => $tag ) {
				if ( ! empty( $sec[ $key ] ) ) {
					$html .= '<' . $tag . ' style="margin:0 0 14px;padding-left:22px;color:#334155;' . $font . '">';
					foreach ( $sec[ $key ] as $item ) {
						$html .= '<li style="margin:0 0 7px;font-size:15px;line-height:1.55;">' . $e( $item ) . '</li>';
					}
					$html .= '</' . $tag . '>';
				}
			}
		}
		$html .= '</td></tr>';
		$c     = is_array( $a['contact'] ) ? wp_parse_args( $a['contact'], self::contact() ) : self::contact();
		$html .= '<tr><td style="padding:18px 28px 26px;border-top:1px solid #F1EFEA;">'
			. '<p style="margin:0 0 6px;font-size:13px;line-height:1.55;color:#69727D;' . $font . '">Questions? ' . $e( $c['name'] ) . ' · ' . $e( $c['phone'] ) . ' · <a href="mailto:' . esc_attr( $c['email'] ) . '" style="color:#046BD2;">' . $e( $c['email'] ) . '</a></p>'
			. ( $a['footer'] ? '<p style="margin:0;font-size:12px;line-height:1.5;color:#8A929C;' . $font . '">' . $e( $a['footer'] ) . '</p>' : '' )
			. '</td></tr></table>'
			. '<p style="margin:14px 0 0;font-size:11px;color:#8A929C;' . $font . '">AdaptExec · Scottsdale, Arizona</p>'
			. '</td></tr></table></body></html>';

		// The same message as plain text.
		$t = array();
		if ( $a['heading'] ) {
			$t[] = $a['heading'];
			$t[] = str_repeat( '=', min( 60, mb_strlen( $a['heading'] ) ) );
			$t[] = '';
		}
		foreach ( (array) $a['paras'] as $para ) {
			$t[] = $para;
			$t[] = '';
		}
		$tr = array();
		$tc = array();
		if ( $a['rows'] ) {
			foreach ( $a['rows'] as $label => $value ) {
				$tr[] = $label . ': ' . $value;
			}
			$tr[] = '';
		}
		if ( ! empty( $a['cta'][0] ) && ! empty( $a['cta'][1] ) ) {
			$tc[] = $a['cta'][0] . ':';
			$tc[] = $a['cta'][1];
			if ( $a['cta_note'] ) {
				$tc[] = $a['cta_note'];
			}
			$tc[] = '';
		}
		$t = array_merge( $t, ! empty( $a['cta_first'] ) ? array_merge( $tc, $tr ) : array_merge( $tr, $tc ) );
		foreach ( (array) $a['sections'] as $sec ) {
			$t[] = strtoupper( $sec['title'] ?? '' );
			foreach ( (array) ( $sec['paras'] ?? array() ) as $para ) {
				$t[] = $para;
			}
			$n = 1;
			foreach ( (array) ( $sec['steps'] ?? array() ) as $item ) {
				$t[] = ( $n++ ) . '. ' . $item;
			}
			foreach ( (array) ( $sec['list'] ?? array() ) as $item ) {
				$t[] = '- ' . $item;
			}
			$t[] = '';
		}
		$t[] = '--';
		$t[] = 'Questions? ' . $c['name'] . ' · ' . $c['phone'] . ' · ' . $c['email'];
		if ( $a['footer'] ) {
			$t[] = $a['footer'];
		}

		return array(
			'html' => $html,
			'text' => implode( "\n", $t ),
		);
	}

	/**
	 * Send a branded message. Returns whether WordPress accepted it — false
	 * means the mail server refused it (WP Mail SMTP logs the reason).
	 */
	public static function send( $to, $subject, $args, $headers = array(), $attachments = array() ) {
		$m = self::render( $args );
		self::$alt = $m['text'];
		add_action( 'phpmailer_init', array( __CLASS__, 'add_alt' ) );
		$headers   = (array) $headers;
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$ok        = wp_mail( $to, $subject, $m['html'], $headers, (array) $attachments );
		remove_action( 'phpmailer_init', array( __CLASS__, 'add_alt' ) );
		self::$alt = '';
		return (bool) $ok;
	}

	/** Attach the plain-text alternative to the message being sent. */
	public static function add_alt( $phpmailer ) {
		if ( self::$alt ) {
			$phpmailer->AltBody = self::$alt; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		}
		if ( self::$oneshot ) {
			// Only the very next message: never let it leak onto another email.
			self::$oneshot = false;
			self::$alt     = '';
			remove_action( 'phpmailer_init', array( __CLASS__, 'add_alt' ) );
		}
	}

	/** Set when set_alt() is used for one message WordPress sends itself. */
	protected static $oneshot = false;

	/** For WordPress's own new-user and password emails, which build their message themselves. */
	public static function set_alt( $text ) {
		self::$alt     = (string) $text;
		self::$oneshot = true;
		add_action( 'phpmailer_init', array( __CLASS__, 'add_alt' ) );
	}
}

/**
 * Notifications: the bell in the Hub, plus email when a person wants it.
 */
class AXH_Notify {

	public static function init() {
		add_action( 'axh_daily', array( __CLASS__, 'daily' ) );
		add_action( 'init', array( 'AXH_Install', 'schedule' ) );
	}

	/**
	 * Every kind of notification, who can get it and whether it is emailed
	 * unless the person turns it off.
	 */
	public static function types() {
		return array(
			'lead_assigned'         => array( 'label' => 'A lead is assigned to me', 'who' => 'rep', 'email' => true ),
			'form_submission'       => array( 'label' => 'Someone fills in one of my lead forms', 'who' => 'all', 'email' => true ),
			'registration_decision' => array( 'label' => 'A prospect I submitted is approved or declined', 'who' => 'rep', 'email' => true ),
			'win_decision'          => array( 'label' => 'A deal I reported is confirmed Closed Won (or sent back)', 'who' => 'rep', 'email' => true ),
			'payout_recorded'       => array( 'label' => 'A commission payment to me is recorded', 'who' => 'rep', 'email' => true ),
			'discovery_completed'   => array( 'label' => 'A client finishes the discovery form I sent', 'who' => 'all', 'email' => true ),
			'note_on_lead'          => array( 'label' => 'AdaptExec adds a note to one of my leads', 'who' => 'rep', 'email' => true ),
			'style_picks'           => array( 'label' => 'A client picks their website style (style link or questionnaire)', 'who' => 'all', 'email' => true ),
			'followups_digest'      => array( 'label' => 'Morning briefing: follow-ups due, deck views, waiting enquiries', 'who' => 'all', 'email' => true ),
			'followup_due'          => array( 'label' => 'A follow-up with a set time is due (reminder at that time)', 'who' => 'all', 'email' => false ),
			'registration_request'  => array( 'label' => 'A rep submits a prospect for registration', 'who' => 'manager', 'email' => true ),
			'win_request'           => array( 'label' => 'A rep reports a signed deal', 'who' => 'manager', 'email' => true ),
			'discovery_submitted'   => array( 'label' => 'A rep requests a quote (questionnaire hand-off)', 'who' => 'manager', 'email' => true ),
			'rep_activated'         => array( 'label' => 'A new rep signs in for the first time', 'who' => 'manager', 'email' => true ),
			'onboarding_update'     => array( 'label' => 'A rep signs their agreement, uploads a W-9 or sets/changes their payout address', 'who' => 'manager', 'email' => true ),
			'onboarding_admin'      => array( 'label' => 'AdaptExec countersigns my agreement or asks me for something for onboarding', 'who' => 'rep', 'email' => true ),
			'billing_viewed'        => array( 'label' => 'A customer opens a proposal, estimate or invoice', 'who' => 'all', 'email' => true ),
			'billing_decision'      => array( 'label' => 'A customer accepts or declines a proposal or estimate', 'who' => 'all', 'email' => true ),
			'billing_paid'          => array( 'label' => 'A payment is recorded on one of my clients\' invoices', 'who' => 'rep', 'email' => true ),
			'share_viewed'          => array( 'label' => 'A prospect opens a deck link I sent (first open and return visits)', 'who' => 'all', 'email' => true ),
			'share_cta'             => array( 'label' => 'A prospect clicks the button on a deck link I sent', 'who' => 'all', 'email' => true ),
			'mention'               => array( 'label' => 'Someone mentions me in a note on a deal', 'who' => 'all', 'email' => true ),
			'email_opened'          => array( 'label' => 'A prospect opens an email I sent from the Hub, or clicks a link in it', 'who' => 'all', 'email' => false ),
			'team_summary'          => array( 'label' => 'Morning team summary', 'who' => 'manager', 'email' => false ),
		);
	}

	/** The types that apply to this person, with their current setting. */
	public static function prefs( $user_id ) {
		$saved   = get_user_meta( (int) $user_id, 'axh_notify_prefs', true );
		$saved   = is_array( $saved ) ? $saved : array();
		$manager = axh_is_manager( $user_id );
		$out     = array();
		foreach ( self::types() as $key => $t ) {
			if ( 'manager' === $t['who'] && ! $manager ) {
				continue;
			}
			if ( 'rep' === $t['who'] && $manager ) {
				continue;
			}
			$out[ $key ] = array(
				'label' => $t['label'],
				'email' => array_key_exists( $key, $saved ) ? (bool) $saved[ $key ] : (bool) $t['email'],
			);
		}
		return $out;
	}

	public static function wants_email( $user_id, $type ) {
		$p = self::prefs( $user_id );
		return ! empty( $p[ $type ]['email'] );
	}

	public static function save_prefs( $user_id, $in ) {
		$valid = self::prefs( $user_id );
		$saved = get_user_meta( (int) $user_id, 'axh_notify_prefs', true );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( (array) $in as $key => $on ) {
			if ( isset( $valid[ $key ] ) ) {
				$saved[ $key ] = (bool) $on;
			}
		}
		update_user_meta( (int) $user_id, 'axh_notify_prefs', $saved );
		return self::prefs( $user_id );
	}

	/**
	 * Notify one person: a row for the bell and, if they want it, an email.
	 *
	 * @param int    $user_id Recipient.
	 * @param string $type    One of types().
	 * @param string $title   One line.
	 * @param string $body    A few lines of detail.
	 * @param string $link    Hub route, e.g. "#/lead/12".
	 * @param int    $lead_id Lead it is about, if any.
	 * @param array  $mail    Extra AXH_Mail::render() arguments (rows, sections…).
	 * @return int Notification id, or 0 if skipped.
	 */
	public static function add( $user_id, $type, $title, $body = '', $link = '', $lead_id = null, $mail = array() ) {
		global $wpdb;
		$user = $user_id ? get_userdata( (int) $user_id ) : null;
		// Nobody needs telling about something they just did themselves.
		if ( ! $user || (int) $user->ID === get_current_user_id() || ! user_can( $user, 'axh_use_hub' ) ) {
			return 0;
		}
		$wpdb->insert(
			AXH_DB::table( 'notifications' ),
			array(
				'user_id'    => (int) $user->ID,
				'type'       => sanitize_key( $type ),
				'title'      => mb_substr( sanitize_text_field( $title ), 0, 191 ),
				'body'       => sanitize_textarea_field( $body ),
				'link'       => mb_substr( (string) $link, 0, 191 ),
				'lead_id'    => $lead_id ? (int) $lead_id : null,
				'created_at' => current_time( 'mysql' ),
			)
		);
		$id = (int) $wpdb->insert_id;
		do_action( 'axh_notification_added', (int) $user->ID, $type, $title, $body, $link, $id ); // 1.10.0: phone push

		if ( self::wants_email( $user->ID, $type ) && is_email( $user->user_email ) ) {
			$sent = self::email( $user->user_email, $type, $title, $body, $link, $mail );
			if ( $sent && $id ) {
				$wpdb->update( AXH_DB::table( 'notifications' ), array( 'emailed' => 1 ), array( 'id' => $id ) );
			}
		}
		return $id;
	}

	/** The email that goes with a notification. */
	public static function email( $to, $type, $title, $body, $link, $mail = array() ) {
		$paras = array_values( array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", (string) $body ) ) ) );
		$args  = array_merge(
			array(
				'heading'   => $title,
				'preheader' => $paras ? $paras[0] : $title,
				'paras'     => $paras,
				'cta'       => $link ? array( 'Open in the Hub', axh_portal_url( $link ) ) : array( 'Open the Hub', axh_portal_url() ),
				'footer'    => 'You get this email because of your notification settings. Change them in the Hub under Settings → Notifications: ' . axh_portal_url( '#/settings' ),
			),
			(array) $mail
		);
		return AXH_Mail::send( $to, '[AdaptExec Hub] ' . $title, $args );
	}

	/** Everyone who manages the Hub. */
	public static function managers() {
		$out = array();
		foreach ( get_users( array( 'role__in' => array( 'administrator' ) ) ) as $u ) {
			if ( user_can( $u, 'axh_manage_all' ) ) {
				$out[] = $u;
			}
		}
		return $out;
	}

	/**
	 * Notify AdaptExec. Every manager gets it in the Hub. The email goes to
	 * the notification address from Settings (honouring that person's
	 * preferences if the address is theirs), and to any other manager who
	 * wants it.
	 *
	 * @param array $skip User ids already told about this.
	 */
	public static function managers_add( $type, $title, $body = '', $link = '', $lead_id = null, $mail = array(), $skip = array() ) {
		global $wpdb;
		$addr  = get_option( 'axh_notify_email', get_option( 'admin_email' ) );
		$addr  = is_email( $addr ) ? strtolower( $addr ) : '';
		$me    = get_current_user_id();
		$owner = $addr ? get_user_by( 'email', $addr ) : null;

		foreach ( self::managers() as $m ) {
			if ( (int) $m->ID === $me || in_array( (int) $m->ID, array_map( 'intval', $skip ), true ) ) {
				continue;
			}
			$wpdb->insert(
				AXH_DB::table( 'notifications' ),
				array(
					'user_id'    => (int) $m->ID,
					'type'       => sanitize_key( $type ),
					'title'      => mb_substr( sanitize_text_field( $title ), 0, 191 ),
					'body'       => sanitize_textarea_field( $body ),
					'link'       => mb_substr( (string) $link, 0, 191 ),
					'lead_id'    => $lead_id ? (int) $lead_id : null,
					'created_at' => current_time( 'mysql' ),
				)
			);
			do_action( 'axh_notification_added', (int) $m->ID, $type, $title, $body, $link, (int) $wpdb->insert_id ); // 1.10.0: phone push
			if ( strtolower( $m->user_email ) !== $addr && self::wants_email( $m->ID, $type ) ) {
				self::email( $m->user_email, $type, $title, $body, $link, $mail );
			}
		}
		if ( $addr ) {
			$skip_addr = $owner && ( (int) $owner->ID === $me || in_array( (int) $owner->ID, array_map( 'intval', $skip ), true ) );
			if ( ! $skip_addr && ( ! $owner || self::wants_email( $owner->ID, $type ) ) ) {
				self::email( $addr, $type, $title, $body, $link, $mail );
			}
		}
	}

	/** The latest notifications for the current user. */
	public static function latest( $limit = 40 ) {
		global $wpdb;
		$t = AXH_DB::table( 'notifications' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT id, type, title, body, link, lead_id, read_at, created_at FROM {$t} WHERE user_id = %d ORDER BY id DESC LIMIT %d", get_current_user_id(), (int) $limit ),
			ARRAY_A
		);
	}

	public static function unread() {
		global $wpdb;
		$t = AXH_DB::table( 'notifications' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE user_id = %d AND read_at IS NULL", get_current_user_id() ) );
	}

	/** Mark some (or all) of the current user's notifications read. */
	public static function mark_read( $ids = array(), $all = false ) {
		global $wpdb;
		$t   = AXH_DB::table( 'notifications' );
		$now = current_time( 'mysql' );
		if ( $all ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET read_at = %s WHERE user_id = %d AND read_at IS NULL", $now, get_current_user_id() ) );
			return;
		}
		foreach ( array_slice( array_map( 'intval', (array) $ids ), 0, 200 ) as $id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET read_at = %s WHERE id = %d AND user_id = %d AND read_at IS NULL", $now, $id, get_current_user_id() ) );
		}
	}

	/** Remove one of the current user's own notifications (1.7.2). */
	public static function remove( $id ) {
		global $wpdb;
		$me = get_current_user_id();
		if ( ! $me || ! (int) $id ) {
			return false;
		}
		return (bool) $wpdb->delete( AXH_DB::table( 'notifications' ), array( 'id' => (int) $id, 'user_id' => $me ) );
	}

	/** "Clear all read": removes the current user's read notifications only. */
	public static function clear_read() {
		global $wpdb;
		$me = get_current_user_id();
		if ( ! $me ) {
			return 0;
		}
		$t = AXH_DB::table( 'notifications' );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE user_id = %d AND read_at IS NOT NULL", $me ) ); // phpcs:ignore
	}

	/**
	 * The morning run: each person's follow-ups due today or overdue, and a
	 * team summary for AdaptExec. Runs once per day however often cron fires.
	 */
	public static function daily( $force = false ) {
		global $wpdb;
		$today = wp_date( 'Y-m-d' );
		if ( ! $force && get_option( 'axh_daily_ran' ) === $today ) {
			return array( 'skipped' => true );
		}
		update_option( 'axh_daily_ran', $today, false );

		$tasks = AXH_DB::table( 'tasks' );
		$leads = AXH_DB::table( 'leads' );
		$end   = $today . ' 23:59:59';
		$sent  = 0;

		// Run as nobody, so the "not about yourself" rule never skips anyone.
		$was = get_current_user_id();
		wp_set_current_user( 0 );

		$people = get_users( array( 'role__in' => array( AXH_ROLE, 'administrator' ) ) );
		foreach ( $people as $u ) {
			if ( ! user_can( $u, 'axh_use_hub' ) ) {
				continue;
			}
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.title, t.due_at, t.lead_id, l.company FROM {$tasks} t LEFT JOIN {$leads} l ON l.id = t.lead_id
					 WHERE t.rep_id = %d AND t.done_at IS NULL AND t.due_at IS NOT NULL AND t.due_at <= %s
					 ORDER BY t.due_at ASC LIMIT 30",
					(int) $u->ID,
					$end
				),
				ARRAY_A
			);
			// 1.11.0: the briefing also covers deck views, waiting enquiries
			// and deals with nothing scheduled.
			$extra = class_exists( 'AXH_P1' ) ? AXH_P1::digest_extra( $u->ID ) : array( 'sections' => array(), 'count' => 0, 'lines' => array() );
			if ( ! $rows && ! $extra['count'] ) {
				continue;
			}
			$overdue = 0;
			$list    = array();
			foreach ( $rows as $r ) {
				$late = substr( $r['due_at'], 0, 10 ) < $today;
				$overdue += $late ? 1 : 0;
				$list[]   = ( $late ? 'Overdue (' . mysql2date( 'M j', $r['due_at'] ) . '): ' : 'Today: ' ) . $r['title'] . ( $r['company'] ? ' — ' . $r['company'] : '' );
			}
			$n     = count( $rows );
			$title = $n
				? $n . ' follow-up' . ( 1 === $n ? '' : 's' ) . ' due' . ( $overdue ? ' (' . $overdue . ' overdue)' : ' today' )
				: 'Your morning briefing';
			if ( $n && $extra['lines'] ) {
				$title .= ' · ' . $extra['lines'][0];
			}
			$secs = $n ? array( array( 'title' => 'Your follow-ups', 'list' => $list ) ) : array();
			self::add(
				$u->ID,
				'followups_digest',
				$title,
				implode( "\n", array_merge( array_slice( $list, 0, 8 ), $extra['lines'] ) ),
				'#/tasks',
				null,
				array(
					'paras'    => array( 'Good morning. Here is what is waiting on you today.' ),
					'sections' => array_merge( $secs, $extra['sections'] ),
					'cta'      => array( $n ? 'Open your follow-ups' : 'Open the Sales Hub', axh_portal_url( $n ? '#/tasks' : '#/' ) ),
				)
			);
			$sent++;
		}

		// The team summary for AdaptExec.
		$since    = wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$new      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE created_at >= %s", $since ) );
		$regs     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE reg_status = 'pending'" ); // phpcs:ignore
		$wins     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE win_requested_at IS NOT NULL AND stage <> 'won'" ); // phpcs:ignore
		$owed     = AXH_DB::payouts_owed_total();
		$pending  = count( array_filter( AXH_Team::roster(), function ( $r ) { return in_array( $r['status'], array( 'invited', 'not_invited', 'invite_failed' ), true ); } ) );
		$rows     = array(
			'New leads (24h)'           => $new,
			'Registrations to decide'   => $regs,
			'Closed Won to confirm'     => $wins,
			'Commission due or overdue' => '$' . number_format( $owed, 2 ),
			'Reps not signed in yet'    => $pending,
			'Payout addresses to verify' => count( array_filter( AXH_Team::roster(), function ( $r ) { return $r['active'] && 'verify' === get_user_meta( $r['id'], 'axh_payee_status', true ); } ) ),
		);
		$body = sprintf( '%d new leads · %d registrations · %d Closed Won to confirm · $%s commission due', $new, $regs, $wins, number_format( $owed, 2 ) );
		foreach ( self::managers() as $m ) {
			self::add( $m->ID, 'team_summary', 'Team summary for ' . wp_date( 'l, M j' ), $body, '#/manage', null, array( 'rows' => $rows, 'paras' => array( 'Here is where the team stands this morning.' ) ) );
		}

		wp_set_current_user( $was );
		return array( 'digests' => $sent );
	}
}

/**
 * The sales team: invitations, sign-in status and account changes.
 */
class AXH_Team {

	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'after_password_reset', array( __CLASS__, 'on_password_set' ), 10, 1 );
		add_action( 'after_password_reset', array( __CLASS__, 'quiet_core_notice' ), 1, 1 );
		add_filter( 'password_reset_expiration', array( __CLASS__, 'invite_expiry' ) );
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'core_new_user_email' ), 10, 3 );
		add_filter( 'login_message', array( __CLASS__, 'login_message' ) );
		add_filter( 'retrieve_password_notification_email', array( __CLASS__, 'core_reset_email' ), 10, 4 );
	}

	/** "Forgot password?" for a rep: the branded sign-in email, same reset key. */
	public static function core_reset_email( $defaults, $key, $user_login, $user ) {
		if ( ! ( $user instanceof WP_User ) || ! self::is_rep_account( $user ) || ! self::is_active( $user ) ) {
			return $defaults;
		}
		$link = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' );
		$args = self::invite_args( $user, $link, 'signin' );
		$args['paras'][1] = 'Someone (hopefully you) asked to reset your AdaptExec Sales Hub password. Choose a new one with the button below, then sign in at ' . axh_portal_url();
		$args['footer']   = 'If you did not ask for this, ignore this email — your password stays the same.';
		$m = AXH_Mail::render( $args );
		AXH_Mail::set_alt( $m['text'] );
		$defaults['subject'] = 'Reset your AdaptExec Sales Hub password';
		$defaults['message'] = $m['html'];
		$defaults['headers'] = array( 'Content-Type: text/html; charset=UTF-8' );
		return $defaults;
	}

	/** A Hub rep, past or present — never an administrator. */
	public static function is_rep_account( $user ) {
		if ( ! $user || user_can( $user, 'axh_manage_all' ) || user_can( $user, 'edit_posts' ) ) {
			return false;
		}
		return in_array( AXH_ROLE, (array) $user->roles, true ) || get_user_meta( $user->ID, 'axh_was_rep', true );
	}

	public static function is_active( $user ) {
		return $user && in_array( AXH_ROLE, (array) $user->roles, true );
	}

	/** A fresh set-password link. Replaces any earlier link for this person. */
	public static function password_link( $user ) {
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return '';
		}
		return network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
	}

	/** How long an invitation link lasts. Ordinary reset links keep WordPress's 24 hours. */
	public static function invite_days() {
		return 7;
	}

	/**
	 * Let an invitation link live for a week. WordPress asks for one
	 * expiry for every reset key, so this checks whose key is being used and
	 * only lengthens it for a rep whose invitation is still open.
	 */
	public static function invite_expiry( $seconds ) {
		$login = '';
		if ( isset( $_GET['login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = sanitize_user( wp_unslash( $_GET['login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( defined( 'COOKIEHASH' ) && isset( $_COOKIE[ 'wp-resetpass-' . COOKIEHASH ] ) ) {
			$parts = explode( ':', sanitize_text_field( wp_unslash( $_COOKIE[ 'wp-resetpass-' . COOKIEHASH ] ) ), 2 );
			$login = sanitize_user( $parts[0] );
		} elseif ( isset( $_POST['rp_key'], $_POST['user_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$login = sanitize_user( wp_unslash( $_POST['user_login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user && get_user_meta( $user->ID, 'axh_invite_open', true ) && self::is_rep_account( $user ) ) {
				return max( (int) $seconds, self::invite_days() * DAY_IN_SECONDS );
			}
		}
		return $seconds;
	}

	/**
	 * The invitation (or a fresh sign-in link) as email arguments.
	 *
	 * @param string $kind "invite" or "signin".
	 */
	public static function invite_args( $user, $link, $kind = 'invite' ) {
		$first   = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
		$first   = $first ? strtok( $first, ' ' ) : strtok( $user->display_name, ' ' );
		$c       = AXH_Mail::contact();
		$hub     = axh_portal_url();
		$payout  = trim( (string) get_option( 'axh_payout_note', '' ) );
		$tiers   = array();
		foreach ( AXH_Install::commission_tiers() as $t ) {
			$tiers[] = $t['label'] . ': ' . rtrim( rtrim( number_format( $t['rate'], 2 ), '0' ), '.' ) . '%';
		}
		$override = (float) get_user_meta( $user->ID, 'axh_rate_override', true );

		if ( 'signin' === $kind ) {
			return array(
				'heading'   => 'Your sign-in link for the Sales Hub',
				'preheader' => 'Set a new password and you are back in.',
				'paras'     => array(
					'Hi ' . $first . ',',
					$c['name'] . ' sent you a link to set a new password for the AdaptExec Sales Hub. Choose a password, then sign in at ' . $hub,
				),
				'rows'      => array( 'Your username' => $user->user_login, 'Your email' => $user->user_email, 'Sign in at' => $hub ),
				'cta_first' => true,
				'cta'       => array( 'Set my password', $link ),
				'cta_note'  => 'This link works once and expires in 24 hours. If it has expired, use "Forgot password?" on the sign-in page.',
				'footer'    => 'You received this because ' . $c['name'] . ' asked the Hub to send you a sign-in link. If you were not expecting it, you can ignore it — your password has not changed.',
			);
		}

		$pay = array(
			$override > 0
				? 'Your commission rate is ' . rtrim( rtrim( number_format( $override, 2 ), '0' ), '.' ) . '% of the whole contract.'
				: 'One rate applies to the whole contract, set by its size — ' . implode( '; ', $tiers ) . '.',
			'Commission is earned in two halves — one when the client\'s deposit has fully cleared, one when their final payment has fully cleared — and paid by check to the address you give in the Hub after the funds clear. There is no fixed pay date. The rate locks when AdaptExec confirms the deal Closed Won.',
			'You work as an independent contractor. Upload a Form W-9 in the Hub before your first payout; where required, AdaptExec sends a Form 1099-NEC each January. Nothing is withheld for taxes.',
			$payout ? 'Payment method: ' . $payout : 'Payment method: check, mailed to the payout address you add in the Hub.',
			'Your Independent Contractor Agreement governs. You read and e-sign it in the Hub under Onboarding, and can view or print your signed copy there any time.',
		);

		return array(
			'heading'   => 'Welcome to the AdaptExec Sales Hub, ' . $first,
			'preheader' => 'Set your password and sign in — here is how to get rolling this week.',
			'paras'     => array(
				$c['name'] . ' has set up your account on the AdaptExec Sales Hub: your pipeline, lead registration, presentations, lead forms and commission tracking, all in one place.',
				'Start by choosing your password.',
			),
			'cta_first' => true,
			'cta'       => array( 'Set my password', $link ),
			'cta_note'  => 'This link is just for you, works once, and expires in ' . self::invite_days() . ' days. If it expires, ask ' . $c['name'] . ' to resend your invitation or use "Forgot password?" on the sign-in page.',
			'rows'      => array( 'Sign in at' => $hub, 'Your username' => $user->user_login, 'Your email' => $user->user_email ),
			'sections'  => array(
				array(
					'title' => 'Your first week',
					'steps' => array(
						'Set your password, sign in at ' . $hub . ' and bookmark it (on your phone too).',
						'Finish onboarding in the Hub (about 20–25 minutes; the agreement is a 15–20 minute read): e-sign your Independent Contractor Agreement, upload your W-9 and add the mailing address for your commission checks.',
						'Read "How you get paid" under Earnings.',
						'Watch the 38-second promo and open the demo site for your first industry (Demo sites tab).',
						'Add your first prospect, then register it before your first real conversation — registration is what protects your commission.',
						'Make your own lead form and save its link and QR code.',
						'Rehearse a deck in Present, then share one with a prospect as a link.',
						'Run discovery with a prospect yourself: fill in the questionnaire together (or send their link), then press Request a quote so Adam can write the proposal.',
					),
				),
				array(
					'title' => 'How you get paid',
					'list'  => $pay,
				),
				array(
					'title' => 'Need a hand?',
					'paras' => array( 'Call or text ' . $c['name'] . ' at ' . $c['phone'] . ' or email ' . $c['email'] . '. Turn on notifications in the Hub under Settings so you hear about new leads and approvals right away.' ),
				),
			),
			'footer'    => 'You received this because ' . $c['name'] . ' added you to the AdaptExec Sales Hub. If you were not expecting it, reply and let us know.',
		);
	}

	/**
	 * Send (or preview) an invitation or sign-in link.
	 *
	 * @param WP_User $user
	 * @param string  $kind  "invite" or "signin".
	 * @param string  $mode  "send", "preview" (build only) or "test" (send a copy to the current user, with a dummy link).
	 * @return array ok, emailed, link (when a real link was made), html/text (preview).
	 */
	public static function send_invite( $user, $kind = 'invite', $mode = 'send' ) {
		if ( 'send' !== $mode ) {
			$dummy = add_query_arg( 'axh_preview', '1', axh_portal_url() );
			$args  = self::invite_args( $user, $dummy, $kind );
			$args['cta_note'] = 'PREVIEW — this button does not set a password. ' . $args['cta_note'];
			if ( 'preview' === $mode ) {
				$m = AXH_Mail::render( $args );
				return array( 'ok' => true, 'subject' => self::subject( $kind ), 'html' => $m['html'], 'text' => $m['text'] );
			}
			$me = wp_get_current_user();
			$ok = is_email( $me->user_email ) && AXH_Mail::send( $me->user_email, '[Test copy] ' . self::subject( $kind ), $args );
			return array( 'ok' => (bool) $ok, 'emailed' => (bool) $ok, 'to' => $me->user_email );
		}

		if ( 'invite' === $kind ) {
			update_user_meta( $user->ID, 'axh_invite_open', 1 );
		}
		$link = self::password_link( $user );
		if ( ! $link ) {
			return array( 'ok' => false, 'emailed' => false, 'message' => 'Could not create a sign-in link.' );
		}
		$reply = AXH_Mail::contact();
		$ok    = AXH_Mail::send( $user->user_email, self::subject( $kind ), self::invite_args( $user, $link, $kind ), array( 'Reply-To: ' . $reply['name'] . ' <' . $reply['email'] . '>' ) );

		$now = current_time( 'mysql' );
		if ( 'invite' === $kind ) {
			update_user_meta( $user->ID, 'axh_invited_at', $now );
			update_user_meta( $user->ID, 'axh_invite_count', (int) get_user_meta( $user->ID, 'axh_invite_count', true ) + 1 );
		}
		if ( $ok ) {
			delete_user_meta( $user->ID, 'axh_invite_failed_at' );
		} else {
			update_user_meta( $user->ID, 'axh_invite_failed_at', $now );
		}
		return array( 'ok' => true, 'emailed' => $ok, 'link' => $link );
	}

	public static function subject( $kind ) {
		return 'signin' === $kind ? 'Your AdaptExec Sales Hub sign-in link' : 'You\'re invited to the AdaptExec Sales Hub';
	}

	/**
	 * When a rep is added from Users → Add New with "send the new user an
	 * email" ticked, send our invitation instead of WordPress's bare one.
	 */
	public static function core_new_user_email( $email, $user, $blogname ) {
		if ( ! self::is_rep_account( $user ) ) {
			return $email;
		}
		update_user_meta( $user->ID, 'axh_was_rep', 1 );
		update_user_meta( $user->ID, 'axh_invite_open', 1 );
		$link = self::password_link( $user );
		if ( ! $link ) {
			return $email;
		}
		$m = AXH_Mail::render( self::invite_args( $user, $link, 'invite' ) );
		AXH_Mail::set_alt( $m['text'] );
		update_user_meta( $user->ID, 'axh_invited_at', current_time( 'mysql' ) );
		update_user_meta( $user->ID, 'axh_invite_count', (int) get_user_meta( $user->ID, 'axh_invite_count', true ) + 1 );
		$email['subject'] = self::subject( 'invite' );
		$email['message'] = $m['html'];
		$email['headers'] = array( 'Content-Type: text/html; charset=UTF-8' );
		return $email;
	}

	/** Last sign-in, and the first one counts as activation. */
	public static function on_login( $login, $user ) {
		if ( ! ( $user instanceof WP_User ) || ! user_can( $user, 'axh_use_hub' ) ) {
			return;
		}
		update_user_meta( $user->ID, 'axh_last_login', current_time( 'mysql' ) );
		if ( self::is_rep_account( $user ) && ! get_user_meta( $user->ID, 'axh_activated_at', true ) ) {
			update_user_meta( $user->ID, 'axh_activated_at', current_time( 'mysql' ) );
			delete_user_meta( $user->ID, 'axh_invite_open' );
			$was = get_current_user_id();
			wp_set_current_user( 0 );
			AXH_Notify::managers_add(
				'rep_activated',
				$user->display_name . ' signed in to the Hub for the first time',
				$user->display_name . ' (' . $user->user_email . ') has set up their account and signed in.',
				'#/manage/rep/' . $user->ID
			);
			wp_set_current_user( $was );
		}
	}

	/** Choosing a password closes the invitation. */
	public static function on_password_set( $user ) {
		if ( $user instanceof WP_User && self::is_rep_account( $user ) ) {
			update_user_meta( $user->ID, 'axh_password_set_at', current_time( 'mysql' ) );
			delete_user_meta( $user->ID, 'axh_invite_open' );
		}
	}

	/** After a rep sets their password, point them at the Hub rather than wp-login. */
	/**
	 * A rep setting their password from the invitation already sends AdaptExec
	 * a "signed in for the first time" note; WordPress's own "Password changed"
	 * email to the site admin on top of it is noise. Other users keep it.
	 */
	public static function quiet_core_notice( $user ) {
		if ( $user && user_can( $user, 'axh_use_hub' ) && ! user_can( $user, 'axh_manage_all' ) ) {
			remove_action( 'after_password_reset', 'wp_password_change_notification' );
		}
	}

	public static function login_message( $message ) {
		if ( isset( $_GET['action'] ) && 'resetpass' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message .= '<p class="message">Sales reps: sign in at <a href="' . esc_url( axh_portal_url() ) . '">' . esc_html( axh_portal_url() ) . '</a>.</p>';
		}
		return $message;
	}

	/** Where someone is with their account. */
	public static function status( $user ) {
		if ( ! self::is_active( $user ) ) {
			return 'deactivated';
		}
		if ( get_user_meta( $user->ID, 'axh_activated_at', true ) || get_user_meta( $user->ID, 'axh_last_login', true ) ) {
			return 'active';
		}
		if ( get_user_meta( $user->ID, 'axh_invite_failed_at', true ) ) {
			return 'invite_failed';
		}
		if ( get_user_meta( $user->ID, 'axh_invited_at', true ) ) {
			return 'invited';
		}
		if ( get_user_meta( $user->ID, 'axh_password_set_at', true ) ) {
			return 'password_set';
		}
		return 'not_invited';
	}

	/** Profile fields a manager can edit. */
	public static function profile( $user ) {
		$ind = get_user_meta( $user->ID, 'axh_industries', true );
		return array(
			'phone'         => (string) get_user_meta( $user->ID, 'axh_phone', true ),
			'territory'     => (string) get_user_meta( $user->ID, 'axh_territory', true ),
			'industries'    => is_array( $ind ) ? array_values( $ind ) : array(),
			'rate_override' => (string) get_user_meta( $user->ID, 'axh_rate_override', true ),
			'admin_note'    => (string) get_user_meta( $user->ID, 'axh_admin_note', true ),
		);
	}

	/** Everyone on the team, active or not, with their account status. */
	public static function roster() {
		$users = get_users(
			array(
				'orderby'    => 'display_name',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array( 'key' => 'axh_was_rep', 'compare' => 'EXISTS' ),
					array( 'key' => 'axh_deactivated_at', 'compare' => 'EXISTS' ),
				),
			)
		);
		foreach ( get_users( array( 'role__in' => array( AXH_ROLE ) ) ) as $u ) {
			$users[] = $u;
			if ( ! get_user_meta( $u->ID, 'axh_was_rep', true ) ) {
				update_user_meta( $u->ID, 'axh_was_rep', 1 );
			}
		}
		$seen = array();
		$out  = array();
		foreach ( $users as $u ) {
			if ( isset( $seen[ $u->ID ] ) || ! self::is_rep_account( $u ) ) {
				continue;
			}
			$seen[ $u->ID ] = true;
			$out[]          = array_merge(
				array(
					'id'             => (int) $u->ID,
					'name'           => $u->display_name,
					'email'          => $u->user_email,
					'login'          => $u->user_login,
					'status'         => self::status( $u ),
					'active'         => self::is_active( $u ),
					'registered'     => get_date_from_gmt( $u->user_registered ),
					'invited_at'     => (string) get_user_meta( $u->ID, 'axh_invited_at', true ),
					'invite_count'   => (int) get_user_meta( $u->ID, 'axh_invite_count', true ),
					'invite_failed'  => (string) get_user_meta( $u->ID, 'axh_invite_failed_at', true ),
					'activated_at'   => (string) get_user_meta( $u->ID, 'axh_activated_at', true ),
					'last_login'     => (string) get_user_meta( $u->ID, 'axh_last_login', true ),
					'deactivated_at' => (string) get_user_meta( $u->ID, 'axh_deactivated_at', true ),
				),
				self::profile( $u )
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return ( (int) $b['active'] - (int) $a['active'] ) ?: strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	/** Save a manager's edits to a rep's profile. */
	public static function save_profile( $user, $data ) {
		$update = array( 'ID' => $user->ID );
		if ( isset( $data['name'] ) && '' !== trim( (string) $data['name'] ) ) {
			$update['display_name'] = sanitize_text_field( $data['name'] );
			$update['first_name']   = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['email'] ) && sanitize_email( $data['email'] ) !== $user->user_email ) {
			$email = sanitize_email( $data['email'] );
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'axh_bad_email', 'Enter a valid email address.', array( 'status' => 400 ) );
			}
			$other = email_exists( $email );
			if ( $other && (int) $other !== (int) $user->ID ) {
				return new WP_Error( 'axh_exists', 'That email already belongs to another account.', array( 'status' => 400 ) );
			}
			$update['user_email'] = $email;
		}
		if ( count( $update ) > 1 ) {
			$r = wp_update_user( $update );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( 'axh_failed', $r->get_error_message(), array( 'status' => 400 ) );
			}
		}
		if ( isset( $data['phone'] ) ) {
			update_user_meta( $user->ID, 'axh_phone', mb_substr( sanitize_text_field( $data['phone'] ), 0, 40 ) );
		}
		if ( isset( $data['territory'] ) ) {
			update_user_meta( $user->ID, 'axh_territory', mb_substr( sanitize_text_field( $data['territory'] ), 0, 191 ) );
		}
		if ( isset( $data['industries'] ) ) {
			$valid = AXH_Install::industries();
			update_user_meta( $user->ID, 'axh_industries', array_values( array_intersect( $valid, array_map( 'sanitize_text_field', (array) $data['industries'] ) ) ) );
		}
		if ( isset( $data['admin_note'] ) ) {
			update_user_meta( $user->ID, 'axh_admin_note', sanitize_textarea_field( $data['admin_note'] ) );
		}
		if ( array_key_exists( 'rate_override', $data ) ) {
			$rate = trim( (string) $data['rate_override'] );
			if ( '' === $rate ) {
				delete_user_meta( $user->ID, 'axh_rate_override' );
			} else {
				$rate = (float) $rate;
				if ( $rate <= 0 || $rate > 50 ) {
					return new WP_Error( 'axh_bad_rate', 'Enter a rate between 0 and 50%, or leave it blank for the standard schedule.', array( 'status' => 400 ) );
				}
				update_user_meta( $user->ID, 'axh_rate_override', round( $rate, 2 ) );
			}
		}
		return true;
	}

	/** Take Hub access away. Their leads, history and commission stay put. */
	public static function deactivate( $user ) {
		update_user_meta( $user->ID, 'axh_was_rep', 1 );
		update_user_meta( $user->ID, 'axh_deactivated_at', current_time( 'mysql' ) );
		delete_user_meta( $user->ID, 'axh_invite_open' );
		$user->set_role( 'subscriber' );
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		}
	}

	public static function reactivate( $user ) {
		$user->set_role( AXH_ROLE );
		delete_user_meta( $user->ID, 'axh_deactivated_at' );
	}
}

/* =========================================================================
   Rep onboarding (1.6.0): the Independent Contractor Agreement with
   e-signature, the W-9, and where commission checks are mailed.

   Lives in this file for the same reason as AXH_Mail and AXH_Team above:
   the plugin editor can change files but not add them.

   Sensitive files (completed W-9s, signature images, signed copies) never
   sit in a public folder in the clear. They are encrypted with libsodium
   (XSalsa20-Poly1305 secretbox) under a key derived from this site's
   wp-config salts, written with random names to a private folder that has
   its own deny rules, and served only through authenticated REST routes.
   ========================================================================= */

class AXH_Onboard {

	/** Mixed into the key derivation so this key is used for nothing else. */
	const KEY_CONTEXT = 'adaptexec-hub|private-files|v1';

	const W9_MAX       = 10485760; // 10 MB

	/**
	 * The real W-9 limit in MB: 10, or less when this server's PHP upload
	 * limit (upload_max_filesize / post_max_size) is lower, so the Hub never
	 * promises a size the server will then refuse.
	 */
	public static function w9_max_mb() {
		$server = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 0;
		$bytes  = $server > 0 ? min( self::W9_MAX, $server ) : self::W9_MAX;
		return max( 1, (int) floor( $bytes / 1048576 ) );
	}
	const IRS_W9_PDF   = 'https://www.irs.gov/pub/irs-pdf/fw9.pdf';
	const IRS_W9_INFO  = 'https://www.irs.gov/forms-pubs/about-form-w-9';
	const IRS_W9_REV   = 'Rev. March 2024';
	const SIG_MAX      = 400000;   // bytes of signature PNG
	const DOC_TITLE    = 'Independent Contractor Sales Representative Agreement';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'axh_daily', array( __CLASS__, 'reminders' ), 20 );
	}

	/* ------------------------------------------------------------- settings */

	public static function settings() {
		$name = trim( (string) get_option( 'axh_company_legal_name', '' ) );
		$sign = trim( (string) get_option( 'axh_signer', '' ) );
		return array(
			'company_legal_name' => $name ? $name : 'AdaptExec',
			'company_address'    => trim( (string) get_option( 'axh_company_address', '' ) ),
			'signer'             => $sign ? $sign : 'Adam Neuerburg, Owner',
			'reminders'          => '0' !== (string) get_option( 'axh_onb_reminders', '1' ),
		);
	}

	public static function save_settings( $in ) {
		$in = (array) $in;
		if ( isset( $in['company_legal_name'] ) ) {
			$v = mb_substr( sanitize_text_field( $in['company_legal_name'] ), 0, 120 );
			update_option( 'axh_company_legal_name', '' === trim( $v ) ? 'AdaptExec' : $v, false );
		}
		if ( isset( $in['company_address'] ) ) {
			$v = mb_substr( sanitize_textarea_field( $in['company_address'] ), 0, 300 );
			update_option( 'axh_company_address', trim( preg_replace( "/\n{2,}/", "\n", $v ) ), false );
		}
		if ( isset( $in['signer'] ) ) {
			$v = mb_substr( sanitize_text_field( $in['signer'] ), 0, 120 );
			update_option( 'axh_signer', '' === trim( $v ) ? 'Adam Neuerburg, Owner' : $v, false );
		}
		if ( isset( $in['reminders'] ) ) {
			update_option( 'axh_onb_reminders', empty( $in['reminders'] ) ? '0' : '1', false );
		}
		return self::settings();
	}

	/* ------------------------------------------------------------ agreement */

	/**
	 * The agreement as published, one entry per version. The text is a
	 * template: {{company}} and {{notice_clause}} are filled from Settings, and
	 * the SHA-256 is taken over the filled-in text a rep actually signs.
	 */
	public static function versions() {
		$v = get_option( 'axh_agreement_versions', array() );
		if ( ! is_array( $v ) || ! $v ) {
			$v = array(
				array(
					'version'    => '1.0',
					'effective'  => wp_date( 'Y-m-d' ),
					'template'   => self::default_template(),
					'created_at' => current_time( 'mysql' ),
					'created_by' => 0,
					'note'       => 'First version, built into the Hub.',
				),
			);
			update_option( 'axh_agreement_versions', $v, false );
		}
		return $v;
	}

	/** Fill a template from Settings. */
	public static function resolve( $template, $settings = null ) {
		$s      = $settings ? $settings : self::settings();
		$addr   = trim( preg_replace( '/\s*\n\s*/', ', ', (string) $s['company_address'] ) );
		$clause = 'Notices to AdaptExec must be sent by email to the AdaptExec contact email address shown in the Hub'
			. ( $addr ? ', or by mail to ' . $s['company_legal_name'] . ', ' . $addr : '' ) . '.';
		$text = strtr(
			(string) $template,
			array(
				'{{company}}'       => $s['company_legal_name'],
				'{{notice_clause}}' => $clause,
			)
		);
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $text ) ) . "\n";
	}

	/** The agreement in force now: version, date, exact text and its hash. */
	public static function current() {
		$all  = self::versions();
		$v    = end( $all );
		$text = self::resolve( $v['template'] );
		return array(
			'version'   => (string) $v['version'],
			'effective' => (string) $v['effective'],
			'text'      => $text,
			'hash'      => hash( 'sha256', $text ),
			'title'     => self::DOC_TITLE,
		);
	}

	/** Manager: publish a new version of the text. Reps are asked to re-sign. */
	public static function publish_version( $template, $version = '', $note = '' ) {
		$template = trim( str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( (string) $template ) ) );
		$template = wp_kses( $template, array() ); // plain text only
		if ( mb_strlen( $template ) < 1500 ) {
			return new WP_Error( 'axh_short', 'That is too short to be the agreement. Paste the whole text.', array( 'status' => 400 ) );
		}
		if ( mb_strlen( $template ) > 120000 ) {
			return new WP_Error( 'axh_long', 'That text is too long (120,000 characters at most).', array( 'status' => 400 ) );
		}
		$all  = self::versions();
		$last = end( $all );
		if ( self::resolve( $last['template'] ) === self::resolve( $template ) ) {
			return new WP_Error( 'axh_same', 'Nothing changed — the text is identical to version ' . $last['version'] . '.', array( 'status' => 400 ) );
		}
		$taken   = wp_list_pluck( $all, 'version' );
		$version = preg_match( '/^\d{1,3}(\.\d{1,3}){0,2}$/', (string) $version ) ? (string) $version : '';
		if ( ! $version || in_array( $version, $taken, true ) || version_compare( $version, $last['version'], '<=' ) ) {
			$p       = explode( '.', $last['version'] );
			$version = (int) $p[0] . '.' . ( (int) ( $p[1] ?? 0 ) + 1 );
		}
		$all[] = array(
			'version'    => $version,
			'effective'  => wp_date( 'Y-m-d' ),
			'template'   => $template,
			'created_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id(),
			'note'       => mb_substr( sanitize_text_field( $note ), 0, 300 ),
		);
		update_option( 'axh_agreement_versions', $all, false );
		self::audit( 0, 'agreement_version', 'Published agreement version ' . $version );
		return self::current();
	}

	/**
	 * The built-in agreement. Plain text with light markup:
	 *   "## 3. Title"  a section heading
	 *   "3.4 Title."   a numbered clause (the lead-in is shown in bold)
	 *   "- item"       a list item
	 * Placeholders: {{company}}, {{notice_clause}}.
	 */
	public static function default_template() {
		return <<<'AXHTEXT'
This Independent Contractor Sales Representative Agreement (the "Agreement") is between {{company}} ("AdaptExec," "we" or "us") and the individual or business identified as the Representative in the signature block of this Agreement ("Representative" or "you"). It takes effect on the date you sign it electronically in the AdaptExec Sales Hub (the "Effective Date").

## 1. Independent Contractor Relationship

1.1 Independent contractor. You are an independent contractor. You are not an employee, partner, joint venturer, franchisee or legal agent of AdaptExec, and nothing in this Agreement creates an employment relationship.

1.2 You control how you work. You decide your own hours, location, methods and means of doing the work, subject only to this Agreement and the law. AdaptExec does not set a schedule, require minimum hours or impose quotas. Any goals, targets or suggestions shared in the Hub are informational only.

1.3 Your own tools and expenses. You provide your own phone, computer, internet service, vehicle and other tools, and you pay your own business expenses (for example travel, meals, mileage, marketing and licenses), unless AdaptExec agrees in writing, in advance, to reimburse a specific expense.

1.4 No employee benefits. You are not eligible for, and you waive any claim to, salary, wages, overtime, health insurance, retirement plans, paid time off, workers' compensation, unemployment benefits or any other employee benefit from AdaptExec. Your only compensation is the commission described in Section 3.

1.5 Taxes and Form 1099-NEC. AdaptExec does not withhold income tax, Social Security, Medicare or any other tax from payments to you. You are solely responsible for reporting and paying all taxes on what you are paid. AdaptExec will report payments to you on IRS Form 1099-NEC (or any successor form) where the law requires it.

1.6 Form W-9 required before any payment. You must provide a complete and signed IRS Form W-9 through the Hub before AdaptExec will issue any payment to you, and you must keep it current. AdaptExec may hold payments until a valid W-9 is on file and may apply backup withholding when the law requires it.

1.7 Your business. You may work as a sole proprietor or through your own business entity, and you are responsible for any business licenses or registrations your work requires where you operate. Carrying your own business insurance is recommended but not required.

1.8 Other work. You may sell other products and services and work for others while this Agreement is in effect, as long as you comply with Sections 5, 7 and 9. This Agreement does not contain a non-compete.

## 2. What You Do

2.1 Appointment. AdaptExec appoints you, on a non-exclusive basis, to find prospective clients for, and promote the sale of, AdaptExec's services, which include custom websites, custom CRMs and web applications, search engine optimization, marketing, branding and design, Core Values Index coaching and other business services AdaptExec offers from time to time (the "Services"). You will do this using the AdaptExec Sales Hub (the "Hub") and AdaptExec-approved materials.

2.2 Non-exclusive; no territory. Your appointment is non-exclusive. AdaptExec may appoint other representatives anywhere and may sell directly to anyone, except that it will respect a prospect that is registered to you under Section 2.4. Any territory or industry focus noted in the Hub is for coordination only and does not give you exclusive rights.

2.3 Using the Hub. You will record your prospects, activity and deals in the Hub and keep that information accurate and up to date. Your Hub account is personal: do not share your login or let anyone else use your account. AdaptExec may change, suspend or discontinue Hub features at any time.

2.4 Lead registration. To protect your commission on a prospect, you must register the prospect in the Hub and receive AdaptExec's confirmation before your first substantive sales contact with them. Registration is first come, first served, decided by AdaptExec from the Hub's records. AdaptExec may decline a registration, for example when the prospect is an existing or former client, is already in discussions with AdaptExec, is a lead AdaptExec generated, or is registered to someone else (each a "House Account"). AdaptExec may release a registration that has had no meaningful recorded activity for 90 consecutive days after giving you notice in the Hub.

2.5 Pricing and proposals. AdaptExec scopes and prices every project. You may share only the prices, ranges, offers and terms that appear in AdaptExec-approved materials or in the Hub's tools (such as a published estimator), and you must present any figure as an estimate that is subject to AdaptExec's written proposal. Only a written proposal or contract issued by AdaptExec sets a client's price and terms.

2.6 Acceptance of clients. AdaptExec may accept or decline any prospect, proposal or project in its sole discretion and is not liable to you for declining, delaying or not completing any sale or project.

## 3. Commission

3.1 Commission rate. For each client deal that is registered to you under Section 2.4 and confirmed "Closed Won" by AdaptExec in the Hub, you earn a commission equal to a percentage of the Contract Value. The percentage comes from the commission schedule displayed in the Hub on the Earnings page (the "Commission Schedule") in effect on the date the client signs. One rate applies to the whole Contract Value and is set by its size. If AdaptExec sets a different rate for you in writing in the Hub, that rate applies instead. The Commission Schedule in effect when you sign this Agreement is attached to your signed copy for reference.

3.2 Contract Value. "Contract Value" means the total fees the client agrees to pay AdaptExec for the Services under its signed contract, as confirmed by AdaptExec in the Hub when it marks the deal Closed Won. It excludes sales and similar taxes; third-party and pass-through costs (such as hosting, domains, software licenses, stock assets, advertising spend, printing and postage); shipping; and late fees and interest. If the Contract Value later changes (for example through a change order, discount, reduction or cancellation), AdaptExec will adjust the commission, and the rate may be re-tiered to the adjusted Contract Value.

3.3 When commission is earned. Commission is earned only when, and only to the extent that, AdaptExec actually receives the client's payment for the deal and that payment has fully cleared. "Fully cleared" means the funds are final and available in AdaptExec's account and are no longer subject to any chargeback, reversal, dispute, return, stop-payment or recall. The Hub tracks commission in two halves: one half is earned when the client's deposit has fully cleared, and the other half when the client's final payment has fully cleared. If a client pays in other installments, AdaptExec may allocate commission in proportion to the payments that have fully cleared.

3.4 How and when you are paid. Earned commission is paid by check mailed to the payout address on file for you in the Hub, unless AdaptExec tells you it will use another method. AdaptExec pays earned commission within a reasonable time after the client's payment has fully cleared. There is no fixed payment date or schedule, and AdaptExec may combine several commissions into one payment. No commission is due or payable before the conditions in Section 3.3 are met.

3.5 No commission on certain amounts. No commission is earned on any amount that is (a) refunded, credited, reversed, disputed, charged back or returned, including a returned or bounced check; (b) fraudulent or unauthorized, or paid with funds obtained by fraud; (c) not actually collected by AdaptExec for any reason; or (d) excluded from Contract Value under Section 3.2.

3.6 Clawback and offset. If AdaptExec pays you commission on an amount that is later refunded, reversed, disputed, charged back, returned, found to be fraudulent or otherwise not kept by AdaptExec, that commission was not earned and you must repay it. AdaptExec may recover it by deducting it from any commission owed to you now or later. If no further commission is expected (for example after this Agreement ends), you will repay it within 30 days of AdaptExec's written request.

3.7 Holds while AdaptExec verifies. AdaptExec may withhold or delay any commission payment, without interest, while it investigates or verifies (a) a transaction it reasonably believes may be suspicious, fraudulent, disputed or at risk of reversal; (b) any change to your payee or payout information, including a new mailing address; or (c) your compliance with this Agreement. AdaptExec will release commission confirmed to be earned once the matter is resolved.

3.8 Misconduct forfeits commission. No commission is earned on any deal obtained or influenced by misrepresentation, unauthorized promises or pricing, self-dealing or kickbacks; on fake, straw or related-party clients created to generate commission; on deals with yourself, your own business or your immediate family unless AdaptExec approves in writing in advance; or on any deal involving a material violation of this Agreement or of AdaptExec's written policies. AdaptExec may recover commission already paid on such a deal under Section 3.6.

3.9 AdaptExec's records control. AdaptExec's accounting records and the Hub determine whether, when and how much a client paid, whether a payment has fully cleared, and the commission owed. Commission figures shown in the Hub are a record of what this Agreement provides for, not a guarantee or a statement of account. If you believe a commission calculation is wrong, tell AdaptExec in writing within 90 days of the payment or Hub entry concerned and AdaptExec will review it in good faith.

3.10 Split deals and House Accounts. If more than one representative contributed to a deal, or a deal involves a House Account, an existing client or a lead AdaptExec generated, AdaptExec will decide in its reasonable discretion whether any commission is owed, to whom, and how it is divided. AdaptExec's decision is final.

3.11 Renewals and additional work. Unless AdaptExec confirms otherwise in writing, including in the Hub, commission applies only to the Contract Value of the deal confirmed Closed Won. It does not apply to renewals, recurring or monthly plans, maintenance, hosting, add-ons or later projects for the same client unless AdaptExec registers and confirms that later work as a separate Closed Won deal for you.

3.12 Changes to the Commission Schedule. AdaptExec may change the Commission Schedule by posting the new schedule in the Hub with notice to you. A change applies only to deals that clients sign after the change takes effect. The rate locked onto a deal when AdaptExec confirms it Closed Won does not change because of a later change to the schedule.

3.13 Keeping your payout information current. You are responsible for keeping your payee name, mailing address and Form W-9 current in the Hub. Payout information can be changed only in the Hub; AdaptExec will not act on change requests made by email, text message or phone. AdaptExec may verify any change, for example by contacting you at the email address or phone number on file, before sending the next check. AdaptExec is not responsible for a payment sent to the address on file before you changed it, or for delays in the mail. If a check is lost, AdaptExec will reissue it after confirming that the original has not been cashed.

3.14 No advances or draws. You will not receive draws, advances, guaranteed minimums or expense reimbursements unless AdaptExec agrees in writing.

## 4. Authority and Representations

4.1 No authority to bind AdaptExec. You have no authority to sign contracts, accept orders, make commitments, grant discounts, extend credit, collect payments or incur any obligation on AdaptExec's behalf, and you will not suggest that you do. Clients pay AdaptExec directly; you will never accept a payment from a client.

4.2 Honest, accurate representations. You will describe AdaptExec and its Services honestly and accurately, using AdaptExec-approved materials. You will not make any false, misleading or unauthorized statement or promise, including about price, timelines, guarantees, features, results (such as search rankings, traffic, leads or revenue), or AdaptExec's experience or clients.

4.3 How you identify yourself. You will make clear that you are an independent sales representative for AdaptExec and will not present yourself as an AdaptExec employee, officer or owner.

## 5. Conduct and Compliance with Laws

5.1 Laws. You will comply with all laws that apply to your work, including consumer-protection, privacy, anti-spam, telemarketing and text-messaging laws.

5.2 Calls and text messages. You will comply with the Telephone Consumer Protection Act (TCPA), the Telemarketing Sales Rule, the National Do Not Call Registry and their state equivalents. You will not use autodialers, prerecorded or artificial-voice calls ("robocalls") or mass text messaging, and you will not call or text anyone without the consent the law requires. You will honor every opt-out and do-not-call request immediately and let AdaptExec know about it.

5.3 Email. You will comply with the CAN-SPAM Act and similar laws: no false or misleading headers or subject lines, identify yourself and include a valid postal address where required, and honor unsubscribe requests promptly. You will not send unsolicited bulk email ("spam"), and you will not use purchased or scraped lists in AdaptExec's name.

5.4 Advertising and AdaptExec's name. You will not run paid advertising, register domains, create websites, social media profiles or business listings, or post publicly on AdaptExec's behalf using AdaptExec's name, logo or trademarks without AdaptExec's prior written approval.

5.5 Professional conduct. You will treat prospects and clients professionally and respectfully, will not engage in harassment, discrimination, bribery or deceptive practices, and will promptly tell AdaptExec about any complaint from a prospect or client.

5.6 Personal information. You will protect any personal information about prospects and clients that you collect, use it only to perform this Agreement, and keep it in the Hub rather than in personal systems wherever practical.

## 6. Ownership of Leads, Client Relationships and Materials

6.1 Leads and clients belong to AdaptExec. All leads, prospects and client relationships you develop or receive under this Agreement, all client and prospect information entered in the Hub, and all client contracts belong to AdaptExec. Your rights in them are limited to the commission described in Section 3.

6.2 AdaptExec Materials and the Hub. AdaptExec owns the Hub and its software and content, sales decks, demo sites, forms, pricing, scripts, templates, case studies, trademarks and all other materials it provides ("AdaptExec Materials"). AdaptExec gives you a limited, revocable, non-transferable license to use AdaptExec Materials only to perform this Agreement, and you will not modify them except as the Hub's tools allow.

6.3 Work you create for AdaptExec. Materials you create for AdaptExec under this Agreement, such as lead forms and content in the Hub, belong to AdaptExec, and you assign to AdaptExec any rights you have in them.

## 7. Confidentiality

7.1 Confidential Information. "Confidential Information" means non-public information about AdaptExec, its clients and its prospects that you learn through this relationship, including client lists and contact details, pricing and proposals, project scopes, discovery questionnaires and build briefs, commission arrangements, business plans, software and the contents of the Hub. It does not include information that is or becomes public through no fault of yours, that you already knew lawfully, or that you receive lawfully from someone else without a duty of confidentiality.

7.2 Your obligations. You will use Confidential Information only to perform this Agreement, will not disclose it to anyone except as AdaptExec authorizes, and will protect it with at least reasonable care. If the law requires you to disclose it, you will give AdaptExec prompt notice first where the law allows.

7.3 How long it lasts. These obligations last while this Agreement is in effect and for three (3) years after it ends, and, for trade secrets and personal information, for as long as the law protects them.

7.4 Protected reporting. Nothing in this Agreement stops you from reporting a possible violation of law to a government agency or making any other disclosure the law protects. Under the federal Defend Trade Secrets Act, you will not be held liable for disclosing a trade secret in confidence to a government official or an attorney solely to report or investigate a suspected violation of law, or in a court filing made under seal.

## 8. Ending This Agreement

8.1 Either party may end it at any time. Either you or AdaptExec may end this Agreement at any time, for any reason or no reason, by giving written notice to the other. Notice by email or through the Hub is enough. It ends when the notice is given unless the notice states a later date.

8.2 Suspension. AdaptExec may suspend your Hub access immediately if it reasonably believes you have breached this Agreement or acted fraudulently or unlawfully.

8.3 Commission after it ends. After this Agreement ends, you remain entitled to commission earned under Section 3 on client payments that AdaptExec receives and that fully clear after the end date, for deals that were registered to you and that the client signed before the end date. Those commissions remain subject to every condition in Section 3, including clawback, offset and holds. No commission is earned on deals clients sign after the end date.

8.4 Return of materials. When this Agreement ends, or earlier if AdaptExec asks, you will stop using AdaptExec Materials and Confidential Information, return or permanently delete every copy you have (including any client information kept outside the Hub), and stop presenting yourself as connected with AdaptExec. AdaptExec will close your Hub access. You may ask AdaptExec for a copy of your signed agreement and commission records at any time.

8.5 Survival. Sections 1.5, 3 (for commission earned before or after the end date and for clawback and offset), 6, 7, 9, 10, 11, 12 and 13 survive the end of this Agreement.

## 9. Non-Solicitation

9.1 Clients and leads. While this Agreement is in effect and for twelve (12) months after it ends, you will not, directly or indirectly, for yourself or anyone else, solicit, divert or accept business for website, CRM, software, search engine optimization, marketing or other services that compete with the Services from (a) any AdaptExec client, or (b) any prospect or lead that was registered or assigned to you, or that you learned about through the Hub or AdaptExec, during the last twelve (12) months of this Agreement. You also will not encourage any AdaptExec client to end or reduce its relationship with AdaptExec.

9.2 AdaptExec's team. During the same period, you will not solicit any AdaptExec employee, contractor or sales representative to stop working with AdaptExec. General advertising that is not aimed at them is not a violation.

9.3 Not a non-compete. This Section does not stop you from working in sales or for any other company, including one in a similar industry, as long as you comply with Section 7 and Sections 9.1 and 9.2.

9.4 Reasonableness. You agree that these restrictions are reasonable and necessary to protect AdaptExec's client relationships, goodwill and Confidential Information. If a court finds any restriction broader than the law allows, the court may narrow or strike it to the minimum extent necessary to make it enforceable.

## 10. Indemnification

You will indemnify, defend and hold harmless AdaptExec and its owners, officers, employees and contractors from all claims, losses, damages, fines, penalties, liabilities and expenses (including reasonable attorneys' fees) arising out of (a) your breach of this Agreement; (b) your negligent, unlawful, fraudulent or willful misconduct; (c) any statement, promise or representation you make that AdaptExec did not authorize; (d) your violation of the TCPA, the CAN-SPAM Act or any other law; or (e) any claim that you are AdaptExec's employee or are owed wages or benefits by AdaptExec, and any taxes, interest or penalties assessed on your compensation.

## 11. Limitation of Liability

To the fullest extent the law allows, AdaptExec is not liable to you for any indirect, incidental, special, consequential, exemplary or punitive damages, or for lost profits or lost opportunities, arising out of or relating to this Agreement or its ending, even if AdaptExec was told they were possible. AdaptExec's total liability to you under or relating to this Agreement will not exceed the commission that has been earned under Section 3 and not yet paid at the time the claim arises. This Section does not limit any liability that cannot be limited by law.

## 12. Electronic Signatures and Records

12.1 Consent to electronic signatures and records. You agree to do business with AdaptExec electronically. You consent to receive this Agreement, notices, commission information and other communications electronically through the Hub or by email, and to sign this Agreement electronically. Your electronic signature, whether drawn or typed, has the same legal effect as a handwritten signature under the federal Electronic Signatures in Global and National Commerce Act (E-SIGN Act) and the Arizona Electronic Transactions Act (Arizona's version of the Uniform Electronic Transactions Act).

12.2 Records. AdaptExec keeps an electronic record of this Agreement and of your signature, including the date and time, your IP address, device and browser information, and a SHA-256 cryptographic hash of the exact text you signed. You can view, download and print your signed copy in the Hub at any time.

12.3 Paper copies and withdrawing consent. You may ask AdaptExec for a paper copy at no charge. You may withdraw your consent to electronic records by written notice. Withdrawing does not affect anything signed or delivered before you withdrew, and AdaptExec may end this Agreement if you withdraw consent.

12.4 What you need. To access and keep electronic records you need a device with a current web browser, internet access, an email account, and the ability to save or print HTML or PDF files.

## 13. General Terms

13.1 Governing law and venue. Arizona law governs this Agreement, without regard to its conflict-of-laws rules. Any lawsuit relating to this Agreement must be brought in the state or federal courts located in Maricopa County, Arizona, and both parties consent to the jurisdiction of those courts.

13.2 Entire agreement. This Agreement, together with the Commission Schedule displayed in the Hub and any rate AdaptExec sets for you in writing in the Hub, is the entire agreement between you and AdaptExec about its subject. It replaces all earlier discussions, offers, postings and agreements on that subject, including any earlier sales representative agreement with AdaptExec, except that commission earned under an earlier agreement remains payable under its terms.

13.3 Changes to this Agreement. AdaptExec may change the Commission Schedule as described in Section 3.12. Any other change to this Agreement requires a new version that you accept electronically in the Hub, or a written amendment signed by both parties. If you do not accept a new version, either party may end this Agreement under Section 8.1.

13.4 Assignment. You may not assign or delegate this Agreement or any right under it, including any right to commission, without AdaptExec's written consent. AdaptExec may assign this Agreement to an affiliate or to a successor to its business.

13.5 Severability. If any part of this Agreement is held invalid or unenforceable, the rest remains in effect, and that part will be enforced to the maximum extent the law allows.

13.6 No waiver. A failure or delay in enforcing any part of this Agreement is not a waiver of it.

13.7 Notices. AdaptExec may give you notice through the Hub or by email to the address on your Hub account. {{notice_clause}} Notice by email is effective when sent, unless the sender receives a delivery-failure message; notice by mail is effective three business days after it is mailed.

13.8 Opportunity to review. You confirm that you have read this Agreement, have had the opportunity to ask questions and to consult an attorney of your choice, and are signing it voluntarily.

13.9 Interpretation. Headings are for convenience only. "Including" means "including without limitation." This Agreement will not be interpreted against either party because that party drafted it.
AXHTEXT;
	}

	/* ------------------------------------------------- private file storage */

	/** Where encrypted files live. Override with AXH_PRIVATE_DIR in wp-config. */
	public static function dir() {
		if ( defined( 'AXH_PRIVATE_DIR' ) && AXH_PRIVATE_DIR ) {
			return untrailingslashit( (string) AXH_PRIVATE_DIR );
		}
		$u = wp_upload_dir( null, false );
		return untrailingslashit( $u['basedir'] ) . '/axh-private';
	}

	/** Public URL the folder would have, for the "not web-reachable" check. */
	public static function dir_url() {
		if ( defined( 'AXH_PRIVATE_DIR' ) && AXH_PRIVATE_DIR ) {
			return '';
		}
		$u = wp_upload_dir( null, false );
		return untrailingslashit( $u['baseurl'] ) . '/axh-private/';
	}

	/** Create the folder with its deny rules. Safe to call on every write. */
	public static function ensure_dir() {
		$d = self::dir();
		if ( ! is_dir( $d ) && ! wp_mkdir_p( $d ) ) {
			return false;
		}
		$guards = array(
			'index.php'  => "<?php\n// Silence is golden. AdaptExec Hub private files are never served from here.\nhttp_response_code( 404 );\n",
			'.htaccess'  => "# AdaptExec Hub private files: encrypted, and never served directly.\nOptions -Indexes\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'README.txt' => "AdaptExec Sales Hub - private, encrypted files (W-9s, signatures, signed agreements).\nThe files are unreadable without this site's WordPress salts. Deleting the plugin does NOT delete this folder.\n",
		);
		foreach ( $guards as $name => $body ) {
			if ( ! file_exists( $d . '/' . $name ) ) {
				file_put_contents( $d . '/' . $name, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		return is_writable( $d );
	}

	/** Encryption keys, newest first. A file records which key sealed it. */
	protected static function keys() {
		$keys = array();
		if ( defined( 'AXH_PRIVATE_KEY' ) && strlen( (string) AXH_PRIVATE_KEY ) >= 32 ) {
			$keys[] = sodium_crypto_generichash( self::KEY_CONTEXT . '|const|' . AXH_PRIVATE_KEY, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		}
		$keys[] = sodium_crypto_generichash( self::KEY_CONTEXT . '|' . wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		return $keys;
	}

	protected static function key_id( $key ) {
		return substr( sodium_crypto_generichash( 'key-id|' . $key, '', 16 ), 0, 8 );
	}

	/** Seal bytes: "AXH1" + key id (8) + nonce (24) + secretbox. */
	public static function encrypt( $plain ) {
		$keys  = self::keys();
		$key   = $keys[0];
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'AXH1' . self::key_id( $key ) . $nonce . sodium_crypto_secretbox( (string) $plain, $nonce, $key );
	}

	public static function decrypt( $blob ) {
		if ( strlen( $blob ) < 4 + 8 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES || 0 !== strpos( $blob, 'AXH1' ) ) {
			return new WP_Error( 'axh_corrupt', 'That file is damaged or not a Hub file.', array( 'status' => 500 ) );
		}
		$kid   = substr( $blob, 4, 8 );
		$nonce = substr( $blob, 12, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = substr( $blob, 12 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		foreach ( self::keys() as $key ) {
			if ( hash_equals( self::key_id( $key ), $kid ) ) {
				$plain = sodium_crypto_secretbox_open( $box, $nonce, $key );
				if ( false !== $plain ) {
					return $plain;
				}
			}
		}
		return new WP_Error( 'axh_key', 'This file was sealed with a different site key, so it cannot be opened. (Were the WordPress salts in wp-config.php changed?)', array( 'status' => 500 ) );
	}

	/** Encrypt and write. Returns the random file name, or a WP_Error. */
	protected static function put( $plain ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return new WP_Error( 'axh_no_sodium', 'This server is missing PHP\'s sodium extension, which the Hub needs to encrypt files.', array( 'status' => 500 ) );
		}
		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'axh_no_dir', 'The private folder could not be created. Check that wp-content/uploads is writable.', array( 'status' => 500 ) );
		}
		$name = bin2hex( random_bytes( 16 ) ) . '.axh';
		$ok   = file_put_contents( self::dir() . '/' . $name, self::encrypt( $plain ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $ok ) {
			return new WP_Error( 'axh_write', 'Could not save the file. Please try again.', array( 'status' => 500 ) );
		}
		@chmod( self::dir() . '/' . $name, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $name;
	}

	protected static function get_file( $name ) {
		if ( ! preg_match( '/^[a-f0-9]{32}\.axh$/', (string) $name ) ) {
			return new WP_Error( 'axh_bad_file', 'File not found.', array( 'status' => 404 ) );
		}
		$path = self::dir() . '/' . $name;
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'axh_missing', 'The file is missing from the private folder.', array( 'status' => 404 ) );
		}
		return self::decrypt( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** Store a document row plus its encrypted file. */
	protected static function add_doc( $rep_id, $kind, $plain, $mime, $orig = '', $ref = 0 ) {
		global $wpdb;
		$name = self::put( $plain );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		$wpdb->insert(
			AXH_DB::table( 'docs' ),
			array(
				'rep_id'      => (int) $rep_id,
				'kind'        => $kind,
				'ref_id'      => (int) $ref,
				'file'        => $name,
				'mime'        => $mime,
				'orig_name'   => mb_substr( (string) $orig, 0, 191 ),
				'size'        => strlen( $plain ),
				'sha256'      => hash( 'sha256', $plain ),
				'status'      => 'current',
				'uploaded_by' => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			@unlink( self::dir() . '/' . $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'axh_db', 'Could not record the file.', array( 'status' => 500 ) );
		}
		return $id;
	}

	protected static function doc( $id ) {
		global $wpdb;
		$t = AXH_DB::table( 'docs' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	/** Reps reach only their own documents; managers reach everyone's. */
	protected static function can_see( $row ) {
		return $row && ( axh_is_manager() || (int) $row['rep_id'] === get_current_user_id() );
	}

	/* --------------------------------------------------------------- audit */

	public static function audit( $rep_id, $action, $detail = '' ) {
		global $wpdb;
		$wpdb->insert(
			AXH_DB::table( 'audit' ),
			array(
				'rep_id'     => (int) $rep_id,
				'actor_id'   => get_current_user_id(),
				'action'     => sanitize_key( $action ),
				'detail'     => is_string( $detail ) ? mb_substr( $detail, 0, 4000 ) : wp_json_encode( $detail ),
				'ip'         => self::ip(),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	public static function audit_log( $rep_id, $limit = 100 ) {
		global $wpdb;
		$t    = AXH_DB::table( 'audit' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, actor_id, action, detail, ip, created_at FROM {$t} WHERE rep_id = %d ORDER BY id DESC LIMIT %d", (int) $rep_id, (int) $limit ), ARRAY_A );
		foreach ( $rows as &$r ) {
			$u          = $r['actor_id'] ? get_userdata( (int) $r['actor_id'] ) : null;
			$r['actor'] = $u ? $u->display_name : 'System';
			if ( 0 === strpos( (string) $r['detail'], '{' ) ) {
				$r['data']   = json_decode( $r['detail'], true );
				$r['detail'] = '';
			}
		}
		return $rows;
	}

	protected static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** At most $max actions per hour, per person. */
	protected static function limited( $what, $max ) {
		$key = 'axh_rl_' . $what . '_' . get_current_user_id();
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	/* -------------------------------------------------------------- status */

	protected static function signatures( $rep_id ) {
		global $wpdb;
		$t = AXH_DB::table( 'signatures' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE rep_id = %d ORDER BY id DESC", (int) $rep_id ), ARRAY_A );
	}

	protected static function signature( $id ) {
		global $wpdb;
		$t = AXH_DB::table( 'signatures' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	protected static function w9_docs( $rep_id ) {
		global $wpdb;
		$t = AXH_DB::table( 'docs' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, status, mime, orig_name, size, uploaded_by, created_at FROM {$t} WHERE rep_id = %d AND kind = 'w9' ORDER BY id DESC", (int) $rep_id ), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['id']   = (int) $r['id'];
			$r['size'] = (int) $r['size'];
		}
		return $rows;
	}

	public static function payee( $rep_id ) {
		$p = get_user_meta( (int) $rep_id, 'axh_payee', true );
		return is_array( $p ) && ! empty( $p['street'] ) ? $p : null;
	}

	/**
	 * Where one rep is with onboarding.
	 *
	 * agreement: todo | signed | resign      w9: todo | on_file | rejected
	 * payee:     todo | set | verify
	 */
	public static function status( $rep_id ) {
		$rep_id = (int) $rep_id;
		$cur    = self::current();
		$sigs   = self::signatures( $rep_id );
		$last   = $sigs ? $sigs[0] : null;
		$resign = (int) get_user_meta( $rep_id, 'axh_onb_resign_sig', true ); // re-sign asked while this signature was the latest

		$a = array( 'state' => 'todo', 'at' => null, 'id' => 0, 'version' => '', 'countersigned' => false, 'reason' => '' );
		if ( $last ) {
			$a['id']            = (int) $last['id'];
			$a['at']            = get_date_from_gmt( $last['signed_at'] );
			$a['version']       = $last['version'];
			$a['countersigned'] = ! empty( $last['countersigned_at'] );
			$a['state']         = 'signed';
			if ( $last['text_hash'] !== $cur['hash'] ) {
				$a['state']  = 'resign';
				$a['reason'] = $last['version'] !== $cur['version'] ? 'new_version' : 'details_changed';
			} elseif ( $resign && (int) $last['id'] <= $resign ) {
				$a['state']  = 'resign';
				$a['reason'] = 'requested';
			}
		}

		$docs = self::w9_docs( $rep_id );
		$w    = array( 'state' => 'todo', 'at' => null, 'id' => 0, 'note' => '' );
		foreach ( $docs as $d ) {
			if ( 'current' === $d['status'] ) {
				$w = array( 'state' => 'on_file', 'at' => $d['created_at'], 'id' => (int) $d['id'], 'note' => '' );
				break;
			}
			if ( 'rejected' === $d['status'] ) {
				$w = array( 'state' => 'rejected', 'at' => $d['created_at'], 'id' => (int) $d['id'], 'note' => (string) get_user_meta( $rep_id, 'axh_w9_reject_note', true ) );
				break;
			}
		}

		$payee = self::payee( $rep_id );
		$p     = array( 'state' => 'todo', 'at' => null );
		if ( $payee ) {
			$p = array(
				'state' => 'verify' === get_user_meta( $rep_id, 'axh_payee_status', true ) ? 'verify' : 'set',
				'at'    => (string) get_user_meta( $rep_id, 'axh_payee_saved_at', true ),
			);
		}

		$done = (int) ( 'signed' === $a['state'] ) + (int) ( 'on_file' === $w['state'] ) + (int) ( 'todo' !== $p['state'] );
		$ovr  = get_user_meta( $rep_id, 'axh_onb_override', true );
		return array(
			'steps'    => array( 'agreement' => $a, 'w9' => $w, 'payee' => $p ),
			'done'     => $done,
			'total'    => 3,
			'override' => is_array( $ovr ) ? $ovr : null,
			'complete' => 3 === $done || is_array( $ovr ),
		);
	}

	/**
	 * Can AdaptExec pay this rep without a warning? Needs any signed
	 * agreement, a W-9 on file, and a payout address that is not waiting on
	 * verification.
	 */
	public static function readiness( $rep_id ) {
		$s       = self::status( $rep_id );
		$missing = array();
		if ( 'todo' === $s['steps']['agreement']['state'] ) {
			$missing[] = 'signed agreement';
		}
		if ( 'on_file' !== $s['steps']['w9']['state'] ) {
			$missing[] = 'W-9';
		}
		if ( 'todo' === $s['steps']['payee']['state'] ) {
			$missing[] = 'payout address';
		} elseif ( 'verify' === $s['steps']['payee']['state'] ) {
			$missing[] = 'verified payout address (it was changed)';
		}
		return array( 'ready' => ! $missing, 'missing' => $missing );
	}

	/** The rep's payee, as a short block for the payouts screen. */
	public static function payee_card( $rep_id ) {
		$p = self::payee( $rep_id );
		if ( ! $p ) {
			return null;
		}
		return array(
			'payable_to' => $p['payable_to'],
			'lines'      => self::address_lines( $p ),
			'verify'     => 'verify' === get_user_meta( (int) $rep_id, 'axh_payee_status', true ),
		);
	}

	public static function address_lines( $p ) {
		return array_values( array_filter( array(
			trim( $p['street'] . ( ! empty( $p['unit'] ) ? ', ' . $p['unit'] : '' ) ),
			trim( $p['city'] . ', ' . $p['state'] . ' ' . $p['zip'] ),
		) ) );
	}

	/** For bootstrap: a rep's own status; a manager's to-do counts. */
	public static function boot( $user ) {
		if ( axh_is_manager( $user->ID ) ) {
			$verify = 0;
			$todo   = 0;
			$cs     = 0;
			foreach ( AXH_Team::roster() as $r ) {
				if ( ! $r['active'] ) {
					continue;
				}
				$s       = self::status( $r['id'] );
				$verify += (int) ( 'verify' === $s['steps']['payee']['state'] );
				$todo   += (int) ! $s['complete'];
				$cs     += (int) ( 'signed' === $s['steps']['agreement']['state'] && ! $s['steps']['agreement']['countersigned'] );
			}
			return array( 'manager' => true, 'verify' => $verify, 'incomplete' => $todo, 'countersign' => $cs );
		}
		return self::status( $user->ID );
	}

	/* ------------------------------------------------------------- routes */

	public static function routes() {
		$ns     = AXH_Rest::NS;
		$auth   = array( 'AXH_Rest', 'can_use' );
		$manage = array( 'AXH_Rest', 'can_manage' );
		$rep    = array( __CLASS__, 'can_rep' );

		register_rest_route( $ns, '/onboarding', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_get' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/onboarding/agreement', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_agreement' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/onboarding/agreement/sign', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_sign' ), 'permission_callback' => $rep ) );
		register_rest_route( $ns, '/onboarding/copy/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_copy' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/onboarding/w9', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_w9_upload' ), 'permission_callback' => $rep ) );
		register_rest_route( $ns, '/onboarding/w9/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_w9_file' ), 'permission_callback' => $auth ) );
		register_rest_route( $ns, '/onboarding/payee', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_payee' ), 'permission_callback' => $rep ) );

		register_rest_route( $ns, '/manage/onboarding', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_manage' ), 'permission_callback' => $manage ) );
		register_rest_route( $ns, '/manage/onboarding/rep/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_manage_rep' ), 'permission_callback' => $manage ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_manage_do' ), 'permission_callback' => $manage ),
		) );
		register_rest_route( $ns, '/manage/onboarding/sig/(?P<id>\d+)/countersign', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_countersign' ), 'permission_callback' => $manage ) );
		register_rest_route( $ns, '/manage/onboarding/settings', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_settings' ), 'permission_callback' => $manage ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_settings_save' ), 'permission_callback' => $manage ),
		) );
		register_rest_route( $ns, '/manage/onboarding/agreement', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_publish' ), 'permission_callback' => $manage ) );
		register_rest_route( $ns, '/manage/onboarding/selftest', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_selftest' ), 'permission_callback' => $manage ) );
	}

	/** Signing, uploading a W-9 and payout details are for reps, not administrators. */
	public static function can_rep() {
		return AXH_Rest::can_use() && ! axh_is_manager();
	}

	protected static function rep_or_404( $id ) {
		$u = get_user_by( 'id', (int) $id );
		if ( ! $u || ! AXH_Team::is_rep_account( $u ) ) {
			return new WP_Error( 'axh_not_rep', 'That is not a sales representative account.', array( 'status' => 404 ) );
		}
		return $u;
	}

	protected static function sig_public( $s ) {
		return array(
			'id'               => (int) $s['id'],
			'version'          => $s['version'],
			'text_hash'        => $s['text_hash'],
			'legal_name'       => $s['legal_name'],
			'business_name'    => $s['business_name'],
			'method'           => $s['method'],
			'signed_at'        => get_date_from_gmt( $s['signed_at'] ),
			'signed_at_utc'    => $s['signed_at'],
			'ip'               => $s['ip'],
			'user_agent'       => $s['user_agent'],
			'record_hash'      => $s['record_hash'],
			'countersigned_at' => $s['countersigned_at'] ? get_date_from_gmt( $s['countersigned_at'] ) : null,
			'countersign_name' => $s['countersign_name'],
		);
	}

	/** Everything a rep's onboarding pages need, for the rep themselves. */
	protected static function state_for( $rep_id, $admin = false ) {
		$cur   = self::current();
		$sigs  = self::signatures( $rep_id );
		$payee = self::payee( $rep_id );
		$out   = array(
			'status'    => self::status( $rep_id ),
			'agreement' => array(
				'version'   => $cur['version'],
				'effective' => $cur['effective'],
				'hash'      => $cur['hash'],
				'title'     => $cur['title'],
			),
			'signed'    => $sigs ? self::sig_public( $sigs[0] ) : null,
			'w9'        => self::w9_docs( $rep_id ),
			'payee'     => $payee,
			'payee_status' => $payee ? ( 'verify' === get_user_meta( $rep_id, 'axh_payee_status', true ) ? 'verify' : 'set' ) : 'todo',
			'irs'       => array( 'pdf' => self::IRS_W9_PDF, 'info' => self::IRS_W9_INFO, 'rev' => self::IRS_W9_REV ),
			'max_mb'    => self::w9_max_mb(),
			'company'   => self::settings()['company_legal_name'],
			'signer'    => self::settings()['signer'],
		);
		if ( $admin ) {
			$out['signatures'] = array_map( array( __CLASS__, 'sig_public' ), $sigs );
			$out['audit']      = self::audit_log( $rep_id, 150 );
			$out['readiness']  = self::readiness( $rep_id );
			$out['reminded']   = (string) get_user_meta( $rep_id, 'axh_onb_reminded_at', true );
			$out['verified']   = array(
				'at' => (string) get_user_meta( $rep_id, 'axh_payee_verified_at', true ),
				'by' => (string) get_user_meta( $rep_id, 'axh_payee_verified_by', true ),
			);
		}
		return $out;
	}

	public static function r_get( $req ) {
		if ( axh_is_manager() ) {
			return rest_ensure_response( array( 'manager' => true, 'agreement' => self::current() ) );
		}
		return rest_ensure_response( self::state_for( get_current_user_id() ) );
	}

	public static function r_agreement( $req ) {
		$cur = self::current();
		return rest_ensure_response( array_merge( $cur, array( 'company' => self::settings()['company_legal_name'], 'tiers' => AXH_Install::commission_tiers() ) ) );
	}

	/* ------------------------------------------------------------- signing */

	/** Words in a name, lower-case, accents folded. */
	protected static function name_tokens( $s ) {
		$s = strtolower( remove_accents( (string) $s ) );
		$s = preg_replace( '/[^a-z\s\'-]/', ' ', $s );
		return array_values( array_filter( preg_split( '/[\s\'-]+/', $s ), function ( $w ) { return strlen( $w ) >= 2; } ) );
	}

	/**
	 * Loose match between the typed legal name and the account: they must
	 * share a word, when the account has a real name to compare against.
	 */
	public static function name_matches( $legal, $user ) {
		$have = array_merge(
			self::name_tokens( $user->display_name ),
			self::name_tokens( get_user_meta( $user->ID, 'first_name', true ) ),
			self::name_tokens( get_user_meta( $user->ID, 'last_name', true ) )
		);
		$have = array_unique( $have );
		$real = count( $have ) >= 2;
		if ( ! $real ) {
			return true; // A username-style account name: nothing reliable to compare.
		}
		foreach ( self::name_tokens( $legal ) as $t ) {
			foreach ( $have as $h ) {
				if ( $t === $h || ( strlen( $t ) >= 4 && strlen( $h ) >= 4 && levenshtein( $t, $h ) <= 1 ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function r_sign( $req ) {
		$b    = (array) $req->get_json_params();
		$user = wp_get_current_user();
		$cur  = self::current();

		if ( self::limited( 'sign', 20 ) ) {
			return new WP_Error( 'axh_throttled', 'Too many attempts. Please wait a while and try again.', array( 'status' => 429 ) );
		}
		if ( (string) ( $b['version'] ?? '' ) !== $cur['version'] || (string) ( $b['hash'] ?? '' ) !== $cur['hash'] ) {
			return new WP_Error( 'axh_changed', 'The agreement was updated while you were reading it. Reload the page to read the latest version before signing.', array( 'status' => 409 ) );
		}
		$st = self::status( $user->ID );
		if ( 'signed' === $st['steps']['agreement']['state'] ) {
			return new WP_Error( 'axh_signed', 'You have already signed this version of the agreement.', array( 'status' => 400 ) );
		}
		if ( empty( $b['read'] ) ) {
			return new WP_Error( 'axh_unread', 'Please read the whole agreement (or confirm you have read it) before signing.', array( 'status' => 400 ) );
		}
		if ( empty( $b['consent'] ) ) {
			return new WP_Error( 'axh_consent', 'Please tick the box to agree to sign electronically.', array( 'status' => 400 ) );
		}
		$legal = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( $b['legal_name'] ?? '' ) ) );
		if ( mb_strlen( $legal ) < 3 || mb_strlen( $legal ) > 120 || ! preg_match( '/\p{L}.*\s+.*\p{L}/u', $legal ) ) {
			return new WP_Error( 'axh_name', 'Type your full legal name — first and last.', array( 'status' => 400 ) );
		}
		if ( ! self::name_matches( $legal, $user ) ) {
			return new WP_Error( 'axh_name_mismatch', 'That name does not match the name on your Hub account (' . $user->display_name . '). Type your full legal name, or ask AdaptExec to correct your account name.', array( 'status' => 400 ) );
		}
		$business = mb_substr( trim( sanitize_text_field( $b['business_name'] ?? '' ) ), 0, 120 );
		$method   = 'type' === ( $b['method'] ?? '' ) ? 'type' : 'draw';
		$typed    = 'type' === $method ? mb_substr( trim( sanitize_text_field( $b['typed_name'] ?? '' ) ), 0, 120 ) : '';
		if ( 'type' === $method && mb_strlen( $typed ) < 3 ) {
			return new WP_Error( 'axh_typed', 'Type your name to create your signature.', array( 'status' => 400 ) );
		}
		$png = self::png_from_data_url( (string) ( $b['signature'] ?? '' ) );
		if ( is_wp_error( $png ) ) {
			return $png;
		}

		$utc   = gmdate( 'Y-m-d H:i:s' );
		$ua    = mb_substr( sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ), 0, 500 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$fwd   = mb_substr( sanitize_text_field( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) : '' ), 0, 191 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$s     = self::settings();
		$meta  = array(
			'tiers'         => AXH_Install::commission_tiers(),
			'rate_override' => (float) get_user_meta( $user->ID, 'axh_rate_override', true ),
			'company'       => $s['company_legal_name'],
			'company_address' => $s['company_address'],
			'signer'        => $s['signer'],
			'effective'     => $cur['effective'],
			'email'         => $user->user_email,
			'forwarded_for' => $fwd,
			'consent'       => self::consent_text(),
			'read_method'   => 'scrolled' === ( $b['read'] ?? '' ) ? 'Scrolled to the end of the agreement' : 'Confirmed "I have read the entire agreement"',
		);
		$record = array(
			'rep_id'        => (int) $user->ID,
			'version'       => $cur['version'],
			'text_hash'     => $cur['hash'],
			'legal_name'    => $legal,
			'business_name' => $business,
			'email'         => $user->user_email,
			'method'        => $method,
			'typed_name'    => $typed,
			'sig_sha256'    => hash( 'sha256', $png ),
			'ip'            => self::ip(),
			'user_agent'    => $ua,
			'signed_at'     => $utc,
		);
		$record_hash = hash( 'sha256', wp_json_encode( $record ) );

		global $wpdb;
		$sig_doc = self::add_doc( $user->ID, 'sig', $png, 'image/png', 'signature.png' );
		if ( is_wp_error( $sig_doc ) ) {
			return $sig_doc;
		}
		$wpdb->insert(
			AXH_DB::table( 'signatures' ),
			array_merge(
				$record,
				array(
					'text'        => $cur['text'],
					'meta'        => wp_json_encode( $meta ),
					'sig_doc'     => (int) $sig_doc,
					'record_hash' => $record_hash,
					'created_at'  => current_time( 'mysql' ),
				)
			)
		);
		$sid = (int) $wpdb->insert_id;
		if ( ! $sid ) {
			return new WP_Error( 'axh_db', 'Could not save your signature. Please try again.', array( 'status' => 500 ) );
		}
		$wpdb->update( AXH_DB::table( 'docs' ), array( 'ref_id' => $sid ), array( 'id' => (int) $sig_doc ) );
		$copy = self::make_copy( $sid );
		if ( is_wp_error( $copy ) ) {
			return $copy;
		}
		self::audit( $user->ID, 'agreement_signed', sprintf( 'Signed version %s (%s) · text SHA-256 %s · %s signature', $cur['version'], $legal, $cur['hash'], $method ) );

		// Adam: a notification and a link. The rep: a confirmation with their copy attached.
		AXH_Notify::managers_add(
			'onboarding_update',
			$user->display_name . ' signed the contractor agreement',
			sprintf( '%s signed the Independent Contractor Agreement (version %s) as %s%s. Review it and countersign under Onboarding.', $user->display_name, $cur['version'], $legal, $business ? ' for ' . $business : '' ),
			'#/manage/onboarding/rep/' . $user->ID,
			null,
			array( 'rows' => array( 'Version' => $cur['version'], 'Signed as' => $legal, 'Business' => $business ? $business : '—', 'Signed' => self::phx( $utc ) ) )
		);
		self::email_rep_copy( $user, $sid );

		return rest_ensure_response( array( 'ok' => true, 'signature' => self::sig_public( self::signature( $sid ) ), 'state' => self::state_for( $user->ID ) ) );
	}

	public static function consent_text() {
		return 'I agree to use electronic records and signatures. I understand that my electronic signature, drawn or typed, is legally binding just like a handwritten signature, and that I can download or print this agreement and ask for a paper copy at any time.';
	}

	protected static function png_from_data_url( $url ) {
		if ( ! preg_match( '#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $url, $m ) ) {
			return new WP_Error( 'axh_sig', 'Please draw or type your signature.', array( 'status' => 400 ) );
		}
		$png = base64_decode( preg_replace( '/\s+/', '', $m[1] ), true );
		if ( ! $png || strlen( $png ) > self::SIG_MAX || 0 !== strpos( $png, "\x89PNG\r\n\x1a\n" ) ) {
			return new WP_Error( 'axh_sig', 'That signature image could not be read. Clear it and try again.', array( 'status' => 400 ) );
		}
		$dim = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $png ) : array( 600, 200 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $dim || $dim[0] < 120 || $dim[1] < 40 || $dim[0] > 2400 || $dim[1] > 1200 ) {
			return new WP_Error( 'axh_sig', 'That signature image is not the right size. Clear it and try again.', array( 'status' => 400 ) );
		}
		if ( strlen( $png ) < 900 ) {
			return new WP_Error( 'axh_sig_blank', 'Your signature looks empty. Please sign in the box.', array( 'status' => 400 ) );
		}
		return $png;
	}

	public static function phx( $utc ) {
		if ( ! $utc ) {
			return '';
		}
		$d = new DateTime( $utc, new DateTimeZone( 'UTC' ) );
		$d->setTimezone( new DateTimeZone( 'America/Phoenix' ) );
		return $d->format( 'F j, Y \a\t g:i:s A' ) . ' (Phoenix, MST)';
	}

	/* --------------------------------------------------------- signed copy */

	/** Build, encrypt and store the signed copy for a signature. */
	public static function make_copy( $sid ) {
		global $wpdb;
		$s = self::signature( $sid );
		if ( ! $s ) {
			return new WP_Error( 'axh_not_found', 'Signature not found.', array( 'status' => 404 ) );
		}
		$html = self::copy_html( $s );
		$doc  = self::add_doc( $s['rep_id'], 'signed', $html, 'text/html', 'signed-agreement.html', $sid );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		if ( ! empty( $s['copy_doc'] ) ) {
			$old = self::doc( $s['copy_doc'] );
			if ( $old ) {
				$wpdb->update( AXH_DB::table( 'docs' ), array( 'status' => 'replaced' ), array( 'id' => (int) $old['id'] ) );
			}
		}
		$wpdb->update( AXH_DB::table( 'signatures' ), array( 'copy_doc' => (int) $doc ), array( 'id' => (int) $sid ) );
		return $doc;
	}

	/** Render the agreement text's light markup as HTML. */
	public static function text_html( $text ) {
		$out  = '';
		$list = false;
		foreach ( preg_split( "/\n\s*\n/", trim( (string) $text ) ) as $block ) {
			$lines = explode( "\n", trim( $block ) );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( 0 === strpos( $line, '- ' ) ) {
					if ( ! $list ) {
						$out .= '<ul>';
						$list = true;
					}
					$out .= '<li>' . esc_html( substr( $line, 2 ) ) . '</li>';
					continue;
				}
				if ( $list ) {
					$out .= '</ul>';
					$list = false;
				}
				if ( 0 === strpos( $line, '## ' ) ) {
					$out .= '<h2>' . esc_html( substr( $line, 3 ) ) . '</h2>';
				} elseif ( preg_match( '/^(\d+\.\d+)\s+([^.]{2,90}\.)\s+(.*)$/u', $line, $m ) ) {
					$out .= '<p><b>' . esc_html( $m[1] . ' ' . $m[2] ) . '</b> ' . esc_html( $m[3] ) . '</p>';
				} else {
					$out .= '<p>' . esc_html( $line ) . '</p>';
				}
			}
			if ( $list ) {
				$out .= '</ul>';
				$list = false;
			}
		}
		return $out;
	}

	protected static function sig_image_uri( $s ) {
		$png = self::get_file( self::doc( $s['sig_doc'] )['file'] ?? '' );
		return is_wp_error( $png ) ? '' : 'data:image/png;base64,' . base64_encode( $png );
	}

	/** The standalone, print-ready signed copy. No scripts, no outside requests. */
	public static function copy_html( $s ) {
		$m     = json_decode( (string) $s['meta'], true );
		$m     = is_array( $m ) ? $m : array();
		$e     = 'esc_html';
		$img   = self::sig_image_uri( $s );
		$tiers = '';
		foreach ( (array) ( $m['tiers'] ?? array() ) as $t ) {
			$tiers .= '<tr><td>' . $e( $t['label'] ) . '</td><td class="n">' . $e( rtrim( rtrim( number_format( (float) $t['rate'], 2 ), '0' ), '.' ) ) . '%</td></tr>';
		}
		$ovr  = (float) ( $m['rate_override'] ?? 0 );
		$cs   = ! empty( $s['countersigned_at'] );
		$rows = array(
			'Document'                   => self::DOC_TITLE,
			'Agreement version'          => $s['version'] . ' (effective ' . mysql2date( 'F j, Y', $m['effective'] ?? '' ) . ')',
			'Agreement text SHA-256'     => $s['text_hash'],
			'Signer'                     => $s['legal_name'] . ( $s['business_name'] ? ' · ' . $s['business_name'] : '' ),
			'Signer Hub account'         => 'User #' . (int) $s['rep_id'] . ' · ' . $s['email'],
			'Signature method'           => 'draw' === $s['method'] ? 'Drawn by hand on screen' : 'Typed name rendered as a signature ("' . $s['typed_name'] . '")',
			'Signature image SHA-256'    => $s['sig_sha256'],
			'Reading confirmation'       => $m['read_method'] ?? '',
			'Electronic-records consent' => 'Given: "' . ( $m['consent'] ?? self::consent_text() ) . '"',
			'Signed (UTC)'               => gmdate( 'Y-m-d H:i:s', strtotime( $s['signed_at'] . ' UTC' ) ) . ' UTC',
			'Signed (Phoenix)'           => self::phx( $s['signed_at'] ),
			'IP address'                 => $s['ip'] ? $s['ip'] : 'not recorded',
			'Forwarded-for header'       => ! empty( $m['forwarded_for'] ) ? $m['forwarded_for'] : '—',
			'Browser / device'           => $s['user_agent'],
			'Countersigned'              => $cs ? $s['countersign_name'] . ' · ' . self::phx( $s['countersigned_at'] ) : 'Not yet countersigned',
			'Signature record SHA-256'   => $s['record_hash'],
			'Document ID'                => 'AXH-ICA-' . str_pad( (string) (int) $s['id'], 5, '0', STR_PAD_LEFT ),
		);
		$audit = '';
		foreach ( $rows as $k => $v ) {
			$audit .= '<tr><th>' . $e( $k ) . '</th><td>' . $e( (string) $v ) . '</td></tr>';
		}
		$company = $m['company'] ?? 'AdaptExec';
		ob_start();
		?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo $e( 'Signed agreement — ' . $s['legal_name'] . ' — AXH-ICA-' . str_pad( (string) (int) $s['id'], 5, '0', STR_PAD_LEFT ) ); ?></title>
<style>
	@page { size: letter; margin: 0.7in 0.75in; }
	* { box-sizing: border-box; }
	body { margin: 0; background: #f5f4f1; color: #1d1f23; font: 15px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, Helvetica, Arial, sans-serif; }
	.page { max-width: 820px; margin: 28px auto; background: #fff; border: 1px solid #e3e0d9; border-radius: 14px; overflow: hidden; }
	.top { background: #0b0b0b; color: #fff; padding: 22px 36px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
	.mark { font-size: 22px; font-weight: 800; letter-spacing: .08em; } .mark span { color: #ef5d0f; }
	.tag { font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: #b9bec6; }
	.bar { height: 4px; background: #ef5d0f; }
	.body { padding: 34px 44px 40px; }
	h1 { font-size: 26px; line-height: 1.2; margin: 0 0 6px; color: #0b0b0b; letter-spacing: -.01em; }
	.meta { color: #666b73; font-size: 13px; margin: 0 0 26px; }
	.meta b { color: #0b0b0b; }
	h2 { font-size: 15px; letter-spacing: .06em; text-transform: uppercase; color: #0b0b0b; margin: 28px 0 10px; padding-top: 16px; border-top: 1px solid #ece9e3; }
	p { margin: 0 0 12px; } ul { margin: 0 0 12px 20px; padding: 0; }
	.sigs { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 30px 0 6px; }
	.sig { border: 1px solid #e3e0d9; border-radius: 12px; padding: 16px 18px; break-inside: avoid; }
	.sig h3 { margin: 0 0 8px; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #ef5d0f; }
	.sig .line { height: 86px; border-bottom: 1.5px solid #0b0b0b; display: flex; align-items: flex-end; margin-bottom: 8px; }
	.sig .line img { max-height: 82px; max-width: 100%; }
	.sig .cs { font-family: "Snell Roundhand", "Segoe Script", "Brush Script MT", cursive; font-size: 30px; line-height: 1; color: #0b0b0b; padding-bottom: 6px; }
	.sig .pending { color: #9a9fa6; font-size: 13px; padding-bottom: 10px; }
	.sig dl { margin: 0; font-size: 13px; display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; } .sig dt { color: #666b73; } .sig dd { margin: 0; font-weight: 600; }
	table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
	.sched td { padding: 7px 10px; border-bottom: 1px solid #ece9e3; } .sched .n { text-align: right; font-weight: 700; }
	.audit th { text-align: left; vertical-align: top; width: 34%; color: #666b73; font-weight: 600; padding: 7px 10px; border-bottom: 1px solid #ece9e3; }
	.audit td { padding: 7px 10px; border-bottom: 1px solid #ece9e3; word-break: break-all; }
	.box { break-inside: avoid; }
	.note { font-size: 12px; color: #666b73; margin-top: 10px; }
	.foot { padding: 14px 44px 22px; font-size: 11.5px; color: #8a8f96; border-top: 1px solid #ece9e3; }
	@media (max-width: 640px) { .body { padding: 24px 20px 28px; } .top { padding: 18px 20px; } .sigs { grid-template-columns: 1fr; } .foot { padding: 14px 20px 20px; } }
	@media print { body { background: #fff; } .page { margin: 0; border: 0; border-radius: 0; max-width: none; } .top { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style></head><body><div class="page">
<div class="top"><div class="mark">ADAPT<span>EXEC</span></div><div class="tag">Signed electronically · AXH-ICA-<?php echo $e( str_pad( (string) (int) $s['id'], 5, '0', STR_PAD_LEFT ) ); ?></div></div><div class="bar"></div>
<div class="body">
<h1><?php echo $e( self::DOC_TITLE ); ?></h1>
<p class="meta">Between <b><?php echo $e( $company ); ?></b> and <b><?php echo $e( $s['legal_name'] . ( $s['business_name'] ? ' (' . $s['business_name'] . ')' : '' ) ); ?></b> · Version <b><?php echo $e( $s['version'] ); ?></b> · Effective <?php echo $e( mysql2date( 'F j, Y', $m['effective'] ?? '' ) ); ?></p>
<?php echo self::text_html( $s['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in text_html(). ?>

<h2>Signatures</h2>
<div class="sigs">
	<div class="sig"><h3>Representative</h3>
		<div class="line"><?php if ( $img ) : ?><img src="<?php echo esc_attr( $img ); ?>" alt="Signature of <?php echo esc_attr( $s['legal_name'] ); ?>"><?php endif; ?></div>
		<dl><dt>Name</dt><dd><?php echo $e( $s['legal_name'] ); ?></dd>
		<?php if ( $s['business_name'] ) : ?><dt>Business</dt><dd><?php echo $e( $s['business_name'] ); ?></dd><?php endif; ?>
		<dt>Email</dt><dd><?php echo $e( $s['email'] ); ?></dd>
		<dt>Signed</dt><dd><?php echo $e( self::phx( $s['signed_at'] ) ); ?></dd></dl>
	</div>
	<div class="sig"><h3><?php echo $e( $company ); ?></h3>
		<div class="line"><?php if ( $cs ) : ?><span class="cs"><?php echo $e( preg_replace( '/,.*$/', '', $s['countersign_name'] ) ); ?></span><?php else : ?><span class="pending">Awaiting countersignature</span><?php endif; ?></div>
		<dl><dt>By</dt><dd><?php echo $e( $cs ? $s['countersign_name'] : ( $m['signer'] ?? '' ) ); ?></dd>
		<dt>Signed</dt><dd><?php echo $e( $cs ? self::phx( $s['countersigned_at'] ) : '—' ); ?></dd></dl>
	</div>
</div>
<p class="note">AdaptExec's countersignature is optional: this Agreement is effective when the Representative signs it.</p>

<div class="box"><h2>Schedule A — Commission Schedule in effect at signing</h2>
<table class="sched"><tbody><?php echo $tiers; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></tbody></table>
<p class="note">One rate applies to the whole Contract Value, set by its size. <?php echo $ovr > 0 ? $e( 'A rate of ' . rtrim( rtrim( number_format( $ovr, 2 ), '0' ), '.' ) . '% was set for this Representative in the Hub at signing and applies instead of the table.' ) : ''; ?> Shown for reference; the schedule displayed in the Hub governs, and may change only as Section 3.12 describes.</p></div>

<div class="box"><h2>Audit trail</h2>
<table class="audit"><tbody><?php echo $audit; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></tbody></table>
<p class="note">To verify this copy, compute the SHA-256 of the agreement text as signed (stored by AdaptExec with this record); it must equal the agreement text SHA-256 above. The signature record SHA-256 covers the signer, text hash, signature image hash, IP address, browser and time.</p></div>
</div>
<div class="foot">Generated by the AdaptExec Sales Hub on <?php echo $e( gmdate( 'Y-m-d H:i' ) ); ?> UTC. Keep this copy for your records. To print or save as PDF, use your browser's Print command.</div>
</div></body></html>
		<?php
		return (string) ob_get_clean();
	}

	protected static function copy_name( $s ) {
		$n = sanitize_file_name( remove_accents( $s['legal_name'] ) );
		return 'AdaptExec-Contractor-Agreement-v' . $s['version'] . '-' . ( $n ? $n : 'rep' ) . '.html';
	}

	/** The rep's confirmation, with their signed copy attached. */
	protected static function email_rep_copy( $user, $sid ) {
		$s    = self::signature( $sid );
		$doc  = $s ? self::doc( $s['copy_doc'] ) : null;
		$html = $doc ? self::get_file( $doc['file'] ) : null;
		$att  = array();
		$tmp  = '';
		if ( $html && ! is_wp_error( $html ) ) {
			$tmpdir = trailingslashit( get_temp_dir() ) . 'axh-' . bin2hex( random_bytes( 6 ) );
			if ( wp_mkdir_p( $tmpdir ) ) {
				$tmp = $tmpdir . '/' . self::copy_name( $s );
				file_put_contents( $tmp, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$att[] = $tmp;
			}
		}
		$first = strtok( $user->display_name, ' ' );
		AXH_Mail::send(
			$user->user_email,
			'Your signed AdaptExec contractor agreement',
			array(
				'heading'   => 'You\'re signed, ' . $first . '.',
				'preheader' => 'Your signed Independent Contractor Agreement is attached.',
				'paras'     => array(
					'Thanks for signing the AdaptExec Independent Contractor Agreement (version ' . $s['version'] . '). Your signed copy is attached — open it in any browser to read or print it, or save it as a PDF.',
					'You can also view, print or download it any time in the Hub under Onboarding.',
				),
				'rows'      => array( 'Signed as' => $s['legal_name'], 'Signed' => self::phx( $s['signed_at'] ), 'Document ID' => 'AXH-ICA-' . str_pad( (string) $sid, 5, '0', STR_PAD_LEFT ) ),
				'cta'       => array( 'Open Onboarding', axh_portal_url( '#/onboarding' ) ),
				'footer'    => 'You received this because you signed an agreement in the AdaptExec Sales Hub. If that was not you, contact AdaptExec right away.',
			),
			array(),
			$att
		);
		if ( $tmp ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@rmdir( dirname( $tmp ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/** Raw-response helper for files: no caching, never sniffed, no framing. */
	protected static function send_file( $bytes, $mime, $filename, $inline = false ) {
		axh_nocache();
		nocache_headers();
		header( 'Content-Type: ' . $mime . ( 'text/html' === $mime ? '; charset=utf-8' : '' ) );
		$ascii = preg_replace( '/[^A-Za-z0-9._ -]/', '', $filename );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: DENY' );
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'" . ( 'text/html' === $mime ? '' : '; sandbox' ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput -- a file download.
		exit;
	}

	public static function r_copy( $req ) {
		$s = self::signature( (int) $req['id'] );
		if ( ! $s || ! ( axh_is_manager() || (int) $s['rep_id'] === get_current_user_id() ) ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$doc  = self::doc( $s['copy_doc'] );
		$html = $doc ? self::get_file( $doc['file'] ) : new WP_Error( 'axh_missing', 'The signed copy is missing.', array( 'status' => 404 ) );
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		self::audit( $s['rep_id'], 'agreement_viewed', 'Signed copy AXH-ICA-' . str_pad( (string) (int) $s['id'], 5, '0', STR_PAD_LEFT ) . ' opened' );
		self::send_file( $html, 'text/html', self::copy_name( $s ), ! $req->get_param( 'download' ) );
	}

	/* ---------------------------------------------------------------- W-9 */

	/** What a file really is, from its bytes — never from its name. */
	protected static function sniff( $bytes ) {
		$mime = '';
		if ( function_exists( 'finfo_open' ) ) {
			$f    = finfo_open( FILEINFO_MIME_TYPE );
			$mime = (string) finfo_buffer( $f, $bytes );
			finfo_close( $f );
		}
		$magic = '';
		if ( 0 === strpos( $bytes, '%PDF-' ) ) {
			$magic = 'application/pdf';
		} elseif ( 0 === strpos( $bytes, "\xFF\xD8\xFF" ) ) {
			$magic = 'image/jpeg';
		} elseif ( 0 === strpos( $bytes, "\x89PNG\r\n\x1a\n" ) ) {
			$magic = 'image/png';
		} elseif ( 'ftyp' === substr( $bytes, 4, 4 ) && in_array( substr( $bytes, 8, 4 ), array( 'heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1', 'heif' ), true ) ) {
			$magic = 'image/heic';
		}
		if ( in_array( $mime, array( 'image/heif', 'image/heic-sequence', 'image/heif-sequence' ), true ) ) {
			$mime = 'image/heic';
		}
		// finfo is the authority; on a server whose magic database predates
		// HEIC it says application/octet-stream, so the ftyp box decides.
		if ( 'image/heic' === $magic && in_array( $mime, array( '', 'application/octet-stream', 'image/heic' ), true ) ) {
			return 'image/heic';
		}
		if ( $magic && $mime === $magic ) {
			return $mime;
		}
		if ( ! function_exists( 'finfo_open' ) && $magic ) {
			return $magic;
		}
		return '';
	}

	public static function r_w9_upload( $req ) {
		global $wpdb;
		$user  = wp_get_current_user();
		$files = $req->get_file_params();
		$f     = $files['file'] ?? null;
		if ( ! $f || ! isset( $f['error'] ) ) {
			// A request bigger than post_max_size arrives with no file at all.
			$len = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
			if ( $len && $len > wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ) ) {
				return new WP_Error( 'axh_too_big', 'That file is larger than this server accepts. Keep it under ' . self::w9_max_mb() . ' MB — a PDF, or a photo taken at a smaller size, works.', array( 'status' => 413 ) );
			}
			return new WP_Error( 'axh_no_file', 'Choose your completed W-9 to upload.', array( 'status' => 400 ) );
		}
		if ( UPLOAD_ERR_INI_SIZE === (int) $f['error'] || UPLOAD_ERR_FORM_SIZE === (int) $f['error'] ) {
			return new WP_Error( 'axh_too_big', 'That file is larger than this server accepts. Keep it under ' . self::w9_max_mb() . ' MB — a PDF, or a photo taken at a smaller size, works.', array( 'status' => 413 ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $f['error'] || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'axh_upload', 'The upload did not finish. Please try again.', array( 'status' => 400 ) );
		}
		$size = (int) filesize( $f['tmp_name'] );
		if ( $size <= 0 ) {
			return new WP_Error( 'axh_empty', 'That file is empty.', array( 'status' => 400 ) );
		}
		if ( $size > self::W9_MAX ) {
			return new WP_Error( 'axh_too_big', 'That file is ' . size_format( $size, 1 ) . '. The limit is 10 MB — try a PDF, or a smaller photo.', array( 'status' => 413 ) );
		}
		if ( self::limited( 'w9try', 20 ) ) {
			return new WP_Error( 'axh_throttled', 'Too many upload attempts in the last hour. Please try again later.', array( 'status' => 429 ) );
		}
		$name = sanitize_file_name( wp_basename( (string) $f['name'] ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$fam  = array( 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'heic' => 'image/heic', 'heif' => 'image/heic' );
		if ( ! isset( $fam[ $ext ] ) ) {
			return new WP_Error( 'axh_type', 'Upload a PDF, JPG, PNG or HEIC file.', array( 'status' => 415 ) );
		}
		$bytes = (string) file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$mime  = self::sniff( $bytes );
		if ( ! $mime ) {
			return new WP_Error( 'axh_type', 'That file is not a PDF, JPG, PNG or HEIC image. Upload a PDF of your W-9, or a clear photo of it.', array( 'status' => 415 ) );
		}
		if ( $mime !== $fam[ $ext ] ) {
			return new WP_Error( 'axh_type_mismatch', 'That file\'s contents do not match its .' . $ext . ' name. Save it again as a PDF, JPG or PNG and upload that.', array( 'status' => 415 ) );
		}
		if ( self::limited( 'w9', 6 ) ) {
			return new WP_Error( 'axh_throttled', 'Too many uploads in the last hour. Please try again later.', array( 'status' => 429 ) );
		}
		$prev = self::w9_docs( $user->ID );
		$id   = self::add_doc( $user->ID, 'w9', $bytes, $mime, $name );
		unset( $bytes );
		@unlink( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$t = AXH_DB::table( 'docs' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'replaced' WHERE rep_id = %d AND kind = 'w9' AND id <> %d AND status IN ('current','rejected')", $user->ID, $id ) );
		delete_user_meta( $user->ID, 'axh_w9_reject_note' );
		self::audit( $user->ID, 'w9_uploaded', sprintf( 'Uploaded a W-9 (%s, %s)%s', strtoupper( $ext ), size_format( $size, 1 ), $prev ? ' — replaces the earlier one, which is kept' : '' ) );
		AXH_Notify::managers_add(
			'onboarding_update',
			$user->display_name . ( $prev ? ' uploaded a new W-9' : ' uploaded their W-9' ),
			$user->display_name . ' uploaded a completed Form W-9. It is encrypted in the Hub — open it under Onboarding. For security the W-9 itself is never emailed.',
			'#/manage/onboarding/rep/' . $user->ID
		);
		return rest_ensure_response( array( 'ok' => true, 'state' => self::state_for( $user->ID ) ) );
	}

	public static function r_w9_file( $req ) {
		$d = self::doc( (int) $req['id'] );
		if ( ! $d || 'w9' !== $d['kind'] || ! self::can_see( $d ) ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$bytes = self::get_file( $d['file'] );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}
		$u    = get_userdata( (int) $d['rep_id'] );
		$ext  = array( 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic' )[ $d['mime'] ] ?? 'bin';
		$name = 'W-9 - ' . ( $u ? remove_accents( $u->display_name ) : 'rep' ) . ' - ' . mysql2date( 'Y-m-d', $d['created_at'] ) . '.' . $ext;
		self::audit( $d['rep_id'], 'w9_downloaded', 'W-9 #' . (int) $d['id'] . ' downloaded' . ( (int) $d['rep_id'] === get_current_user_id() ? ' by the rep' : '' ) );
		self::send_file( $bytes, $d['mime'], $name );
	}

	/* -------------------------------------------------------------- payee */

	public static function states() {
		return array( 'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'PR', 'GU', 'VI', 'AS', 'MP', 'AA', 'AE', 'AP' );
	}

	protected static function clean_payee( $b, $user ) {
		$t   = function ( $k, $len ) use ( $b ) { return trim( mb_substr( preg_replace( '/\s+/', ' ', sanitize_text_field( $b[ $k ] ?? '' ) ), 0, $len ) ); };
		$p   = array(
			'legal_name'    => $t( 'legal_name', 120 ),
			'business_name' => $t( 'business_name', 120 ),
			'payable_to'    => $t( 'payable_to', 120 ),
			'street'        => $t( 'street', 120 ),
			'unit'          => $t( 'unit', 40 ),
			'city'          => $t( 'city', 80 ),
			'state'         => strtoupper( $t( 'state', 2 ) ),
			'zip'           => $t( 'zip', 10 ),
			'country'       => 'US',
			'phone'         => $t( 'phone', 30 ),
			'email'         => sanitize_email( $b['email'] ?? '' ),
		);
		if ( '' === $p['payable_to'] ) {
			$p['payable_to'] = $p['legal_name'];
		}
		if ( '' === $p['email'] ) {
			$p['email'] = $user->user_email;
		}
		$err = array();
		if ( mb_strlen( $p['legal_name'] ) < 3 ) {
			$err['legal_name'] = 'Enter your full legal name.';
		}
		if ( mb_strlen( $p['street'] ) < 3 || ! preg_match( '/\d/', $p['street'] ) && ! preg_match( '/^(p\.?\s*o\.?\s*box|rr|hc)/i', $p['street'] ) ) {
			$err['street'] = 'Enter the street address, including the number (or a PO Box).';
		}
		if ( mb_strlen( $p['city'] ) < 2 ) {
			$err['city'] = 'Enter the city.';
		}
		if ( ! in_array( $p['state'], self::states(), true ) ) {
			$err['state'] = 'Choose a state.';
		}
		if ( ! preg_match( '/^\d{5}(-\d{4})?$/', $p['zip'] ) ) {
			$err['zip'] = 'Enter a 5-digit ZIP code (or ZIP+4, like 85251-1234).';
		}
		$digits = preg_replace( '/\D/', '', $p['phone'] );
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		if ( 10 !== strlen( $digits ) ) {
			$err['phone'] = 'Enter a 10-digit US phone number.';
		} else {
			$p['phone'] = '(' . substr( $digits, 0, 3 ) . ') ' . substr( $digits, 3, 3 ) . '-' . substr( $digits, 6 );
		}
		if ( ! is_email( $p['email'] ) ) {
			$err['email'] = 'Enter a valid email address.';
		}
		return $err ? new WP_Error( 'axh_payee_invalid', reset( $err ), array( 'status' => 400, 'fields' => $err ) ) : $p;
	}

	public static function r_payee( $req ) {
		$b    = (array) $req->get_json_params();
		$user = wp_get_current_user();
		$p    = self::clean_payee( $b, $user );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		if ( empty( $b['confirm'] ) ) {
			return new WP_Error( 'axh_confirm', 'Check the address and tick the box to confirm it is correct.', array( 'status' => 400 ) );
		}
		$old = self::payee( $user->ID );
		$key = array( 'legal_name', 'business_name', 'payable_to', 'street', 'unit', 'city', 'state', 'zip' );
		$changed = array();
		if ( $old ) {
			foreach ( array_merge( $key, array( 'phone', 'email' ) ) as $k ) {
				if ( (string) ( $old[ $k ] ?? '' ) !== (string) $p[ $k ] ) {
					$changed[] = $k;
				}
			}
			if ( ! $changed ) {
				return rest_ensure_response( array( 'ok' => true, 'unchanged' => true, 'state' => self::state_for( $user->ID ) ) );
			}
			if ( empty( $b['confirm_change'] ) ) {
				return new WP_Error( 'axh_confirm_change', 'Confirm that you want to change where your commission checks are mailed.', array( 'status' => 400 ) );
			}
		}
		if ( self::limited( 'payee', 10 ) ) {
			return new WP_Error( 'axh_throttled', 'Too many changes in the last hour. Please try again later.', array( 'status' => 429 ) );
		}
		$now = current_time( 'mysql' );
		update_user_meta( $user->ID, 'axh_payee', $p );
		update_user_meta( $user->ID, 'axh_payee_saved_at', $now );
		$addr_changed = (bool) array_intersect( $changed, $key );
		$c            = AXH_Mail::contact();
		$lines        = implode( "\n", array_merge( array( $p['payable_to'] ), self::address_lines( $p ) ) );

		if ( ! $old ) {
			update_user_meta( $user->ID, 'axh_payee_status', 'set' );
			self::audit( $user->ID, 'payee_set', array( 'event' => 'Payout address added', 'address' => $p ) );
			AXH_Notify::managers_add(
				'onboarding_update',
				$user->display_name . ' added their payout address',
				$user->display_name . ' set where commission checks should be mailed.',
				'#/manage/onboarding/rep/' . $user->ID,
				null,
				array( 'rows' => array( 'Checks payable to' => $p['payable_to'], 'Mail to' => implode( "\n", self::address_lines( $p ) ) ) )
			);
		} else {
			if ( $addr_changed ) {
				update_user_meta( $user->ID, 'axh_payee_status', 'verify' );
				update_user_meta( $user->ID, 'axh_payee_changed_at', $now );
			}
			self::audit( $user->ID, 'payee_changed', array( 'event' => $addr_changed ? 'Payout address changed' : 'Payout contact details changed', 'changed' => $changed, 'address' => $p, 'previous' => $old ) );
			// Tell the account holder at their account email, whoever made the change.
			AXH_Mail::send(
				$user->user_email,
				'Your AdaptExec payout details were changed',
				array(
					'heading'   => $addr_changed ? 'Your payout address was changed' : 'Your payout details were changed',
					'preheader' => 'If this wasn\'t you, contact ' . $c['name'] . ' right away.',
					'paras'     => array(
						'Hi ' . strtok( $user->display_name, ' ' ) . ', the ' . ( $addr_changed ? 'name or mailing address your AdaptExec commission checks are sent to' : 'contact details for your commission payments' ) . ' changed in the Sales Hub on ' . self::phx( gmdate( 'Y-m-d H:i:s' ) ) . '.',
						$addr_changed ? 'For your protection, AdaptExec verifies a new address before mailing the next check.' : 'No action is needed if you made this change.',
						'If this wasn\'t you, contact ' . $c['name'] . ' right away at ' . $c['phone'] . ' or ' . $c['email'] . ', and change your Hub password.',
					),
					'rows'      => array( 'Checks payable to' => $p['payable_to'], 'Mail to' => implode( "\n", self::address_lines( $p ) ), 'Changed' => implode( ', ', array_map( array( __CLASS__, 'field_label' ), $changed ) ) ),
					'cta'       => array( 'Review in the Hub', axh_portal_url( '#/onboarding/payment' ) ),
					'footer'    => 'This security notice is sent whenever payout details change. It cannot be turned off.',
				)
			);
			AXH_Notify::managers_add(
				'onboarding_update',
				$user->display_name . ( $addr_changed ? ' changed their payout address — verify before the next check' : ' updated their payout contact details' ),
				$user->display_name . ( $addr_changed ? ' changed where commission checks are mailed. Confirm the change with them (by phone or the email you already have) before sending the next check, then mark it verified under Onboarding.' : ' changed the phone or email on their payout details.' ),
				'#/manage/onboarding/rep/' . $user->ID,
				null,
				array( 'rows' => array( 'Checks payable to' => $p['payable_to'], 'New mailing address' => implode( "\n", self::address_lines( $p ) ), 'Previous' => implode( "\n", array_merge( array( $old['payable_to'] ), self::address_lines( $old ) ) ), 'Changed' => implode( ', ', array_map( array( __CLASS__, 'field_label' ), $changed ) ) ) )
			);
		}
		return rest_ensure_response( array( 'ok' => true, 'changed' => $changed, 'state' => self::state_for( $user->ID ) ) );
	}

	public static function field_label( $k ) {
		$l = array( 'legal_name' => 'legal name', 'business_name' => 'business name', 'payable_to' => 'payable to', 'street' => 'street', 'unit' => 'unit', 'city' => 'city', 'state' => 'state', 'zip' => 'ZIP', 'phone' => 'phone', 'email' => 'email' );
		return $l[ $k ] ?? $k;
	}

	/* ------------------------------------------------------------- manage */

	public static function r_manage( $req ) {
		global $wpdb;
		$a    = AXH_DB::table( 'audit' );
		$reps = array();
		foreach ( AXH_Team::roster() as $r ) {
			$st     = self::status( $r['id'] );
			$last   = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM {$a} WHERE rep_id = %d", $r['id'] ) );
			$payee  = self::payee( $r['id'] );
			$reps[] = array(
				'id'         => (int) $r['id'],
				'name'       => $r['name'],
				'email'      => $r['email'],
				'status'     => $r['status'],
				'active'     => $r['active'],
				'onboarding' => $st,
				'payee'      => $payee ? array( 'payable_to' => $payee['payable_to'], 'lines' => self::address_lines( $payee ) ) : null,
				'last'       => $last ? $last : ( $r['last_login'] ? $r['last_login'] : null ),
				'reminded'   => (string) get_user_meta( $r['id'], 'axh_onb_reminded_at', true ),
				'registered' => $r['registered'],
			);
		}
		$cur = self::current();
		return rest_ensure_response( array(
			'reps'      => $reps,
			'agreement' => array( 'version' => $cur['version'], 'effective' => $cur['effective'], 'hash' => $cur['hash'] ),
			'settings'  => self::settings(),
		) );
	}

	public static function r_manage_rep( $req ) {
		$u = self::rep_or_404( $req['id'] );
		if ( is_wp_error( $u ) ) {
			return $u;
		}
		return rest_ensure_response( array_merge(
			array( 'rep' => array( 'id' => $u->ID, 'name' => $u->display_name, 'email' => $u->user_email, 'login' => $u->user_login, 'phone' => (string) get_user_meta( $u->ID, 'axh_phone', true ), 'active' => AXH_Team::is_active( $u ), 'status' => AXH_Team::status( $u ) ) ),
			self::state_for( $u->ID, true )
		) );
	}

	public static function r_manage_do( $req ) {
		global $wpdb;
		$u = self::rep_or_404( $req['id'] );
		if ( is_wp_error( $u ) ) {
			return $u;
		}
		$b    = (array) $req->get_json_params();
		$do   = sanitize_key( $b['do'] ?? '' );
		$note = mb_substr( sanitize_textarea_field( $b['note'] ?? '' ), 0, 1000 );
		$me   = wp_get_current_user();
		$out  = array( 'ok' => true );

		if ( 'verify_payee' === $do ) {
			if ( ! self::payee( $u->ID ) ) {
				return new WP_Error( 'axh_no_payee', 'There is no payout address to verify yet.', array( 'status' => 400 ) );
			}
			update_user_meta( $u->ID, 'axh_payee_status', 'set' );
			update_user_meta( $u->ID, 'axh_payee_verified_at', current_time( 'mysql' ) );
			update_user_meta( $u->ID, 'axh_payee_verified_by', $me->display_name );
			self::audit( $u->ID, 'payee_verified', 'Payout address verified' . ( $note ? ': ' . $note : '' ) );
		} elseif ( 'remind' === $do ) {
			$sent = self::send_reminder( $u, true );
			if ( ! $sent ) {
				return new WP_Error( 'axh_mail', 'The reminder email could not be sent. Check WP Mail SMTP.', array( 'status' => 500 ) );
			}
			$out['emailed'] = true;
		} elseif ( 'complete' === $do ) {
			update_user_meta( $u->ID, 'axh_onb_override', array( 'by' => $me->display_name, 'at' => current_time( 'mysql' ), 'note' => $note ) );
			self::audit( $u->ID, 'onboarding_override', 'Marked onboarding complete' . ( $note ? ': ' . $note : '' ) );
		} elseif ( 'uncomplete' === $do ) {
			delete_user_meta( $u->ID, 'axh_onb_override' );
			self::audit( $u->ID, 'onboarding_override_removed', 'Removed the "complete" override' );
		} elseif ( 'resign' === $do ) {
			if ( ! self::signatures( $u->ID ) ) {
				return new WP_Error( 'axh_nothing', 'They have not signed yet, so there is nothing to re-sign.', array( 'status' => 400 ) );
			}
			$latest = self::signatures( $u->ID );
			update_user_meta( $u->ID, 'axh_onb_resign_sig', (int) $latest[0]['id'] );
			update_user_meta( $u->ID, 'axh_onb_resign_at', current_time( 'mysql' ) );
			self::audit( $u->ID, 'resign_requested', 'Asked to re-sign the agreement' . ( $note ? ': ' . $note : '' ) );
			AXH_Notify::add( $u->ID, 'onboarding_admin', 'Please re-sign your contractor agreement', 'AdaptExec asked you to review and sign the Independent Contractor Agreement again.' . ( $note ? "\n\nNote from AdaptExec: " . $note : '' ), '#/onboarding/agreement' );
		} elseif ( 'reject_w9' === $do ) {
			$t = AXH_DB::table( 'docs' );
			$n = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'rejected' WHERE rep_id = %d AND kind = 'w9' AND status = 'current'", $u->ID ) );
			if ( ! $n ) {
				return new WP_Error( 'axh_nothing', 'There is no W-9 on file to send back.', array( 'status' => 400 ) );
			}
			update_user_meta( $u->ID, 'axh_w9_reject_note', $note );
			self::audit( $u->ID, 'w9_rejected', 'Asked for a new W-9' . ( $note ? ': ' . $note : '' ) );
			AXH_Notify::add( $u->ID, 'onboarding_admin', 'Please upload a new W-9', 'AdaptExec needs a new Form W-9 from you before your next payment.' . ( $note ? "\n\nNote from AdaptExec: " . $note : '' ), '#/onboarding/w9' );
		} else {
			return new WP_Error( 'axh_bad_action', 'Unknown action.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array_merge( $out, array( 'state' => self::r_manage_rep( $req )->get_data() ) ) );
	}

	public static function r_countersign( $req ) {
		global $wpdb;
		$s = self::signature( (int) $req['id'] );
		if ( ! $s ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		if ( ! empty( $s['countersigned_at'] ) ) {
			return new WP_Error( 'axh_done', 'Already countersigned.', array( 'status' => 400 ) );
		}
		$name = self::settings()['signer'];
		$wpdb->update(
			AXH_DB::table( 'signatures' ),
			array( 'countersigned_by' => get_current_user_id(), 'countersign_name' => $name, 'countersigned_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => (int) $s['id'] )
		);
		$doc = self::make_copy( (int) $s['id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		self::audit( $s['rep_id'], 'agreement_countersigned', 'Countersigned by ' . $name );
		AXH_Notify::add( (int) $s['rep_id'], 'onboarding_admin', 'Your contractor agreement was countersigned', $name . ' countersigned your Independent Contractor Agreement. The updated signed copy is in the Hub under Onboarding.', '#/onboarding/agreement' );
		return rest_ensure_response( array( 'ok' => true, 'signature' => self::sig_public( self::signature( (int) $s['id'] ) ) ) );
	}

	public static function r_settings( $req ) {
		global $wpdb;
		$cur  = self::current();
		$t    = AXH_DB::table( 'signatures' );
		$hash = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT rep_id) FROM {$t} WHERE text_hash = %s", $cur['hash'] ) );
		$vers = array();
		foreach ( self::versions() as $v ) {
			$vers[] = array( 'version' => $v['version'], 'effective' => $v['effective'], 'note' => $v['note'], 'created_at' => $v['created_at'] );
		}
		$all = self::versions();
		return rest_ensure_response( array(
			'settings'   => self::settings(),
			'agreement'  => array_merge( $cur, array( 'template' => end( $all )['template'], 'signed_current' => (int) $hash ) ),
			'versions'   => array_reverse( $vers ),
			'private'    => array( 'dir_url' => self::dir_url(), 'sodium' => function_exists( 'sodium_crypto_secretbox' ), 'finfo' => function_exists( 'finfo_open' ), 'writable' => self::ensure_dir(), 'custom_dir' => defined( 'AXH_PRIVATE_DIR' ) ),
		) );
	}

	public static function r_settings_save( $req ) {
		$b      = (array) $req->get_json_params();
		$before = self::current()['hash'];
		self::save_settings( $b );
		$after = self::current()['hash'];
		if ( $before !== $after ) {
			self::audit( 0, 'agreement_details', 'Company details in the agreement changed; text SHA-256 is now ' . $after );
		}
		return self::r_settings( $req );
	}

	public static function r_publish( $req ) {
		$b = (array) $req->get_json_params();
		$r = self::publish_version( (string) ( $b['template'] ?? '' ), (string) ( $b['version'] ?? '' ), (string) ( $b['note'] ?? '' ) );
		return is_wp_error( $r ) ? $r : self::r_settings( $req );
	}

	/**
	 * Prove the private folder is not web-reachable: write a harmless probe
	 * file through the same code path, hand back its URL for the browser to
	 * try, then remove it.
	 */
	public static function r_selftest( $req ) {
		$b = (array) $req->get_json_params();
		if ( 'cleanup' === ( $b['do'] ?? '' ) ) {
			$name = (string) ( $b['name'] ?? '' );
			if ( preg_match( '/^probe-[a-f0-9]{16}\.txt$/', $name ) && file_exists( self::dir() . '/' . $name ) ) {
				@unlink( self::dir() . '/' . $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			return rest_ensure_response( array( 'ok' => true, 'removed' => ! file_exists( self::dir() . '/' . $name ) ) );
		}
		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'axh_no_dir', 'The private folder could not be created.', array( 'status' => 500 ) );
		}
		$name = 'probe-' . bin2hex( random_bytes( 8 ) ) . '.txt';
		file_put_contents( self::dir() . '/' . $name, "AdaptExec Hub access test. If you can read this in a browser, the private folder is exposed.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$url = self::dir_url();
		return rest_ensure_response( array( 'ok' => true, 'name' => $name, 'url' => $url ? $url . $name : '', 'dir' => $url ) );
	}

	/* ---------------------------------------------------------- reminders */

	protected static function remaining_steps( $st ) {
		$left = array();
		if ( 'signed' !== $st['steps']['agreement']['state'] ) {
			$left[] = 'resign' === $st['steps']['agreement']['state'] ? 'Review and re-sign the updated Independent Contractor Agreement' : 'Read and sign your Independent Contractor Agreement';
		}
		if ( 'on_file' !== $st['steps']['w9']['state'] ) {
			$left[] = 'Upload your completed Form W-9';
		}
		if ( 'todo' === $st['steps']['payee']['state'] ) {
			$left[] = 'Add the mailing address for your commission checks';
		}
		return $left;
	}

	public static function send_reminder( $user, $manual = false ) {
		$st   = self::status( $user->ID );
		$left = self::remaining_steps( $st );
		if ( ! $left ) {
			return false;
		}
		$c  = AXH_Mail::contact();
		$ok = AXH_Mail::send(
			$user->user_email,
			'Finish your AdaptExec onboarding',
			array(
				'heading'   => 'A few minutes to finish onboarding',
				'preheader' => 'Commission checks can only go out once these are done.',
				'paras'     => array(
					'Hi ' . strtok( $user->display_name, ' ' ) . ', you\'re ' . $st['done'] . ' of 3 steps through onboarding in the AdaptExec Sales Hub. AdaptExec can\'t send commission checks until these are done:',
				),
				'sections'  => array( array( 'title' => 'Still to do', 'list' => $left ) ),
				'cta'       => array( 'Finish onboarding', axh_portal_url( '#/onboarding' ) ),
				'footer'    => 'Questions? ' . $c['name'] . ' · ' . $c['phone'] . '. ' . ( $manual ? 'Sent by ' . $c['name'] . '.' : 'Daily reminder while onboarding is incomplete; it stops once you finish.' ),
			)
		);
		update_user_meta( $user->ID, 'axh_onb_reminded_at', current_time( 'mysql' ) );
		update_user_meta( $user->ID, 'axh_onb_reminder_count', (int) get_user_meta( $user->ID, 'axh_onb_reminder_count', true ) + 1 );
		self::audit( $user->ID, 'reminder_sent', ( $manual ? 'Reminder sent by AdaptExec' : 'Automatic reminder' ) . ' (' . count( $left ) . ' step' . ( 1 === count( $left ) ? '' : 's' ) . ' left)' . ( $ok ? '' : ' — mail server refused it' ) );
		return $ok;
	}

	/**
	 * Daily (from the 7am Hub run): a reminder to reps who are 3+ days in and
	 * not finished. Once per day at most; never to anyone whose account
	 * existed before 1.6.0 was installed; stops after 10 reminders.
	 */
	public static function reminders( $force = false ) {
		if ( ! self::settings()['reminders'] ) {
			return array( 'off' => true );
		}
		$today = wp_date( 'Y-m-d' );
		if ( ! $force && get_option( 'axh_onb_reminders_ran' ) === $today ) {
			return array( 'skipped' => true );
		}
		update_option( 'axh_onb_reminders_ran', $today, false );
		$since = (int) get_option( 'axh_onb_since', 0 );
		$sent  = 0;
		foreach ( get_users( array( 'role__in' => array( AXH_ROLE ) ) ) as $u ) {
			if ( ! AXH_Team::is_rep_account( $u ) ) {
				continue;
			}
			$joined = strtotime( $u->user_registered . ' UTC' );
			if ( ! $since || $joined < $since || $joined > time() - 3 * DAY_IN_SECONDS ) {
				continue;
			}
			if ( (int) get_user_meta( $u->ID, 'axh_onb_reminder_count', true ) >= 10 ) {
				continue;
			}
			$last = (string) get_user_meta( $u->ID, 'axh_onb_reminded_at', true );
			if ( $last && substr( $last, 0, 10 ) === $today ) {
				continue;
			}
			$st = self::status( $u->ID );
			if ( $st['complete'] ) {
				continue;
			}
			$sent += (int) self::send_reminder( $u );
		}
		return array( 'sent' => $sent );
	}
}
