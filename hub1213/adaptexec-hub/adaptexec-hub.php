<?php
/**
 * Plugin Name:       AdaptExec Sales Hub
 * Plugin URI:        https://adaptexec.com
 * Description:       Branded CRM, client discovery questionnaire, and presentation platform for AdaptExec sales representatives.
 * Version:           1.21.3
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

define( 'AXH_VERSION', '1.21.3' );
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
require_once AXH_DIR . 'includes/class-axh-p2.php';    // 1.15.0: people on a deal, tags, note edits, stage automations
require_once AXH_DIR . 'includes/class-axh-p3.php';    // 1.16.0: merge duplicates (undoable), message templates, mentions
require_once AXH_DIR . 'includes/class-axh-p4.php';    // 1.17.0: expected close dates, email open/click tracking
require_once AXH_DIR . 'includes/class-axh-p5.php';    // 1.18.0: repeating follow-ups, custom fields on deals
require_once AXH_DIR . 'includes/class-axh-p6.php';    // 1.19.0: deal health, focus list, insights
require_once AXH_DIR . 'includes/class-axh-p7.php';    // 1.20.0: sequences, round-robin form routing
require_once AXH_DIR . 'includes/class-axh-qbo.php';   // 1.21.0: QuickBooks Online, online payments, cash discount pricing

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
 * 1.21.2: AdaptExec's contact address is adam@adaptexec.com. Swap the old
 * support@ address in the Hub's own settings, once. Only settings change:
 * sent messages, notes and other records keep what they said.
 */
function axh_swap_contact_email() {
	if ( get_option( 'axh_mig_email_1212' ) ) {
		return;
	}
	global $wpdb;
	$swap = function ( $v ) use ( &$swap ) {
		if ( is_string( $v ) ) {
			return str_ireplace( 'support@adaptexec.com', 'adam@adaptexec.com', $v );
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$v[ $k ] = $swap( $x );
			}
		}
		return $v;
	};
	$done  = array();
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'axh\\_%' AND option_name NOT LIKE '\\_transient%' AND option_value LIKE '%support@adaptexec.com%'" ); // phpcs:ignore
	foreach ( (array) $names as $name ) {
		if ( 'axh_qbo' === $name ) {
			continue;
		}
		$old = get_option( $name );
		$new = $swap( $old );
		if ( $new !== $old ) {
			update_option( $name, $new );
			$done[] = $name;
		}
	}
	update_option( 'axh_mig_email_1212', array( 'at' => current_time( 'mysql' ), 'options' => $done ), false );
}
add_action( 'init', 'axh_swap_contact_email', 20 );

/**
 * 1.21.2: show adam@adaptexec.com instead of support@adaptexec.com across the
 * website: published pages and posts, Elementor designs (header, footer and
 * page layouts) and site settings. Addresses that send or receive mail (form
 * "send to" / "from" fields, the WordPress admin email, the mail sender) are
 * left alone and reported. Every value changed is kept first in
 * axh_email_swap_backup, so it can be put back. Administrators only; run with
 * apply=false first to see what would change.
 */
