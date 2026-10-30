<?php
/**
 * Sales Hub as a phone app (1.10.0): installable to the home screen, with
 * push alerts on the lock screen.
 *
 * - /hub/manifest.webmanifest and /hub/sw.js are served from here (no
 *   rewrite rules needed, so nothing has to be flushed).
 * - Push is standard Web Push (RFC 8030/8291/8292): every Hub notification
 *   that lands in the bell is also pushed to each device the person turned
 *   alerts on for. No outside account or service is involved; the browser
 *   makers' own push services deliver it. The keys are created on first use
 *   and kept in the options table.
 * - Subscriptions are per user (user meta), at most 10 devices. A device
 *   that the push service says is gone is removed automatically.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_App {

	const NS = 'axh/v1';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'serve' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'axh_notification_added', array( __CLASS__, 'on_notification' ), 10, 6 );
	}

	/* ------------------------------------------------------- app shell files */

	protected static function base_path() {
		$p = wp_parse_url( axh_portal_url(), PHP_URL_PATH );
		return '/' . trim( (string) $p, '/' ) . '/';
	}

	/** Answer /hub/sw.js and /hub/manifest.webmanifest before WordPress routes. */
	public static function serve() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$base = self::base_path();
		if ( $path === $base . 'sw.js' ) {
			self::nocache();
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Service-Worker-Allowed: ' . $base );
			echo self::sw(); // phpcs:ignore WordPress.Security.EscapeOutput -- static script.
			exit;
		}
		if ( $path === $base . 'manifest.webmanifest' ) {
			self::nocache();
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			echo wp_json_encode( self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
	}

	protected static function nocache() {
		if ( function_exists( 'axh_nocache' ) ) {
			axh_nocache();
		}
		nocache_headers();
	}

	public static function manifest() {
		$img = AXH_URL . 'assets/app/';
		return array(
			'name'             => 'AdaptExec Sales Hub',
			'short_name'       => 'Sales Hub',
			'description'      => 'Your AdaptExec pipeline, follow-ups, decks and alerts.',
			'id'               => self::base_path(),
			'start_url'        => self::base_path() . '#/',
			'scope'            => self::base_path(),
			'display'          => 'standalone',
			'orientation'      => 'any',
			'background_color' => '#0D0E10',
			'theme_color'      => '#0D0E10',
			'icons'            => array(
				array( 'src' => $img . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => $img . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => $img . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
			),
			'shortcuts'        => array(
				array( 'name' => 'Follow-ups', 'url' => self::base_path() . '#/tasks' ),
				array( 'name' => 'Pipeline', 'url' => self::base_path() . '#/leads' ),
				array( 'name' => 'Shared links', 'url' => self::base_path() . '#/shares' ),
			),
		);
	}

	/** Tags for the app page's <head>. */
	public static function head() {
		$img = AXH_URL . 'assets/app/';
		echo '<link rel="manifest" href="' . esc_url( self::base_path() . 'manifest.webmanifest' ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $img . 'apple-touch-icon.png' ) . '">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="Sales Hub">' . "\n";
	}

	/**
	 * The service worker. Network-only for everything (the Hub is live CRM
	 * data, so nothing is cached and nothing can go stale); it exists for
	 * install and push. A failed page load while offline gets a small
	 * "you are offline" page instead of the browser's error.
	 */
	public static function sw() {
		$icon  = AXH_URL . 'assets/app/icon-192.png';
		$badge = AXH_URL . 'assets/app/badge-96.png';
		$base  = self::base_path();
		return "/* AdaptExec Sales Hub service worker (1.10.0) */\n" .
			"var BASE = " . wp_json_encode( $base ) . ", ICON = " . wp_json_encode( $icon ) . ", BADGE = " . wp_json_encode( $badge ) . ";\n" .
			<<<'JS'
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
	if (e.request.mode !== 'navigate') return;
	e.respondWith(fetch(e.request).catch(function () {
		return new Response('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title>' +
			'<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0D0E10;color:#fff;font:16px system-ui;text-align:center;padding:24px">' +
			'<div><h1 style="font-size:22px;margin:0 0 8px">You are offline</h1><p style="color:#9AA3AE;margin:0 0 18px">The Sales Hub needs a connection to show live data.</p>' +
			'<button onclick="location.reload()" style="background:#EF5D0F;color:#fff;border:0;border-radius:10px;padding:12px 18px;font:600 15px system-ui">Try again</button></div></body>',
			{ headers: { 'Content-Type': 'text/html; charset=utf-8' } });
	}));
});
self.addEventListener('push', function (e) {
	var d = {};
	try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: e.data ? e.data.text() : 'Sales Hub' }; }
	var opts = {
		body: d.body || '',
		icon: ICON,
		badge: BADGE,
		tag: d.tag || undefined,
		renotify: !!d.tag,
		data: { url: BASE + (d.link || '#/') },
		timestamp: Date.now()
	};
	var jobs = [self.registration.showNotification(d.title || 'Sales Hub', opts)];
	if (typeof d.unread === 'number' && self.navigator && self.navigator.setAppBadge) {
		jobs.push((d.unread ? self.navigator.setAppBadge(d.unread) : self.navigator.clearAppBadge()).catch(function () {}));
	}
	e.waitUntil(Promise.all(jobs));
});
self.addEventListener('notificationclick', function (e) {
	e.notification.close();
	var url = (e.notification.data && e.notification.data.url) || BASE;
	e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
		for (var i = 0; i < list.length; i++) {
			var c = list[i];
			if (c.url.indexOf(BASE) > -1 && 'focus' in c) {
				c.postMessage({ axh: 'open', url: url });
				return c.focus();
			}
		}
		return self.clients.openWindow(url);
	}));
});
JS;
	}

	/* ------------------------------------------------------------- REST */

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		register_rest_route( self::NS, '/p1/push', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_status' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_subscribe' ),
				'permission_callback' => $auth,
			),
		) );
		register_rest_route( self::NS, '/p1/push/remove', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_unsubscribe' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( self::NS, '/p1/push/test', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_test' ),
			'permission_callback' => $auth,
		) );
	}

	public static function available() {
		return function_exists( 'openssl_pkey_new' ) && function_exists( 'openssl_pkey_derive' ) && in_array( 'aes-128-gcm', array_map( 'strtolower', openssl_get_cipher_methods() ), true );
	}

	protected static function subs( $user_id ) {
		$s = get_user_meta( (int) $user_id, 'axh_push_subs', true );
		return is_array( $s ) ? $s : array();
	}

	public static function rest_status( $req ) {
		$subs = self::subs( get_current_user_id() );
		return rest_ensure_response( array(
			'available' => self::available(),
			'key'       => self::available() ? self::public_key() : '',
			'devices'   => array_values( array_map( function ( $s ) { return array( 'id' => $s['id'], 'label' => $s['label'], 'added' => $s['added'] ); }, $subs ) ),
		) );
	}

	public static function rest_subscribe( $req ) {
		if ( ! self::available() ) {
			return new WP_Error( 'axh_push_off', 'This server cannot send push alerts (OpenSSL is missing).', array( 'status' => 501 ) );
		}
		$b        = (array) $req->get_json_params();
		$endpoint = esc_url_raw( (string) ( $b['endpoint'] ?? '' ), array( 'https' ) );
		$p256dh   = (string) ( $b['keys']['p256dh'] ?? '' );
		$auth     = (string) ( $b['keys']['auth'] ?? '' );
		if ( ! $endpoint || 65 !== strlen( self::b64d( $p256dh ) ) || 16 !== strlen( self::b64d( $auth ) ) ) {
			return new WP_Error( 'axh_push_bad', 'That device did not send a valid push subscription.', array( 'status' => 400 ) );
		}
		$me   = get_current_user_id();
		$subs = array_values( array_filter( self::subs( $me ), function ( $s ) use ( $endpoint ) { return $s['endpoint'] !== $endpoint; } ) );
		$subs[] = array(
			'id'       => substr( md5( $endpoint ), 0, 12 ),
			'endpoint' => $endpoint,
			'p256dh'   => $p256dh,
			'auth'     => $auth,
			'label'    => mb_substr( sanitize_text_field( (string) ( $b['label'] ?? 'This device' ) ), 0, 60 ),
			'added'    => AXH_DB::now(),
		);
		update_user_meta( $me, 'axh_push_subs', array_slice( $subs, -10 ) );
		return self::rest_status( $req );
	}

	public static function rest_unsubscribe( $req ) {
		$b    = (array) $req->get_json_params();
		$me   = get_current_user_id();
		$id   = sanitize_key( (string) ( $b['id'] ?? '' ) );
		$ep   = (string) ( $b['endpoint'] ?? '' );
		$subs = array_values( array_filter( self::subs( $me ), function ( $s ) use ( $id, $ep ) { return $s['id'] !== $id && $s['endpoint'] !== $ep; } ) );
		update_user_meta( $me, 'axh_push_subs', $subs );
		return self::rest_status( $req );
	}

	public static function rest_test( $req ) {
		$me = get_current_user_id();
		$n  = self::push_user( $me, array( 'title' => 'Alerts are on', 'body' => 'You will get a buzz like this when a prospect opens your deck, a form comes in, or a deal moves.', 'link' => '#/settings', 'tag' => 'axh-test' ) );
		if ( ! $n['sent'] ) {
			return new WP_Error( 'axh_push_fail', $n['devices'] ? 'The push service did not accept the alert (' . implode( ', ', $n['errors'] ) . ').' : 'Turn alerts on for this device first.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( $n );
	}

	/* ---------------------------------------------------- notification hook */

	public static function on_notification( $user_id, $type, $title, $body, $link, $id ) {
		if ( ! self::available() || ! self::subs( $user_id ) ) {
			return;
		}
		global $wpdb;
		$t      = AXH_DB::table( 'notifications' );
		$unread = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE user_id = %d AND read_at IS NULL", (int) $user_id ) ); // phpcs:ignore
		$first  = trim( (string) strtok( (string) $body, "\n" ) );
		self::push_user(
			(int) $user_id,
			array(
				'title'  => $title,
				'body'   => mb_substr( $first, 0, 180 ),
				'link'   => $link ? $link : '#/',
				'tag'    => $type . ( $id ? '-' . $id : '' ),
				'unread' => $unread,
			)
		);
	}

	/** Push one message to every device of one user. */
	public static function push_user( $user_id, $msg ) {
		$subs   = self::subs( $user_id );
		$keep   = array();
		$sent   = 0;
		$errors = array();
		$payload = wp_json_encode( $msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		foreach ( $subs as $s ) {
			$r = self::send( $s, $payload );
			if ( 'gone' === $r ) {
				continue; // The device unsubscribed or was reset: forget it.
			}
			$keep[] = $s;
			if ( true === $r ) {
				$sent++;
			} else {
				$errors[] = (string) $r;
			}
		}
		if ( count( $keep ) !== count( $subs ) ) {
			update_user_meta( (int) $user_id, 'axh_push_subs', $keep );
		}
		return array( 'sent' => $sent, 'devices' => count( $keep ), 'errors' => $errors );
	}

	/**
	 * Encrypt (RFC 8291, aes128gcm) and deliver one push with VAPID (RFC 8292).
	 * Returns true, 'gone', or an error string.
	 */
	public static function send( $sub, $payload ) {
		$body = self::encrypt( $payload, self::b64d( $sub['p256dh'] ), self::b64d( $sub['auth'] ) );
		if ( ! $body ) {
			return 'encrypt';
		}
		$aud = wp_parse_url( $sub['endpoint'], PHP_URL_SCHEME ) . '://' . wp_parse_url( $sub['endpoint'], PHP_URL_HOST );
		$jwt = self::vapid_jwt( $aud );
		if ( ! $jwt ) {
			return 'vapid';
		}
		$res = wp_remote_post(
			$sub['endpoint'],
			array(
				'timeout' => 6,
				'headers' => array(
					'TTL'              => '86400',
					'Urgency'          => 'high',
					'Content-Type'     => 'application/octet-stream',
					'Content-Encoding' => 'aes128gcm',
					'Authorization'    => 'vapid t=' . $jwt . ', k=' . self::public_key(),
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res->get_error_message();
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 404 === $code || 410 === $code ) {
			return 'gone';
		}
		return ( $code >= 200 && $code < 300 ) ? true : 'HTTP ' . $code;
	}

	/* ------------------------------------------------------------- crypto */

	public static function b64u( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	public static function b64d( $s ) {
		$s = strtr( (string) $s, '-_', '+/' );
		return (string) base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true );
	}

	/** The site's VAPID key pair, created once. */
	protected static function vapid() {
		$pem = get_option( 'axh_vapid_pem' );
		if ( ! $pem ) {
			$k = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
			if ( ! $k ) {
				return null;
			}
			openssl_pkey_export( $k, $pem );
			add_option( 'axh_vapid_pem', $pem, '', false );
			$pem = get_option( 'axh_vapid_pem' ); // Another request may have won the race.
		}
		$key = openssl_pkey_get_private( $pem );
		return $key ? $key : null;
	}

	/** Uncompressed P-256 point (65 bytes) of a key. */
	protected static function point( $key ) {
		$d = openssl_pkey_get_details( $key );
		if ( empty( $d['ec']['x'] ) ) {
			return '';
		}
		return "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
	}

	public static function public_key() {
		$k = self::vapid();
		return $k ? self::b64u( self::point( $k ) ) : '';
	}

	/** A raw P-256 public point as an OpenSSL key. */
	protected static function point_key( $point ) {
		$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $point;
		$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
		return openssl_pkey_get_public( $pem );
	}

	protected static function hkdf( $salt, $ikm, $info, $len ) {
		$prk = hash_hmac( 'sha256', $ikm, $salt, true );
		return substr( hash_hmac( 'sha256', $info . "\x01", $prk, true ), 0, $len );
	}

	public static function encrypt( $plain, $ua_public, $auth_secret ) {
		if ( 65 !== strlen( $ua_public ) || 16 !== strlen( $auth_secret ) ) {
			return '';
		}
		$eph = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
		$ua  = self::point_key( $ua_public );
		if ( ! $eph || ! $ua ) {
			return '';
		}
		$as_public = self::point( $eph );
		$shared    = openssl_pkey_derive( $ua, $eph );
		if ( ! $shared || 65 !== strlen( $as_public ) ) {
			return '';
		}
		$ikm   = self::hkdf( $auth_secret, $shared, "WebPush: info\0" . $ua_public . $as_public, 32 );
		$salt  = random_bytes( 16 );
		$cek   = self::hkdf( $salt, $ikm, "Content-Encoding: aes128gcm\0", 16 );
		$nonce = self::hkdf( $salt, $ikm, "Content-Encoding: nonce\0", 12 );
		$tag   = '';
		$ct    = openssl_encrypt( $plain . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( false === $ct ) {
			return '';
		}
		return $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_public . $ct . $tag;
	}

	/** ES256 JWT for the push service. */
	public static function vapid_jwt( $aud ) {
		$key = self::vapid();
		if ( ! $key ) {
			return '';
		}
		$c    = AXH_Mail::contact();
		$h    = self::b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$p    = self::b64u( wp_json_encode( array( 'aud' => $aud, 'exp' => time() + 12 * HOUR_IN_SECONDS, 'sub' => 'mailto:' . $c['email'] ) ) );
		$der  = '';
		if ( ! openssl_sign( $h . '.' . $p, $der, $key, OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}
		$raw = self::der_to_raw( $der );
		return $raw ? $h . '.' . $p . '.' . self::b64u( $raw ) : '';
	}

	/** ECDSA DER (SEQUENCE of two INTEGERs) to the 64-byte r||s JOSE form. */
	protected static function der_to_raw( $der ) {
		$o = 0;
		if ( ord( $der[ $o++ ] ) !== 0x30 ) {
			return '';
		}
		$len = ord( $der[ $o++ ] );
		if ( $len & 0x80 ) {
			$o += $len & 0x7f;
		}
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			if ( ord( $der[ $o++ ] ) !== 0x02 ) {
				return '';
			}
			$l    = ord( $der[ $o++ ] );
			$v    = ltrim( substr( $der, $o, $l ), "\0" );
			$o   += $l;
			$out .= str_pad( $v, 32, "\0", STR_PAD_LEFT );
		}
		return 64 === strlen( $out ) ? $out : '';
	}
}

AXH_App::init();
