<?php
/**
 * The front-end portal: routing, template, assets, login, and deck discovery.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_Portal {

	const NS_PUBLIC = 'axh/v1';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_wp_admin' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	public static function add_rewrite_rules() {
		$slug = AXH_Install::portal_slug();
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?axh_portal=1', 'top' );
		add_rewrite_rule( '^f/([^/]+)/?$', 'index.php?axh_form=$matches[1]', 'top' );
		add_rewrite_rule( '^s/([^/]+)/?$', 'index.php?axh_share=$matches[1]', 'top' );
		add_rewrite_rule( '^deck/([^/]+)/?$', 'index.php?axh_deck=$matches[1]', 'top' );
		add_rewrite_rule( '^d/([^/]+)/?$', 'index.php?axh_discovery=$matches[1]', 'top' );
		add_rewrite_rule( '^style/([a-f0-9]{16,32})/?$', 'index.php?axh_style=$matches[1]', 'top' );
		// Billing (1.7.0): a customer's proposal, estimate or invoice.
		add_rewrite_rule( '^([pei])/([a-f0-9]{32,64})/?$', 'index.php?axh_bill=$matches[2]&axh_bill_t=$matches[1]', 'top' );
		add_rewrite_tag( '%axh_portal%', '([0-9]+)' );
		add_rewrite_tag( '%axh_form%', '([^&]+)' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'axh_portal';
		$vars[] = 'axh_form';
		$vars[] = 'axh_share';
		$vars[] = 'axh_deck';
		$vars[] = 'axh_discovery';
		$vars[] = 'axh_style';
		$vars[] = 'axh_bill';
		$vars[] = 'axh_bill_t';
		return $vars;
	}

	/**
	 * Reps have no business in wp-admin; send them to the hub instead.
	 */
	public static function block_wp_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		if ( current_user_can( 'axh_manage_all' ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( current_user_can( 'axh_use_hub' ) ) {
			wp_safe_redirect( axh_portal_url() );
			exit;
		}
	}

	public static function hide_admin_bar( $show ) {
		if ( is_user_logged_in() && current_user_can( 'axh_use_hub' ) && ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return $show;
	}

	public static function login_redirect( $to, $requested, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $to;
		}
		if ( user_can( $user, 'axh_use_hub' ) && ! user_can( $user, 'edit_posts' ) ) {
			return axh_portal_url();
		}
		return $to;
	}

	/**
	 * Serve the app (or the branded sign-in screen) at the portal URL.
	 */
	public static function render() {
		$form_slug = get_query_var( 'axh_form' );
		if ( $form_slug ) {
			self::render_form( $form_slug );
			exit;
		}

		$share_token = get_query_var( 'axh_share' );
		if ( $share_token ) {
			self::render_share( $share_token );
			exit;
		}

		$deck_slug = get_query_var( 'axh_deck' );
		if ( $deck_slug ) {
			self::render_deck( $deck_slug );
			exit;
		}

		$style_token = get_query_var( 'axh_style' );
		if ( $style_token ) {
			self::render_style( $style_token );
			exit;
		}

		$bill_token = get_query_var( 'axh_bill' );
		if ( $bill_token ) {
			AXH_Bill_View::serve( sanitize_key( get_query_var( 'axh_bill_t' ) ), $bill_token );
			exit;
		}

		$disc_token = get_query_var( 'axh_discovery' );
		if ( $disc_token ) {
			self::render_discovery( $disc_token );
			exit;
		}

		if ( ! get_query_var( 'axh_portal' ) ) {
			return;
		}

		nocache_headers();

		if ( ! is_user_logged_in() || ! current_user_can( 'axh_use_hub' ) ) {
			self::render_login();
			exit;
		}

		self::enqueue();
		include AXH_DIR . 'templates/app.php';
		exit;
	}

	protected static function render_login() {
		$error = '';

		if ( ! empty( $_POST['axh_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( 'axh_login' );
			$creds = array(
				'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ),
				'user_password' => (string) ( $_POST['pwd'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				'remember'      => ! empty( $_POST['rememberme'] ),
			);
			$user  = wp_signon( $creds, is_ssl() );
			if ( is_wp_error( $user ) ) {
				$error = 'That email or password did not match. Try again.';
			} elseif ( ! user_can( $user, 'axh_use_hub' ) ) {
				wp_logout();
				$error = 'That account does not have Hub access.';
			} else {
				wp_safe_redirect( axh_portal_url() );
				exit;
			}
		}

		include AXH_DIR . 'templates/login.php';
	}

	/**
	 * The public lead-capture form at /f/<slug>/.
	 */
	/**
	 * The client's own discovery form at /d/<token>/.
	 *
	 * No login: the token is the credential, and it reaches exactly one lead's
	 * questionnaire. The sections a rep switched off are not sent, and the
	 * sections meant for us are never sent at all.
	 */
	protected static function render_discovery( $token ) {
		nocache_headers();

		if ( 'preview' === $token ) {
			self::render_discovery_preview();
			return;
		}

		$disc = AXH_DB::get_discovery_by_token( $token );
		if ( ! $disc ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}

		if ( ! self::is_preview_bot() && ! self::is_internal_viewer( $disc['lead']['rep_id'] ?? 0 ) ) {
			AXH_DB::discovery_opened( $disc['token'] );
		}

		$lead   = $disc['lead'];
		$rep    = $lead['rep_id'] ? get_user_by( 'id', (int) $lead['rep_id'] ) : null;
		$schema = axh_discovery_visible(
			axh_discovery_schema( $lead['industry'] ),
			(array) $disc['config'],
			true
		);

		// Only the answers to sections this client is shown: the rep's internal
		// notes never reach the page (1.7.1).
		$answers = array();
		foreach ( $schema as $sec ) {
			if ( isset( $disc['payload'][ $sec['key'] ] ) ) {
				$answers[ $sec['key'] ] = $disc['payload'][ $sec['key'] ];
			}
		}

		$config = array(
			'token'   => $disc['token'],
			'rest'    => esc_url_raw( rest_url( 'axh/v1/public/discovery/' . $disc['token'] ) ),
			'nonce'   => '',
			'schema'  => $schema,
			'payload' => $answers ? $answers : new stdClass(),
			'status'  => $disc['status'],
			'company' => $lead['company'],
			'rep'     => $rep ? $rep->display_name : 'AdaptExec',
			// The optional "Pick your style" step.
			'styles'    => true,
			'styleRest' => esc_url_raw( rest_url( 'axh/v1/public/discovery/' . $disc['token'] . '/styles' ) ),
			'style'     => AXH_DB::style_value( $lead ),
		);

		include AXH_DIR . 'templates/discovery-public.php';
	}

	/**
	 * /d/preview/ (1.7.1): what a prospect sees, for anyone signed in to the
	 * Hub. Sample only — no lead, nothing saved, nothing counted. ?industry=
	 * shows a trade packet.
	 */
	protected static function render_discovery_preview() {
		if ( ! is_user_logged_in() || ! current_user_can( 'axh_use_hub' ) ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}
		$industry = sanitize_text_field( wp_unslash( $_GET['industry'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$config   = array(
			'token'     => 'preview',
			'rest'      => '',
			'nonce'     => '',
			'preview'   => true,
			'schema'    => axh_discovery_visible( axh_discovery_schema( $industry ), array(), true ),
			'payload'   => new stdClass(),
			'status'    => 'draft',
			'company'   => 'Your business',
			'rep'       => wp_get_current_user()->display_name,
			'styles'    => true,
			'styleRest' => '',
			'style'     => null,
		);
		include AXH_DIR . 'templates/discovery-public.php';
	}

	protected static function render_form( $slug ) {
		nocache_headers();

		$form = AXH_DB::form_by_slug( $slug );
		if ( ! $form ) {
			status_header( 404 );
			include AXH_DIR . 'templates/form-missing.php';
			return;
		}

		// A page that pre-loads this form's settings in the background (the
		// website contact page does) is not a visitor; count real page views only.
		if ( 'empty' !== strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '' ) ) ) && ! self::is_preview_bot() && ! self::is_internal_viewer( $form['rep_id'] ) ) {
			AXH_DB::count_form_view( $form['id'] );
		}

		$catalog = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			foreach ( $step['fields'] as $f ) {
				$catalog[ $f['key'] ] = $f;
			}
		}

		// Build the steps this form actually shows.
		$steps = array();
		foreach ( AXH_Install::form_fields() as $step ) {
			$fields = array();
			foreach ( $step['fields'] as $f ) {
				if ( in_array( $f['key'], (array) $form['fields'], true ) ) {
					$fields[] = $f;
				}
			}
			if ( $fields ) {
				$steps[] = array( 'key' => $step['key'], 'name' => $step['name'], 'fields' => $fields );
			}
		}
		if ( ! empty( $form['custom'] ) ) {
			$extra = array();
			foreach ( $form['custom'] as $i => $c ) {
				$item = array(
					'key'      => 'custom_' . $i,
					'label'    => $c['label'],
					'type'     => 'textarea' === $c['type'] ? 'textarea' : 'text',
					'required' => ! empty( $c['required'] ),
					'width'    => 'full',
				);
				if ( 'select' === ( $c['type'] ?? '' ) && ! empty( $c['options'] ) ) {
					$item['type']    = 'select';
					$item['options'] = (array) $c['options'];
				}
				$extra[] = $item;
			}
			$steps[] = array( 'key' => 'custom', 'name' => 'A few more questions', 'fields' => $extra );
		}
		// Optional style step, just before the last step (forms without the
		// toggle are unchanged, including what the website contact page reads).
		$styles = ! empty( $form['style_picker'] );
		if ( $styles ) {
			$style_step = array( 'key' => 'style', 'name' => 'Pick a look you like', 'fields' => array(), 'style' => true );
			if ( count( $steps ) >= 2 ) {
				array_splice( $steps, count( $steps ) - 1, 0, array( $style_step ) );
			} else {
				$steps[] = $style_step; // A one-step form: the style step comes last, with the send button.
			}
		}

		$rep = get_userdata( (int) $form['rep_id'] );

		$config = array(
			'root'   => esc_url_raw( rest_url( self::NS_PUBLIC ) ),
			'nonce'  => wp_create_nonce( 'axh_public_form' ),
			'slug'   => $form['slug'],
			// Render time, signed, so the "filled impossibly fast" check cannot be
			// skipped by sending t=0. Pages (and the contact page) pass it back as-is.
			't'      => AXH_Rest::form_stamp( $form['slug'] ),
			'steps'  => $steps,
			'styles' => $styles,
		);
		// 1.9.0: call/text consent box and the "pick a time" button.
		$config = array_merge( $config, AXH_P1::public_config( $form, $rep ) );

		include AXH_DIR . 'templates/form-public.php';
	}

	/**
	 * The app's <head>: its own stylesheet and the site icon, nothing else.
	 * wp_head() would also pull in the theme, Elementor, the translate
	 * switcher and the chat bubble — none of which belong in a work tool, and
	 * which covered the Hub's buttons on phones.
	 */
	public static function head() {
		if ( function_exists( 'wp_site_icon' ) ) {
			wp_site_icon();
		}
		wp_print_styles( array( 'axh-hub' ) );
	}

	/**
	 * Link-preview tags (Open Graph + Twitter) for the Hub's public pages, so a
	 * shared /s/, /f/ or /d/ link unfurls with the branded 1200x630 card on
	 * Facebook, LinkedIn, iMessage, Slack, X and the rest.
	 *
	 * @param array $args title, description, url, type (default 'website').
	 */
	public static function share_tags( $args ) {
		$args  = wp_parse_args(
			(array) $args,
			array(
				'title'       => 'AdaptExec',
				'description' => 'One platform built around how your business actually runs.',
				'url'         => '',
				'type'        => 'website',
			)
		);
		$image = (string) apply_filters( 'axh_share_image', wp_get_attachment_image_url( 3435, 'full' ) );
		$title = wp_strip_all_tags( (string) $args['title'] );
		$desc  = wp_strip_all_tags( (string) $args['description'] );

		$tags = array(
			array( 'property', 'og:type', $args['type'] ),
			array( 'property', 'og:site_name', 'AdaptExec' ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $desc ),
		);
		if ( $args['url'] ) {
			$tags[] = array( 'property', 'og:url', esc_url_raw( $args['url'] ) );
		}
		if ( $image ) {
			$tags[] = array( 'property', 'og:image', esc_url_raw( $image ) );
			$tags[] = array( 'property', 'og:image:secure_url', esc_url_raw( $image ) );
			$tags[] = array( 'property', 'og:image:width', '1200' );
			$tags[] = array( 'property', 'og:image:height', '630' );
			$tags[] = array( 'property', 'og:image:alt', 'AdaptExec' );
		}
		$tags[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );
		$tags[] = array( 'name', 'twitter:title', $title );
		$tags[] = array( 'name', 'twitter:description', $desc );
		if ( $image ) {
			$tags[] = array( 'name', 'twitter:image', esc_url_raw( $image ) );
		}

		foreach ( $tags as $t ) {
			printf( "\t<meta %s=\"%s\" content=\"%s\">\n", $t[0], esc_attr( $t[1] ), esc_attr( $t[2] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- attribute name is a literal.
		}
	}

	/**
	 * True for the robots that fetch a link to draw its preview card. Their
	 * visits are not people opening a shared deck, so they are not counted.
	 */
	public static function is_preview_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === trim( $ua ) ) {
			return true; // Real browsers always send one.
		}
		return (bool) preg_match( '/facebookexternalhit|Facebot|Twitterbot|Slackbot|LinkedInBot|WhatsApp|Discordbot|TelegramBot|Applebot|SkypeUriPreview|Googlebot|bingbot|BingPreview|Google-|Yandex|Baidu|DuckDuck|redditbot|Pinterest|Iframely|Embedly|vkShare|W3C_Validator|HeadlessChrome|PhantomJS|python-requests|python-urllib|curl\/|wget|Go-http-client|okhttp|axios|node-fetch|\bbot\b|crawler|spider|preview|scanner|monitor/i', $ua );
	}

	/**
	 * True when the person opening a public link is its own rep or AdaptExec
	 * (checking the link before sending it), so it is not counted as the
	 * prospect opening it.
	 */
	public static function is_internal_viewer( $owner_id ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return get_current_user_id() === (int) $owner_id || axh_is_manager();
	}

	public static function foot() {
		echo axh_style_assets(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from bundled data.
		wp_print_scripts( array( 'axh-hub' ) );
	}

	/**
	 * A client's own website style picker at /style/<token>/. No login; the
	 * token reaches one lead, and only the picks can be written.
	 */
	protected static function render_style( $token ) {
		nocache_headers();
		$lead = AXH_DB::lead_by_style_token( $token );
		if ( ! $lead ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}
		$rep     = $lead['rep_id'] ? get_user_by( 'id', (int) $lead['rep_id'] ) : null;
		$rep     = $rep ? $rep->display_name : 'AdaptExec';
		$company = $lead['company'] ? $lead['company'] : 'your business';
		$cfg     = array(
			'rest'    => esc_url_raw( rest_url( 'axh/v1/public/styles/' . $lead['style_token'] ) ),
			'style'   => AXH_DB::style_value( $lead ),
			'rep'     => $rep,
			'company' => $company,
		);
		$title = 'Pick your website style · ' . $company;
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#14181E">
	<title><?php echo esc_html( $title ); ?></title>
	<?php if ( function_exists( 'wp_site_icon' ) ) { wp_site_icon(); } ?>
	<style>
	*{margin:0;padding:0;box-sizing:border-box}
	body{background:#F6F5F3;color:#14181E;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;-webkit-font-smoothing:antialiased}
	.ys-bar{background:#0D0E10;color:#fff}
	.ys-bar .w{display:flex;align-items:center;gap:12px;height:58px}
	.ys-logo{font-weight:800;letter-spacing:.06em;font-size:19px}.ys-logo b{color:#EF5D0F}
	.ys-who{margin-left:auto;font-size:13px;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
	.w{max-width:1180px;margin:0 auto;padding:0 24px}
	.ys-hero{padding:34px 0 20px}
	.ys-hero span{display:block;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#EF5D0F}
	.ys-hero h1{font-size:clamp(28px,4vw,42px);line-height:1.1;font-weight:800;margin:6px 0 10px}
	.ys-hero p{color:#4b5563;max-width:64ch}
	.ys-main{padding-bottom:40px}
	.ys-done{display:none;margin:0 0 18px;padding:16px 18px;border-radius:12px;background:#DCFCE7;color:#14532d;font-weight:600}
	.ys-done.on{display:block}
	.ys-foot{padding:24px 0 40px;font-size:12.5px;color:#69727D}
	@media (max-width:560px){.w{padding:0 16px}.ys-hero{padding-top:24px}}
	</style>
</head>
<body>
<div class="ys-bar"><div class="w"><span class="ys-logo">ADAPT<b>EXEC</b></span><span class="ys-who"><?php echo esc_html( $company ); ?></span></div></div>
<div class="w ys-hero">
	<span>Website style finder</span>
	<h1>Find the look for <?php echo esc_html( $company ); ?>&rsquo;s new website</h1>
	<p>Answer a few quick questions and we will match you with styles, or browse all of them. Shortlist the ones you like, add a note if you want, and press <b>Send my picks</b> &mdash; <?php echo esc_html( $rep ); ?> sees them straight away. Nothing here is final; it just helps us start in the right place.</p>
</div>
<main class="w ys-main">
	<div class="ys-done" id="ys-done" role="status"></div>
	<div id="ys-picker"></div>
</main>
<div class="w ys-foot">AdaptExec &middot; Websites, CRMs &amp; SEO &middot; This page is private to <?php echo esc_html( $company ); ?>.</div>
<?php echo axh_style_assets(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<script>
(function(){
	var CFG = <?php echo wp_json_encode( $cfg ); ?>;
	if (!window.AXStylePicker) return;
	var done = document.getElementById('ys-done');
	window.AXStylePicker.mount(document.getElementById('ys-picker'), {
		value: CFG.style || {},
		notes: true,
		saveLabel: 'Send my picks to ' + CFG.rep,
		onSave: function (v) {
			if (!v.shortlist.length && !Object.keys(v.answers).length) return Promise.reject(new Error('Shortlist at least one style first.'));
			return fetch(CFG.rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ picks: v }) })
				.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Could not save.'); return j; }); })
				.then(function (j) {
					done.textContent = 'Thank you! ' + CFG.rep + ' has your picks' + (j.names ? ': ' + j.names : '') + '. You can change them and send again any time.';
					done.classList.add('on');
					window.scrollTo({ top: 0, behavior: 'smooth' });
					return 'Sent to ' + CFG.rep + '.';
				});
		}
	});
})();
</script>
</body>
</html>
		<?php
	}

	protected static function enqueue() {
		wp_enqueue_style( 'axh-hub', AXH_URL . 'assets/css/hub.css', array(), AXH_VERSION );
		wp_enqueue_script( 'axh-qr', AXH_URL . 'assets/js/qr.js', array(), AXH_VERSION, true );
		wp_enqueue_script( 'axh-hub', AXH_URL . 'assets/js/hub.js', array( 'axh-qr' ), AXH_VERSION, true );
		wp_localize_script(
			'axh-hub',
			'AXH',
			array(
				'root'    => esc_url_raw( rest_url( AXH_Rest::NS ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'assets'  => AXH_URL . 'assets/',
				'decks'   => AXH_URL . 'decks/',
				'portal'  => axh_portal_url(),
				'logout'  => wp_logout_url( axh_portal_url() ),
			)
		);
	}

	/**
	 * The same player, signed in, for rehearsing or presenting in the room.
	 * No share token, so nothing is counted against a prospect's link.
	 */
	protected static function render_deck( $slug ) {
		nocache_headers();

		if ( ! is_user_logged_in() || ! current_user_can( 'axh_use_hub' ) ) {
			wp_safe_redirect( axh_portal_url() );
			exit;
		}

		$deck = null;
		foreach ( self::slide_decks() as $d ) {
			if ( $d['slug'] === sanitize_key( $slug ) ) {
				$deck = $d;
				break;
			}
		}
		if ( ! $deck ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}

		$user     = wp_get_current_user();
		$rep_name = $user->display_name;
		$share    = null;
		$form     = null;

		wp_enqueue_style( 'axh-slides', AXH_URL . 'assets/css/slides.css', array(), AXH_VERSION );
		wp_enqueue_script( 'axh-slides', AXH_URL . 'assets/js/slides.js', array(), AXH_VERSION, true );

		include AXH_DIR . 'templates/share-public.php';
	}

	/**
	 * A shared deck at /s/<token>/. No login: the visitor sees the slides and,
	 * at the end, the lead form the representative attached — nothing else.
	 */
	protected static function render_share( $token ) {
		nocache_headers();

		$share = AXH_DB::share_by_token( $token );
		if ( ! $share ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}

		$deck = null;
		foreach ( self::slide_decks() as $d ) {
			if ( $d['slug'] === $share['deck'] ) {
				$deck = $d;
				break;
			}
		}
		if ( ! $deck ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}

		// 1.9.0: a view is counted by the deck itself once it runs in a real
		// browser (signed view key below), so link-preview robots, scanners
		// and anything that never runs the page are not views. The rep (or
		// AdaptExec) checking the link gets no key: a preview, not a view.
		$view_preview = self::is_internal_viewer( $share['rep_id'] );
		$view_key     = ( $view_preview || self::is_preview_bot() ) ? '' : AXH_P1::view_key( $share['token'] );

		$rep      = get_userdata( $share['rep_id'] );
		$rep_name = $rep ? $rep->display_name : '';
		$form     = $share['form_id'] ? AXH_DB::form_public( $share['form_id'] ) : null;

		wp_enqueue_style( 'axh-slides', AXH_URL . 'assets/css/slides.css', array(), AXH_VERSION );
		wp_enqueue_script( 'axh-slides', AXH_URL . 'assets/js/slides.js', array(), AXH_VERSION, true );

		include AXH_DIR . 'templates/share-public.php';
	}

	/**
	 * The demo websites, packaged by bin/sync-demos.py.
	 *
	 * These are plain static files, so a representative can open one in front
	 * of a prospect or send the link and it works with no login — which is the
	 * whole point of having them.
	 */
	public static function demo_sites() {
		$cached = get_transient( 'axh_demo_sites' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$uploads = wp_get_upload_dir();
		// Filterable so the demo sites can live outside this plugin — they are
		// 20MB of static pages, and keeping them in a companion plugin means
		// deleting and re-uploading the Hub never loses them.
		$sources = apply_filters(
			'axh_demo_sources',
			array(
				array( 'dir' => AXH_DIR . 'demos/', 'url' => AXH_URL . 'demos/' ),
				array(
					'dir' => trailingslashit( $uploads['basedir'] ) . 'adaptexec-demos/',
					'url' => trailingslashit( $uploads['baseurl'] ) . 'adaptexec-demos/',
				),
			)
		);

		$sites = array();
		$seen  = array();
		foreach ( $sources as $source ) {
			$file = $source['dir'] . 'index.json';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			$raw = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			foreach ( (array) $raw as $d ) {
				$slug = sanitize_key( $d['slug'] ?? '' );
				if ( ! $slug || isset( $seen[ $slug ] ) ) {
					continue;
				}
				if ( ! is_readable( $source['dir'] . $slug . '/index.html' ) ) {
					continue;
				}
				$seen[ $slug ] = true;
				$sites[]       = array(
					'slug'     => $slug,
					'title'    => sanitize_text_field( $d['title'] ?? $slug ),
					'industry' => sanitize_text_field( $d['industry'] ?? '' ),
					'line'     => sanitize_text_field( $d['line'] ?? '' ),
					'accent'   => sanitize_hex_color( $d['accent'] ?? '' ) ? $d['accent'] : '#EF5D0F',
					'url'      => $source['url'] . $slug . '/index.html',
					'thumb'    => ! empty( $d['thumb'] ) ? $source['url'] . $slug . '/' . $d['thumb'] : '',
				);
			}
		}

		set_transient( 'axh_demo_sites', $sites, HOUR_IN_SECONDS );
		return $sites;
	}

	/**
	 * Presentation decks are JSON under /slides/, written by build_slides.py.
	 */
	public static function slide_decks() {
		$cached = get_transient( 'axh_slide_decks' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$decks = array();
		$file  = AXH_DIR . 'slides/index.json';
		if ( is_readable( $file ) ) {
			$raw = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			foreach ( (array) $raw as $d ) {
				if ( empty( $d['slug'] ) ) {
					continue;
				}
				$slug = sanitize_key( $d['slug'] );
				if ( ! is_readable( AXH_DIR . 'slides/' . $slug . '.json' ) ) {
					continue;
				}
				$decks[] = array(
					'slug'   => $slug,
					'title'  => sanitize_text_field( $d['title'] ?? $slug ),
					'kicker' => sanitize_text_field( $d['kicker'] ?? '' ),
					'accent' => sanitize_hex_color( $d['accent'] ?? '' ) ? $d['accent'] : '#EF5D0F',
					'count'  => (int) ( $d['count'] ?? 0 ),
					'url'    => AXH_URL . 'slides/' . $slug . '.json',
				);
			}
		}

		set_transient( 'axh_slide_decks', $decks, HOUR_IN_SECONDS );
		return $decks;
	}

	/**
	 * Decks are self-contained folders under /decks/, each with a deck.json.
	 * Drop a new folder in and it appears in the portal — no code change.
	 */
	public static function decks() {
		$cached = get_transient( 'axh_decks' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$uploads = wp_get_upload_dir();
		$sources = apply_filters(
			'axh_deck_sources',
			array(
				array( 'dir' => AXH_DIR . 'decks/', 'url' => AXH_URL . 'decks/' ),
				array( 'dir' => trailingslashit( $uploads['basedir'] ) . 'adaptexec-decks/', 'url' => trailingslashit( $uploads['baseurl'] ) . 'adaptexec-decks/' ),
			)
		);

		$decks = array();
		$seen  = array();

		foreach ( $sources as $source ) {
			$dir      = $source['dir'];
			$base_url = $source['url'];
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( (array) glob( $dir . '*', GLOB_ONLYDIR ) as $path ) {
				$slug = basename( $path );
				if ( '_shared' === $slug || isset( $seen[ $slug ] ) ) {
					continue;
				}
				$meta = array();
				if ( file_exists( $path . '/deck.json' ) ) {
					$meta = json_decode( (string) file_get_contents( $path . '/deck.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				if ( ! file_exists( $path . '/index.html' ) ) {
					continue;
				}
				$seen[ $slug ] = true;
				$decks[]       = array(
					'slug'     => $slug,
					'title'    => $meta['title'] ?? ucwords( str_replace( '-', ' ', $slug ) ),
					'subtitle' => $meta['subtitle'] ?? '',
					'industry' => $meta['industry'] ?? '',
					'pages'    => (int) ( $meta['pages'] ?? 0 ),
					'accent'   => $meta['accent'] ?? '#EF5D0F',
					'url'      => $base_url . $slug . '/index.html',
					'pdf'      => file_exists( $path . '/deck.pdf' ) ? $base_url . $slug . '/deck.pdf' : '',
				);
			}
		}

		usort(
			$decks,
			function ( $a, $b ) {
				if ( '' === $a['industry'] ) {
					return -1;
				}
				if ( '' === $b['industry'] ) {
					return 1;
				}
				return strcmp( $a['title'], $b['title'] );
			}
		);

		set_transient( 'axh_decks', $decks, HOUR_IN_SECONDS );
		return $decks;
	}
}

/**
 * Billing (1.7.0): the customer's pages at /p/<token>/ (proposal),
 * /e/<token>/ (estimate) and /i/<token>/ (invoice), their print stylesheet
 * ("Download PDF" is the browser's Save as PDF), and the customer email.
 * Self-contained: inline CSS and JS, the Hub's own fonts, nothing from the
 * theme. Everything printed is escaped; rich text is a small markdown subset
 * rendered here and passed through wp_kses with a short allowlist.
 */
class AXH_Bill_View {

	/* -------------------------------------------------------- formatting */

	public static function money( $n ) {
		$n = round( (float) $n, 2 );
		return ( $n < 0 ? '−' : '' ) . '$' . number_format( abs( $n ), fmod( abs( $n ), 1 ) ? 2 : 0 );
	}

	/** Is the rep on a document the same person as AdaptExec's contact ("Adam Neuerburg" and "Adam")? */
	public static function same_person( $a, $b ) {
		$a = strtolower( trim( (string) $a ) );
		$b = strtolower( trim( (string) $b ) );
		if ( '' === $a || '' === $b ) {
			return false;
		}
		$fa = strtok( $a, ' ' );
		$fb = strtok( $b, ' ' );
		return $a === $b || 0 === strpos( $a, $b . ' ' ) || 0 === strpos( $b, $a . ' ' ) || ( $fa === $fb && ( false === strpos( $a, ' ' ) || false === strpos( $b, ' ' ) ) );
	}

	/** A unit price: whole cents as usual, or up to 4 places when it has fractions of a cent (card prices of small unit rates). */
	public static function rate_txt( $n ) {
		$n = (float) $n;
		if ( abs( $n * 100 - round( $n * 100 ) ) < 0.000001 ) {
			return self::money2( $n );
		}
		return ( $n < 0 ? '−' : '' ) . '$' . rtrim( number_format( abs( $n ), 4 ), '0' );
	}

	public static function money2( $n ) {
		$n = round( (float) $n, 2 );
		return ( $n < 0 ? '−' : '' ) . '$' . number_format( abs( $n ), 2 );
	}

	public static function date( $d, $fmt = 'F j, Y' ) {
		if ( ! $d ) {
			return '';
		}
		$ts = strtotime( substr( (string) $d, 0, 10 ) . ' 00:00:00 UTC' );
		return $ts ? wp_date( $fmt, $ts, new DateTimeZone( 'UTC' ) ) : '';
	}

	protected static function inline( $s ) {
		$s = esc_html( $s );
		$s = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s );
		$s = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s );
		$s = preg_replace( '/(?<![\w])_(?!\s)(.+?)(?<!\s)_(?![\w])/s', '<em>$1</em>', $s );
		$s = preg_replace_callback(
			'/\[([^\]]{1,200})\]\((https?:\/\/[^\s)]{1,500}|mailto:[^\s)]{1,200}|tel:[^\s)]{1,60})\)/',
			function ( $m ) {
				return '<a href="' . esc_url( html_entity_decode( $m[2], ENT_QUOTES ) ) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
			},
			$s
		);
		return $s;
	}

	/** Markdown-lite: paragraphs, **bold**, *italic*, [links](https://…), - lists, 1. lists, ### headings, > quotes. */
	public static function md( $text, $class = '' ) {
		$text = trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) );
		if ( '' === $text ) {
			return '';
		}
		$html = '';
		foreach ( preg_split( "/\n{2,}/", $text ) as $block ) {
			$lines = array_values( array_filter( explode( "\n", $block ), function ( $l ) { return '' !== trim( $l ); } ) );
			if ( ! $lines ) {
				continue;
			}
			$ul = count( array_filter( $lines, function ( $l ) { return preg_match( '/^\s*[-*•]\s+/', $l ); } ) ) === count( $lines );
			$ol = count( array_filter( $lines, function ( $l ) { return preg_match( '/^\s*\d+[.)]\s+/', $l ); } ) ) === count( $lines );
			if ( $ul || $ol ) {
				$tag   = $ul ? 'ul' : 'ol';
				$html .= '<' . $tag . '>';
				foreach ( $lines as $l ) {
					$html .= '<li><span>' . self::inline( preg_replace( $ul ? '/^\s*[-*•]\s+/' : '/^\s*\d+[.)]\s+/', '', $l ) ) . '</span></li>';
				}
				$html .= '</' . $tag . '>';
			} elseif ( preg_match( '/^#{1,4}\s+(.+)$/', $lines[0], $m ) ) {
				$html .= '<h3>' . self::inline( $m[1] ) . '</h3>';
				if ( count( $lines ) > 1 ) {
					$html .= '<p>' . implode( '<br>', array_map( array( __CLASS__, 'inline' ), array_slice( $lines, 1 ) ) ) . '</p>';
				}
			} elseif ( preg_match( '/^>\s?/', $lines[0] ) ) {
				$html .= '<blockquote>' . implode( '<br>', array_map( function ( $l ) { return self::inline( preg_replace( '/^>\s?/', '', $l ) ); }, $lines ) ) . '</blockquote>';
			} else {
				$html .= '<p>' . implode( '<br>', array_map( array( __CLASS__, 'inline' ), $lines ) ) . '</p>';
			}
		}
		$allowed = array(
			'p' => array(), 'br' => array(), 'strong' => array(), 'em' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
			'h3' => array(), 'blockquote' => array(), 'span' => array(),
			'a' => array( 'href' => true, 'target' => true, 'rel' => true ),
		);
		$out = self::ph_wrap( wp_kses( $html, $allowed, array( 'http', 'https', 'mailto', 'tel' ) ) );
		return '<div class="md' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '">' . $out . '</div>';
	}

	/** 1.7.3: an unfilled template blank like [what is not working] (not a [link](url)). */
	const PH_RE = '/\[(?=[^\]\n]*[A-Za-z0-9])[^\]\[\n<>]{1,80}\](?!\()/u';

	/** Wrap template blanks in already-escaped HTML so they are never printed silently. */
	public static function ph_wrap( $html ) {
		return preg_replace_callback(
			'/(<[^>]*>)|\[(?=[^\]\n<>]*[A-Za-z0-9])[^\]\[\n<>]{1,80}\](?!\()/u',
			function ( $m ) {
				return isset( $m[1] ) && '' !== $m[1] ? $m[1] : '<mark class="ph" title="Placeholder: fill this in before sending">' . $m[0] . '</mark>';
			},
			(string) $html
		);
	}

	/** esc_html() plus placeholder marking, for plain one-line fields. */
	public static function phx( $s ) {
		return self::ph_wrap( esc_html( (string) $s ) );
	}

	/** Every distinct unfilled [placeholder] a document still carries, with where it is. */
	public static function placeholders( $doc ) {
		$c    = (array) ( $doc['content'] ?? array() );
		$bits = array();
		$add  = function ( $where, $txt ) use ( &$bits ) {
			if ( is_string( $txt ) && preg_match_all( self::PH_RE, $txt, $m ) ) {
				foreach ( $m[0] as $p ) {
					$bits[] = array( 'where' => $where, 'text' => $p );
				}
			}
		};
		$add( 'Title', $doc['title'] ?? '' );
		if ( 'proposal' === ( $doc['type'] ?? '' ) ) {
			$cv = (array) ( $c['cover'] ?? array() );
			foreach ( array( 'title', 'subtitle', 'intro' ) as $k ) {
				$add( 'Cover', $cv[ $k ] ?? '' );
			}
			foreach ( (array) ( $c['sections'] ?? array() ) as $sec ) {
				if ( empty( $sec['on'] ) && isset( $sec['on'] ) ) {
					continue;
				}
				$w = (string) ( $sec['title'] ?? 'Section' );
				$add( $w, $sec['title'] ?? '' );
				$add( $w, $sec['body'] ?? '' );
				foreach ( (array) ( $sec['items'] ?? array() ) as $it ) {
					foreach ( array( 'title', 'when', 'detail' ) as $k ) {
						$add( $w, $it[ $k ] ?? '' );
					}
				}
			}
			foreach ( (array) ( $c['options'] ?? array() ) as $o ) {
				$w = 'Option: ' . ( $o['name'] ?? '' );
				foreach ( array( 'name', 'tagline', 'desc' ) as $k ) {
					$add( $w, $o[ $k ] ?? '' );
				}
				foreach ( (array) ( $o['lines'] ?? array() ) as $l ) {
					$add( $w, $l['name'] ?? '' );
					$add( $w, $l['desc'] ?? '' );
				}
			}
			foreach ( (array) ( $c['addons'] ?? array() ) as $a ) {
				$add( 'Add-on: ' . ( $a['name'] ?? '' ), $a['name'] ?? '' );
				$add( 'Add-on: ' . ( $a['name'] ?? '' ), $a['desc'] ?? '' );
			}
		}
		foreach ( (array) ( $doc['lines'] ?? array() ) as $l ) {
			$add( 'Line items', $l['name'] ?? '' );
			$add( 'Line items', $l['desc'] ?? '' );
		}
		foreach ( (array) ( $c['schedule'] ?? array() ) as $sc ) {
			$add( 'Payment schedule', $sc['label'] ?? '' );
			$add( 'Payment schedule', $sc['due'] ?? '' );
		}
		$add( 'Notes', $doc['notes'] ?? '' );
		$add( 'Terms', $doc['terms_text'] ?? '' );
		return $bits;
	}

	public static function draft_doc( $row, $in ) {
		return AXH_Bill::preview_doc( $row, $in );
	}

	protected static function logo( $dark_bg = false ) {
		$b = AXH_Bill::business();
		if ( $b['logo'] ) {
			return '<img class="lg lg--custom' . ( $dark_bg ? ' lg--chip' : '' ) . '" src="' . esc_url( $b['logo'] ) . '" alt="' . esc_attr( $b['name'] ) . '">';
		}
		return '<img class="lg" src="' . esc_url( AXH_URL . 'assets/img/' . ( $dark_bg ? 'Mask-group-27.svg' : 'logo-dark.svg' ) ) . '" alt="' . esc_attr( $b['name'] ) . '" width="165" height="58">';
	}

	protected static function icon( $name, $size = 18 ) {
		$p = array(
			'check'  => '<path d="M20 6 9 17l-5-5"/>',
			'pdf'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
			'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'mail'   => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
			'phone'  => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.8 2z"/>',
			'zap'    => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
			'bank'   => '<path d="M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M2 21h20M12 3l9 5H3z"/>',
			'cheque' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M6 15h6M15 11h3M6 11h5"/>',
			'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
			'lock'   => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
			'pen'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
			'x'      => '<path d="M18 6 6 18M6 6l12 12"/>',
			'card'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
		)[ $name ] ?? '';
		return '<svg aria-hidden="true" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
	}

	protected static function chevron() {
		return '<svg class="mark" viewBox="90 4 30 51" aria-hidden="true"><path d="M117.603 28.8762C117.833 29.1977 117.833 29.629 117.603 29.9506L113.933 35.0481L101.14 52.8267H95.0359C94.1956 52.8267 93.7042 51.8856 94.1956 51.2112L109.883 29.4094L94.1956 7.61553C93.7121 6.94108 94.2036 6 95.0438 6H101.147L113.941 23.7786L117.603 28.8762Z"/></svg>';
	}

	/* ------------------------------------------------------------ route */

	/** /p/, /e/ or /i/ followed by the token. */
	public static function serve( $letter, $token ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		$doc = AXH_Bill::doc_by_token( $token );
		$internal = $doc && AXH_Portal::is_internal_viewer( $doc['rep_id'] );
		if ( ! $doc || AXH_Bill::path_letter( $doc['type'] ) !== $letter || ( 'draft' === $doc['status'] && ! ( $internal && axh_is_manager() ) ) ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			return;
		}
		AXH_Bill::record_view( $doc );
		$doc = AXH_Bill::doc_by_token( $token );
		echo self::page( $doc, array( 'internal' => $internal ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped below.
	}

	/* ------------------------------------------------------------- page */

	public static function page( $doc, $opts = array() ) {
		$opts     = wp_parse_args( $opts, array( 'preview' => false, 'internal' => false ) );
		$doc      = apply_filters( 'axh_bill_display_doc', $doc, $opts ); // 1.21.0: cash discount pricing
		$biz      = AXH_Bill::business();
		$cust     = $doc['customer_id'] ? AXH_Bill::customer_row( $doc['customer_id'], false ) : null;
		$cname    = $cust ? ( $cust['company'] ? $cust['company'] : $cust['contact_name'] ) : 'Your company';
		$type     = $doc['type'];
		$label    = AXH_Bill::type_label( $type );
		$ds       = $doc['display_status'];
		$c        = $doc['content'];
		$accepted = 'accepted' === $doc['status'];
		$acc      = $accepted ? ( $c['accepted'] ?? array() ) : array();
		// Only the customer accepts. AdaptExec and the rep see the same form, switched off.
		$decide   = 'invoice' !== $type && in_array( $ds, array( 'sent', 'viewed' ), true ) && ! $opts['preview'] && ! $opts['internal'];
		$decide_ui = 'invoice' !== $type && ( $decide || ( ( $opts['preview'] || $opts['internal'] ) && in_array( $ds, array( 'draft', 'sent', 'viewed' ), true ) ) );
		$title    = ( 'proposal' === $type && ! empty( $c['cover']['title'] ) ) ? $c['cover']['title'] : ( $doc['title'] ? $doc['title'] : $label . ' ' . $doc['number'] );
		$rep      = $doc['rep_id'] ? get_userdata( (int) $doc['rep_id'] ) : null;
		$rep_name = ( $rep && ! user_can( $rep, 'axh_manage_all' ) ) ? $rep->display_name : '';
		$gateways = AXH_Bill::gateways();

		$cfg = array(
			'type'     => $type,
			'rest'     => $opts['preview'] ? '' : esc_url_raw( rest_url( 'axh/v1/public/bill/' . $doc['token'] ) ),
			'hash'     => AXH_Bill::fingerprint( $doc ),
			'preview'  => (bool) $opts['preview'],
			'decide'   => $decide,
			'lines'    => array_values( $doc['lines'] ),
			'options'  => array_values( (array) $c['options'] ),
			'addons'   => array_values( array_map( function ( $a ) { $a['name'] = AXH_Bill::addon_label( $a['name'] ?? '' ); return $a; }, (array) $c['addons'] ) ),
			'selected' => $accepted ? ( $acc['option'] ?? '' ) : (string) ( $c['selected'] ?? '' ),
			'chosen'   => $accepted ? array_values( (array) ( $acc['addons'] ?? array() ) ) : null,
			'dtype'    => $doc['discount_type'],
			'dval'     => (float) $doc['discount_value'],
			'taxOn'    => (bool) $doc['tax_on'],
			'taxRate'  => (float) $doc['tax_rate'],
			'schedule' => array_values( (array) $c['schedule'] ),
			'locked'   => ! $decide_ui,
			'gateways' => array_map( function ( $g ) { return $g['label']; }, $gateways ),
			'label'    => strtolower( $label ),
		);

		ob_start();
		?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#0b0b0b">
<meta name="format-detection" content="telephone=no">
<title><?php echo esc_html( $label . ' ' . $doc['number'] . ' — ' . $cname . ' — ' . $biz['name'] ); ?></title>
<?php
if ( ! $opts['preview'] ) {
	if ( function_exists( 'wp_site_icon' ) ) {
		wp_site_icon();
	}
	AXH_Portal::share_tags(
		array(
			'title'       => $label . ' for ' . $cname . ' · ' . $biz['name'],
			'description' => 'invoice' === $type ? 'Your invoice from ' . $biz['name'] . '. View the balance and how to pay.' : 'Your ' . strtolower( $label ) . ' from ' . $biz['name'] . '. Review the details and accept online.',
			'url'         => AXH_Bill::public_url( $doc ),
		)
	);
}
if ( 'invoice' !== $type ) {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Allura&display=swap">' . "\n";
}
?>
<style><?php echo self::css( $biz['accent'] ); // phpcs:ignore ?></style>
<style><?php echo self::print_pages( $doc, $biz, $label, $cname ); // phpcs:ignore ?></style>
</head>
<body class="bx bx--<?php echo esc_attr( $type ); ?><?php echo $opts['preview'] ? ' is-preview' : ''; ?>">
<?php $phs = ( $opts['preview'] || $opts['internal'] ) ? self::placeholders( $doc ) : array(); ?>
<?php if ( $phs ) : ?>
<div class="flag flag--ph no-print" role="note"><?php echo self::icon( 'info', 15 ); // phpcs:ignore ?> <span><b><?php echo count( $phs ); ?> unfilled placeholder<?php echo 1 === count( $phs ) ? '' : 's'; ?></b> (highlighted below): <?php echo esc_html( implode( ', ', array_unique( wp_list_pluck( $phs, 'text' ) ) ) ); ?>. Fill them in before sending.</span></div>
<?php endif; ?>
<?php if ( $opts['preview'] ) : ?>
<div class="flag no-print"><?php echo self::icon( 'info', 15 ); // phpcs:ignore ?> Live preview — this is exactly what your customer will see. Buttons are disabled here.</div>
<?php elseif ( $opts['internal'] ) : ?>
<div class="flag no-print"><?php echo self::icon( 'lock', 15 ); // phpcs:ignore ?> <?php echo 'draft' === $doc['status'] ? 'Draft — your customer cannot open this link until it is sent.' : 'You are signed in to the Hub, so this visit is not counted as the customer viewing it.'; ?></div>
<?php endif; ?>

<header class="tb<?php echo 'proposal' === $type ? ' is-dark' : ''; ?> no-print" id="tb">
	<div class="tb__in">
		<a class="tb__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php echo self::logo( false ); // phpcs:ignore ?><span class="tb__lgw"><?php echo self::logo( true ); // phpcs:ignore ?></span></a>
		<span class="tb__doc"><?php echo esc_html( $label ); ?> <b><?php echo esc_html( $doc['number'] ); ?></b></span>
		<span class="pill pill--<?php echo esc_attr( $ds ); ?>"><?php echo esc_html( AXH_Bill::status_label( $ds ) ); ?></span>
		<span class="tb__sp"></span>
		<button type="button" class="btn btn--ghost btn--sm" data-print><?php echo self::icon( 'pdf', 16 ); // phpcs:ignore ?><span>Download PDF</span></button>
		<?php if ( $decide_ui ) : ?>
			<a class="btn btn--o btn--sm tb__cta" href="#accept">Review &amp; accept</a>
		<?php elseif ( 'invoice' === $type && (float) $doc['balance'] > 0.004 && 'void' !== $doc['status'] ) : ?>
			<a class="btn btn--o btn--sm tb__cta" href="#pay">How to pay</a>
		<?php endif; ?>
	</div>
</header>

<?php
if ( 'proposal' === $type ) {
	echo self::proposal( $doc, $biz, $cust, $cname, $rep_name, $opts ); // phpcs:ignore
} else {
	echo self::paper( $doc, $biz, $cust, $cname, $rep_name, $opts ); // phpcs:ignore
}
if ( 'invoice' !== $type ) {
	echo self::decision( $doc, $biz, $cname, $decide_ui, $opts ); // phpcs:ignore
}
echo self::footer( $doc, $biz, $rep_name ); // phpcs:ignore
?>

<?php if ( $decide_ui ) : ?>
<div class="mbar no-print" id="mbar"><div><span>Total</span><b data-total-out><?php echo esc_html( self::money2( $doc['total'] ) ); ?></b></div><a class="btn btn--o" href="#accept">Review &amp; accept</a></div>
<?php endif; ?>

<dialog class="dlg" id="decline-dlg">
	<form method="dialog" class="dlg__in">
		<button class="dlg__x" value="cancel" aria-label="Close"><?php echo self::icon( 'x', 18 ); // phpcs:ignore ?></button>
		<h3 class="th">Not the right fit?</h3>
		<p>No problem. A quick note on why helps us put together something better — it is optional.</p>
		<label class="fld"><span>What would you change? (optional)</span><textarea id="decline-reason" rows="4" maxlength="1000" placeholder="Timing, budget, scope…"></textarea></label>
		<div class="dlg__ft"><button class="btn btn--ghost" value="cancel">Keep reviewing</button><button class="btn btn--ink" type="button" data-decline-send>Decline <?php echo esc_html( strtolower( $label ) ); ?></button></div>
	</form>
</dialog>

<script>window.AXB = <?php echo wp_json_encode( $cfg ); ?>;</script>
<?php if ( is_readable( AXH_DIR . 'assets/js/qr.js' ) ) : ?><script><?php echo file_get_contents( AXH_DIR . 'assets/js/qr.js' ); // phpcs:ignore -- bundled, self-contained QR encoder ?></script><?php endif; ?>
<script><?php echo self::js(); // phpcs:ignore ?></script>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------- proposal (long form) */

	protected static function proposal( $doc, $biz, $cust, $cname, $rep_name, $opts ) {
		$c      = $doc['content'];
		$cv     = $c['cover'];
		$secs   = array_values( array_filter( (array) $c['sections'], function ( $s ) { return ! empty( $s['on'] ); } ) );
		$has_inv = false;
		foreach ( $secs as $s ) {
			if ( 'investment' === $s['kind'] ) {
				$has_inv = true;
			}
		}
		if ( ! $has_inv && ( $c['options'] || $doc['lines'] ) ) {
			$secs[] = array( 'id' => 'investment', 'kind' => 'investment', 'title' => 'Investment', 'body' => '', 'on' => true );
		}
		$min = null;
		foreach ( (array) $c['options'] as $o ) {
			$t   = AXH_Bill::calc( array_merge( $doc['lines'], $o['lines'] ), $doc );
			$min = null === $min ? $t['total'] : min( $min, $t['total'] );
		}
		$words = preg_split( '/\s+/', trim( (string) ( $cv['title'] ? $cv['title'] : ( $doc['title'] ? $doc['title'] : 'Proposal' ) ) ) );
		ob_start();
		?>
<section class="cv<?php echo $cv['image'] ? ' cv--img' : ''; ?>" id="cover">
	<div class="cv__bg" aria-hidden="true"><?php echo self::chevron(); // phpcs:ignore ?></div>
	<div class="cv__plogo print-only" aria-hidden="true"><?php echo self::logo( true ); // phpcs:ignore ?></div>
	<div class="wrap cv__grid">
		<div class="cv__txt">
			<div class="kick rv"><?php echo esc_html( $cv['subtitle'] ? $cv['subtitle'] : 'Proposal' ); ?> <i>·</i> <?php echo esc_html( $doc['number'] ); ?></div>
			<h1 class="th cv__h"><?php foreach ( $words as $i => $w ) : ?><span class="wd"><span style="--i:<?php echo (int) $i; ?>"><?php echo esc_html( $w ); ?></span></span> <?php endforeach; ?></h1>
			<?php if ( $cv['intro'] ) : ?><div class="cv__intro rv" style="--d:.35s"><?php echo self::md( $cv['intro'] ); // phpcs:ignore ?></div><?php endif; ?>
			<dl class="cv__meta rv" style="--d:.45s">
				<div><dt>Prepared for</dt><dd><?php echo esc_html( $cname ); ?><?php if ( $cust && $cust['company'] && $cust['contact_name'] ) : ?><small><?php echo esc_html( $cust['contact_name'] ); ?></small><?php endif; ?></dd></div>
				<div><dt>Prepared by</dt><dd><?php echo esc_html( $biz['name'] ); ?><small><?php echo esc_html( $rep_name ? ( self::same_person( $rep_name, $biz['contact'] ) ? $rep_name : $rep_name . ' · ' . $biz['contact'] ) : $biz['contact'] ); ?></small></dd></div>
				<div><dt>Date</dt><dd><?php echo esc_html( self::date( $doc['issue_date'] ) ); ?></dd></div>
				<?php if ( $doc['expiry_date'] ) : ?><div><dt>Valid until</dt><dd><?php echo esc_html( self::date( $doc['expiry_date'] ) ); ?></dd></div><?php endif; ?>
			</dl>
			<div class="cv__cta rv no-print" style="--d:.55s">
				<a class="btn btn--o btn--lg" href="#<?php echo esc_attr( $secs ? $secs[0]['id'] : 'accept' ); ?>">Read the proposal <?php echo self::icon( 'arrow', 18 ); // phpcs:ignore ?></a>
				<?php if ( null !== $min && count( (array) $c['options'] ) > 1 ) : ?><span class="cv__from">Options from <b><?php echo esc_html( self::money( $min ) ); ?></b></span>
				<?php else : ?><span class="cv__from">Investment <b data-total-out><?php echo esc_html( self::money2( $doc['total'] ) ); ?></b></span><?php endif; ?>
			</div>
		</div>
		<?php if ( $cv['image'] ) : ?>
		<div class="cv__art rv" style="--d:.3s"><div class="frame"><div class="frame__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( $cv['image'] ); ?>" alt="" loading="eager"></div></div>
		<?php endif; ?>
	</div>
	<a class="cv__down no-print" href="#<?php echo esc_attr( $secs ? $secs[0]['id'] : 'accept' ); ?>" aria-label="Scroll to the proposal"><span></span></a>
</section>

<div class="wrap doc">
	<aside class="rail no-print" aria-label="Sections">
		<div class="rail__in">
			<div class="rail__h">Contents</div>
			<ol class="toc">
				<?php foreach ( $secs as $i => $s ) : ?>
				<li><a href="#<?php echo esc_attr( $s['id'] ); ?>" data-toc="<?php echo esc_attr( $s['id'] ); ?>"><span><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $s['title'] ? $s['title'] : ucfirst( $s['kind'] ) ); ?></a></li>
				<?php endforeach; ?>
				<li><a href="#accept" data-toc="accept"><span><?php echo esc_html( str_pad( (string) ( count( $secs ) + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo 'accepted' === $doc['status'] ? 'Accepted' : 'Accept'; ?></a></li>
			</ol>
			<div class="rail__sum">
				<span>Your total</span>
				<b data-total-out><?php echo esc_html( self::money2( $doc['total'] ) ); ?></b>
				<?php if ( 'accepted' !== $doc['status'] && in_array( $doc['display_status'], array( 'draft', 'sent', 'viewed' ), true ) ) : ?><a class="btn btn--o btn--sm" href="#accept">Review &amp; accept</a><?php endif; ?>
			</div>
		</div>
	</aside>
	<main class="main">
		<?php foreach ( $secs as $i => $s ) : ?>
		<section class="sec sec--<?php echo esc_attr( $s['kind'] ); ?>" id="<?php echo esc_attr( $s['id'] ); ?>">
			<header class="sec__h rv"><span class="sec__n th"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><h2 class="th"><?php echo esc_html( $s['title'] ? $s['title'] : ucfirst( $s['kind'] ) ); ?></h2></header>
			<?php echo self::keep_intro( self::section_body( $s, $doc ) ); // phpcs:ignore ?>
		</section>
		<?php endforeach; ?>
	</main>
</div>
		<?php
		return ob_get_clean();
	}

	/** 1.7.4: mark a short section intro (about two printed lines) so print keeps it with the block after it. */
	protected static function keep_intro( $html ) {
		return (string) preg_replace_callback(
			'#^(<div class="rv"><div class="md[^"]*">)<p>(.*?)</p>(?=.)#s',
			function ( $m ) {
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $m[2] ) ) : strlen( wp_strip_all_tags( $m[2] ) );
				return $m[1] . ( $len <= 190 ? '<p class="intro-keep">' : '<p>' ) . $m[2] . '</p>';
			},
			(string) $html,
			1
		);
	}

	protected static function section_body( $s, $doc ) {
		$kind = $s['kind'];
		$out  = '';
		if ( 'investment' === $kind ) {
			return ( $s['body'] ? '<div class="rv">' . self::md( $s['body'], 'md--lead' ) . '</div>' : '' ) . self::investment( $doc );
		}
		if ( 'timeline' === $kind ) {
			$out .= $s['body'] ? '<div class="rv">' . self::md( $s['body'] ) . '</div>' : '';
			$items = array_values( array_filter( (array) ( $s['items'] ?? array() ), function ( $it ) { return '' !== trim( $it['title'] . $it['when'] ); } ) );
			if ( $items ) {
				$out .= '<ol class="tl">';
				foreach ( $items as $n => $it ) {
					$out .= '<li class="rv" style="--d:' . ( 0.08 * $n ) . 's"><span class="tl__dot"></span><span class="tl__when">' . self::phx( $it['when'] ) . '</span><b>' . self::phx( $it['title'] ) . '</b>' . ( $it['detail'] ? '<p>' . self::phx( $it['detail'] ) . '</p>' : '' ) . '</li>';
				}
				$out .= '</ol>';
			}
			return $out;
		}
		if ( 'terms' === $kind ) {
			$txt = trim( $s['body'] . ( $s['body'] && $doc['terms_text'] ? "\n\n" : '' ) . $doc['terms_text'] );
			return '<div class="rv">' . self::md( $txt, 'md--terms' ) . '</div>' . ( $doc['notes'] ? '<div class="note rv">' . self::md( $doc['notes'] ) . '</div>' : '' );
		}
		$cls = array( 'scope' => 'md--cards', 'included' => 'md--checks', 'why' => 'md--feat', 'next' => 'md--steps' )[ $kind ] ?? '';
		return '<div class="rv">' . self::md( $s['body'], $cls ) . '</div>';
	}

	protected static function line_rows( $lines, $class = 'lt' ) {
		$h = '<table class="' . esc_attr( $class ) . '"><thead><tr><th>Item</th><th class="n">Qty</th><th class="n">Rate</th><th class="n">Amount</th></tr></thead><tbody>';
		foreach ( $lines as $l ) {
			list( $amt, $d, $net ) = AXH_Bill::line_net( $l );
			$unit = 'each' !== ( $l['unit'] ?? 'each' ) ? ' / ' . $l['unit'] : '';
			$h   .= '<tr><td><b>' . self::phx( $l['name'] ) . '</b>' . ( ! empty( $l['desc'] ) ? '<span>' . self::phx( $l['desc'] ) . '</span>' : '' ) . ( $d > 0 ? '<em>Discount ' . ( 'pct' === $l['disc_type'] ? esc_html( rtrim( rtrim( number_format( (float) $l['disc'], 2 ), '0' ), '.' ) ) . '%' : '' ) . ' −' . esc_html( self::money2( $d ) ) . '</em>' : '' ) . '</td>'
				. '<td class="n" data-th="Qty">' . esc_html( rtrim( rtrim( number_format( (float) $l['qty'], 3 ), '0' ), '.' ) ) . '</td>'
				. '<td class="n" data-th="Rate">' . esc_html( self::rate_txt( $l['rate'] ) . $unit ) . '</td>'
				. '<td class="n" data-th="Amount"><b>' . esc_html( self::money2( $net ) ) . '</b></td></tr>';
		}
		return $h . '</tbody></table>';
	}

	/** Options (pick one), add-ons (toggle), and a live total with the payment schedule. */
	protected static function investment( $doc ) {
		$c      = $doc['content'];
		$opts   = (array) $c['options'];
		$addons = (array) $c['addons'];
		$acc    = 'accepted' === $doc['status'] ? ( $c['accepted'] ?? array() ) : null;
		$sel    = $acc ? ( $acc['option'] ?? '' ) : ( $c['selected'] ?? '' );
		$h      = '';
		if ( $doc['lines'] ) {
			$h .= '<div class="card rv"><div class="card__k">Included in every option</div>' . self::line_rows( $doc['lines'] ) . '</div>';
		}
		if ( $opts ) {
			$h .= '<div class="opts opts--' . count( $opts ) . '" role="radiogroup" aria-label="Choose an option">';
			foreach ( $opts as $i => $o ) {
				$t   = AXH_Bill::calc( array_merge( $doc['lines'], $o['lines'] ), $doc );
				$on  = $o['id'] === $sel;
				$h  .= '<label class="opt rv' . ( $on ? ' is-on' : '' ) . ( $o['recommended'] ? ' is-rec' : '' ) . '" style="--d:' . ( 0.08 * $i ) . 's" data-opt="' . esc_attr( $o['id'] ) . '">'
					. '<input type="radio" name="opt" value="' . esc_attr( $o['id'] ) . '"' . ( $on ? ' checked' : '' ) . ( $acc ? ' disabled' : '' ) . '>'
					. ( $o['recommended'] ? '<span class="opt__rec">Recommended</span>' : '' )
					. '<span class="opt__tick">' . self::icon( 'check', 15 ) . '</span>'
					. '<span class="opt__name th">' . self::phx( $o['name'] ) . '</span>'
					. ( $o['tagline'] ? '<span class="opt__tag">' . self::phx( $o['tagline'] ) . '</span>' : '' )
					. '<span class="opt__price th">' . esc_html( self::money( $t['total'] ) ) . '</span>'
					. ( $o['desc'] ? self::md( $o['desc'], 'opt__desc' ) : '' )
					. '<ul class="opt__lines">';
				foreach ( $o['lines'] as $l ) {
					list( , , $net ) = AXH_Bill::line_net( $l );
					$h .= '<li><span>' . self::phx( $l['name'] ) . ( (float) $l['qty'] !== 1.0 ? ' <i>× ' . esc_html( rtrim( rtrim( number_format( (float) $l['qty'], 3 ), '0' ), '.' ) ) . '</i>' : '' ) . ( ! empty( $l['desc'] ) ? '<small>' . self::phx( $l['desc'] ) . '</small>' : '' ) . '</span><b>' . esc_html( self::money( $net ) ) . '</b></li>';
				}
				$h .= '</ul><span class="opt__sel">' . ( $on ? 'Selected' : 'Select' ) . '</span></label>';
			}
			$h .= '</div>';
		}
		if ( $addons ) {
			$h .= '<div class="adds rv"><div class="adds__h"><b>Optional add-ons</b><span class="no-print">Switch on what you want. The total updates as you go.</span><span class="print-inline">Only the add-ons marked <i>Added</i> are in the total below.</span></div>';
			foreach ( $addons as $a ) {
				$on  = $acc ? in_array( $a['id'], (array) ( $acc['addons'] ?? array() ), true ) : ! empty( $a['on'] );
				list( , , $net ) = AXH_Bill::line_net( $a );
				$per = 'each' !== $a['unit'] && 'project' !== $a['unit'] ? ' / ' . $a['unit'] : '';
				$h  .= '<label class="add' . ( $on ? ' is-on' : '' ) . '" data-add="' . esc_attr( $a['id'] ) . '"><span class="add__t"><b>' . self::phx( AXH_Bill::addon_label( $a['name'] ) ) . '</b>' . ( $a['desc'] ? '<small>' . self::phx( $a['desc'] ) . '</small>' : '' ) . '</span>'
					. '<span class="add__p">' . ( $net > 0 ? '+' . esc_html( self::money( $net ) . $per ) : 'Quoted on request' ) . '</span>'
					. '<input type="checkbox" class="sw" value="' . esc_attr( $a['id'] ) . '"' . ( $on ? ' checked' : '' ) . ( $acc ? ' disabled' : '' ) . ' aria-label="' . esc_attr( 'Add ' . AXH_Bill::addon_label( $a['name'] ) ) . '"></label>';
			}
			$h .= '</div>';
		}
		$h .= '<div class="sum rv" id="sum">' . self::summary_rows( $doc ) . '</div>';
		return $h;
	}

	protected static function summary_rows( $doc ) {
		$sched = (array) $doc['content']['schedule'];
		$h     = '<div class="sum__rows">'
			. '<div data-row="sub"><span>Subtotal</span><b data-out="sub">' . esc_html( self::money2( $doc['subtotal'] ) ) . '</b></div>'
			. '<div data-row="disc"' . ( $doc['discount_total'] > 0 ? '' : ' hidden' ) . '><span>Discount' . ( 'pct' === $doc['discount_type'] && $doc['discount_value'] ? ' (' . esc_html( rtrim( rtrim( number_format( $doc['discount_value'], 2 ), '0' ), '.' ) ) . '%)' : '' ) . '</span><b data-out="disc">−' . esc_html( self::money2( $doc['discount_total'] ) ) . '</b></div>'
			. '<div data-row="tax"' . ( $doc['tax_on'] ? '' : ' hidden' ) . '><span>' . esc_html( $doc['tax_label'] ? $doc['tax_label'] : 'Tax' ) . ' (' . esc_html( rtrim( rtrim( number_format( $doc['tax_rate'], 3 ), '0' ), '.' ) ) . '%)</span><b data-out="tax">' . esc_html( self::money2( $doc['tax_total'] ) ) . '</b></div>'
			. '</div><div class="sum__total"><span>Total</span><b class="th" data-total-out>' . esc_html( self::money2( $doc['total'] ) ) . '</b></div>';
		if ( $sched ) {
			$h .= '<div class="sum__sched"><div class="sum__k">Payment schedule</div>';
			$acc = 0.0;
			foreach ( $sched as $i => $s ) {
				$amt = $i === count( $sched ) - 1 ? round( $doc['total'] - $acc, 2 ) : round( $doc['total'] * $s['pct'] / 100, 2 );
				$acc += $amt;
				$h  .= '<div class="sch"><span class="sch__pct">' . esc_html( rtrim( rtrim( number_format( $s['pct'], 2 ), '0' ), '.' ) ) . '%</span><span class="sch__t"><b>' . esc_html( $s['label'] ) . '</b>' . ( $s['due'] ? '<small>' . esc_html( $s['due'] ) . '</small>' : '' ) . '</span><b data-sched="' . (int) $i . '">' . esc_html( self::money2( $amt ) ) . '</b></div>';
			}
			$h .= '</div>';
		}
		return $h;
	}

	/* ------------------------------------------------ estimate / invoice */

	protected static function paper( $doc, $biz, $cust, $cname, $rep_name, $opts ) {
		$type    = $doc['type'];
		$label   = AXH_Bill::type_label( $type );
		$ds      = $doc['display_status'];
		$inv     = 'invoice' === $type;
		$paid    = $inv && 'paid' === $doc['status'];
		$void    = 'void' === $doc['status'];
		$payments = $inv ? array_values( array_filter( AXH_Bill::payments_public( $doc['id'] ), function ( $p ) { return true; } ) ) : array();
		$addr    = array();
		if ( $cust ) {
			foreach ( array( $cust['addr1'], $cust['addr2'], trim( $cust['city'] . ( $cust['city'] && $cust['state'] ? ', ' : '' ) . $cust['state'] . ' ' . $cust['zip'] ) ) as $l ) {
				if ( trim( $l ) ) {
					$addr[] = trim( $l );
				}
			}
		}
		ob_start();
		?>
<section class="hero">
	<div class="hero__bg" aria-hidden="true"><?php echo self::chevron(); // phpcs:ignore ?></div>
	<div class="wrap hero__in">
		<div class="kick rv"><?php echo esc_html( $label ); ?> <i>·</i> <?php echo esc_html( $doc['number'] ); ?></div>
		<h1 class="th hero__h rv" style="--d:.08s"><?php echo esc_html( $doc['title'] ? $doc['title'] : $label ); ?></h1>
		<p class="hero__p rv" style="--d:.16s"><?php echo $inv ? 'For ' . esc_html( $cname ) . ' · issued ' . esc_html( self::date( $doc['issue_date'] ) ) : 'Prepared for ' . esc_html( $cname ) . ' · ' . esc_html( self::date( $doc['issue_date'] ) ); ?></p>
	</div>
</section>
<div class="wrap">
	<article class="paper rv" style="--d:.2s">
		<?php if ( $paid ) : ?><div class="stamp" aria-hidden="true">Paid</div><?php elseif ( $void ) : ?><div class="stamp stamp--void" aria-hidden="true">Void</div><?php endif; ?>
		<header class="paper__h">
			<div class="paper__from"><?php echo self::logo( false ); // phpcs:ignore ?>
				<address><b><?php echo esc_html( $biz['name'] ); ?></b><br><?php echo esc_html( $biz['email'] ); ?><?php echo $biz['phone'] ? ' · ' . esc_html( $biz['phone'] ) : ''; ?></address>
			</div>
			<div class="due">
				<?php if ( $inv ) : ?>
					<span class="due__k"><?php echo $paid ? 'Paid in full' : ( $void ? 'Void' : 'Amount due' ); ?></span>
					<b class="th due__v"><?php echo esc_html( self::money2( $paid ? $doc['total'] : max( 0, $doc['balance'] ) ) ); ?></b>
					<span class="due__d"><?php echo $paid ? ( $doc['paid_at'] ? 'on ' . esc_html( self::date( $doc['paid_at'] ) ) : '' ) : ( $void ? 'This invoice was cancelled.' : ( $doc['due_date'] ? ( 'overdue' === $ds ? 'Was due ' : 'Due ' ) . esc_html( self::date( $doc['due_date'] ) ) : '' ) ); ?></span>
					<?php if ( ! $paid && ! $void && ! empty( $doc['_cd']['cd'] ) && 'card' === $doc['_cd']['view'] && $doc['_cd']['discount'] > 0.004 ) : ?><span class="due__cd"><?php echo esc_html( self::money2( $doc['_cd']['cash'] ) ); ?> by check, cash or Zelle</span><?php endif; ?>
				<?php else : ?>
					<span class="due__k">Estimate total</span>
					<b class="th due__v" data-total-out><?php echo esc_html( self::money2( $doc['total'] ) ); ?></b>
					<span class="due__d"><?php echo $doc['expiry_date'] ? ( 'expired' === $ds ? 'Expired ' : 'Valid until ' ) . esc_html( self::date( $doc['expiry_date'] ) ) : ''; ?></span>
				<?php endif; ?>
				<span class="pill pill--<?php echo esc_attr( $ds ); ?>"><?php echo esc_html( AXH_Bill::status_label( $ds ) ); ?></span>
			</div>
		</header>
		<dl class="facts">
			<div><dt><?php echo $inv ? 'Bill to' : 'Prepared for'; ?></dt><dd><b><?php echo esc_html( $cname ); ?></b><?php echo $cust && $cust['company'] && $cust['contact_name'] ? '<br>' . esc_html( $cust['contact_name'] ) : ''; ?><?php echo $addr ? '<br>' . implode( '<br>', array_map( 'esc_html', $addr ) ) : ''; ?><?php echo $cust && $cust['email'] ? '<br>' . esc_html( $cust['email'] ) : ''; ?></dd></div>
			<div><dt><?php echo esc_html( $label ); ?> no.</dt><dd><?php echo esc_html( $doc['number'] ); ?></dd></div>
			<div><dt>Issued</dt><dd><?php echo esc_html( self::date( $doc['issue_date'] ) ); ?></dd></div>
			<?php if ( $inv ) : ?>
			<div><dt>Due</dt><dd><?php echo esc_html( self::date( $doc['due_date'] ) ); ?><br><small><?php $tl = AXH_Bill::terms_list(); echo esc_html( 'custom' === $doc['terms'] ? 'Net ' . (int) $doc['terms_days'] : ( $tl[ $doc['terms'] ]['label'] ?? '' ) ); ?></small></dd></div>
			<?php else : ?>
			<div><dt>Valid until</dt><dd><?php echo esc_html( self::date( $doc['expiry_date'] ) ); ?></dd></div>
			<?php endif; ?>
			<?php if ( $rep_name ) : ?><div><dt>Your contact</dt><dd><?php echo esc_html( $rep_name ); ?></dd></div><?php endif; ?>
		</dl>
		<?php echo self::line_rows( $doc['lines'] ); // phpcs:ignore ?>
		<div class="tot">
			<div><span>Subtotal</span><b><?php echo esc_html( self::money2( $doc['subtotal'] ) ); ?></b></div>
			<?php if ( $doc['discount_total'] > 0 ) : ?><div><span>Discount<?php echo 'pct' === $doc['discount_type'] ? ' (' . esc_html( rtrim( rtrim( number_format( $doc['discount_value'], 2 ), '0' ), '.' ) ) . '%)' : ''; ?></span><b>−<?php echo esc_html( self::money2( $doc['discount_total'] ) ); ?></b></div><?php endif; ?>
			<?php if ( $doc['tax_on'] ) : ?><div><span><?php echo esc_html( ( $doc['tax_label'] ? $doc['tax_label'] : 'Tax' ) . ' (' . rtrim( rtrim( number_format( $doc['tax_rate'], 3 ), '0' ), '.' ) . '%)' ); ?></span><b><?php echo esc_html( self::money2( $doc['tax_total'] ) ); ?></b></div><?php endif; ?>
			<div class="tot__t"><span>Total</span><b><?php echo esc_html( self::money2( $doc['total'] ) ); ?></b></div>
			<?php if ( ! empty( $doc['_cd']['cd'] ) && 'card' === $doc['_cd']['view'] && $doc['_cd']['discount'] > 0.004 && (float) $doc['balance'] > 0.004 ) : ?>
			<div class="tot__cd"><span><?php echo esc_html( rtrim( rtrim( number_format( $doc['_cd']['pct'], 2 ), '0' ), '.' ) ); ?>% cash discount for check, cash, Zelle or bank transfer</span><b>−<?php echo esc_html( self::money2( $doc['_cd']['discount'] ) ); ?></b></div>
			<div class="tot__cd tot__cd--t"><span>Total paying by check, cash or Zelle</span><b><?php echo esc_html( self::money2( $doc['_cd']['cash'] ) ); ?></b></div>
			<?php elseif ( ! empty( $doc['_cd']['cd'] ) && 'cash' === $doc['_cd']['view'] && (float) $doc['balance'] > 0.004 ) : ?>
			<div class="tot__cd"><span>Shown at the cash price (<?php echo esc_html( rtrim( rtrim( number_format( $doc['_cd']['pct'], 2 ), '0' ), '.' ) ); ?>% cash discount). Paying the rest by card is <?php echo esc_html( self::money2( $doc['_cd']['card'] ) ); ?>.</span><b></b></div>
			<?php endif; ?>
			<?php if ( $inv && (float) $doc['amount_paid'] > 0 ) : ?>
			<div><span>Paid</span><b>−<?php echo esc_html( self::money2( $doc['amount_paid'] ) ); ?></b></div>
			<div class="tot__b"><span>Balance due</span><b><?php echo esc_html( self::money2( max( 0, $doc['balance'] ) ) ); ?></b></div>
			<?php endif; ?>
		</div>
		<?php if ( ! $inv && $doc['content']['schedule'] ) : ?>
		<div class="sum sum--light"><?php echo self::summary_rows( $doc ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<?php if ( $payments ) : ?>
		<div class="hist">
			<div class="card__k">Payment history</div>
			<ul>
			<?php foreach ( $payments as $p ) : ?>
				<li><span class="hist__i"><?php echo self::icon( 'refund' === $p['kind'] ? 'arrow' : 'check', 14 ); // phpcs:ignore ?></span><span><b><?php echo esc_html( 'refund' === $p['kind'] ? 'Refund' : ( 'credit' === $p['kind'] ? 'Credit applied' : 'Payment received' ) ); ?></b><small><?php echo esc_html( self::date( $p['received_at'] ) . ( 'credit' !== $p['kind'] ? ' · ' . ( 'check' === $p['method'] && $p['reference'] ? $p['reference'] : $p['method_label'] ) : '' ) ); ?></small></span><b class="hist__a"><?php echo esc_html( ( 'refund' === $p['kind'] ? '−' : '' ) . self::money2( $p['amount'] ) ); ?></b></li>
			<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
		<?php if ( $inv && ! $void ) : ?>
			<?php echo self::how_to_pay( $doc, $biz, $paid ); // phpcs:ignore ?>
		<?php endif; ?>
		<?php if ( $doc['notes'] ) : ?><div class="note"><?php echo self::md( $doc['notes'] ); // phpcs:ignore ?></div><?php endif; ?>
		<?php if ( $doc['terms_text'] ) : ?><div class="terms"><div class="card__k">Terms</div><?php echo self::md( $doc['terms_text'], 'md--terms' ); // phpcs:ignore ?></div><?php endif; ?>
	</article>
</div>
		<?php
		return ob_get_clean();
	}

	protected static function how_to_pay( $doc, $biz, $paid ) {
		$s     = AXH_Bill::settings();
		// Gateways price themselves from the real invoice, not the card-price view.
		$raw   = ! empty( $doc['_cd'] ) ? AXH_Bill::doc_by_token( $doc['token'] ) : $doc;
		$raw   = $raw ? $raw : $doc;
		$cards = array();
		if ( trim( $s['pay_zelle'] ) ) {
			$cards[] = array( 'zap', 'Zelle', self::md( $s['pay_zelle'] ) );
		}
		if ( $biz['address'] ) {
			$payable = trim( $s['check_payable'] ) ? $s['check_payable'] : $biz['name'];
			$cards[] = array( 'cheque', 'Check', '<div class="md"><p>Make it payable to <strong>' . esc_html( $payable ) . '</strong> and mail it to:</p><p>' . nl2br( esc_html( $biz['address'] ) ) . '</p><p>Write <strong>' . esc_html( $doc['number'] ) . '</strong> on the memo line.</p></div>' );
		}
		if ( trim( $s['pay_other'] ) ) {
			$cards[] = array( 'bank', 'Other ways to pay', self::md( $s['pay_other'] ) );
		}
		$gw = AXH_Bill::gateways();
		ob_start();
		?>
		<section class="pay" id="pay">
			<div class="pay__h"><h2 class="th"><?php echo $paid ? 'Thank you' : 'How to pay'; ?></h2>
			<?php if ( $paid ) : ?><p>This invoice is paid in full. Keep this page (or Download PDF) as your receipt.</p>
			<?php else : ?><p>Reference <b><?php echo esc_html( $doc['number'] ); ?></b> with your payment. We confirm when it arrives.</p><?php endif; ?></div>
			<?php if ( ! $paid ) : ?>
				<div class="ponl print-only"><div class="ponl__qr" data-qr="<?php echo esc_attr( AXH_Bill::public_url( $doc ) ); ?>"></div><div><b>View this invoice online</b><span><?php echo esc_html( AXH_Bill::public_url( $doc ) ); ?></span></div></div>
				<?php if ( $gw ) : ?>
				<div class="pay__gw no-print"><?php foreach ( $gw as $id => $g ) : $gamt = isset( $g['amount'] ) ? (float) call_user_func( $g['amount'], $raw ) : (float) $doc['balance']; $gnote = isset( $g['note'] ) ? (string) call_user_func( $g['note'], $raw ) : ''; ?><button type="button" class="btn btn--o btn--lg" data-pay="<?php echo esc_attr( $id ); ?>"><?php echo self::icon( 'qbo_bank' === $id ? 'bank' : 'card', 18 ); // phpcs:ignore ?> <?php echo esc_html( $g['label'] ); ?> · <?php echo esc_html( self::money2( $gamt ) ); ?><?php if ( $gnote ) : ?><small class="pay__gn"><?php echo esc_html( $gnote ); ?></small><?php endif; ?></button><?php endforeach; ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $doc['_cd']['cd'] ) && $doc['_cd']['discount'] > 0.004 ) : ?>
				<p class="pay__cd"><b><?php echo esc_html( self::money2( $doc['_cd']['cash'] ) ); ?></b> by check, cash or Zelle: that's the <?php echo esc_html( rtrim( rtrim( number_format( $doc['_cd']['pct'], 2 ), '0' ), '.' ) ); ?>% cash discount off the card price of <?php echo esc_html( self::money2( $doc['_cd']['card'] ) ); ?>.</p>
				<?php endif; ?>
				<?php if ( $cards ) : ?>
				<div class="pay__grid pay__grid--<?php echo count( $cards ); ?>">
					<?php foreach ( $cards as $cd ) : ?><div class="pm"><span class="pm__i"><?php echo self::icon( $cd[0], 20 ); // phpcs:ignore ?></span><b><?php echo esc_html( $cd[1] ); ?></b><?php echo $cd[2]; // phpcs:ignore -- built from md()/esc_html ?></div><?php endforeach; ?>
				</div>
				<?php else : ?>
				<div class="pm pm--solo"><span class="pm__i"><?php echo self::icon( 'mail', 20 ); // phpcs:ignore ?></span><b>Ready to pay?</b><div class="md"><p>Contact us and we will send payment details: <a href="mailto:<?php echo esc_attr( $biz['email'] ); ?>"><?php echo esc_html( $biz['email'] ); ?></a><?php echo $biz['phone'] ? ' · <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $biz['phone'] ) ) . '">' . esc_html( $biz['phone'] ) . '</a>' : ''; ?></p></div></div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------- accept / decline area */

	protected static function decision( $doc, $biz, $cname, $decide_ui, $opts ) {
		$label = AXH_Bill::type_label( $doc['type'] );
		$ds    = $doc['display_status'];
		$c     = $doc['content'];
		if ( 'accepted' !== $doc['status'] && 'declined' !== $doc['status'] && 'expired' !== $ds && 'void' !== $doc['status'] && ! $decide_ui ) {
			return '';
		}
		ob_start();
		echo '<section class="acc" id="accept"><div class="acc__glow" aria-hidden="true"></div><div class="wrap acc__in">';
		if ( 'accepted' === $doc['status'] ) {
			$a   = $c['accepted'] ?? array();
			$sig = (string) ( $doc['accepted_sig'] ?? '' );
			?>
			<div class="cert rv">
				<div class="cert__seal"><?php echo self::icon( 'check', 34 ); // phpcs:ignore ?></div>
				<div class="kick kick--dark">Accepted</div>
				<h2 class="th">Thank you, <?php echo esc_html( strtok( (string) $doc['accepted_name'], ' ' ) ); ?>.</h2>
				<p class="cert__p">This <?php echo esc_html( strtolower( $label ) ); ?> was accepted on <?php echo esc_html( wp_date( 'F j, Y \a\t g:i a T', strtotime( get_gmt_from_date( $doc['accepted_at'] ) ) ) ); ?>. We will be in touch with next steps<?php echo ! empty( $c['schedule'] ) ? ' and your first invoice' : ''; ?>.</p>
				<dl class="cert__dl">
					<div><dt>Accepted by</dt><dd><?php echo esc_html( $doc['accepted_name'] ); ?></dd></div>
					<?php if ( ! empty( $a['option_name'] ) ) : ?><div><dt>Option</dt><dd><?php echo esc_html( $a['option_name'] ); ?></dd></div><?php endif; ?>
					<?php if ( ! empty( $a['addon_names'] ) ) : ?><div><dt>Add-ons</dt><dd><?php echo esc_html( implode( ', ', array_map( array( 'AXH_Bill', 'addon_label' ), (array) $a['addon_names'] ) ) ); ?></dd></div><?php endif; ?>
					<div><dt>Total</dt><dd><?php echo esc_html( self::money2( $doc['total'] ) ); ?></dd></div>
				</dl>
				<div class="cert__sig">
					<?php if ( $sig && 0 === strpos( $sig, 'data:image/png;base64,' ) ) : ?><img src="<?php echo esc_attr( $sig ); ?>" alt="Signature of <?php echo esc_attr( $doc['accepted_name'] ); ?>">
					<?php else : ?><span class="sigtype"><?php echo esc_html( $doc['accepted_name'] ); ?></span><?php endif; ?>
					<small><?php echo esc_html( $doc['accepted_name'] ); ?> · <?php echo 'manual' === ( $doc['accepted_method'] ?? '' ) ? 'recorded by ' . esc_html( $biz['name'] ) : ( 'draw' === ( $doc['accepted_method'] ?? '' ) ? 'signed electronically' : 'typed electronic signature' ); ?></small>
				</div>
				<p class="cert__fp">Document fingerprint <code><?php echo esc_html( substr( (string) $doc['accepted_hash'], 0, 16 ) ); ?>…</code><?php echo ! empty( $a['record_hash'] ) ? ' · Record <code>' . esc_html( substr( $a['record_hash'], 0, 12 ) ) . '</code>' : ''; ?></p>
			</div>
			<?php
		} elseif ( 'declined' === $doc['status'] ) {
			echo '<div class="cert cert--muted rv"><div class="kick kick--dark">Declined</div><h2 class="th">Thanks for letting us know.</h2><p class="cert__p">This ' . esc_html( strtolower( $label ) ) . ' was declined on ' . esc_html( self::date( $doc['declined_at'] ) ) . '. If anything changes, we would be glad to revisit it: <a href="mailto:' . esc_attr( $biz['email'] ) . '">' . esc_html( $biz['email'] ) . '</a>.</p></div>';
		} elseif ( 'expired' === $ds ) {
			echo '<div class="cert cert--muted rv"><div class="kick kick--dark">Expired</div><h2 class="th">This ' . esc_html( strtolower( $label ) ) . ' has expired.</h2><p class="cert__p">It was valid until ' . esc_html( self::date( $doc['expiry_date'] ) ) . '. Contact us for an updated one: <a href="mailto:' . esc_attr( $biz['email'] ) . '">' . esc_html( $biz['email'] ) . '</a>' . ( $biz['phone'] ? ' · ' . esc_html( $biz['phone'] ) : '' ) . '.</p></div>';
		} elseif ( 'void' === $doc['status'] ) {
			echo '<div class="cert cert--muted rv"><div class="kick kick--dark">Withdrawn</div><h2 class="th">This ' . esc_html( strtolower( $label ) ) . ' is no longer available.</h2></div>';
		} elseif ( $decide_ui ) {
			$sched = (array) $c['schedule'];
			?>
			<div class="acc__l rv">
				<div class="kick">Accept <?php echo esc_html( strtolower( $label ) ); ?></div>
				<h2 class="th acc__h">Ready to get started?</h2>
				<p class="acc__p">Accepting takes a minute. You will get a copy of this page, and we will send <?php echo $sched ? 'the first invoice' : 'next steps'; ?> straight after.</p>
				<ul class="acc__list">
					<li data-sel-row hidden><span>Option</span><b data-sel-name></b></li>
					<li data-add-row hidden><span>Add-ons</span><b data-add-names></b></li>
					<li><span>Total</span><b data-total-out><?php echo esc_html( self::money2( $doc['total'] ) ); ?></b></li>
					<?php if ( $sched ) : ?><li><span><?php echo esc_html( $sched[0]['label'] . ' · ' . rtrim( rtrim( number_format( $sched[0]['pct'], 2 ), '0' ), '.' ) . '%' ); ?></span><b data-sched="0"><?php echo esc_html( self::money2( round( $doc['total'] * $sched[0]['pct'] / 100, 2 ) ) ); ?></b></li><?php endif; ?>
				</ul>
			</div>
			<?php echo self::accept_print( $doc, $biz, $label ); // phpcs:ignore ?>
			<form class="acc__f rv" id="accept-form" style="--d:.1s" novalidate>
				<label class="fld"><span>Your full name</span><input type="text" id="acc-name" autocomplete="name" maxlength="120" placeholder="First and last name" required></label>
				<div class="sigtabs" role="tablist"><button type="button" class="is-on" data-sig="type" role="tab" aria-selected="true"><?php echo self::icon( 'pen', 14 ); // phpcs:ignore ?> Type</button><button type="button" data-sig="draw" role="tab" aria-selected="false"><?php echo self::icon( 'pen', 14 ); // phpcs:ignore ?> Draw</button></div>
				<div class="sigbox" data-sig-type><span class="sigtype is-empty" id="sig-preview">Your signature</span></div>
				<div class="sigbox sigbox--draw" data-sig-draw hidden><canvas id="sig-pad" aria-label="Draw your signature"></canvas><button type="button" class="sigclear" data-sig-clear>Clear</button><span class="sighint">Sign with your finger or mouse</span></div>
				<label class="chk"><input type="checkbox" id="acc-agree"><span>I agree to this <?php echo esc_html( strtolower( $label ) ); ?> and its terms, and that the name above is my electronic signature.</span></label>
				<button type="submit" class="btn btn--o btn--lg btn--block" id="acc-go"<?php echo ( $opts['preview'] || $opts['internal'] ) ? ' disabled' : ''; ?>>Accept <?php echo esc_html( strtolower( $label ) ); ?> · <span data-total-out><?php echo esc_html( self::money2( $doc['total'] ) ); ?></span></button>
				<?php if ( $opts['internal'] ) : ?><p class="acc__fine"><?php echo self::icon( 'info', 13 ); // phpcs:ignore ?> You are signed in to the Hub, so accepting is switched off here. Your customer sees this form working.</p><?php endif; ?>
				<p class="acc__fine"><?php echo self::icon( 'lock', 13 ); // phpcs:ignore ?> We record your name, the date and time, your IP address and a fingerprint of this document as your acceptance.</p>
				<button type="button" class="acc__decline" data-decline<?php echo ( $opts['preview'] || $opts['internal'] ) ? ' disabled' : ''; ?>>Not the right fit? Decline</button>
			</form>
			<?php
		}
		echo '</div></section>';
		return ob_get_clean();
	}

	/** Print-only: the customer link + QR code, the selection, and lines for a wet signature. */
	protected static function accept_print( $doc, $biz, $label ) {
		$c     = $doc['content'];
		$url   = AXH_Bill::public_url( $doc );
		$sched = (array) $c['schedule'];
		$lab   = strtolower( $label );
		$opts  = (array) $c['options'];
		$h  = '<div class="ap print-only">';
		$h .= '<div class="kick">Accept ' . esc_html( $lab ) . '</div><h2 class="th ap__h">How to accept</h2>';
		$h .= '<div class="ap__grid"><div class="ap__on"><b>Online, in a minute</b><p>Scan the code or open the link below. Choose your option, sign, and you get a copy straight away.</p><span class="ap__url">' . esc_html( $url ) . '</span></div><div class="ap__qr" data-qr="' . esc_attr( $url ) . '" aria-hidden="true"></div></div>';
		$h .= '<ul class="ap__sum">';
		if ( count( $opts ) > 1 ) {
			$h .= '<li data-sel-row><span>Option</span><b data-sel-name></b></li>';
		}
		if ( (array) $c['addons'] ) {
			$h .= '<li data-add-row hidden><span>Add-ons</span><b data-add-names></b></li>';
		}
		$h .= '<li><span>Total</span><b data-total-out>' . esc_html( self::money2( $doc['total'] ) ) . '</b></li>';
		if ( $sched ) {
			$h .= '<li><span>' . esc_html( $sched[0]['label'] . ' · ' . rtrim( rtrim( number_format( $sched[0]['pct'], 2 ), '0' ), '.' ) . '%' ) . '</span><b data-sched="0">' . esc_html( self::money2( round( $doc['total'] * $sched[0]['pct'] / 100, 2 ) ) ) . '</b></li>';
		}
		$h .= '</ul>';
		$h .= '<div class="ap__paper"><b>Prefer to sign on paper?</b> Sign below and email a photo or scan to ' . esc_html( $biz['email'] ) . '. By signing you accept this ' . esc_html( $lab ) . ' and its terms' . ( count( $opts ) > 1 ? ', for the option marked above' : '' ) . '.'
			. '<div class="ap__lines"><span><i></i>Full name</span><span><i></i>Signature</span><span><i></i>Date</span></div></div>';
		return $h . '</div>';
	}

	/** 1.7.3: page size, margins, running header and "Page X of Y" for the PDF. */
	protected static function print_pages( $doc, $biz, $label, $cname ) {
		$q    = function ( $t ) {
			return '"' . addcslashes( preg_replace( '/[\r\n<>]+/', ' ', (string) $t ), "\\\"" ) . '"';
		};
		$head = $q( $label . ' ' . $doc['number'] . ' · ' . $cname );
		$foot = $q( trim( $biz['name'] . ( $biz['website'] ? ' · ' . $biz['website'] : '' ) . ( $biz['email'] ? ' · ' . $biz['email'] : '' ) ) );
		$logo = '';
		if ( $biz['logo'] ) {
			$logo = 'url("' . esc_url_raw( $biz['logo'] ) . '")';
		} elseif ( is_readable( AXH_DIR . 'assets/img/logo-dark.svg' ) ) {
			$svg  = (string) file_get_contents( AXH_DIR . 'assets/img/logo-dark.svg' );
			$svg  = preg_replace( '/<svg\b([^>]*?)\swidth="[^"]*"/', '<svg$1', $svg, 1 );
			$svg  = preg_replace( '/<svg\b([^>]*?)\sheight="[^"]*"/', '<svg$1', $svg, 1 );
			$svg  = preg_replace( '/<svg\b/', '<svg width="62" height="22"', $svg, 1 );
			$logo = 'url("data:image/svg+xml;base64,' . base64_encode( $svg ) . '")';
		}
		$box  = 'font-family:"AXB Inter",Inter,Arial,sans-serif;font-size:7.5pt;color:#8a847b;';
		$css  = '@page{size:letter;margin:17mm 15mm 16mm;'
			. ( $logo ? '@top-left{content:' . $logo . ';vertical-align:bottom;padding-bottom:3.5mm}' : '' )
			. '@top-right{content:' . $head . ';' . $box . 'vertical-align:bottom;padding-bottom:4.5mm}'
			. '@bottom-left{content:' . $foot . ';' . $box . 'vertical-align:top;padding-top:4mm}'
			. '@bottom-right{content:"Page " counter(page) " of " counter(pages);' . $box . 'vertical-align:top;padding-top:4mm}}';
		if ( 'proposal' === $doc['type'] ) {
			// The cover is exactly page 1, edge to edge, with no running header or footer.
			$css .= '@page:first{margin:0;@top-left{content:none}@top-right{content:none}@bottom-left{content:none}@bottom-right{content:none}}';
		} else {
			$css .= '@page:first{@top-left{content:none}@top-right{content:none}}';
		}
		return $css;
	}

	protected static function footer( $doc, $biz, $rep_name ) {
		ob_start();
		?>
<footer class="ft">
	<div class="wrap ft__in">
		<div class="ft__brand"><?php echo self::logo( false ); // phpcs:ignore ?><p>Websites, custom CRMs &amp; SEO. You own what we build.</p></div>
		<div class="ft__c">
			<b>Questions about this <?php echo esc_html( strtolower( AXH_Bill::type_label( $doc['type'] ) ) ); ?>?</b>
			<?php if ( $rep_name ) : ?><span><?php echo esc_html( self::same_person( $rep_name, $biz['contact'] ) ? $rep_name : $rep_name . ' or ' . $biz['contact'] ); ?> at <?php echo esc_html( $biz['name'] ); ?></span><?php endif; ?>
			<a href="mailto:<?php echo esc_attr( $biz['email'] ); ?>"><?php echo self::icon( 'mail', 15 ); // phpcs:ignore ?> <?php echo esc_html( $biz['email'] ); ?></a>
			<?php if ( $biz['phone'] ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $biz['phone'] ) ); ?>"><?php echo self::icon( 'phone', 15 ); // phpcs:ignore ?> <?php echo esc_html( $biz['phone'] ); ?></a><?php endif; ?>
		</div>
		<div class="ft__l"><?php echo esc_html( $biz['name'] ); ?><?php echo $biz['website'] ? '<br>' . esc_html( $biz['website'] ) : ''; ?><br><span class="ft__priv">This page is private to you. Please don’t share the link.</span></div>
	</div>
</footer>
		<?php
		return ob_get_clean();
	}

	/* --------------------------------------------------------------- CSS */

	protected static function css( $accent ) {
		$f   = esc_url( AXH_URL . 'assets/fonts/' );
		$acc = sanitize_hex_color( $accent ) ? $accent : '#EF5D0F';
		$css = <<<CSS
@font-face{font-family:"Thunder LC";font-weight:500;font-display:swap;src:url("{$f}Thunder-MediumLC.ttf") format("truetype")}
@font-face{font-family:"AXB Inter";font-weight:100 900;font-display:swap;src:url("{$f}Inter-var.woff2") format("woff2")}
:root{--ink:#0b0b0b;--ink2:#161616;--o:{$acc};--paper:#f5f4f1;--line:#e3e0d9;--mute:#6f6a63;--soft:#faf9f6;--ok:#0f7a3c;--okbg:#e7f6ec;--bad:#b42318;--r:22px;--ease:cubic-bezier(.2,.7,.1,1);--wrap:1180px}
*,*::before,*::after{box-sizing:border-box}
[hidden]{display:none!important}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.62 "AXB Inter",Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased;font-feature-settings:"cv11","ss01";overflow-x:hidden}
a{color:inherit}
img{max-width:100%;display:block}
h1,h2,h3,p,ul,ol,dl,dd,figure{margin:0}
button{font:inherit;cursor:pointer}
.th{font-family:"Thunder LC","Arial Narrow",sans-serif;font-weight:500;text-transform:uppercase;letter-spacing:.004em;line-height:.88}
.wrap{max-width:var(--wrap);margin:0 auto;padding:0 32px}
.kick{font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--o)}
.kick i{font-style:normal;opacity:.5;margin:0 4px}
.kick--dark{color:var(--ok)}
.flag{position:relative;z-index:60;display:flex;gap:8px;align-items:center;justify-content:center;padding:9px 16px;background:#fff3e9;color:#7a3307;font-size:13px;font-weight:600;border-bottom:1px solid #f6d6bd;text-align:center}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:999px;padding:13px 22px;font-weight:650;font-size:15px;text-decoration:none;line-height:1;transition:transform .2s var(--ease),box-shadow .2s var(--ease),background .2s;white-space:nowrap}
.btn:focus-visible,a:focus-visible,input:focus-visible,textarea:focus-visible,.opt:has(input:focus-visible){outline:3px solid color-mix(in srgb,var(--o) 45%,transparent);outline-offset:2px}
.fld input:focus-visible,.fld textarea:focus-visible{outline:0}
.btn--o{background:var(--o);color:#fff;box-shadow:0 10px 30px -10px color-mix(in srgb,var(--o) 80%,transparent)}
.btn--o:hover{transform:translateY(-1px);box-shadow:0 16px 36px -12px color-mix(in srgb,var(--o) 85%,transparent)}
.btn--ink{background:var(--ink);color:#fff}
.btn--ghost{background:transparent;color:inherit;box-shadow:inset 0 0 0 1.5px currentColor}
.btn--sm{padding:9px 15px;font-size:13.5px}
.btn--lg{padding:17px 28px;font-size:16px}
.btn--block{width:100%}
.btn[disabled]{opacity:.55;cursor:not-allowed;transform:none}
.pill{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;font-size:11.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:#ecebe7;color:#4b4741}
.pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.8}
.pill--sent,.pill--viewed{background:#e8f0fd;color:#1d4fb8}
.pill--accepted,.pill--paid{background:var(--okbg);color:var(--ok)}
.pill--partial{background:#fff4e0;color:#9a5a00}
.pill--overdue,.pill--declined{background:#fde8e6;color:var(--bad)}
.pill--expired,.pill--void,.pill--draft{background:#ecebe7;color:#625d56}
/* top bar */
.tb{position:sticky;top:0;z-index:50;height:66px;background:rgba(245,244,241,.84);backdrop-filter:saturate(1.6) blur(16px);-webkit-backdrop-filter:saturate(1.6) blur(16px);border-bottom:1px solid rgba(11,11,11,.07);transition:background .35s,border-color .35s,color .35s}
.tb__in{max-width:var(--wrap);margin:0 auto;padding:0 32px;height:100%;display:flex;align-items:center;gap:14px}
.tb__brand{display:flex;align-items:center;position:relative}
.tb__brand .lg{height:30px;width:auto}
.tb__lgw{position:absolute;inset:0;opacity:0;transition:opacity .35s}
.tb__lgw .lg{height:30px}
.tb__doc{font-size:13.5px;color:var(--mute);padding-left:14px;border-left:1px solid var(--line)}
.tb__doc b{color:var(--ink);font-weight:650}
.tb__sp{flex:1}
.tb.is-dark{background:rgba(11,11,11,.35);border-color:rgba(255,255,255,.08);color:#fff}
.tb.is-dark .tb__brand>.lg{opacity:0}
.tb.is-dark .tb__lgw{opacity:1}
.tb.is-dark .tb__doc{color:rgba(255,255,255,.6);border-color:rgba(255,255,255,.18)}
.tb.is-dark .tb__doc b{color:#fff}
.tb.is-dark .pill{background:rgba(255,255,255,.12);color:#fff}
.lg--custom{max-height:40px;width:auto}
.lg--chip{background:#fff;border-radius:10px;padding:4px 8px}
/* reveal */
.rv{opacity:0;transform:translateY(22px);transition:opacity .9s var(--ease) var(--d,0s),transform .9s var(--ease) var(--d,0s)}
.rv.in{opacity:1;transform:none}
.wd{display:inline-block;overflow:hidden;vertical-align:bottom;padding-bottom:.04em;margin-bottom:-.04em}
.wd>span{display:inline-block;transform:translateY(105%);transition:transform 1s var(--ease);transition-delay:calc(var(--i) * 70ms + 120ms)}
.ready .wd>span{transform:none}
.no-js .rv{opacity:1;transform:none}.no-js .wd>span{transform:none}
/* cover */
.cv{position:relative;overflow:hidden;background:var(--ink);color:#fff;min-height:min(100svh,980px);margin-top:-66px;padding:150px 0 96px;display:flex;align-items:flex-end;isolation:isolate}
.is-preview .cv{min-height:760px}
.cv__bg{position:absolute;inset:0;z-index:-1;background:radial-gradient(900px 620px at 88% 8%,color-mix(in srgb,var(--o) 42%,transparent),transparent 62%),radial-gradient(760px 520px at -5% 105%,color-mix(in srgb,var(--o) 16%,transparent),transparent 60%),repeating-linear-gradient(45deg,rgba(255,255,255,.018) 0 36px,transparent 36px 72px),repeating-linear-gradient(-45deg,rgba(0,0,0,.25) 0 36px,transparent 36px 72px)}
.mark{position:absolute;right:-6%;top:6%;height:118%;width:auto;fill:none;stroke:color-mix(in srgb,var(--o) 55%,transparent);stroke-width:.18;opacity:.55;animation:drift 18s ease-in-out infinite alternate}
.mark path{vector-effect:non-scaling-stroke;stroke-width:1.4}
@keyframes drift{to{transform:translate3d(-2%,2%,0) rotate(-2deg)}}
.cv__grid{display:grid;grid-template-columns:1fr;gap:56px;align-items:end;width:100%}
.cv--img .cv__grid{grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr)}
.cv__h{font-size:clamp(68px,10.4vw,172px);margin:18px 0 26px;max-width:12ch}
.cv--img .cv__h{font-size:clamp(64px,8.2vw,150px)}
.cv__intro{max-width:58ch;color:#cfcac3;font-size:19px;line-height:1.6}
.cv__intro .md p{margin:0;color:inherit;font-size:inherit;max-width:none}
.cv__meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;margin-top:40px;padding-top:26px;border-top:1px solid rgba(255,255,255,.14);max-width:860px}
.cv__meta dt{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:6px}
.cv__meta dd{font-weight:600;font-size:15px;line-height:1.35}
.cv__meta small{display:block;font-weight:400;color:rgba(255,255,255,.6);font-size:13px;margin-top:2px}
.cv__cta{display:flex;flex-wrap:wrap;align-items:center;gap:22px;margin-top:40px}
.cv__from{color:rgba(255,255,255,.66);font-size:15px}
.cv__from b{color:#fff;font-size:18px;margin-left:4px}
.cv__art{perspective:1600px}
.frame{border-radius:18px;overflow:hidden;background:#1b1b1b;border:1px solid rgba(255,255,255,.12);box-shadow:0 60px 140px -30px rgba(0,0,0,.85),0 0 0 1px rgba(255,255,255,.04);transform:rotateY(-9deg) rotateX(4deg);transform-origin:left center;transition:transform 1.2s var(--ease)}
.frame:hover{transform:rotateY(-3deg) rotateX(1deg)}
.frame__bar{display:flex;gap:6px;padding:11px 14px;background:#232323;border-bottom:1px solid rgba(255,255,255,.06)}
.frame__bar i{width:9px;height:9px;border-radius:50%;background:#3d3d3d}
.frame img{width:100%;aspect-ratio:16/11;object-fit:cover;object-position:top}
.cv__down{position:absolute;left:50%;bottom:26px;width:26px;height:42px;margin-left:-13px;border:1.5px solid rgba(255,255,255,.35);border-radius:14px}
.cv__down span{position:absolute;left:50%;top:9px;width:3px;height:8px;margin-left:-1.5px;border-radius:2px;background:#fff;animation:scroll 1.8s ease-in-out infinite}
@keyframes scroll{0%{opacity:0;transform:translateY(-3px)}40%{opacity:1}100%{opacity:0;transform:translateY(12px)}}
/* doc grid */
.doc{display:grid;grid-template-columns:230px minmax(0,1fr);gap:72px;padding-top:24px}
.rail{position:relative}
.rail__in{position:sticky;top:96px;padding:40px 0}
.rail__h{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);margin-bottom:14px}
.toc{list-style:none;padding:0;margin:0 0 28px;border-left:1.5px solid var(--line)}
.toc a{display:flex;gap:12px;padding:8px 0 8px 16px;margin-left:-1.5px;border-left:1.5px solid transparent;text-decoration:none;color:var(--mute);font-size:14px;font-weight:550;transition:color .2s,border-color .2s}
.toc a span{font-variant-numeric:tabular-nums;font-size:12px;opacity:.7;padding-top:1px}
.toc a:hover{color:var(--ink)}
.toc a.on{color:var(--ink);border-left-color:var(--o)}
.toc a.on span{color:var(--o);opacity:1}
.rail__sum{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px;display:grid;gap:6px}
.rail__sum span{font-size:12px;color:var(--mute);font-weight:600;letter-spacing:.04em}
.rail__sum b{font-size:24px;font-weight:750;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.rail__sum .btn{margin-top:8px}
.main{min-width:0}
.sec{padding:96px 0 32px;scroll-margin-top:70px}
.sec+.sec{border-top:1px solid var(--line)}
.sec__h{display:flex;align-items:baseline;gap:18px;margin-bottom:34px}
.sec__n{color:var(--o);font-size:30px}
.sec__h h2{font-size:clamp(46px,6vw,84px)}
/* markdown */
.md p{font-size:17.5px;line-height:1.72;color:#2b2925;max-width:68ch}
.md p+p,.md p+ul,.md ul+p,.md p+ol,.md ol+p,.md h3+p,.md p+h3,.md ul+h3{margin-top:16px}
.md h3{font-size:13px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink);margin:30px 0 12px}
.md h3:first-child{margin-top:0}
.md ul,.md ol{padding:0;list-style:none;display:grid;gap:10px}
.md ul li{position:relative;padding-left:24px;font-size:17px;color:#2b2925;line-height:1.6}
.md ul li::before{content:"";position:absolute;left:2px;top:.62em;width:8px;height:8px;border-radius:2px;background:var(--o);transform:rotate(45deg)}
.md ol{counter-reset:n}
.md ol li{counter-increment:n;position:relative;padding-left:36px;font-size:17px;color:#2b2925}
.md ol li::before{content:counter(n);position:absolute;left:0;top:.1em;width:24px;height:24px;border-radius:50%;background:var(--ink);color:#fff;font-size:12px;font-weight:700;display:grid;place-items:center}
.md a{color:var(--o);text-underline-offset:3px}
.md strong{color:var(--ink);font-weight:680}
.md blockquote{border-left:3px solid var(--o);padding:6px 0 6px 18px;font-size:19px;color:var(--ink)}
.md--lead p{font-size:19px;color:var(--mute)}
.md--cards ul{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.md--cards ul li{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px 20px 20px 58px;font-size:16px;line-height:1.5;min-height:78px;display:flex;align-items:center;transition:transform .3s var(--ease),box-shadow .3s}
.md--cards ul li:hover{transform:translateY(-2px);box-shadow:0 18px 40px -24px rgba(0,0,0,.35)}
.md--cards ul li::before{left:18px;top:50%;width:26px;height:26px;margin-top:-13px;border-radius:50%;transform:none;background:var(--o) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E") center/14px no-repeat}
.md--checks ul{display:flex;flex-wrap:wrap;gap:10px}
.md--checks ul li{background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 18px 10px 42px;font-size:15px;font-weight:550}
.md--checks ul li::before{left:14px;top:50%;margin-top:-9px;width:18px;height:18px;border-radius:50%;transform:none;background:var(--ok) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E") center/11px no-repeat}
.sec--why{position:relative;color:#fff;border-top:0!important;padding:88px 56px 64px;margin:56px 0;background:var(--ink);border-radius:32px;overflow:hidden;isolation:isolate}
.sec--why::before{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(700px 400px at 100% 0,color-mix(in srgb,var(--o) 30%,transparent),transparent 60%)}
.sec--why+.sec{border-top:0}
.sec--why .md ul{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.sec--why .md ul li{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:24px;color:#c9c4bd;font-size:15.5px;line-height:1.55}
.sec--why .md ul li::before{display:none}
.sec--why .md ul li strong{display:block;color:#fff;font-size:19px;margin-bottom:6px;letter-spacing:-.01em}
.sec--why .md p{color:#cfcac3}
.md--steps ol{gap:0}
.md--steps ol li{padding:0 0 26px 64px;font-size:17.5px;min-height:44px}
.md--steps ol li::before{width:42px;height:42px;font-size:15px;background:#fff;color:var(--ink);border:1.5px solid var(--ink);top:-6px}
.md--steps ol li:not(:last-child)::after{content:"";position:absolute;left:20px;top:40px;bottom:-2px;width:1.5px;background:var(--line)}
.md--steps ol li:first-child::before{background:var(--o);border-color:var(--o);color:#fff}
.md--terms p,.md--terms li{font-size:14px;color:var(--mute);line-height:1.65}
.md--terms ul li::before{background:#c8c3bb}
/* timeline */
.tl{list-style:none;padding:0;margin:8px 0 0;display:grid;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);gap:18px;position:relative}
.tl::before{content:"";position:absolute;left:0;right:0;top:9px;height:2px;background:linear-gradient(90deg,var(--o),var(--line) 70%)}
.tl li{position:relative;padding-top:34px}
.tl__dot{position:absolute;top:0;left:0;width:20px;height:20px;border-radius:50%;background:var(--paper);border:2px solid var(--o)}
.tl li:first-child .tl__dot{background:var(--o)}
.tl__when{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--o);margin-bottom:6px}
.tl b{display:block;font-size:17px;line-height:1.3;margin-bottom:6px}
.tl p{font-size:14.5px;color:var(--mute);line-height:1.55}
/* investment */
.card{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:22px;margin-bottom:18px}
.card__k{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--mute);margin-bottom:12px}
.opts{display:grid;gap:18px;margin:26px 0 22px}
.opts--2{grid-template-columns:repeat(2,minmax(0,1fr))}
.opts--3,.opts--4{grid-template-columns:repeat(3,minmax(0,1fr))}
.opt{position:relative;display:flex;flex-direction:column;background:#fff;border:1.5px solid var(--line);border-radius:24px;padding:28px 26px 22px;cursor:pointer;transition:border-color .25s,box-shadow .35s var(--ease),transform .35s var(--ease)}
.opt:hover{transform:translateY(-3px);box-shadow:0 24px 50px -30px rgba(0,0,0,.45)}
.opt input{position:absolute;opacity:0;pointer-events:none}
.opt.is-on{border-color:var(--o);box-shadow:0 0 0 4px color-mix(in srgb,var(--o) 14%,transparent),0 28px 60px -34px color-mix(in srgb,var(--o) 80%,transparent)}
.opt__rec{position:absolute;top:-12px;left:24px;background:var(--ink);color:#fff;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:6px 12px;border-radius:999px}
.opt__tick{position:absolute;top:22px;right:22px;width:28px;height:28px;border-radius:50%;border:1.5px solid var(--line);display:grid;place-items:center;color:transparent;transition:all .25s}
.opt.is-on .opt__tick{background:var(--o);border-color:var(--o);color:#fff}
.opt__name{font-size:38px;padding-right:40px}
.opt__tag{color:var(--mute);font-size:15px;margin-top:6px;min-height:22px}
.opt__price{font-size:64px;margin:18px 0 6px;letter-spacing:.01em}
.opt__desc p{font-size:15px}
.opt__lines{list-style:none;padding:16px 0 0;margin:14px 0 18px;border-top:1px solid var(--line);display:grid;gap:10px;flex:1}
.opt__lines li{display:flex;justify-content:space-between;gap:14px;font-size:14.5px}
.opt__lines li span{color:#2b2925}
.opt__lines li i{color:var(--mute);font-style:normal}
.opt__lines li small{display:block;color:var(--mute);font-size:13px;line-height:1.45;margin-top:2px}
.opt__lines li b{white-space:nowrap;font-variant-numeric:tabular-nums}
.opt__sel{display:block;text-align:center;border-radius:999px;padding:11px;font-weight:650;font-size:14px;background:var(--paper);transition:background .25s,color .25s}
.opt.is-on .opt__sel{background:var(--o);color:#fff}
.adds{background:#fff;border:1px solid var(--line);border-radius:24px;padding:8px 24px;margin-bottom:22px}
.adds__h{display:flex;flex-wrap:wrap;gap:4px 14px;align-items:baseline;padding:16px 0 12px;border-bottom:1px solid var(--line)}
.adds__h b{font-size:16px}.adds__h span{color:var(--mute);font-size:14px}
.add{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:18px;align-items:center;padding:16px 0;cursor:pointer}
.add+.add{border-top:1px solid var(--line)}
.add__t b{display:block;font-size:15.5px}
.add__t small{display:block;color:var(--mute);font-size:13.5px;line-height:1.45;margin-top:2px}
.add__p{font-weight:650;font-variant-numeric:tabular-nums;white-space:nowrap;font-size:15px}
.sw{appearance:none;-webkit-appearance:none;width:48px;height:28px;border-radius:999px;background:#d9d5ce;position:relative;cursor:pointer;transition:background .25s;margin:0;flex:none}
.sw::after{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25);transition:transform .3s var(--ease)}
.sw:checked{background:var(--o)}
.sw:checked::after{transform:translateX(20px)}
.sw:disabled{opacity:.6}
.sum{background:var(--ink);color:#fff;border-radius:26px;padding:28px 30px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 48px;position:relative;overflow:hidden}
.sum::before{content:"";position:absolute;right:-120px;top:-160px;width:420px;height:420px;border-radius:50%;background:radial-gradient(closest-side,color-mix(in srgb,var(--o) 35%,transparent),transparent)}
.sum__rows{display:grid;gap:10px;align-content:start;position:relative}
.sum__rows div{display:flex;justify-content:space-between;color:rgba(255,255,255,.7);font-size:15px}
.sum__rows b{color:#fff;font-variant-numeric:tabular-nums;font-weight:600}
.sum__total{display:flex;justify-content:space-between;align-items:flex-end;grid-column:1;border-top:1px solid rgba(255,255,255,.14);padding-top:16px;margin-top:6px;position:relative}
.sum__total span{font-weight:650;color:rgba(255,255,255,.8);padding-bottom:8px}
.sum__total b{font-size:62px;color:#fff;transition:color .3s}
.sum__total b.bump{animation:bump .6s var(--ease)}
@keyframes bump{30%{color:var(--o);transform:translateY(-2px)}}
.sum__sched{grid-column:2;grid-row:1/span 2;position:relative;align-self:center}
.sum__k{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:10px}
.sch{display:grid;grid-template-columns:56px minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid rgba(255,255,255,.1)}
.sch__pct{font-weight:750;color:var(--o);font-size:18px}
.sch__t b{display:block;font-size:15px}.sch__t small{color:rgba(255,255,255,.55);font-size:13px}
.sch>b{font-variant-numeric:tabular-nums}
.sum--light{background:var(--soft);color:var(--ink);border:1px solid var(--line);margin-top:22px}
.sum--light::before{display:none}
.sum--light .sum__rows{display:none}
.sum--light .sum__total{display:none}
.sum--light .sum__sched{grid-column:1/-1;grid-row:auto}
.sum--light .sum__k{color:var(--mute)}
.sum--light .sch{border-color:var(--line)}
.sum--light .sch__t small{color:var(--mute)}
.note{margin-top:30px;padding:20px 22px;border-radius:18px;background:#fff;border:1px dashed #d7d1c7}
.note .md p{font-size:15.5px;color:#3a3732}
/* accept */
.acc{position:relative;background:var(--ink);color:#fff;padding:120px 0;margin-top:96px;overflow:hidden;isolation:isolate;scroll-margin-top:66px}
.acc__glow{position:absolute;inset:0;z-index:-1;background:radial-gradient(800px 500px at 10% 0,color-mix(in srgb,var(--o) 32%,transparent),transparent 60%),repeating-linear-gradient(45deg,rgba(255,255,255,.016) 0 36px,transparent 36px 72px)}
.acc__in{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,480px);gap:72px;align-items:center}
.acc__h{font-size:clamp(64px,8vw,128px);margin:16px 0 20px}
.acc__p{color:#c9c4bd;font-size:18px;max-width:46ch}
.acc__list{list-style:none;padding:0;margin:30px 0 0;max-width:460px}
.acc__list li{display:flex;justify-content:space-between;gap:20px;padding:13px 0;border-top:1px solid rgba(255,255,255,.12);font-size:15.5px}
.acc__list li span{color:rgba(255,255,255,.62)}
.acc__list li b{text-align:right;font-variant-numeric:tabular-nums}
.acc__f{background:#fff;color:var(--ink);border-radius:28px;padding:30px;box-shadow:0 50px 120px -40px rgba(0,0,0,.8)}
.fld{display:grid;gap:8px;margin-bottom:16px}
.fld span{font-size:13px;font-weight:650;color:#3a3732}
.fld input,.fld textarea{width:100%;border:1.5px solid var(--line);border-radius:14px;padding:14px 16px;font:inherit;font-size:16px;color:var(--ink);background:#fff;transition:border-color .2s,box-shadow .2s}
.fld input:focus,.fld textarea:focus{outline:0;border-color:var(--o);box-shadow:0 0 0 4px color-mix(in srgb,var(--o) 16%,transparent)}
.sigtabs{display:inline-flex;background:var(--paper);border-radius:999px;padding:4px;margin-bottom:10px}
.sigtabs button{display:inline-flex;gap:6px;align-items:center;border:0;background:transparent;border-radius:999px;padding:8px 16px;font-size:13.5px;font-weight:650;color:var(--mute)}
.sigtabs button.is-on{background:#fff;color:var(--ink);box-shadow:0 2px 8px rgba(0,0,0,.08)}
.sigbox{position:relative;height:120px;border:1.5px dashed #d2ccc2;border-radius:16px;background:linear-gradient(transparent calc(100% - 34px),#e9e4dc calc(100% - 34px),#e9e4dc calc(100% - 33px),transparent calc(100% - 33px));display:flex;align-items:center;padding:0 22px;margin-bottom:16px;overflow:hidden}
.sigtype{font-family:Allura,"Segoe Script","Snell Roundhand","Brush Script MT",cursive;font-size:52px;line-height:1;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-top:6px}
.sigtype.is-empty{color:#c9c2b7}
.sigbox--draw{padding:0;height:160px;touch-action:none}
.sigbox canvas{width:100%;height:100%;display:block;cursor:crosshair;touch-action:none}
.sigclear{position:absolute;top:10px;right:10px;border:0;background:var(--paper);border-radius:999px;padding:6px 12px;font-size:12.5px;font-weight:650}
.sighint{position:absolute;left:16px;bottom:8px;font-size:12px;color:#a59e93;pointer-events:none}
.chk{display:flex;gap:12px;align-items:flex-start;margin:4px 0 20px;cursor:pointer;font-size:14px;line-height:1.5;color:#3a3732}
.chk input{width:20px;height:20px;margin:1px 0 0;accent-color:var(--o);flex:none}
.acc__fine{display:flex;gap:8px;align-items:flex-start;font-size:12.5px;color:var(--mute);margin-top:14px;line-height:1.5}
.acc__fine svg{flex:none;margin-top:2px}
.acc__decline{display:block;margin:14px auto 0;border:0;background:none;color:var(--mute);font-size:13.5px;text-decoration:underline;text-underline-offset:3px}
.err{background:#fde8e6;color:var(--bad);border-radius:12px;padding:10px 14px;font-size:14px;margin-bottom:14px}
.cert{grid-column:1/-1;max-width:760px;margin:0 auto;background:#fff;color:var(--ink);border-radius:32px;padding:56px 56px 40px;text-align:center;box-shadow:0 50px 120px -40px rgba(0,0,0,.8)}
.cert__seal{width:84px;height:84px;margin:0 auto 18px;border-radius:50%;background:var(--ok);color:#fff;display:grid;place-items:center;box-shadow:0 0 0 10px var(--okbg);animation:pop .8s var(--ease) both .2s}
@keyframes pop{from{transform:scale(.4);opacity:0}}
.cert h2{font-size:clamp(48px,6vw,80px);margin:12px 0 14px}
.cert__p{color:var(--mute);font-size:17px;max-width:52ch;margin:0 auto}
.cert__p a{color:var(--o)}
.cert__dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin:30px 0;padding:22px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);text-align:left}
.cert__dl dt{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);margin-bottom:4px}
.cert__dl dd{font-weight:650}
.cert__sig{display:grid;justify-items:center;gap:6px}
.cert__sig img{max-height:90px;width:auto;max-width:320px}
.cert__sig small{color:var(--mute);font-size:13px;border-top:1px solid var(--ink);padding-top:6px;min-width:260px}
.cert__fp{margin-top:20px;font-size:12px;color:#9a948b}
.cert__fp code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px}
.cert--muted{padding:48px}
.cert--muted .kick{color:var(--mute)}
/* paper (estimate, invoice) */
.hero{position:relative;background:var(--ink);color:#fff;margin-top:-66px;padding:140px 0 170px;overflow:hidden;isolation:isolate}
.hero__bg{position:absolute;inset:0;z-index:-1;background:radial-gradient(760px 460px at 90% 0,color-mix(in srgb,var(--o) 36%,transparent),transparent 62%),repeating-linear-gradient(45deg,rgba(255,255,255,.018) 0 36px,transparent 36px 72px)}
.hero__bg .mark{height:150%;top:-20%;right:-4%}
.hero__h{font-size:clamp(56px,8vw,120px);margin:14px 0 14px;max-width:16ch}
.hero__p{color:rgba(255,255,255,.66);font-size:17px}
.bx--estimate .tb,.bx--invoice .tb{background:rgba(11,11,11,.35);border-color:rgba(255,255,255,.08);color:#fff}
.bx--estimate .tb.is-light,.bx--invoice .tb.is-light{background:rgba(245,244,241,.84);border-color:rgba(11,11,11,.07);color:var(--ink)}
.bx--estimate .tb:not(.is-light) .tb__brand>.lg,.bx--invoice .tb:not(.is-light) .tb__brand>.lg{opacity:0}
.bx--estimate .tb:not(.is-light) .tb__lgw,.bx--invoice .tb:not(.is-light) .tb__lgw{opacity:1}
.bx--estimate .tb:not(.is-light) .tb__doc,.bx--invoice .tb:not(.is-light) .tb__doc{color:rgba(255,255,255,.6);border-color:rgba(255,255,255,.18)}
.bx--estimate .tb:not(.is-light) .tb__doc b,.bx--invoice .tb:not(.is-light) .tb__doc b{color:#fff}
.bx--estimate .tb:not(.is-light) .pill,.bx--invoice .tb:not(.is-light) .pill{background:rgba(255,255,255,.12);color:#fff}
.paper{position:relative;max-width:980px;margin:-120px auto 0;background:#fff;border-radius:30px;padding:48px 52px;box-shadow:0 1px 0 rgba(0,0,0,.02),0 40px 100px -50px rgba(0,0,0,.45);overflow:hidden}
.paper__h{display:flex;justify-content:space-between;gap:32px;align-items:flex-start;padding-bottom:30px;border-bottom:1px solid var(--line)}
.paper__from .lg{height:36px;width:auto;margin-bottom:16px}
.paper__from address{font-style:normal;font-size:13.5px;color:var(--mute);line-height:1.6}
.paper__from address b{color:var(--ink)}
.due{text-align:right;display:grid;justify-items:end;gap:4px}
.due__k{font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mute)}
.due__v{font-size:clamp(54px,7vw,82px);font-variant-numeric:tabular-nums}
.due__d{font-size:14.5px;color:var(--mute);margin-bottom:6px}
.facts{display:grid;grid-template-columns:1.5fr repeat(4,minmax(0,1fr));gap:20px;padding:26px 0;border-bottom:1px solid var(--line)}
.facts dt{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);margin-bottom:6px}
.facts dd{font-size:14.5px;line-height:1.5}
.facts small{color:var(--mute)}
.lt{width:100%;border-collapse:collapse;margin:22px 0 8px}
.lt th{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);text-align:left;padding:10px 0;border-bottom:1.5px solid var(--ink)}
.lt td{padding:18px 0;border-bottom:1px solid var(--line);vertical-align:top;font-size:15px}
.lt td span{display:block;color:var(--mute);font-size:13.5px;line-height:1.5;margin-top:3px;max-width:56ch}
.lt td em{display:block;color:var(--ok);font-style:normal;font-size:13px;margin-top:3px}
.lt .n{text-align:right;white-space:nowrap;padding-left:18px;font-variant-numeric:tabular-nums}
.card .lt{margin:0}
.tot{margin:14px 0 0 auto;max-width:360px;display:grid;gap:10px}
.tot div{display:flex;justify-content:space-between;font-size:15px;color:var(--mute)}
.tot b{color:var(--ink);font-variant-numeric:tabular-nums;font-weight:600}
.tot__t{border-top:1.5px solid var(--ink);padding-top:12px;margin-top:4px}
.tot__t span,.tot__t b{color:var(--ink)!important;font-size:18px;font-weight:750!important}
.tot__b{background:color-mix(in srgb,var(--o) 9%,#fff);border-radius:12px;padding:12px 14px;margin:4px -14px 0}
.tot__b span{color:var(--ink)!important;font-weight:650}
.tot__b b{color:var(--o)!important;font-size:20px;font-weight:800!important}
.stamp{position:absolute;top:58px;right:-46px;transform:rotate(18deg);border:4px solid var(--ok);color:var(--ok);font:500 46px/1 "Thunder LC",sans-serif;letter-spacing:.12em;text-transform:uppercase;padding:8px 62px;opacity:.16;pointer-events:none}
.stamp--void{border-color:var(--bad);color:var(--bad)}
.hist{margin-top:30px;padding:22px;border-radius:20px;background:var(--soft);border:1px solid var(--line)}
.hist ul{list-style:none;padding:0;margin:0;display:grid;gap:12px}
.hist li{display:grid;grid-template-columns:30px minmax(0,1fr) auto;gap:12px;align-items:center}
.hist__i{width:30px;height:30px;border-radius:50%;background:var(--okbg);color:var(--ok);display:grid;place-items:center}
.hist li b{font-size:14.5px}.hist li small{display:block;color:var(--mute);font-size:13px}
.hist__a{font-variant-numeric:tabular-nums}
.pay{margin-top:34px;padding:34px;border-radius:26px;background:var(--ink);color:#fff;position:relative;overflow:hidden;isolation:isolate;scroll-margin-top:84px}
.pay::before{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(600px 300px at 100% 0,color-mix(in srgb,var(--o) 30%,transparent),transparent 60%)}
.pay__h h2{font-size:56px;margin-bottom:8px}
.pay__h p{color:rgba(255,255,255,.7);font-size:15.5px}
.pay__h b{color:#fff}
.pay__gw{margin-top:22px;display:flex;gap:12px;flex-wrap:wrap}
.pay__gw .btn{flex-wrap:wrap;row-gap:0;max-width:100%;white-space:normal;text-align:center;justify-content:center}
@media (max-width:640px){.pay__gw .btn{width:100%}}
.pay__gn{display:block;flex-basis:100%;font-size:12px;font-weight:600;opacity:.82;margin-top:2px}
.pay__cd{margin:16px 0 0;font-size:15px;color:rgba(255,255,255,.78)}
.pay__cd b{color:#fff}
.due__cd{display:block;font-size:13.5px;color:var(--ok);font-weight:650;margin:-2px 0 8px}
.tot .tot__cd{font-size:13.5px}
.tot .tot__cd span{max-width:28ch}
.tot .tot__cd b{color:var(--ok)}
.tot .tot__cd--t span,.tot .tot__cd--t b{color:var(--ink);font-weight:700}
.pay__grid{display:grid;gap:14px;margin-top:24px}
.pay__grid--2{grid-template-columns:repeat(2,minmax(0,1fr))}
.pay__grid--3{grid-template-columns:repeat(3,minmax(0,1fr))}
.pm{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:22px}
.pm--solo{margin-top:24px}
.pm__i{width:42px;height:42px;border-radius:12px;background:var(--o);color:#fff;display:grid;place-items:center;margin-bottom:14px}
.pm>b{display:block;font-size:17px;margin-bottom:8px}
.pm .md p{font-size:14.5px;color:rgba(255,255,255,.75);line-height:1.6}
.pm .md p+p{margin-top:8px}
.pm .md strong{color:#fff}
.pm .md a{color:#fff}
.terms{margin-top:30px;padding-top:24px;border-top:1px solid var(--line)}
/* footer */
.ft{padding:72px 0 120px;background:var(--paper)}
.acc+.ft{padding-top:72px}
.ft__in{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:40px;align-items:start}
.ft__brand .lg{height:30px;width:auto;margin-bottom:12px}
.ft__brand p{color:var(--mute);font-size:14px;max-width:30ch}
.ft__c{display:grid;gap:8px;font-size:14.5px}
.ft__c span{color:var(--mute)}
.ft__c a{display:inline-flex;gap:8px;align-items:center;text-decoration:none;font-weight:600}
.ft__c a:hover{color:var(--o)}
.ft__l{font-size:13px;color:var(--mute);line-height:1.6}
.ft__priv{display:inline-block;margin-top:10px;font-size:12px;color:#a59e93}
/* mobile bar */
.mbar{display:none}
/* dialog */
.dlg{border:0;border-radius:26px;padding:0;max-width:480px;width:calc(100% - 32px);box-shadow:0 40px 100px rgba(0,0,0,.35)}
.dlg::backdrop{background:rgba(11,11,11,.55);backdrop-filter:blur(3px)}
.dlg__in{padding:30px;position:relative}
.dlg__x{position:absolute;top:16px;right:16px;border:0;background:var(--paper);width:36px;height:36px;border-radius:50%;display:grid;place-items:center}
.dlg h3{font-size:44px;margin-bottom:10px}
.dlg p{color:var(--mute);font-size:15px;margin-bottom:18px}
.dlg__ft{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.toast{position:fixed;left:50%;bottom:28px;transform:translateX(-50%);background:var(--ink);color:#fff;padding:12px 18px;border-radius:14px;font-size:14px;z-index:90;box-shadow:0 20px 50px rgba(0,0,0,.35)}
/* responsive */
@media (max-width:1100px){.doc{grid-template-columns:1fr;gap:0}.rail{display:none}.acc__in{grid-template-columns:1fr;gap:40px}.cv--img .cv__grid{grid-template-columns:1fr}.cv__art{max-width:640px}}
@media (max-width:900px){.facts{grid-template-columns:repeat(2,minmax(0,1fr))}.facts>div:first-child{grid-column:1/-1}.opts--3,.opts--4{grid-template-columns:1fr}.sum{grid-template-columns:1fr}.sum__sched{grid-column:1;grid-row:auto}.ft__in{grid-template-columns:1fr;gap:26px}.tl{grid-auto-flow:row;gap:0}.tl::before{left:9px;right:auto;top:6px;bottom:6px;width:2px;height:auto;background:linear-gradient(180deg,var(--o),var(--line) 70%)}.tl li{padding:0 0 26px 40px}.tl__dot{top:2px}}
@media (max-width:640px){
.wrap,.tb__in{padding:0 16px}
body{font-size:15.5px}
.tb{height:60px}
.tb__doc,.tb [data-print] span{display:none}
.tb [data-print]{padding:9px}
.tb__cta{display:none}
.tb__brand .lg,.tb__lgw .lg{height:26px}
.cv{padding:120px 0 84px;margin-top:-60px;min-height:100svh}
.cv__h{font-size:clamp(58px,17vw,92px);margin:14px 0 20px}
.cv--img .cv__h{font-size:clamp(56px,16vw,88px)}
.cv__intro{font-size:17px}
.cv__meta{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:28px}
.cv__cta{gap:14px;margin-top:28px}
.cv__cta .btn{width:100%}
.cv__down{display:none}
.frame{transform:none}
.sec{padding:64px 0 16px;scroll-margin-top:62px}
.sec__h{gap:12px;margin-bottom:22px}
.sec__n{font-size:22px}
.sec__h h2{font-size:clamp(42px,12.5vw,58px)}
.md p,.md ul li,.md ol li{font-size:16px}
.md--cards ul{grid-template-columns:1fr}
.sec--why{padding:56px 22px 32px;margin:40px -16px;border-radius:26px}
.sec--why .md ul{grid-template-columns:1fr}
.opts--2{grid-template-columns:1fr}
.opt{padding:24px 20px 18px}
.opt__price{font-size:56px}
.adds{padding:4px 18px}
.add{grid-template-columns:minmax(0,1fr) auto;gap:6px 14px}
.add__p{grid-column:1;grid-row:2;font-size:14px;color:var(--mute)}
.add .sw{grid-column:2;grid-row:1/span 2}
.sum{padding:22px 20px;border-radius:22px}
.sum__total b{font-size:52px}
.acc{padding:80px 0 120px;margin-top:64px}
.acc__h{font-size:clamp(56px,16vw,84px)}
.acc__f{padding:22px;border-radius:24px}
.cert{padding:40px 22px 28px;border-radius:26px}
.hero{padding:116px 0 150px;margin-top:-60px}
.hero__h{font-size:clamp(50px,14vw,76px)}
.paper{padding:26px 20px;border-radius:24px;margin-top:-110px}
.paper__h{flex-direction:column;gap:22px}
.due{text-align:left;justify-items:start}
.due__v{font-size:58px}
.facts{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.lt thead{display:none}
.lt,.lt tbody,.lt tr,.lt td{display:block;width:100%}
.lt tr{padding:14px 0;border-bottom:1px solid var(--line);display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px 10px}
.lt td{border:0;padding:0}
.lt td:first-child{grid-column:1/-1;margin-bottom:6px}
.lt td.n{text-align:left;padding:0;font-size:14px}
.lt td.n::before{content:attr(data-th);display:block;font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--mute)}
.lt td.n:last-child{text-align:right}
.tot{max-width:none}
.tot__b{margin:4px 0 0}
.pay{padding:24px 20px;margin-left:-4px;margin-right:-4px}
.pay__h h2{font-size:46px}
.pay__grid--2,.pay__grid--3{grid-template-columns:1fr}
.stamp{top:24px;right:-58px;font-size:34px}
.mbar{display:flex;position:fixed;left:12px;right:12px;bottom:max(12px,env(safe-area-inset-bottom));z-index:70;align-items:center;justify-content:space-between;gap:12px;background:rgba(11,11,11,.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);color:#fff;border-radius:999px;padding:8px 8px 8px 22px;box-shadow:0 20px 50px rgba(0,0,0,.35);transform:translateY(140%);transition:transform .45s var(--ease)}
.mbar.on{transform:none}
.mbar span{display:block;font-size:11px;color:rgba(255,255,255,.6);letter-spacing:.08em;text-transform:uppercase;font-weight:700}
.mbar b{font-size:18px;font-variant-numeric:tabular-nums}
.mbar .btn{padding:13px 18px}
.ft{padding-bottom:110px}
}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}.rv{opacity:1;transform:none}.wd>span{transform:none}}
/* placeholders + print-only bits (1.7.3) */
.print-only,.print-inline{display:none}
mark.ph{background:#ffe680;color:#3b2f00;border-radius:4px;padding:0 3px;box-shadow:inset 0 0 0 1px #e0b400;font-weight:inherit}
.flag--ph{background:#fff7d1;color:#5c4700;border-bottom-color:#f0dc8a;justify-content:center;text-align:left}
.flag--ph span{max-width:900px}
/* print: a clean, designed PDF (1.7.3; pagination reworked 1.7.4). Page size, margins, running header and page
   numbers come from print_pages(); the cover is exactly page 1 and edge to edge; no interactive UI.
   1.7.4 break rules: no forced breaks between sections, so pages fill up; headings keep with what follows;
   a paragraph, list item, table row or small card is never split; large containers (an option with many
   lines, a long add-on list or schedule, a long Why grid) may break between their rows, never inside one;
   a heading and a section intro keep with the first block after them; a list that continues never strands a
   single item. */
@media print{
*,*::before,*::after{-webkit-print-color-adjust:exact;print-color-adjust:exact;animation:none!important;transition:none!important}
html{scroll-behavior:auto}
html,body{background:#fff}
body{font-size:10.5pt;line-height:1.5;overflow:visible}
.no-print,.tb,.rail,.mbar,.flag,.dlg,.acc__f,.acc__decline,.cv__down,.cv__cta,.toast,.opt__tick,.sw,.ft__priv,.acc__l{display:none!important}
.print-only{display:block}
.print-inline{display:inline}
.rv,.wd>span{opacity:1!important;transform:none!important}
.wrap{max-width:none;padding:0}
a{text-decoration:none;color:inherit}
p,li{orphans:3;widows:3}
p,li,tr,blockquote,dd,.tl li{break-inside:avoid}
/* a short section intro (up to ~2 lines, marked .intro-keep by keep_intro()) keeps with the first block after it;
   a longer intro may end a page on its own when the block after it cannot fit, rather than leaving a gap */
.sec__h+.rv>.md>p.intro-keep:first-child:not(:last-child),.sec__h+.rv:not(:last-child):has(>.md>p.intro-keep:only-child){break-after:avoid}
/* a list that has to continue never leaves its last item alone at the top of a page */
.md>ul>li:last-child:not(:first-child),.md>ol>li:last-child:not(:first-child){break-before:avoid}
h1,h2,h3,.sec__h,.card__k,.sum__k,.adds__h,.kick{break-after:avoid}
/* cover */
.cv{position:relative;margin:0;width:100%;height:279mm;min-height:0;padding:0 19mm 21mm;border-radius:0;display:flex;flex-direction:column;justify-content:flex-end;overflow:hidden;break-after:page;break-inside:avoid}
.cv__plogo{position:absolute;top:17mm;left:19mm}
.cv__plogo .lg{height:30px;width:auto}
.cv__grid{gap:24px}
.cv__h{font-size:76pt;margin:10px 0 18px;max-width:11ch}
.cv--img .cv__h{font-size:60pt}
.cv--img .cv__grid{grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr)}
.cv__intro{font-size:12.5pt;max-width:56ch}
.cv__meta{margin-top:26px;padding-top:18px;gap:16px;grid-template-columns:repeat(4,minmax(0,1fr))}
.cv__meta dd{font-size:11pt}
.frame{transform:none!important;box-shadow:none}
.mark{animation:none}
/* sections */
.doc{display:block;padding:0}
.sec{padding:12pt 0 3pt;break-inside:auto}
.sec__h{margin-bottom:7pt;gap:10px;padding-top:4px}
.sec__h h2,.ap__h,.pay__h h2,.cert h2{line-height:1}
.sec__n{font-size:17pt}
.sec__h h2{font-size:32pt}
.md p,.md li{font-size:10pt!important;line-height:1.5}
.cv__intro p,.cv__intro li{font-size:10.5pt!important}
.md ul,.md ol{gap:6px}
.md p+p,.md p+ul,.md ul+p{margin-top:9px}
.md h3{margin:14px 0 8px;font-size:9pt;break-after:avoid}
.md--lead p{font-size:10.5pt!important}
.md>ul:not(:has(>li:nth-child(4))),.md>ol:not(:has(>li:nth-child(4))){break-inside:avoid}
.md--cards ul{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.md--cards ul li{min-height:0;padding:10px 12px 10px 42px;border-radius:12px;font-size:10pt!important;line-height:1.4;break-inside:avoid}
.md--cards ul li::before{left:12px;width:20px;height:20px;margin-top:-10px;background-size:11px}
.md--cards ul li:hover,.opt:hover{transform:none;box-shadow:none}
.md--checks ul{gap:7px}
.md--checks ul li{padding:6px 12px 6px 32px;font-size:9.5pt!important;break-inside:avoid}
.md--checks ul li::before{left:10px;width:15px;height:15px;margin-top:-7.5px;background-size:9px}
.md--steps ol li{padding:0 0 12px 44px;min-height:32px;break-inside:avoid}
.md--steps ol:not(:has(>li:nth-child(7))){break-inside:avoid}
.md--steps ol li::before{width:28px;height:28px;font-size:10.5px;top:-3px}
.md--steps ol li:not(:last-child)::after{left:13.5px;top:27px}
.md--terms p,.md--terms li{font-size:9pt!important;line-height:1.45}
/* timeline: rows of three, each step kept whole */
.tl{display:grid;grid-auto-flow:row;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 18px;margin-top:2px}
.tl::before{display:none}
.tl li{position:relative;padding:22px 0 0;break-inside:avoid}
.tl li::before{content:"";position:absolute;left:16px;right:-18px;top:6px;height:2px;background:var(--line)}
.tl li:nth-child(3n)::before,.tl li:last-child::before{right:0}
.tl:has(>li:nth-child(4):last-child){grid-template-columns:repeat(4,minmax(0,1fr))}
.tl:has(>li:nth-child(4):last-child)>li:nth-child(3)::before{right:-18px}
.tl li:first-child::before{background:linear-gradient(90deg,var(--o),var(--line))}
.tl__dot{top:0;width:14px;height:14px;background:#fff}
.tl__when{font-size:7.5pt;margin-bottom:2px}
.tl b{font-size:10.5pt;margin-bottom:2px}
.tl p{font-size:9pt;line-height:1.45}
/* investment: small option cards, add-on rows and the totals box stay whole; very long ones may break between
   rows. Headings and section intros keep with the first block after them (rules under "sections"). */
.sec--investment>.sec__h{break-after:avoid}
.card{break-inside:avoid;padding:12px 16px;border-radius:14px;margin-bottom:12px}
.card .lt td{padding:7px 0}
.opts{gap:12px;margin:8pt 0 10pt;align-items:stretch}
.opts--2{grid-template-columns:repeat(2,minmax(0,1fr))}
.opts--3,.opts--4{grid-template-columns:1fr}
.opts--3 .opt__lines,.opts--4 .opt__lines{grid-template-columns:repeat(2,minmax(0,1fr));column-gap:26px}
.opt{padding:14px 16px 10px;border-radius:16px;box-shadow:none!important;break-inside:avoid;cursor:auto}
.opts--1 .opt:has(.opt__lines>li:nth-child(12)),.opts--2 .opt:has(.opt__lines>li:nth-child(12)),.opts--3 .opt:has(.opt__lines>li:nth-child(20)),.opts--4 .opt:has(.opt__lines>li:nth-child(20)){break-inside:auto;-webkit-box-decoration-break:clone;box-decoration-break:clone}
.opt>*,.opt__lines li{break-inside:avoid}
.opt__rec,.opt__name,.opt__tag,.opt__price,.opt__desc{break-after:avoid}
.opt.is-on{border:2px solid var(--o)}
.opt__rec{position:static;align-self:flex-start;margin:-3px 0 6px;font-size:7pt;padding:3px 9px}
.opt__name{font-size:20pt;padding-right:0}
.opt__tag{font-size:9.5pt;min-height:0;margin-top:3px}
.opt__price{font-size:27pt;margin:6px 0 0}
.opt__desc p{font-size:9.5pt!important}
.opt__lines{padding-top:7px;margin:7px 0 8px;gap:5px}
.opt__lines li{font-size:9.5pt;gap:10px}
.opt__lines li small{font-size:8.5pt;line-height:1.35}
.opt__sel{display:block;font-size:0;padding:6px;border-radius:999px;background:var(--paper);text-align:center}
.opt__sel::after{content:"Also available";font-size:8.5pt;font-weight:650;color:var(--mute)}
.opt.is-on .opt__sel{background:var(--o)}
.opt.is-on .opt__sel::after{content:"✓ Selected option";color:#fff}
.opts--1 .opt__sel{display:none}
.opts--1 .opt__lines{margin-bottom:0}
.adds{padding:2px 16px;border-radius:16px;margin-bottom:12pt;break-inside:avoid}
.adds:has(.add~.add~.add~.add~.add){break-inside:auto;-webkit-box-decoration-break:clone;box-decoration-break:clone}
.adds__h{padding:9px 0 7px}
.adds__h b{font-size:10.5pt}
.adds__h span{font-size:9pt}
.add{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:12px;padding:8px 0;cursor:auto;break-inside:avoid}
.add__t b{font-size:10pt}
.add__t small{font-size:8.5pt}
.adds:has(.add~.add~.add~.add) .add{padding:5px 0}
.adds:has(.add~.add~.add~.add) .add__t small{font-size:8pt;line-height:1.35}
.add__p{font-size:10pt;grid-column:auto;grid-row:auto;color:var(--ink)}
.add::after{content:"Optional";font-size:7pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);border:1px solid var(--line);border-radius:999px;padding:3px 9px;white-space:nowrap}
.add.is-on::after{content:"✓ Added";color:#fff;background:var(--o);border-color:var(--o)}
.sum{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:4px 30px;padding:16px 22px;border-radius:16px;box-shadow:none;break-inside:avoid}
.sum:has(.sch~.sch~.sch~.sch~.sch){break-inside:auto;-webkit-box-decoration-break:clone;box-decoration-break:clone}
.sch{break-inside:avoid}
.sum::before{right:-80px;top:-140px;width:320px;height:320px}
.sum__sched{grid-column:2;grid-row:1/span 2}
.sum__rows div{font-size:10pt}
.sum__total{padding-top:10px}
.sum__total b{font-size:28pt}
.sch{padding:7px 0;grid-template-columns:42px minmax(0,1fr) auto;gap:10px}
.sch__pct{font-size:11.5pt}
.sch__t b{font-size:10pt}.sch__t small{font-size:8.5pt}
.sum--light{margin-top:12pt}
.sum--light .sum__sched{grid-column:1/-1;grid-row:auto}
/* why: one dark card kept whole (a broken dark box would leave a dark slab at the page foot); 7+ cards may break between rows */
.sec--why{margin:10pt 0;padding:20px 22px 14px;border-radius:18px;break-inside:avoid}
.sec--why:has(.md li:nth-child(7)){break-inside:auto;-webkit-box-decoration-break:clone;box-decoration-break:clone}
.sec--why::before{background:radial-gradient(420px 260px at 100% 0,color-mix(in srgb,var(--o) 30%,transparent),transparent 60%)}
.sec--why .sec__h h2{font-size:28pt}
.sec--why .md ul{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.sec--why .md ul li{padding:12px 14px;border-radius:12px;font-size:9pt!important;line-height:1.45;break-inside:avoid}
.sec--why .md ul li strong{font-size:10.5pt;margin-bottom:3px}
.note{break-inside:avoid;margin-top:8pt;padding:10px 14px;border-radius:12px}
.note .md p{font-size:9.5pt!important}
/* accept: print shows "How to accept" (link + QR + lines for a wet signature), or the certificate */
.acc{background:none;color:var(--ink);padding:0;margin:8pt 0 0;overflow:visible;isolation:auto;break-inside:avoid}
.acc__glow{display:none}
.acc__in{display:block}
.ap{border:1.5px solid var(--ink);border-radius:16px;padding:12px 20px 10px;break-inside:avoid}
.ap__h{font-size:24pt;margin:3px 0 8px}
.ap__grid{display:grid;grid-template-columns:minmax(0,1fr) 104px;gap:20px;align-items:center}
.ap__on b{display:block;font-size:11pt}
.ap__on p{font-size:9.5pt;color:var(--mute);margin:2px 0 6px}
.ap__url{display:block;font:9pt/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all;color:var(--ink)}
.ap__qr svg{display:block;width:104px;height:104px}
.ap__sum{list-style:none;padding:0;margin:12px 0 0;display:flex;flex-wrap:wrap;gap:6px 26px;border-top:1px solid var(--line);padding-top:10px}
.ap__sum li{display:flex;gap:8px;font-size:10pt}
.ap__sum li span{color:var(--mute)}
.ap__paper{margin-top:12px;font-size:9.5pt;color:#3a3732}
.ap__paper>b{color:var(--ink)}
.ap__lines{display:grid;grid-template-columns:1.2fr 1.4fr .8fr;gap:18px;margin-top:18px}
.ap__lines span{font-size:8pt;color:var(--mute);letter-spacing:.06em;text-transform:uppercase}
.ap__lines i{display:block;border-top:1.2px solid var(--ink);margin-bottom:4px}
.cert{box-shadow:none;border:1.5px solid var(--line);padding:22px;max-width:none;break-inside:avoid}
.cert h2{font-size:30pt}
.cert__seal{width:56px;height:56px;box-shadow:0 0 0 6px var(--okbg);animation:none}
.cert__dl{margin:16px 0;padding:14px 0}
/* estimate / invoice */
.hero{margin:0;padding:22pt 26pt 22pt;border-radius:16px;break-inside:avoid}
.hero__h{font-size:38pt;margin:8px 0 8px;max-width:20ch}
.hero__p{font-size:10.5pt}
.paper{margin:12pt 0 0;padding:0;border:0;border-radius:0;box-shadow:none;max-width:none;overflow:visible}
.paper__h{flex-direction:row;gap:24px;padding-bottom:12pt;break-inside:avoid}
.paper__from .lg{height:28px;margin-bottom:8px}
.paper__from address{font-size:9pt}
.due{text-align:right;justify-items:end}
.due__v{font-size:38pt}
.due__d{font-size:9.5pt}
.facts{grid-template-columns:1.5fr repeat(4,minmax(0,1fr));gap:14px;padding:12pt 0;break-inside:avoid}
.facts>div:first-child{grid-column:auto}
.facts dd{font-size:9.5pt}
.lt{margin:10pt 0 4pt}
.lt thead{display:table-header-group}
.lt th{padding:6px 0}
.lt td{padding:8px 0;font-size:10pt}
.lt td span{font-size:8.5pt}
.lt tr{break-inside:avoid}
.tot{break-inside:avoid;gap:6px}
.tot__b{margin:4px 0 0}
.tot div{font-size:10pt}
.stamp{top:30px;right:-40px;font-size:34px}
.hist{break-inside:avoid;margin-top:14pt;padding:12px 16px}
.hist li b{font-size:10pt}
.pay{break-inside:avoid;margin-top:14pt;padding:18px 20px;border-radius:16px}
.pay__h h2{font-size:28pt}
.pay__h p{font-size:10pt}
.pay__grid{margin-top:12px;gap:10px}
.pay__grid--2{grid-template-columns:repeat(2,minmax(0,1fr))}
.pay__grid--3{grid-template-columns:repeat(3,minmax(0,1fr))}
.pm{padding:12px 14px;border-radius:12px}
.pm__i{width:30px;height:30px;border-radius:9px;margin-bottom:8px}
.pm .md p{font-size:9pt!important}
.ponl{display:flex;gap:14px;align-items:center;margin-top:12px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12)}
.ponl__qr svg{display:block;width:74px;height:74px;border-radius:6px}
.ponl b{display:block;font-size:10pt}
.ponl span{display:block;font:8.5pt/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;color:rgba(255,255,255,.75);word-break:break-all}
.terms{break-inside:avoid;margin-top:10pt;padding-top:8pt}
/* footer: a compact contact block closing the last page */
.ft,.acc+.ft{padding:9pt 0 0;margin-top:10pt;background:none;border-top:1.5px solid var(--ink);break-inside:avoid;break-before:avoid}
.ft__in{grid-template-columns:1.2fr 1fr 1fr;gap:20px}
.ft__brand .lg{height:24px;margin-bottom:6px}
.ft__brand p,.ft__c,.ft__l{font-size:8.5pt}
.ft__c{gap:3px}
.ft__c a{gap:6px}
}
CSS;
		return $css;
	}

	/* ---------------------------------------------------------------- JS */

	protected static function js() {
		return <<<'JS'
(function(){
'use strict';
var C=window.AXB||{},d=document,html=d.documentElement;
html.classList.remove('no-js');
function $(s,r){return (r||d).querySelector(s);}
function $$(s,r){return [].slice.call((r||d).querySelectorAll(s));}
function money(n){n=Math.round((Number(n)||0)*100)/100;return (n<0?'−':'')+'$'+Math.abs(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function money0(n){n=Math.round((Number(n)||0)*100)/100;return '$'+n.toLocaleString('en-US',{minimumFractionDigits:n%1?2:0,maximumFractionDigits:2});}
function toast(t){var x=d.createElement('div');x.className='toast';x.setAttribute('role','status');x.textContent=t;d.body.appendChild(x);setTimeout(function(){x.remove();},3600);}
/* reveal on scroll */
var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
requestAnimationFrame(function(){d.body.classList.add('ready');});
if('IntersectionObserver' in window&&!reduce){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});},{rootMargin:'0px 0px -8% 0px',threshold:.06});$$('.rv').forEach(function(el){io.observe(el);});}
else{$$('.rv').forEach(function(el){el.classList.add('in');});}
window.addEventListener('beforeprint',function(){$$('.rv').forEach(function(el){el.classList.add('in');});d.body.classList.add('ready');});
/* top bar: dark over the cover / hero, light after */
var tb=$('#tb'),cover=$('#cover')||$('.hero');
if(tb&&cover&&'IntersectionObserver' in window){new IntersectionObserver(function(es){var e=es[0],over=e.isIntersecting;if(C.type==='proposal'){tb.classList.toggle('is-dark',over);}else{tb.classList.toggle('is-light',!over);}},{rootMargin:'-66px 0px 0px 0px',threshold:0}).observe(cover);}
/* contents scrollspy */
var links=$$('[data-toc]');
if(links.length&&'IntersectionObserver' in window){var spy=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){links.forEach(function(a){a.classList.toggle('on',a.getAttribute('data-toc')===e.target.id);});}});},{rootMargin:'-40% 0px -55% 0px'});links.forEach(function(a){var t=d.getElementById(a.getAttribute('data-toc'));if(t)spy.observe(t);});}
/* mobile accept bar */
var mbar=$('#mbar'),accEl=$('#accept');
if(mbar){var showBar=function(){var y=window.scrollY,h=window.innerHeight,a=accEl?accEl.getBoundingClientRect().top:1e9;mbar.classList.toggle('on',y>h*.6&&a>h*.85);};window.addEventListener('scroll',showBar,{passive:true});showBar();}
/* print */
$$('[data-print]').forEach(function(b){b.addEventListener('click',function(){window.print();});});
if(window.AXHQR){$$('[data-qr]').forEach(function(el){try{el.innerHTML=window.AXHQR.svg(el.getAttribute('data-qr'),{quiet:2,fg:'#0b0b0b'});}catch(x){}});}
/* printed from the Hub's preview frame: name the PDF after the document, not the Hub */
var ptop=null;try{if(window.top!==window&&window.top.document)ptop=window.top.document;}catch(x){ptop=null;}
if(ptop){window.addEventListener('beforeprint',function(){if(ptop.__axhTitle==null)ptop.__axhTitle=ptop.title;ptop.title=d.title;});window.addEventListener('afterprint',function(){if(ptop.__axhTitle!=null){ptop.title=ptop.__axhTitle;ptop.__axhTitle=null;}});}
/* totals: mirrors AXH_Bill::calc() */
function lineNet(l){var a=Math.round((Number(l.qty)||0)*(Number(l.rate)||0)*100)/100,dd=l.disc_type==='amt'?Math.min(a,Number(l.disc)||0):Math.round(a*(Number(l.disc)||0))/100;return [a,dd,Math.round((a-dd)*100)/100];}
function calc(lines){var sub=0,tb2=0;lines.forEach(function(l){var n=lineNet(l)[2];sub+=n;if(l.taxable)tb2+=n;});sub=Math.round(sub*100)/100;var dv=Number(C.dval)||0,disc=C.dtype==='amt'?Math.min(sub,dv):Math.round(sub*Math.min(100,dv))/100;disc=Math.max(0,Math.round(disc*100)/100);var f=sub>0?(sub-disc)/sub:0,tax=C.taxOn?Math.round(tb2*f*(Number(C.taxRate)||0))/100:0;return {sub:sub,disc:disc,tax:tax,total:Math.round((sub-disc+tax)*100)/100};}
var sel=C.selected||'',adds=C.chosen?C.chosen.slice():(C.addons||[]).filter(function(a){return a.on;}).map(function(a){return a.id;});
function current(){var ls=(C.lines||[]).slice();(C.options||[]).forEach(function(o){if(o.id===sel)ls=ls.concat(o.lines||[]);});(C.addons||[]).forEach(function(a){if(adds.indexOf(a.id)>-1)ls.push(a);});return ls;}
function paint(bump){var t=calc(current());$$('[data-total-out]').forEach(function(el){el.textContent=money(t.total);if(bump&&el.classList.contains('th')){el.classList.remove('bump');void el.offsetWidth;el.classList.add('bump');}});
var o=function(k,v){var el=$('[data-out="'+k+'"]');if(el)el.textContent=(k==='disc'?'−':'')+money(v);};o('sub',t.sub);o('disc',t.disc);o('tax',t.tax);
var dr=$('[data-row="disc"]');if(dr)dr.hidden=!(t.disc>0);
var acc2=0,sc=C.schedule||[];sc.forEach(function(s,i){var amt=i===sc.length-1?Math.round((t.total-acc2)*100)/100:Math.round(t.total*s.pct)/100;acc2+=amt;$$('[data-sched="'+i+'"]').forEach(function(el){el.textContent=money(amt);});});
var on=(C.options||[]).filter(function(x){return x.id===sel;})[0];$$('[data-sel-row]').forEach(function(sr){sr.hidden=!on||(C.options||[]).length<2;});if(on)$$('[data-sel-name]').forEach(function(sn){sn.textContent=on.name;});
var an=(C.addons||[]).filter(function(a){return adds.indexOf(a.id)>-1;}).map(function(a){return a.name;});$$('[data-add-row]').forEach(function(ar){ar.hidden=!an.length;});$$('[data-add-names]').forEach(function(ae){ae.textContent=an.join(', ');});
return t;}
$$('.opt').forEach(function(lab){lab.addEventListener('click',function(e){if(C.locked&&!C.preview)return;var id=lab.getAttribute('data-opt');if(id===sel)return;sel=id;$$('.opt').forEach(function(x){var me=x===lab;x.classList.toggle('is-on',me);var s=$('.opt__sel',x);if(s)s.textContent=me?'Selected':'Select';var r=$('input',x);if(r)r.checked=me;});paint(true);});});
$$('.add .sw').forEach(function(sw){sw.addEventListener('change',function(){var id=sw.value,i=adds.indexOf(id);if(sw.checked&&i<0)adds.push(id);if(!sw.checked&&i>-1)adds.splice(i,1);sw.closest('.add').classList.toggle('is-on',sw.checked);paint(true);});});
if((C.options||[]).length||(C.addons||[]).length)paint(false);
/* signature: type or draw (same pad as Hub onboarding) */
var mode='type',pad=null,nameIn=$('#acc-name'),pv=$('#sig-preview');
function sigPad(cv){var strokes=[],cur=null,ctx=cv.getContext('2d'),w=0,h=0;function size(){var r=cv.getBoundingClientRect(),dpr=Math.max(1,window.devicePixelRatio||1);w=r.width;h=r.height;cv.width=Math.round(w*dpr);cv.height=Math.round(h*dpr);ctx.setTransform(dpr,0,0,dpr,0,0);draw(ctx,w,h,2.4);}
function draw(c,W,H,lw){c.clearRect(0,0,W,H);c.lineCap='round';c.lineJoin='round';c.strokeStyle='#0b0b0b';c.lineWidth=lw;strokes.forEach(function(s){if(!s.length)return;c.beginPath();c.moveTo(s[0][0]*W,s[0][1]*H);if(s.length===1)c.lineTo(s[0][0]*W+.1,s[0][1]*H+.1);for(var i=1;i<s.length-1;i++){var mx=(s[i][0]+s[i+1][0])/2*W,my=(s[i][1]+s[i+1][1])/2*H;c.quadraticCurveTo(s[i][0]*W,s[i][1]*H,mx,my);}if(s.length>1)c.lineTo(s[s.length-1][0]*W,s[s.length-1][1]*H);c.stroke();});}
function pt(e){var r=cv.getBoundingClientRect();return [Math.min(1,Math.max(0,(e.clientX-r.left)/r.width)),Math.min(1,Math.max(0,(e.clientY-r.top)/r.height))];}
cv.addEventListener('pointerdown',function(e){e.preventDefault();try{cv.setPointerCapture(e.pointerId);}catch(x){}cur=[pt(e)];strokes.push(cur);draw(ctx,w,h,2.4);});
cv.addEventListener('pointermove',function(e){if(!cur)return;e.preventDefault();var evs=e.getCoalescedEvents?e.getCoalescedEvents():[e];(evs.length?evs:[e]).forEach(function(x){cur.push(pt(x));});draw(ctx,w,h,2.4);});
function end(){cur=null;}cv.addEventListener('pointerup',end);cv.addEventListener('pointercancel',end);
size();window.addEventListener('resize',size);
return {resize:size,clear:function(){strokes=[];draw(ctx,w,h,2.4);},length:function(){var L=0;strokes.forEach(function(s){for(var i=1;i<s.length;i++)L+=Math.hypot((s[i][0]-s[i-1][0])*w,(s[i][1]-s[i-1][1])*h);});return L;},png:function(){var W=900,H=Math.max(160,Math.min(600,Math.round(900*(h/(w||1)))||240)),c=d.createElement('canvas');c.width=W;c.height=H;draw(c.getContext('2d'),W,H,2.4*W/(w||1));return c.toDataURL('image/png');}};}
if(nameIn&&pv){nameIn.addEventListener('input',function(){var v=nameIn.value.trim();pv.textContent=v||'Your signature';pv.classList.toggle('is-empty',!v);});}
$$('[data-sig]').forEach(function(b){b.addEventListener('click',function(){mode=b.getAttribute('data-sig');$$('[data-sig]').forEach(function(x){x.classList.toggle('is-on',x===b);x.setAttribute('aria-selected',x===b?'true':'false');});$('[data-sig-type]').hidden=mode!=='type';$('[data-sig-draw]').hidden=mode!=='draw';if(mode==='draw'){if(!pad)pad=sigPad($('#sig-pad'));else pad.resize();}});});
var clr=$('[data-sig-clear]');if(clr)clr.addEventListener('click',function(){if(pad)pad.clear();});
function err(form,msg){var e=$('.err',form);if(!e){e=d.createElement('div');e.className='err';e.setAttribute('role','alert');form.insertBefore(e,form.firstChild);}e.textContent=msg;e.scrollIntoView({behavior:reduce?'auto':'smooth',block:'center'});}
function post(path,body){return fetch(C.rest+path,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'omit',body:JSON.stringify(body)}).then(function(r){return r.json().catch(function(){return {};}).then(function(j){if(!r.ok)throw new Error(j&&j.message?j.message:'Something went wrong. Please try again.');return j;});});}
var form=$('#accept-form');
if(form){form.addEventListener('submit',function(e){e.preventDefault();if(C.preview||!C.decide)return;var name=nameIn.value.trim().replace(/\s+/g,' ');
if(name.length<2){err(form,'Type your full name.');nameIn.focus();return;}
if(mode==='draw'&&(!pad||pad.length()<40)){err(form,'Please draw your signature in the box, or switch to Type.');return;}
if(!$('#acc-agree').checked){err(form,'Tick the box to confirm you agree.');return;}
var btn=$('#acc-go');btn.disabled=true;btn.textContent='Accepting…';
post('/accept',{name:name,agree:true,method:mode,signature:mode==='draw'?pad.png():'',option:sel,addons:adds,hash:C.hash}).then(function(){btn.textContent='Accepted ✓';toast('Accepted. Thank you!');setTimeout(function(){location.reload();},900);}).catch(function(x){btn.disabled=false;btn.innerHTML='Accept '+C.label+' · <span data-total-out>'+money(calc(current()).total)+'</span>';err(form,x.message);});});}
var dlg=$('#decline-dlg');
$$('[data-decline]').forEach(function(b){b.addEventListener('click',function(){if(C.preview||!C.decide)return;if(dlg&&dlg.showModal)dlg.showModal();else if(confirm('Decline this '+C.label+'?'))send();});});
function send(){var b=$('[data-decline-send]');if(b)b.disabled=true;post('/decline',{reason:($('#decline-reason')||{}).value||''}).then(function(){if(dlg&&dlg.close)dlg.close();toast('Thanks for letting us know.');setTimeout(function(){location.reload();},900);}).catch(function(x){if(b)b.disabled=false;toast(x.message);});}
var ds=$('[data-decline-send]');if(ds)ds.addEventListener('click',send);
$$('[data-pay]').forEach(function(b){b.addEventListener('click',function(){if(C.preview)return;b.disabled=true;post('/pay',{gateway:b.getAttribute('data-pay')}).then(function(j){if(j.redirect)location.href=j.redirect;}).catch(function(x){b.disabled=false;toast(x.message);});});});
if(C.preview){$$('a[href^="http"]').forEach(function(a){a.target='_blank';});}
})();
JS;
	}

	/* ------------------------------------------------------------- email */

	/** The customer's email: a branded card with one clear button to their page. */
	public static function email( $doc, $cust, $message = '', $kind = 'send' ) {
		$biz   = AXH_Bill::business();
		$type  = $doc['type'];
		$label = AXH_Bill::type_label( $type );
		$url   = AXH_Bill::public_url( $doc );
		$first = $cust && $cust['contact_name'] ? strtok( $cust['contact_name'], ' ' ) : '';
		$cname = $cust ? ( $cust['company'] ? $cust['company'] : $cust['contact_name'] ) : '';
		$inv   = 'invoice' === $type;
		$rem   = 'reminder' === $kind;
		$doc   = apply_filters( 'axh_bill_display_doc', $doc, array( 'email' => true ) ); // 1.21.0
		$amt   = $inv ? max( 0, (float) $doc['balance'] ) : (float) $doc['total'];
		$cdl   = $inv && ! empty( $doc['_cd']['cd'] ) && 'card' === $doc['_cd']['view'] && $doc['_cd']['discount'] > 0.004 ? self::money2( $doc['_cd']['cash'] ) . ' by check, cash or Zelle' : '';
		$ttl   = ( 'proposal' === $type && ! empty( $doc['content']['cover']['title'] ) ) ? $doc['content']['cover']['title'] : $doc['title'];
		if ( $inv ) {
			$subject = $rem ? 'Reminder: invoice ' . $doc['number'] . ' (' . self::money2( $amt ) . ') is past due' : 'Invoice ' . $doc['number'] . ' from ' . $biz['name'] . ' — ' . self::money2( $amt ) . ( $doc['due_date'] ? ' due ' . self::date( $doc['due_date'], 'M j' ) : '' );
			$cta     = 'View invoice & how to pay';
			$lead    = $rem ? 'A friendly reminder that invoice ' . $doc['number'] . ' was due ' . self::date( $doc['due_date'] ) . '. If you have already sent payment, thank you, and please ignore this.' : 'Here is your invoice from ' . $biz['name'] . '. You can see the details and how to pay at the link below.';
		} else {
			$subject = ( $rem ? 'Reminder: ' : '' ) . 'Your ' . strtolower( $label ) . ' from ' . $biz['name'] . ( $ttl ? ': ' . $ttl : ' (' . $doc['number'] . ')' );
			$cta     = 'Review your ' . strtolower( $label );
			$lead    = 'proposal' === $type ? 'Your proposal is ready. It walks through what we heard, what we will build, the timeline and the investment. You can choose an option and accept online in a minute.' : 'Your estimate is ready. Review the details and accept online when you are ready.';
		}
		$e    = 'esc_html';
		$font = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
		$o    = $biz['accent'];
		$msg  = trim( (string) $message );
		$h    = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>' . $e( $subject ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f5f4f1;">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $e( $label . ' ' . $doc['number'] . ' · ' . self::money2( $amt ) . ( $ttl ? ' · ' . $ttl : '' ) ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f4f1;"><tr><td align="center" style="padding:28px 12px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">'
			. '<tr><td style="background:#0b0b0b;border-radius:20px 20px 0 0;padding:26px 32px;">'
			. '<span style="' . $font . 'font-size:22px;font-weight:800;letter-spacing:.08em;color:#ffffff;">ADAPT<span style="color:' . $e( $o ) . ';">EXEC</span></span></td></tr>'
			. '<tr><td style="background:#0b0b0b;padding:0 32px 34px;">'
			. '<div style="' . $font . 'font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:' . $e( $o ) . ';">' . $e( $label . ' · ' . $doc['number'] ) . '</div>'
			. '<div style="' . $font . 'font-size:30px;line-height:1.15;font-weight:800;color:#ffffff;margin-top:10px;letter-spacing:-.01em;">' . $e( $ttl ? $ttl : $label . ' for ' . $cname ) . '</div>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:22px;"><tr>'
			. '<td style="' . $font . 'padding-right:28px;"><div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8e8a84;font-weight:700;">' . ( $inv ? 'Amount due' : 'Total' ) . '</div><div style="font-size:26px;font-weight:800;color:#ffffff;margin-top:4px;">' . $e( self::money2( $amt ) ) . '</div>' . ( $cdl ? '<div style="font-size:12.5px;color:#c9c5be;margin-top:4px;">or ' . $e( $cdl ) . '</div>' : '' ) . '</td>'
			. '<td style="' . $font . '"><div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8e8a84;font-weight:700;">' . ( $inv ? 'Due' : 'Valid until' ) . '</div><div style="font-size:16px;font-weight:700;color:#ffffff;margin-top:8px;">' . $e( self::date( $inv ? $doc['due_date'] : $doc['expiry_date'], 'M j, Y' ) ) . '</div></td>'
			. '</tr></table></td></tr>'
			. '<tr><td style="background:' . $e( $o ) . ';height:4px;line-height:4px;font-size:0;">&nbsp;</td></tr>'
			. '<tr><td style="background:#ffffff;padding:32px;border:1px solid #e3e0d9;border-top:0;border-radius:0 0 20px 20px;">'
			. '<p style="' . $font . 'margin:0 0 14px;font-size:16px;line-height:1.6;color:#1f1d1a;">' . ( $first ? 'Hi ' . $e( $first ) . ',' : 'Hello,' ) . '</p>';
		if ( $msg ) {
			foreach ( preg_split( "/\n\s*\n/", $msg ) as $para ) {
				$h .= '<p style="' . $font . 'margin:0 0 14px;font-size:16px;line-height:1.6;color:#1f1d1a;">' . nl2br( $e( trim( $para ) ) ) . '</p>';
			}
		} else {
			$h .= '<p style="' . $font . 'margin:0 0 14px;font-size:16px;line-height:1.6;color:#1f1d1a;">' . $e( $lead ) . '</p>';
		}
		$h .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 18px;"><tr><td style="border-radius:999px;background:' . $e( $o ) . ';">'
			. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 28px;' . $font . 'font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;">' . $e( $cta ) . ' &rarr;</a></td></tr></table>'
			. '<p style="' . $font . 'margin:0 0 22px;font-size:12.5px;line-height:1.5;color:#8e8a84;word-break:break-all;">Or open this link: <a href="' . esc_url( $url ) . '" style="color:#8e8a84;">' . $e( $url ) . '</a></p>'
			. '<p style="' . $font . 'margin:0;font-size:15px;line-height:1.6;color:#1f1d1a;">Thank you,<br><b>' . $e( $biz['name'] ) . '</b></p>'
			. '</td></tr>'
			. '<tr><td style="padding:20px 8px;text-align:center;' . $font . 'font-size:12px;line-height:1.6;color:#8e8a84;">'
			. $e( $biz['name'] ) . ' · ' . $e( $biz['email'] ) . ( $biz['phone'] ? ' · ' . $e( $biz['phone'] ) : '' ) . ( $biz['website'] ? ' · ' . $e( $biz['website'] ) : '' )
			. '<br>This link is private to you. Reply to this email with any questions.</td></tr>'
			. '</table></td></tr></table></body></html>';
		$t = array( $first ? 'Hi ' . $first . ',' : 'Hello,', '', $msg ? $msg : $lead, '', $label . ' ' . $doc['number'] . ( $ttl ? ' — ' . $ttl : '' ), ( $inv ? 'Amount due: ' : 'Total: ' ) . self::money2( $amt ) . ( $cdl ? ' (or ' . $cdl . ')' : '' ), ( $inv ? 'Due: ' . self::date( $doc['due_date'] ) : 'Valid until: ' . self::date( $doc['expiry_date'] ) ), '', $cta . ':', $url, '', 'Thank you,', $biz['name'], $biz['email'] . ( $biz['phone'] ? ' · ' . $biz['phone'] : '' ) );
		return array( 'subject' => $subject, 'html' => $h, 'text' => implode( "\n", $t ) );
	}
}
