<?php
/**
 * The deck player: /deck/<slug>/ (signed in, for rehearsing and presenting)
 * and /s/<token>/ (a shared link: no login, nothing of the Hub exposed).
 *
 * Decks supplied by the AdaptExec Hub — Demo Sites & Decks plugin (v2: they
 * carry 'v' => 2 and a 'file') run the full-screen player that lives in that
 * plugin: 16:9 slides, presenter view with notes and timer, live demo frames,
 * video, print to PDF. A shared link gets the same slides with the speaker
 * notes removed. Anything else falls back to the original player below.
 *
 * Variables from AXH_Portal: $deck, $share (null on /deck/), $form, $rep_name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Link-preview card (Open Graph / Twitter) for both players below.
$axh_og = array(
	'title'       => ! empty( $share['title'] ) ? $share['title'] : $deck['title'] . ' · AdaptExec',
	'description' => ! empty( $deck['kicker'] )
		? $deck['kicker'] . ' — a short presentation from AdaptExec.'
		: 'A short presentation from AdaptExec: one platform built around how your business actually runs.',
	'url'         => ! empty( $share['token'] ) ? home_url( '/s/' . rawurlencode( $share['token'] ) . '/' ) : home_url( '/deck/' . rawurlencode( $deck['slug'] ) . '/' ),
	'type'        => 'website',
);

$axh_v2_data = null;
if ( ! empty( $deck['v'] ) && 2 === (int) $deck['v'] && ! empty( $deck['file'] ) && defined( 'AXHC_URL' ) && defined( 'AXHC_DIR' )
	&& is_readable( $deck['file'] ) && is_readable( AXHC_DIR . 'decks/player/deck.js' ) ) {
	$axh_v2_data = json_decode( (string) file_get_contents( $deck['file'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $axh_v2_data ) || empty( $axh_v2_data['slides'] ) || ! is_array( $axh_v2_data['slides'] ) ) {
		$axh_v2_data = null;
	}
}

if ( $axh_v2_data ) :
	$axh_public = ! empty( $share );
	if ( $axh_public ) {
		// Talking points and discovery questions are for the representative.
		foreach ( $axh_v2_data['slides'] as $axh_i => $axh_s ) {
			if ( is_array( $axh_s ) ) {
				unset( $axh_v2_data['slides'][ $axh_i ]['notes'] );
			}
		}
	}
	$axh_p_url  = AXHC_URL . 'decks/player/';
	$axh_p_ver  = (string) @filemtime( AXHC_DIR . 'decks/player/deck.js' ) . '.' . (string) @filemtime( AXHC_DIR . 'decks/player/deck.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$axh_font   = content_url( 'uploads/2026/03/Thunder-MediumLC.ttf' );
	$axh_title  = ! empty( $share['title'] ) ? $share['title'] . ' · ' . $deck['title'] : $deck['title'];
	$axh_config = array(
		'deck'     => $axh_v2_data,
		'slug'     => $deck['slug'],
		'mode'     => $axh_public ? 'share' : 'rep',
		'token'    => $axh_public ? (string) ( $share['token'] ?? '' ) : '',
		'ping'     => $axh_public ? rest_url( AXH_Rest::NS . '/public/share-event' ) : '',
		'vk'       => $axh_public && ! empty( $view_key ) ? (string) $view_key : '',
		'preview'  => $axh_public && ! empty( $view_preview ),
		'prospect' => $axh_public ? (string) ( $share['prospect'] ?? '' ) : '',
		'assets'   => AXHC_URL . 'decks/',
		'logo'     => AXHC_URL . 'decks/img/adaptexec-logo-white.svg',
		'cta'      => array(
			'url'     => $form ? $form['url'] : '',
			'label'   => ! empty( $share['cta_label'] ) ? $share['cta_label'] : 'Tell us about your business',
			'rep'     => (string) $rep_name,
			'repLine' => 'Your AdaptExec representative',
			'phone'   => '(602) 584-0614',
			'email'   => 'support@adaptexec.com',
			'contact' => home_url( '/contact-us/' ),
		),
	);
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0b0b0b">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $axh_title ); ?> · AdaptExec</title>
	<?php AXH_Portal::share_tags( $axh_og ); ?>
	<?php if ( function_exists( 'wp_site_icon' ) ) { wp_site_icon(); } ?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
	<style>@font-face{font-family:'Thunder';src:url(<?php echo esc_url( $axh_font ); ?>) format('truetype');font-weight:500;font-style:normal;font-display:swap}</style>
	<link rel="stylesheet" href="<?php echo esc_url( $axh_p_url . 'deck.css?ver=' . rawurlencode( $axh_p_ver ) ); ?>">
</head>
<body class="axd">
<div class="axd-loading"><div><i></i>Loading presentation…</div></div>
<script>window.AXD = <?php echo wp_json_encode( $axh_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;</script>
<script src="<?php echo esc_url( $axh_p_url . 'deck.js?ver=' . rawurlencode( $axh_p_ver ) ); ?>"></script>
</body>
</html>
	<?php
	return;
endif;

// The original player has no view tracking of its own: count the page view.
if ( ! empty( $share ) && ! empty( $view_key ) ) {
	AXH_DB::count_share_view( $share['id'] );
}

/*
 * Original player (slides/*.json in this plugin). Slide screenshots live in
 * <decks>/_shared/shots/: use the first deck source that actually has them.
 */
