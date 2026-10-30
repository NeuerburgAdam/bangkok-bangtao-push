<?php
/**
 * Plugin Name:       AdaptExec Sales Hub
 * Plugin URI:        https://adaptexec.com
 * Description:       Branded CRM, client discovery questionnaire, and presentation platform for AdaptExec sales representatives.
 * Version:           1.13.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AdaptExec
 * Author URI:        https://adaptexec.com
 * License:           GPL-2.0-or-later
 * Text Domain:       adaptexec-hub
 *
 * YOUR DATA SURVIVES REINSTALLING THIS PLUGIN.
 *
 * Leads, activity, discovery answers, forms, shares and sessions live in the
 * plugin's own wp_axh_* tables, and the settings live in ordinary options.
 * Deleting the plugin in WordPress removes only the files:
 *
 *   - there is deliberately no uninstall.php,
 *   - no register_uninstall_hook(),
 *   - nothing anywhere issues DROP TABLE or deletes an axh_ option,
 *   - deactivation only flushes rewrite rules.
 *
 * The same goes for onboarding records (1.6.0): signed agreements, W-9s and
 * signature images are encrypted files in wp-content/uploads/axh-private/,
 * which deleting the plugin never removes. They can only be decrypted with
 * this site's wp-config.php salts, so keep those salts when moving the site.
 *
 * Billing (1.7.0) — customers, catalog, proposals, estimates, invoices,
 * payments and payout batches — lives in its own wp_axh_* tables too
 * (customers, items, bdocs, bpay, bevents, cadjust, cbatch).
 *
 * Re-installing runs dbDelta, which adds new columns and leaves existing rows
 * alone. So delete-and-upload is a safe way to take an update. If the day ever
 * comes that the data really should go, it has to be removed by hand — that is
 * the intended trade.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AXH_VERSION', '1.13.0' );
define( 'AXH_FILE', __FILE__ );
define( 'AXH_DIR', plugin_dir_path( __FILE__ ) );
define( 'AXH_URL', plugin_dir_url( __FILE__ ) );
define( 'AXH_ROLE', 'axh_rep' );

require_once AXH_DIR . 'includes/modules.php';
require_once AXH_DIR . 'includes/class-axh-install.php';
require_once AXH_DIR . 'includes/class-axh-db.php';
require_once AXH_DIR . 'includes/class-axh-rest.php';
require_once AXH_DIR . 'includes/class-axh-portal.php';
require_once AXH_DIR . 'includes/class-axh-admin.php';
require_once AXH_DIR . 'includes/class-axh-flow.php'; // 1.8.0: follow-ups + pipeline signals
require_once AXH_DIR . 'includes/class-axh-p1.php';   // 1.9.0: link analytics, form upgrades, presenting logs
require_once AXH_DIR . 'includes/class-axh-app.php';  // 1.10.0: installable phone app + push alerts
require_once AXH_DIR . 'includes/class-axh-files.php'; // 1.10.0: private, encrypted files on a lead

register_activation_hook( __FILE__, array( 'AXH_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AXH_Install', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function axh_boot() {
	AXH_Install::maybe_upgrade();
	AXH_Portal::init();
	AXH_Rest::init();
	AXH_Admin::init();
	AXH_Team::init();   // includes/class-axh-admin.php
	AXH_Notify::init(); // includes/class-axh-admin.php
	AXH_Onboard::init(); // includes/class-axh-admin.php
	AXH_Bill::init();    // includes/modules.php (Billing, 1.7.0)
}
add_action( 'plugins_loaded', 'axh_boot' );

/**
 * Keep page caches (LiteSpeed in particular) away from everything the Hub
 * serves. These responses carry per-visitor nonces and live CRM data: a cached
 * /f/ form or /hub/ sign-in screen goes stale once its nonce expires ("This
 * form expired"), and a cached /wp-json/axh/v1/ response shows a rep an old
 * pipeline.
 */
function axh_nocache() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'AdaptExec Hub: per-user, nonce-bearing response' );
}

add_action(
	'template_redirect',
	function () {
		foreach ( array( 'axh_portal', 'axh_form', 'axh_share', 'axh_deck', 'axh_discovery', 'axh_style', 'axh_bill' ) as $axh_var ) {
			if ( get_query_var( $axh_var ) ) {
				axh_nocache();
				return;
			}
		}
	},
	1
);

add_filter(
	'rest_post_dispatch',
	function ( $result, $server, $request ) {
		if ( 0 === strpos( (string) $request->get_route(), '/axh/v1' ) ) {
			axh_nocache();
		}
		return $result;
	},
	10,
	3
);

/**
 * Convenience: is the current user an AdaptExec administrator (not a rep)?
 */
function axh_is_manager( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	return user_can( $user_id, 'axh_manage_all' );
}

/**
 * Convenience: is the current user a sales rep?
 */
function axh_is_rep( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	return user_can( $user_id, 'axh_use_hub' );
}

/**
 * Sensible autocomplete tokens so browsers can fill public forms fast.
 */
function axh_autocomplete( $key ) {
	$map = array(
		'name'    => 'name',
		'email'   => 'email',
		'phone'   => 'tel',
		'company' => 'organization',
		'website' => 'url',
		'city'    => 'address-level2',
		'state'   => 'address-level1',
	);
	return isset( $map[ $key ] ) ? $map[ $key ] : 'on';
}

/**
 * The portal's public URL.
 */
function axh_portal_url( $path = '' ) {
	$slug = AXH_Install::portal_slug();
	return home_url( '/' . $slug . '/' . ltrim( $path, '/' ) );
}

/**
 * A client's own discovery link. No login; the token is the credential.
 */
function axh_discovery_url( $token ) {
	return home_url( '/d/' . rawurlencode( $token ) . '/' );
}

/**
 * A client's own website style picker. No login; the token is the credential.
 */
function axh_style_url( $token ) {
	return home_url( '/style/' . rawurlencode( $token ) . '/' );
}
