<?php
/**
 * The app shell. Everything inside #axh-view is rendered by hub.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$axh_user    = wp_get_current_user();
$axh_manager = axh_is_manager();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#0D0E10">
	<title>AdaptExec Hub</title>
	<?php AXH_Portal::head(); // Only the Hub's own assets: no theme, page builder, chat or translate widgets. ?>
	<?php if ( class_exists( 'AXH_App' ) ) { AXH_App::head(); } // 1.10.0: install as an app ?>
</head>
<body class="axh-app-body">

<div class="axh-app" id="axh-app">

	<aside class="axh-side" id="axh-side">
		<a class="axh-brand" href="#/">
			<img src="<?php echo esc_url( AXH_URL . 'assets/img/Mask-group-27.svg' ); ?>" alt="AdaptExec">
			<span class="axh-brand__tag">Sales Hub</span>
		</a>

		<nav class="axh-nav" id="axh-nav"></nav>

		<div class="axh-side__foot">
			<div class="axh-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( substr( $axh_user->display_name, 0, 1 ) ) ); ?></div>
			<div class="axh-side__me">
				<strong><?php echo esc_html( $axh_user->display_name ); ?></strong>
				<span><?php echo $axh_manager ? 'Administrator' : 'Sales Representative'; ?></span>
			</div>
			<a class="axh-side__out" href="<?php echo esc_url( wp_logout_url( axh_portal_url() ) ); ?>" title="Sign out" aria-label="Sign out">
				<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
			</a>
		</div>
	</aside>

	<main class="axh-main">
		<header class="axh-top">
			<button class="axh-burger" id="axh-burger" aria-label="Menu">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
			</button>
			<div class="axh-search">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
				<input type="search" id="axh-search" placeholder="Search name, company, phone…" aria-label="Search your leads" autocomplete="off">
			</div>
			<div class="axh-top__spacer"></div>
			<button class="axh-bell" id="axh-bell" type="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
				<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
				<span class="axh-bell__n" id="axh-bell-n" hidden>0</span>
			</button>
			<div class="axh-notes" id="axh-notes" hidden role="dialog" aria-label="Notifications"></div>
			<a class="axh-btn axh-btn--ghost axh-hide-sm" href="#/present">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
				Present
			</a>
			<button class="axh-btn axh-btn--primary" id="axh-new-lead" aria-label="New lead">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
				<span class="axh-hide-sm">New lead</span>
			</button>
		</header>

		<div class="axh-view" id="axh-view">
			<div class="axh-boot"><span class="axh-spin"></span> Loading your hub…</div>
		</div>
	</main>

	<div class="axh-scrim" id="axh-scrim" hidden></div>
</div>

<div class="axh-modal" id="axh-modal" hidden>
	<div class="axh-modal__box" role="dialog" aria-modal="true"></div>
</div>

<div class="axh-present" id="axh-present" hidden></div>

<div class="axh-toasts" id="axh-toasts" aria-live="polite"></div>

<?php AXH_Portal::foot(); ?>
</body>
</html>