$axh_uploads = wp_get_upload_dir();
$axh_base    = AXH_URL . 'decks/';
foreach ( (array) apply_filters(
	'axh_deck_sources',
	array(
		array( 'dir' => AXH_DIR . 'decks/', 'url' => AXH_URL . 'decks/' ),
		array(
			'dir' => trailingslashit( $axh_uploads['basedir'] ) . 'adaptexec-decks/',
			'url' => trailingslashit( $axh_uploads['baseurl'] ) . 'adaptexec-decks/',
		),
	)
) as $axh_src ) {
	if ( ! empty( $axh_src['dir'] ) && is_dir( $axh_src['dir'] . '_shared/shots' ) ) {
		$axh_base = $axh_src['url'];
		break;
	}
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0D0E10">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( ! empty( $share['title'] ) ? $share['title'] : $deck['title'] ); ?> · AdaptExec</title>
	<?php
	AXH_Portal::share_tags( $axh_og );
	// Our tags above are the only share tags: keep Yoast's (and its canonical,
	// schema and site defaults, which describe the home page) off this route.
	add_filter( 'wpseo_frontend_presenters', '__return_empty_array' );
	wp_head();
	?>
	<style>.sl-titleshot img,.sl-figure img{background:#16181b}.sl-titleshot img[data-broken],.sl-figure img[data-broken]{visibility:hidden}</style>
</head>
<body class="sl-body">
<script>
window.AXS = {
	deck: <?php echo wp_json_encode( $deck['url'] ); ?>,
	base: <?php echo wp_json_encode( $axh_base ); ?>,
	logo: <?php echo wp_json_encode( AXH_URL . 'assets/img/Mask-group-27.svg' ); ?>,
	token: <?php echo wp_json_encode( $share['token'] ?? '' ); ?>,
	ping: <?php echo wp_json_encode( rest_url( AXH_Rest::NS . '/public/share-event' ) ); ?>,
	prospect: <?php echo wp_json_encode( $share['prospect'] ?? '' ); ?>,
	cta: {
		url: <?php echo wp_json_encode( $form ? $form['url'] : '' ); ?>,
		label: <?php echo wp_json_encode( ! empty( $share['cta_label'] ) ? $share['cta_label'] : 'Tell us about your business' ); ?>,
		rep: <?php echo wp_json_encode( $rep_name ); ?>,
		repLine: 'Your AdaptExec representative',
		phone: '(602) 584-0614'
	}
};
/* Screenshots that were never uploaded hide instead of showing a broken icon. */
document.addEventListener('error', function (e) {
	if (e.target && e.target.tagName === 'IMG') e.target.setAttribute('data-broken', '1');
}, true);
</script>
<?php wp_footer(); ?>
</body>
</html>