function axh_site_email_swap( $apply ) {
	global $wpdb;
	$from = 'support@adaptexec.com';
	$to   = 'adam@adaptexec.com';
	$like = '%' . $wpdb->esc_like( $from ) . '%';
	$skip = '/^(email_(to|from|reply_to|to_cc|to_bcc)(_\d+)?|from_email|mail_from|reply_to|to_email|recipients?|admin_email|smtp_.*)$/i';
	$kept = array();
	$walk = function ( $v, $path = '' ) use ( &$walk, $from, $to, $skip, &$kept ) {
		if ( is_string( $v ) ) {
			return str_ireplace( $from, $to, $v );
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				if ( is_string( $k ) && preg_match( $skip, $k ) && is_string( $x ) && false !== stripos( $x, $from ) ) {
					$kept[] = trim( $path . '.' . $k, '.' );
					continue; // sends or receives mail: leave it
				}
				$v[ $k ] = $walk( $x, $path . '.' . $k );
			}
		}
		return $v;
	};
	$report = array( 'posts' => array(), 'meta' => array(), 'options' => array(), 'kept' => array(), 'skipped' => array() );
	$backup = array();
	// Published posts, pages and Elementor templates (not revisions or drafts).
	$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_name, post_content, post_excerpt FROM {$wpdb->posts} WHERE ( post_content LIKE %s OR post_excerpt LIKE %s ) AND post_status IN ('publish','private','future') AND post_type NOT IN ('revision','customize_changeset','oembed_cache')", $like, $like ), ARRAY_A ); // phpcs:ignore
	foreach ( (array) $posts as $p ) {
		$c = str_ireplace( $from, $to, $p['post_content'] );
		$e = str_ireplace( $from, $to, $p['post_excerpt'] );
		$report['posts'][] = $p['post_type'] . ' ' . $p['ID'] . ' ' . $p['post_name'] . ' (' . ( substr_count( strtolower( $p['post_content'] . $p['post_excerpt'] ), $from ) ) . ')';
		if ( $apply ) {
			$backup[] = array( 'post', (int) $p['ID'], 'content', $p['post_content'], $p['post_excerpt'] );
			$wpdb->update( $wpdb->posts, array( 'post_content' => $c, 'post_excerpt' => $e ), array( 'ID' => (int) $p['ID'] ) );
			clean_post_cache( (int) $p['ID'] );
		}
	}
	// Their settings and layouts (Elementor data, SEO fields, …).
	$metas = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value, p.post_type, p.post_name FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_value LIKE %s AND p.post_status IN ('publish','private','future') AND p.post_type NOT IN ('revision','customize_changeset')", $like ), ARRAY_A ); // phpcs:ignore
	foreach ( (array) $metas as $m ) {
		$kept = array();
		$old  = $m['meta_value'];
		$new  = null;
		if ( preg_match( $skip, $m['meta_key'] ) ) {
			$report['kept'][] = 'meta ' . $m['post_id'] . ' ' . $m['meta_key'];
			continue;
		}
		if ( is_serialized( $old ) ) {
			$val = @unserialize( $old, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false === $val && 'b:0;' !== $old ) {
				$report['skipped'][] = 'meta ' . $m['post_id'] . ' ' . $m['meta_key'] . ' (unreadable)';
				continue;
			}
			$nv = $walk( $val );
			$new = $nv === $val ? $old : serialize( $nv ); // phpcs:ignore
		} elseif ( isset( $old[0] ) && ( '[' === $old[0] || '{' === $old[0] ) && null !== json_decode( $old, true ) ) {
			$val = json_decode( $old, true );
			if ( wp_json_encode( $val ) === $old ) {
				$nv  = $walk( $val );
				$new = $nv === $val ? $old : wp_json_encode( $nv ); // same encoding Elementor uses, so only the address changes
			} else {
				$walk( $val ); // find mail fields
				if ( $kept ) {
					$report['skipped'][] = 'meta ' . $m['post_id'] . ' ' . $m['meta_key'] . ' (mixed; left alone)';
					continue;
				}
				$new = str_ireplace( $from, $to, $old );
			}
		} else {
			$new = str_ireplace( $from, $to, $old );
		}
		foreach ( $kept as $k ) {
			$report['kept'][] = 'meta ' . $m['post_id'] . ' ' . $m['meta_key'] . ' → ' . $k;
		}
		if ( $new === $old ) {
			continue;
		}
		$report['meta'][] = $m['post_type'] . ' ' . $m['post_id'] . ' ' . $m['post_name'] . ' · ' . $m['meta_key'];
		if ( $apply ) {
			$backup[] = array( 'meta', (int) $m['meta_id'], $m['meta_key'], $old );
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_id' => (int) $m['meta_id'] ) );
			wp_cache_delete( (int) $m['post_id'], 'post_meta' );
		}
	}
	// Site-wide settings (header and footer scripts, theme options, SEO).
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT IN ('admin_email','new_admin_email','wp_mail_smtp','axh_qbo','axh_email_swap_backup','axh_mig_email_1212')", $like, '%transient%', 'axh\\_email\\_swap%' ) ); // phpcs:ignore
	foreach ( (array) $names as $name ) {
		$kept = array();
		$old  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore
		if ( is_serialized( $old ) ) {
			$val = @unserialize( $old, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false === $val && 'b:0;' !== $old ) {
				$report['skipped'][] = 'option ' . $name . ' (unreadable)';
				continue;
			}
			$nv  = $walk( $val );
			$new = $nv === $val ? $old : serialize( $nv ); // phpcs:ignore
		} else {
			$new = str_ireplace( $from, $to, (string) $old );
		}
		foreach ( $kept as $k ) {
			$report['kept'][] = 'option ' . $name . ' → ' . $k;
		}
		if ( $new === $old ) {
			continue;
		}
		$report['options'][] = $name;
		if ( $apply ) {
			$backup[] = array( 'option', 0, $name, $old );
			$wpdb->update( $wpdb->options, array( 'option_value' => $new ), array( 'option_name' => $name ) );
			wp_cache_delete( $name, 'options' );
		}
	}
	if ( $apply && $backup ) {
		$prev = get_option( 'axh_email_swap_backup', array() );
		$prev = is_array( $prev ) ? $prev : array();
		$prev[] = array( 'at' => current_time( 'mysql' ), 'items' => $backup );
		update_option( 'axh_email_swap_backup', $prev, false );
		wp_cache_delete( 'alloptions', 'options' );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache(); // rebuild designs with the new address
		}
		do_action( 'litespeed_purge_all' );
	}
	$report['applied'] = (bool) $apply;
	return $report;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'axh/v1',
			'/site/email-swap',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function ( $req ) {
					return rest_ensure_response( axh_site_email_swap( ! empty( $req->get_param( 'apply' ) ) ) );
				},
			)
		);
	}
);

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
