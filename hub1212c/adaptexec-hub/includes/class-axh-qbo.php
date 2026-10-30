<?php
/**
 * QuickBooks Online (1.21.0, reworked in 1.21.1).
 *
 * Connects Billing to AdaptExec's own QuickBooks company:
 *  - invoices go to QuickBooks with the client as a QuickBooks customer;
 *  - the client pays online on QuickBooks' own payment page (card or bank
 *    transfer), and the Hub records the payment when QuickBooks has it;
 *  - checks, cash and Zelle recorded in the Hub are recorded in QuickBooks
 *    against the same invoice, so the books and the Hub agree.
 *
 * One rule keeps the two in step. Every time the Hub touches an invoice in
 * QuickBooks it rebuilds the lines from the Hub, then sets one "Cash
 * discount" line for the discounts the Hub actually gave, so the QuickBooks
 * balance is exactly what the client owes for the Hub's open balance in the
 * chosen way of paying:
 *   card / open:  the card price of the open balance;
 *   bank:         the open balance plus the bank fee.
 * Payments the Hub recorded are posted first (or matched to the same payment
 * entered in QuickBooks by hand). Money is only taken online while both agree
 * on what's been paid; otherwise online payment stops for that invoice and
 * AdaptExec is told, instead of guessing. Any change to what's owed turns off
 * or re-prices a payment page already out, and a payment is priced from what
 * its page asked. A payment that is more than the Hub says is owed is never
 * kept quietly: it waits for AdaptExec.
 *
 * Cash discount pricing is decided once per invoice (when the client can
 * first see it) and kept, so changing the setting never re-prices an invoice
 * a client already has. Inside the Hub every total, payment and commission
 * stays at the cash price.
 *
 * Nothing here deletes anything in QuickBooks, and nothing changes a record
 * the Hub did not create there. Links live in their own table.
 */

defined( 'ABSPATH' ) || exit;

class AXH_QBO {

	const DB    = '1';
	const OPT   = 'axh_qbo';
	const NS    = 'axh/v1';
	const MINOR = 75;
	const SCOPE = 'com.intuit.quickbooks.accounting';
	const MARK  = 'AdaptExec Hub';

	/** Methods that take the cash discount. */
	const CASHLIKE = array( 'check', 'cash', 'zelle', 'ach', 'other' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 12 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'axh_bill_gateways', array( __CLASS__, 'gateways' ) );
		add_filter( 'axh_bill_display_doc', array( __CLASS__, 'display_doc' ), 10, 2 );
		add_action( 'axh_bill_payment_recorded', array( __CLASS__, 'on_payment' ), 10, 2 );
		add_action( 'axh_bill_payment_changed', array( __CLASS__, 'on_payment_changed' ), 10, 2 );
		add_action( 'axh_bill_doc_sent', array( __CLASS__, 'on_sent' ) );
		add_action( 'axh_bill_doc_changed', array( __CLASS__, 'on_doc_changed' ), 10, 2 );
		add_action( 'axh_qbo_push', array( __CLASS__, 'cron_push' ) );
		add_action( 'axh_qbo_sync', array( __CLASS__, 'cron_push' ) );
		add_action( 'axh_qbo_poll', array( __CLASS__, 'poll' ) );
		add_action( 'axh_qbo_poll_soon', array( __CLASS__, 'poll' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'init', array( __CLASS__, 'schedule_poll' ), 30 );
	}

	/* ------------------------------------------------------------- storage */

	public static function maybe_upgrade() {
		if ( get_option( 'axh_qbo_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = AXH_DB::table( 'qbo_map' );
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(12) NOT NULL DEFAULT '',
			local_id bigint(20) unsigned NOT NULL DEFAULT 0,
			qbo_id varchar(32) NOT NULL DEFAULT '',
			sync_token varchar(16) NOT NULL DEFAULT '',
			mode varchar(12) NOT NULL DEFAULT '',
			meta longtext NULL,
			synced_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY kind_local (kind, local_id),
			KEY kind_qbo (kind, qbo_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( 'axh_qbo_db', self::DB, false );
		}
	}

	protected static function ready() {
		return (bool) get_option( 'axh_qbo_db' );
	}

	public static function defaults() {
		return array(
			'client_id'     => '',
			'client_secret' => '',
			'realm_id'      => '',
			'map_realm'     => '',
			'company'       => '',
			'access'        => '',
			'access_exp'    => 0,
			'refresh'       => '',
			'refresh_exp'   => 0,
			'connected_at'  => '',
			'connected_by'  => 0,
			'item_id'       => '',
			'item_name'     => '',
			'disc_item_id'  => '',
			'fee_item_id'   => '',
			'cd_on'         => false,
			'cd_pct'        => 3.5,
			'cd_from'       => 0,
			'card_on'       => true,
			'bank_on'       => true,
			'bank_fee_pct'  => 1.0,
			'push_manual'   => true,
			'last_poll'     => '',
			'errors'        => array(),
		);
	}

	public static function opt() {
		$o = get_option( self::OPT, array() );
		return array_merge( self::defaults(), is_array( $o ) ? $o : array() );
	}

	protected static function save_opt( $o ) {
		update_option( self::OPT, array_intersect_key( $o, self::defaults() ), false );
	}

	/** Change a few settings against a fresh read, so a token refresh in between is never undone. */
	protected static function set_opt( $fields ) {
		$o = self::opt();
		foreach ( $fields as $k => $v ) {
			$o[ $k ] = $v;
		}
		self::save_opt( $o );
		return $o;
	}

	/** Secrets are stored encrypted with the site's own salts. */
	protected static function key() {
		$k = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' ) . 'axh-qbo';
		return hash( 'sha256', $k, true );
	}

	protected static function enc( $s ) {
		$s = (string) $s;
		if ( '' === $s ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'p:' . base64_encode( $s ); // phpcs:ignore
		}
		$iv = random_bytes( 16 );
		$c  = openssl_encrypt( $s, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return 'e:' . base64_encode( $iv . $c ); // phpcs:ignore
	}

	protected static function dec( $s ) {
		$s = (string) $s;
		if ( 0 === strpos( $s, 'p:' ) ) {
			return (string) base64_decode( substr( $s, 2 ) ); // phpcs:ignore
		}
		if ( 0 !== strpos( $s, 'e:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $s, 2 ) ); // phpcs:ignore
		if ( strlen( $raw ) < 17 ) {
			return '';
		}
		$out = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return false === $out ? '' : $out;
	}

	public static function connected() {
		$o = self::opt();
		return self::ready() && $o['realm_id'] && $o['refresh'] && $o['client_id'] && $o['client_secret'];
	}

	/**
	 * Remember the last few problems so AdaptExec can see them in Settings;
	 * with a document, also put it on that document's timeline.
	 */
	protected static function fail( $msg, $doc_id = 0 ) {
		$o   = self::opt();
		$e   = (array) $o['errors'];
		$msg = mb_substr( (string) $msg, 0, 400 );
		if ( ! empty( $e[0]['msg'] ) && $e[0]['msg'] === $msg && (int) $e[0]['doc'] === (int) $doc_id ) {
			return; // the same problem again: once is enough
		}
		array_unshift( $e, array( 'at' => current_time( 'mysql' ), 'msg' => $msg, 'doc' => (int) $doc_id ) );
		self::set_opt( array( 'errors' => array_slice( $e, 0, 20 ) ) );
		if ( $doc_id && class_exists( 'AXH_Bill' ) ) {
			$r = AXH_Bill::doc_row( $doc_id, false );
			if ( $r ) {
				AXH_Bill::event( $doc_id, (int) $r['customer_id'], 'qbo', 'QuickBooks: ' . mb_substr( $msg, 0, 300 ), 0 );
			}
		}
	}

	protected static function auth_error( $e ) {
		return is_wp_error( $e ) && in_array( $e->get_error_code(), array( 'axh_qbo_off', 'axh_qbo_token' ), true );
	}

	/* ------------------------------------------------------------ locks */

	/** A database lock: held until released or until this request ends. */
	protected static function lock( $name, $wait = 0 ) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', substr( 'axhqbo_' . md5( DB_NAME . $wpdb->prefix ) . '_' . $name, 0, 64 ), (int) $wait ) );
	}

	protected static function unlock( $name ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', substr( 'axhqbo_' . md5( DB_NAME . $wpdb->prefix ) . '_' . $name, 0, 64 ) ) );
	}

	/* -------------------------------------------------------------- map */

	protected static function map_get( $kind, $local_id ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return null;
		}
		$t   = AXH_DB::table( 'qbo_map' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE kind = %s AND local_id = %d", $kind, (int) $local_id ), ARRAY_A ); // phpcs:ignore
		if ( $row ) {
			$m           = json_decode( (string) $row['meta'], true );
			$row['meta'] = is_array( $m ) ? $m : array();
		}
		return $row;
	}

	protected static function map_by_qbo( $kind, $qbo_id ) {
		global $wpdb;
		$t = AXH_DB::table( 'qbo_map' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE kind = %s AND qbo_id = %s", $kind, (string) $qbo_id ), ARRAY_A ); // phpcs:ignore
	}

	protected static function map_set( $kind, $local_id, $fields ) {
		global $wpdb;
		$t   = AXH_DB::table( 'qbo_map' );
		$cur = self::map_get( $kind, $local_id );
		if ( isset( $fields['meta'] ) ) {
			$fields['meta'] = wp_json_encode( array_merge( $cur ? $cur['meta'] : array(), (array) $fields['meta'] ) );
		}
		$fields['synced_at'] = current_time( 'mysql', true );
		if ( $cur ) {
			$wpdb->update( $t, $fields, array( 'id' => (int) $cur['id'] ) );
		} else {
			$wpdb->insert( $t, array_merge( array( 'kind' => $kind, 'local_id' => (int) $local_id, 'created_at' => current_time( 'mysql', true ) ), $fields ) );
		}
		return self::map_get( $kind, $local_id );
	}

	/* ------------------------------------------------------------ OAuth */

	protected static function oauth_base() {
		return defined( 'AXH_QBO_OAUTH' ) ? AXH_QBO_OAUTH : 'https://oauth.platform.intuit.com';
	}

	protected static function api_base() {
		return defined( 'AXH_QBO_API' ) ? AXH_QBO_API : 'https://quickbooks.api.intuit.com';
	}

	protected static function auth_url() {
		return defined( 'AXH_QBO_AUTHORIZE' ) ? AXH_QBO_AUTHORIZE : 'https://appcenter.intuit.com/connect/oauth2';
	}

	public static function redirect_uri() {
		return rest_url( self::NS . '/qbo/callback' );
	}

	protected static function token_call( $body ) {
		$o   = self::opt();
		$res = wp_remote_post(
			self::oauth_base() . '/oauth2/v1/tokens/bearer',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $o['client_id'] . ':' . self::dec( $o['client_secret'] ) ), // phpcs:ignore
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'axh_qbo_net', 'Could not reach QuickBooks: ' . $res->get_error_message(), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$j    = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || empty( $j['access_token'] ) ) {
			$err = is_array( $j ) ? (string) ( $j['error'] ?? '' ) : '';
			$why = is_array( $j ) ? ( $j['error_description'] ?? $err ) : '';
			$tid = sanitize_text_field( (string) wp_remote_retrieve_header( $res, 'intuit_tid' ) );
			return new WP_Error( in_array( $err, array( 'invalid_grant', 'invalid_client' ), true ) ? 'axh_qbo_token' : 'axh_qbo_tokenx', 'QuickBooks did not accept the request' . ( $why ? ': ' . $why : '' ) . ' (' . $code . ( $tid ? ', intuit_tid ' . $tid : '' ) . ').', array( 'status' => 502, 'oauth' => $err, 'intuit_tid' => $tid ) );
		}
		return $j;
	}

	protected static function store_tokens( $j ) {
		$f = array(
			'access'     => self::enc( $j['access_token'] ),
			'access_exp' => time() + (int) ( $j['expires_in'] ?? 3600 ) - 120,
		);
		if ( ! empty( $j['refresh_token'] ) ) {
			$f['refresh']     = self::enc( $j['refresh_token'] );
			$f['refresh_exp'] = time() + (int) ( $j['x_refresh_token_expires_in'] ?? 8640000 );
		}
		self::set_opt( $f );
	}

	/** A working access token, refreshed when it is close to expiring. */
	protected static function token( $force = false ) {
		$o = self::opt();
		if ( ! self::connected() ) {
			return new WP_Error( 'axh_qbo_off', 'QuickBooks is not connected.', array( 'status' => 409 ) );
		}
		if ( ! $force && $o['access'] && time() < (int) $o['access_exp'] ) {
			return self::dec( $o['access'] );
		}
		// One refresh at a time: Intuit rotates the refresh token.
		if ( ! self::lock( 'token', 15 ) ) {
			return new WP_Error( 'axh_qbo_busy', 'QuickBooks is busy. Try again in a moment.', array( 'status' => 503 ) );
		}
		$fresh = self::opt();
		if ( $fresh['access'] && time() < (int) $fresh['access_exp'] && ( ! $force || $fresh['access'] !== $o['access'] ) ) {
			self::unlock( 'token' );
			return self::dec( $fresh['access'] ); // another request refreshed it meanwhile
		}
		$j = self::token_call( array( 'grant_type' => 'refresh_token', 'refresh_token' => self::dec( $fresh['refresh'] ) ) );
		if ( is_wp_error( $j ) ) {
			self::unlock( 'token' );
			if ( 'axh_qbo_token' === $j->get_error_code() ) {
				// The connection was revoked or has expired: stop using it until AdaptExec reconnects.
				self::set_opt( array( 'access' => '', 'refresh' => '', 'access_exp' => 0, 'refresh_exp' => 0 ) );
				self::fail( 'The QuickBooks connection has ended. Online payment is off until you reconnect in Settings → QuickBooks.' );
			}
			return $j;
		}
		self::store_tokens( $j );
		self::unlock( 'token' );
		return $j['access_token'];
	}

	/* -------------------------------------------------------------- API */

	/**
	 * One call to the QuickBooks accounting API. Returns the decoded body or a
	 * WP_Error carrying QuickBooks' own message (and its error code in data).
	 */
	public static function api( $method, $path, $body = null, $query = array(), $retry = true ) {
		$tok = self::token();
		if ( is_wp_error( $tok ) ) {
			return $tok;
		}
		$o     = self::opt();
		$query = array_merge( (array) $query, array( 'minorversion' => self::MINOR ) );
		$url   = self::api_base() . '/v3/company/' . rawurlencode( $o['realm_id'] ) . '/' . ltrim( $path, '/' ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		$args  = array(
			'method'  => $method,
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $tok,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'axh_qbo_net', 'Could not reach QuickBooks: ' . $res->get_error_message(), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 401 === $code && $retry ) {
			$t = self::token( true );
			if ( is_wp_error( $t ) ) {
				return $t;
			}
			return self::api( $method, $path, $body, $query, false );
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 || ! is_array( $j ) || isset( $j['Fault'] ) ) {
			$err = is_array( $j ) ? ( $j['Fault']['Error'][0] ?? array() ) : array();
			$msg = trim( ( $err['Message'] ?? '' ) . ( ! empty( $err['Detail'] ) ? ' — ' . $err['Detail'] : '' ) );
			$tid = sanitize_text_field( (string) wp_remote_retrieve_header( $res, 'intuit_tid' ) ); // Intuit support finds the call by this
			return new WP_Error( 'axh_qbo_api', 'QuickBooks: ' . ( $msg ? $msg : 'error ' . $code ) . ( $tid ? ' (intuit_tid ' . $tid . ')' : '' ), array( 'status' => 502, 'qbo_code' => (string) ( $err['code'] ?? '' ), 'intuit_tid' => $tid ) );
		}
		return $j;
	}

	public static function query( $sql ) {
		$r = self::api( 'GET', 'query', null, array( 'query' => $sql ) );
		return is_wp_error( $r ) ? $r : ( $r['QueryResponse'] ?? array() );
	}

	protected static function q( $s ) {
		return str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $s );
	}

	protected static function qcode( $e ) {
		return is_wp_error( $e ) ? (string) ( $e->get_error_data()['qbo_code'] ?? '' ) : '';
	}

	protected static function pct_txt( $p ) {
		return rtrim( rtrim( number_format( (float) $p, 2 ), '0' ), '.' ) . '%';
	}

	/* ------------------------------------------------------------ items */

	/** The product/service used for invoice lines, plus the two adjustment items. */
	protected static function items() {
		$o = self::opt();
		if ( ! $o['item_id'] ) {
			$r = self::query( "select * from Item where Name = 'Services' and Active = true" );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$it = $r['Item'][0] ?? null;
			if ( ! $it ) {
				$r  = self::query( "select * from Item where Type = 'Service' and Active = true maxresults 1" );
				$it = is_wp_error( $r ) ? null : ( $r['Item'][0] ?? null );
			}
			if ( ! $it ) {
				return new WP_Error( 'axh_qbo_item', 'Pick the QuickBooks product or service for invoice lines in Settings → QuickBooks.', array( 'status' => 409 ) );
			}
			$o = self::set_opt( array( 'item_id' => (string) $it['Id'], 'item_name' => (string) $it['Name'] ) );
		}
		foreach ( array( 'disc_item_id' => 'Cash discount', 'fee_item_id' => 'Bank transfer fee' ) as $key => $name ) {
			if ( $o[ $key ] ) {
				continue;
			}
			$id = self::ensure_item( $name, $o['item_id'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$o = self::set_opt( array( $key => $id ) );
		}
		return $o;
	}

	/** Find the named service item, or make it on the same income account as the main item. */
	protected static function ensure_item( $name, $main_id ) {
		$r = self::query( "select * from Item where Name = '" . self::q( $name ) . "'" );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! empty( $r['Item'][0]['Id'] ) ) {
			return (string) $r['Item'][0]['Id'];
		}
		$main = self::api( 'GET', 'item/' . rawurlencode( $main_id ) );
		if ( is_wp_error( $main ) ) {
			return $main;
		}
		$acct = $main['Item']['IncomeAccountRef']['value'] ?? '';
		if ( ! $acct ) {
			$a    = self::query( "select * from Account where AccountType = 'Income' and Active = true maxresults 1" );
			$acct = is_wp_error( $a ) ? '' : ( $a['Account'][0]['Id'] ?? '' );
		}
		$c = self::api( 'POST', 'item', array( 'Name' => $name, 'Type' => 'Service', 'Taxable' => false, 'IncomeAccountRef' => array( 'value' => (string) $acct ), 'Description' => $name . ' (' . self::MARK . ')' ) );
		return is_wp_error( $c ) ? $c : (string) $c['Item']['Id'];
	}

	/* -------------------------------------------------------- customers */

	protected static function customer_for( $doc ) {
		$cid = (int) $doc['customer_id'];
		$map = self::map_get( 'customer', $cid );
		if ( $map && $map['qbo_id'] ) {
			return $map['qbo_id'];
		}
		$c = AXH_Bill::customer_row( $cid, false );
		if ( ! $c ) {
			return new WP_Error( 'axh_qbo_cust', 'This invoice has no customer.', array( 'status' => 409 ) );
		}
		$name  = trim( $c['company'] ? $c['company'] : $c['contact_name'] );
		$email = strtolower( trim( (string) $c['email'] ) );
		// Match someone already in QuickBooks: same email first, then same name.
		$all = self::query( 'select * from Customer maxresults 1000' );
		if ( is_wp_error( $all ) ) {
			return $all;
		}
		$found = '';
		foreach ( (array) ( $all['Customer'] ?? array() ) as $q ) {
			if ( $email && strtolower( trim( (string) ( $q['PrimaryEmailAddr']['Address'] ?? '' ) ) ) === $email ) {
				$found = (string) $q['Id'];
				break;
			}
		}
		if ( ! $found ) {
			$key = AXH_DB::company_key( $name );
			foreach ( (array) ( $all['Customer'] ?? array() ) as $q ) {
				if ( $key && ( AXH_DB::company_key( $q['DisplayName'] ?? '' ) === $key || AXH_DB::company_key( $q['CompanyName'] ?? '' ) === $key ) ) {
					$found = (string) $q['Id'];
					break;
				}
			}
		}
		if ( ! $found ) {
			$parts = preg_split( '/\s+/', trim( (string) $c['contact_name'] ), 2 );
			$body  = array(
				'DisplayName' => mb_substr( $name ? $name : 'Customer ' . $cid, 0, 100 ),
				'CompanyName' => mb_substr( (string) $c['company'], 0, 100 ),
				'GivenName'   => mb_substr( (string) ( $parts[0] ?? '' ), 0, 100 ),
				'FamilyName'  => mb_substr( (string) ( $parts[1] ?? '' ), 0, 100 ),
				'Notes'       => 'Added by ' . self::MARK . '.',
			);
			if ( is_email( $email ) ) {
				$body['PrimaryEmailAddr'] = array( 'Address' => $email );
			}
			if ( $c['phone'] ) {
				$body['PrimaryPhone'] = array( 'FreeFormNumber' => mb_substr( (string) $c['phone'], 0, 30 ) );
			}
			if ( $c['addr1'] || $c['city'] ) {
				$body['BillAddr'] = array( 'Line1' => $c['addr1'], 'Line2' => $c['addr2'], 'City' => $c['city'], 'CountrySubDivisionCode' => $c['state'], 'PostalCode' => $c['zip'], 'Country' => $c['country'] );
			}
			$r = self::api( 'POST', 'customer', $body );
			if ( '6240' === self::qcode( $r ) ) {
				// Name taken by a vendor or employee: keep it recognisable but unique.
				$body['DisplayName'] = mb_substr( $body['DisplayName'] . ' (client)', 0, 100 );
				$r                   = self::api( 'POST', 'customer', $body );
			}
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$found = (string) $r['Customer']['Id'];
		}
		self::map_set( 'customer', $cid, array( 'qbo_id' => $found ) );
		return $found;
	}

	/* ------------------------------------------------- cash discount math */

	/**
	 * Cash discount pricing for one invoice, decided the first time the
	 * client can see it and kept from then on.
	 */
	protected static function pricing( $doc ) {
		$id = (int) ( $doc['id'] ?? 0 );
		if ( ! $id || 'invoice' !== ( $doc['type'] ?? '' ) ) {
			return array( 'cd' => false, 'pct' => 0.0 );
		}
		$row = self::map_get( 'pricing', $id );
		if ( $row ) {
			return array( 'cd' => ! empty( $row['meta']['cd'] ), 'pct' => (float) ( $row['meta']['pct'] ?? 0 ) );
		}
		$o = self::opt();
		$p = array( 'cd' => ! empty( $o['cd_on'] ) && (float) $o['cd_pct'] > 0 && $id > (int) $o['cd_from'], 'pct' => min( 10, max( 0, (float) $o['cd_pct'] ) ) );
		if ( self::ready() && 'draft' !== ( $doc['status'] ?? 'draft' ) ) {
			self::map_set( 'pricing', $id, array( 'meta' => $p ) );
		}
		return $p;
	}

	public static function cd_applies( $doc ) {
		return self::pricing( $doc )['cd'];
	}

	/**
	 * The card-price view of an invoice: every rate grossed up so the
	 * discount brings it back to the Hub's price.
	 */
	public static function card_view( $doc ) {
		$pc    = self::pricing( $doc );
		$k     = $pc['cd'] ? 1 / ( 1 - $pc['pct'] / 100 ) : 1;
		$lines = array();
		foreach ( AXH_Bill::lines_for( $doc ) as $l ) {
			// A whole-cent unit price unless that would move the line total by more than a cent
			// (2,000 × $0.15 must stay 3.5% more, not become 2,000 × $0.16).
			$exact     = (float) $l['rate'] * $k;
			$cent      = round( $exact, 2 );
			$q         = (float) $l['qty'];
			$l['rate'] = abs( round( $q * $cent, 2 ) - round( $q * $exact, 2 ) ) <= 0.011 ? $cent : round( $exact, 6 );
			if ( 'amt' === ( $l['disc_type'] ?? 'pct' ) ) {
				$l['disc'] = round( (float) $l['disc'] * $k, 2 );
			}
			$lines[] = $l;
		}
		$d = $doc;
		if ( 'amt' === ( $doc['discount_type'] ?? 'pct' ) ) {
			$d['discount_value'] = round( (float) $doc['discount_value'] * $k, 2 );
		}
		return array( 'lines' => $lines, 'doc' => $d, 'tot' => AXH_Bill::calc( $lines, $d ) );
	}

	/** What the client owes now, each way of paying, for the Hub's open balance. */
	public static function prices( $doc ) {
		$o     = self::opt();
		$pc    = self::pricing( $doc );
		$total = round( (float) $doc['total'], 2 );
		$cash  = max( 0, round( $total - AXH_Bill::invoice_paid( (int) $doc['id'] ), 2 ) );
		$ctot  = $pc['cd'] ? self::card_view( $doc )['tot']['total'] : $total;
		$card  = $cash >= $total - 0.004 ? $ctot : ( $total > 0 ? round( $cash * $ctot / $total, 2 ) : 0.0 );
		$fee   = ! empty( $o['bank_on'] ) ? round( $cash * (float) $o['bank_fee_pct'] / 100, 2 ) : 0.0;
		return array(
			'cd'         => $pc['cd'],
			'pct'        => $pc['pct'],
			'cash'       => $cash,
			'card'       => $card,
			'card_total' => $ctot,
			'discount'   => round( $card - $cash, 2 ),
			'bank'       => round( $cash + $fee, 2 ),
			'bank_fee'   => $fee,
			'bank_pct'   => (float) $o['bank_fee_pct'],
		);
	}

	/** Hub price → card price for this invoice (1 without cash discount). */
	protected static function k_of( $doc ) {
		$p = self::prices( $doc );
		return $p['cd'] && (float) $doc['total'] > 0 ? $p['card_total'] / (float) $doc['total'] : 1.0;
	}

	/** One QuickBooks payment as the Hub saw it on one invoice (state, amount, how it was paid). */
	protected static function qpay_entry( $pid, $qinv ) {
		$q = self::map_get( 'qpay', (int) $pid );
		return $q ? ( $q['meta']['docs'][ (string) $qinv ] ?? null ) : null;
	}

	/** What one Hub payment is worth in QuickBooks: cash-like at face, a card at the card price, a QuickBooks payment at what QuickBooks took. */
	protected static function row_q( $p, $k ) {
		if ( 'credit' === $p['kind'] ) {
			return round( (float) $p['amount'], 2 );
		}
		$face = round( (float) $p['amount'] - (float) $p['credit'], 2 );
		if ( 0 === strpos( (string) $p['reference'], 'qbo:' ) ) {
			$x = explode( ':', (string) $p['reference'] );
			$e = self::qpay_entry( $x[1] ?? 0, $x[2] ?? '' );
			return $e ? round( (float) $e['amount'], 2 ) : $face;
		}
		return in_array( $p['method'], self::CASHLIKE, true ) ? $face : round( $face * $k, 2 );
	}

	/** Everything the Hub counts as paid on an invoice, in QuickBooks money. */
	protected static function hub_paid_q( $doc ) {
		global $wpdb;
		$k    = self::k_of( $doc );
		$pt   = AXH_Bill::t( 'bpay' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pt} WHERE doc_id = %d AND voided_at IS NULL", (int) $doc['id'] ), ARRAY_A ); // phpcs:ignore
		$byid = array();
		foreach ( $rows as $p ) {
			$byid[ (int) $p['id'] ] = $p;
		}
		$sum = 0.0;
		foreach ( $rows as $p ) {
			if ( 'payment' === $p['kind'] || 'credit' === $p['kind'] ) {
				$sum += self::row_q( $p, $k );
			} elseif ( 'refund' === $p['kind'] ) {
				$face  = (float) $p['amount'] - (float) $p['credit'];
				$orig  = $byid[ (int) $p['refund_of'] ] ?? null;
				$of    = $orig ? (float) $orig['amount'] - (float) $orig['credit'] : 0;
				$ratio = $orig && $of > 0.004 ? self::row_q( $orig, $k ) / $of : 1;
				$sum  -= round( $face * $ratio, 2 );
			}
		}
		return round( $sum, 2 );
	}

	/* ------------------------------------------------------- display */

	/**
	 * What the client sees: on a cash-discount invoice with nothing paid yet
	 * (or paid in full online by card), the card price is the price on the
	 * page. The real figures ride along in _cd so the page can show the
	 * discount and both ways of paying.
	 */
	public static function display_doc( $doc, $opts = array() ) {
		if ( ! is_array( $doc ) || 'invoice' !== ( $doc['type'] ?? '' ) || 'void' === ( $doc['status'] ?? '' ) || ! self::cd_applies( $doc ) ) {
			return $doc;
		}
		$p    = self::prices( $doc );
		$paid = (float) ( $doc['amount_paid'] ?? 0 );
		$map  = self::map_get( 'invoice', (int) $doc['id'] );
		$card = $map && 'card' === ( $map['meta']['paid_via'] ?? '' ) && $p['cash'] <= 0.004;
		if ( $paid > 0.004 && ! $card ) {
			$doc['_cd'] = array_merge( $p, array( 'view' => 'cash' ) );
			return $doc;
		}
		$cv                    = self::card_view( $doc );
		$doc['lines']          = $cv['lines'];
		$doc['discount_value'] = $cv['doc']['discount_value'];
		$doc['subtotal']       = $cv['tot']['subtotal'];
		$doc['discount_total'] = $cv['tot']['discount'];
		$doc['tax_total']      = $cv['tot']['tax'];
		$doc['total']          = $cv['tot']['total'];
		$doc['amount_paid']    = $card ? $doc['total'] : 0.0;
		$doc['balance']        = $card ? 0.0 : $doc['total'];
		$doc['_cd']            = array_merge( $p, array( 'view' => 'card' ) );
		return $doc;
	}

	/* --------------------------------------------- invoices to QuickBooks */

	protected static function line_desc( $l ) {
		$d = trim( (string) ( $l['name'] ?? '' ) );
		if ( ! empty( $l['desc'] ) ) {
			$d .= ( $d ? "\n" : '' ) . trim( (string) $l['desc'] );
		}
		return mb_substr( $d, 0, 3900 );
	}

	protected static function item_line( $n, $desc, $amount, $item, $qty = 1, $unit = null ) {
		return array(
			'LineNum'             => $n,
			'Description'         => $desc,
			'Amount'              => round( $amount, 2 ),
			'DetailType'          => 'SalesItemLineDetail',
			'SalesItemLineDetail' => array(
				'ItemRef'    => array( 'value' => (string) $item ),
				'Qty'        => $qty,
				'UnitPrice'  => null === $unit ? round( $amount, 2 ) : $unit,
				'TaxCodeRef' => array( 'value' => 'NON' ),
			),
		);
	}

	/** The invoice's own lines (card prices when cash discount applies) and their total. */
	protected static function base_lines( $doc, $o, $fee_earned = 0.0 ) {
		$cd    = self::cd_applies( $doc );
		$cv    = $cd ? self::card_view( $doc ) : null;
		$lines = $cd ? $cv['lines'] : AXH_Bill::lines_for( $doc );
		$tot   = $cd ? $cv['tot'] : AXH_Bill::calc( $lines, $doc );
		$out   = array();
		$n     = 0;
		foreach ( $lines as $l ) {
			list( $amt, $ld, $net ) = AXH_Bill::line_net( $l );
			if ( abs( $net ) < 0.005 && ! trim( (string) ( $l['name'] ?? '' ) ) ) {
				continue;
			}
			$plain = $ld < 0.005;
			$qty   = (float) $l['qty'];
			$rate  = (float) $l['rate'];
			$cent  = abs( $rate * 100 - round( $rate * 100 ) ) < 0.000001;
			if ( $plain && abs( $qty - 1 ) < 0.0000001 ) {
				$out[] = self::item_line( ++$n, self::line_desc( $l ), $net, $o['item_id'] );
			} elseif ( $plain && $cent ) {
				$out[] = self::item_line( ++$n, self::line_desc( $l ), $net, $o['item_id'], $qty, $rate );
			} else {
				$how   = $plain ? '' : ', less a line discount';
				$out[] = self::item_line( ++$n, self::line_desc( $l ) . "\n(" . $qty . ' × ' . rtrim( rtrim( number_format( $rate, 4, '.', '' ), '0' ), '.' ) . $how . ')', $net, $o['item_id'] );
			}
		}
		if ( $tot['discount'] > 0.004 ) {
			$out[] = array( 'Amount' => $tot['discount'], 'DetailType' => 'DiscountLineDetail', 'DiscountLineDetail' => array( 'PercentBased' => false ) );
		}
		if ( $tot['tax'] > 0.004 ) {
			$out[] = self::item_line( ++$n, ( $doc['tax_label'] ? $doc['tax_label'] : 'Sales tax' ) . ' (' . rtrim( rtrim( number_format( (float) $doc['tax_rate'], 3 ), '0' ), '.' ) . '%, from ' . self::MARK . ')', $tot['tax'], $o['item_id'] );
		}
		$total = round( $tot['total'], 2 );
		if ( $fee_earned > 0.004 ) {
			$out[] = self::item_line( ++$n, 'Bank transfer fee (paid)', $fee_earned, $o['fee_item_id'] );
			$total = round( $total + $fee_earned, 2 );
		}
		return array( $out, $total, $n );
	}

	/**
	 * The lines for a payment mode, given everything the Hub counts as paid
	 * (in QuickBooks money). One cash discount line stands for the discounts
	 * the Hub actually gave, so once QuickBooks has the same payments its
	 * balance is exactly what the client owes now in that mode.
	 */
	protected static function lines_for_mode( $doc, $mode, $o, $paid_h, $fee_earned ) {
		list( $lines, $base, $n ) = self::base_lines( $doc, $o, $fee_earned );
		$p       = self::prices( $doc );
		$target  = 'bank' === $mode ? $p['cash'] : $p['card'];
		$pending = 'bank' === $mode && ! empty( $o['bank_on'] ) ? $p['bank_fee'] : 0.0;
		$adj     = round( $paid_h + $target - $base, 2 );
		if ( $adj > 0.02 ) {
			return new WP_Error( 'axh_qbo_mismatch', 'The payments on invoice ' . $doc['number'] . ' add up to ' . AXH_Bill::fmt( $adj ) . ' more than it costs in QuickBooks. Check the payments in both; online payment is off for it until they agree.', array( 'status' => 409 ) );
		}
		if ( $adj > -0.015 ) {
			$adj = 0.0; // a cent or two of rounding
		} elseif ( ! $p['cd'] ) {
			return new WP_Error( 'axh_qbo_mismatch', 'Invoice ' . $doc['number'] . ' is ' . AXH_Bill::fmt( -$adj ) . ' out between the Hub and QuickBooks. Check the payments in both; online payment is off for it until they agree.', array( 'status' => 409 ) );
		} else {
			$lines[] = self::item_line( ++$n, 'Cash discount (' . self::pct_txt( $p['pct'] ) . ') for paying by check, cash, Zelle or bank transfer', $adj, $o['disc_item_id'] );
		}
		if ( $pending > 0.004 ) {
			$lines[] = self::item_line( ++$n, 'Bank transfer fee (' . self::pct_txt( $p['bank_pct'] ) . ')', $pending, $o['fee_item_id'] );
		}
		return array( $lines, round( $base + $adj + $pending, 2 ) );
	}

	/** Stop QuickBooks taking money online for an invoice (its payment page stops offering it). */
	protected static function online_off( $doc_id, $qbo_id ) {
		if ( ! $qbo_id ) {
			return;
		}
		$cur = self::fetch_invoice( $qbo_id );
		if ( is_wp_error( $cur ) ) {
			return;
		}
		if ( ! empty( $cur['AllowOnlineCreditCardPayment'] ) || ! empty( $cur['AllowOnlineACHPayment'] ) ) {
			$res = self::api( 'POST', 'invoice', array( 'Id' => (string) $cur['Id'], 'SyncToken' => (string) $cur['SyncToken'], 'sparse' => true, 'AllowOnlineCreditCardPayment' => false, 'AllowOnlineACHPayment' => false ) );
			if ( is_wp_error( $res ) ) {
				self::fail( 'Could not turn off online payment in QuickBooks: ' . $res->get_error_message(), (int) $doc_id );
				return;
			}
			$cur = $res['Invoice'];
		}
		self::map_set( 'invoice', (int) $doc_id, array( 'mode' => 'open', 'sync_token' => (string) $cur['SyncToken'], 'meta' => array( 'link' => '' ) ) );
	}

	/** QuickBooks' own record of the invoice. */
	protected static function fetch_invoice( $qbo_id, $link = false ) {
		$g = self::api( 'GET', 'invoice/' . rawurlencode( $qbo_id ), null, $link ? array( 'include' => 'invoiceLink' ) : array() );
		return is_wp_error( $g ) ? $g : $g['Invoice'];
	}

	/** Find an invoice the Hub made earlier but never heard back about (a timed-out create). */
	protected static function adopt_invoice( $doc, $cust ) {
		$num = mb_substr( (string) $doc['number'], 0, 21 );
		$r   = self::query( "select * from Invoice where DocNumber = '" . self::q( $num ) . "'" );
		if ( is_wp_error( $r ) ) {
			return null;
		}
		foreach ( (array) ( $r['Invoice'] ?? array() ) as $inv ) {
			if ( (string) ( $inv['CustomerRef']['value'] ?? '' ) === (string) $cust && false !== strpos( (string) ( $inv['PrivateNote'] ?? '' ), self::MARK ) ) {
				return $inv;
			}
		}
		return null;
	}

	/**
	 * Bring the QuickBooks invoice in line with the Hub for a payment mode:
	 *  open – card price, no online payment;  card – card price, card only;
	 *  bank – cash price + bank fee, bank transfer only.
	 * Posts any Hub payments QuickBooks doesn't have yet (when the Hub keeps
	 * the books), then sets the lines. Returns the map row; for an online
	 * mode, meta.link is the payment page.
	 */
	public static function sync_doc( $doc_id, $mode = 'open' ) {
		if ( ! self::connected() ) {
			return new WP_Error( 'axh_qbo_off', 'QuickBooks is not connected.', array( 'status' => 409 ) );
		}
		if ( ! self::lock( 'doc_' . (int) $doc_id, 20 ) ) {
			return new WP_Error( 'axh_qbo_busy', 'QuickBooks is busy with this invoice. Try again in a moment.', array( 'status' => 503 ) );
		}
		$res = self::sync_locked( (int) $doc_id, $mode );
		self::unlock( 'doc_' . (int) $doc_id );
		return $res;
	}

	protected static function sync_locked( $doc_id, $mode ) {
		$r = AXH_Bill::doc_row( $doc_id, false );
		if ( ! $r || 'invoice' !== $r['type'] || 'draft' === $r['status'] ) {
			return new WP_Error( 'axh_qbo_doc', 'Only a sent invoice goes to QuickBooks.', array( 'status' => 409 ) );
		}
		$doc = AXH_Bill::shape_doc( $r, 'admin' );
		$map = self::map_get( 'invoice', $doc_id );
		if ( 'void' === $doc['status'] ) {
			return self::void_in_qbo( $doc, $map );
		}
		$o = self::items();
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		$cust = self::customer_for( $doc );
		if ( is_wp_error( $cust ) ) {
			return $cust;
		}
		$c    = AXH_Bill::customer_row( (int) $doc['customer_id'], false );
		$push = ! empty( $o['push_manual'] );
		$fee  = (float) ( $map ? ( $map['meta']['fee_earned'] ?? 0 ) : 0 );
		if ( $map && $map['qbo_id'] ) {
			$inv = self::fetch_invoice( $map['qbo_id'] );
			if ( is_wp_error( $inv ) ) {
				return $inv;
			}
		} else {
			$inv = self::adopt_invoice( $doc, $cust );
		}
		$body = array(
			'CustomerRef'  => array( 'value' => (string) $cust ),
			'TxnDate'      => $doc['issue_date'] ? $doc['issue_date'] : gmdate( 'Y-m-d' ),
			'PrivateNote'  => 'From ' . self::MARK . ', invoice ' . $doc['number'] . '. Change it in the Hub: edits here are replaced.',
			'CustomerMemo' => array( 'value' => mb_substr( 'Invoice ' . $doc['number'] . ( $doc['title'] ? ' — ' . $doc['title'] : '' ), 0, 1000 ) ),
		);
		if ( $doc['due_date'] ) {
			$body['DueDate'] = $doc['due_date'];
		}
		if ( $c && is_email( $c['email'] ) ) {
			$body['BillEmail'] = array( 'Address' => $c['email'] );
		}
		list( $base ) = self::base_lines( $doc, $o, $fee );
		// 1. The invoice exists in QuickBooks at its full price, nothing online.
		if ( ! $inv ) {
			$b   = $body + array( 'Line' => $base, 'DocNumber' => mb_substr( (string) $doc['number'], 0, 21 ), 'AllowOnlineCreditCardPayment' => false, 'AllowOnlineACHPayment' => false );
			$res = self::api( 'POST', 'invoice', $b );
			if ( '6140' === self::qcode( $res ) ) {
				unset( $b['DocNumber'] ); // the number is taken by something else in QuickBooks
				$res = self::api( 'POST', 'invoice', $b );
			}
			if ( is_wp_error( $res ) ) {
				self::fail( $res->get_error_message(), $doc_id );
				return $res;
			}
			$inv = $res['Invoice'];
			AXH_Bill::event( $doc_id, (int) $doc['customer_id'], 'qbo', 'Added to QuickBooks as invoice ' . ( $inv['DocNumber'] ?? $inv['Id'] ), 0 );
		}
		self::map_set( 'invoice', $doc_id, array( 'qbo_id' => (string) $inv['Id'], 'sync_token' => (string) $inv['SyncToken'], 'meta' => array( 'doc_number' => (string) ( $inv['DocNumber'] ?? '' ) ) ) );
		$qid    = (string) $inv['Id'];
		$online = in_array( $mode, array( 'card', 'bank' ), true );
		// 2. Payments entered in QuickBooks too are matched; the rest are posted (when the Hub keeps the books).
		$inv = self::push_payments( $doc, $inv, $cust, $body, $base, $push );
		if ( is_wp_error( $inv ) ) {
			self::online_off( $doc_id, $qid );
			return $inv;
		}
		// 3. Only take money online while both agree on what's been paid.
		$paid_q = round( (float) $inv['TotalAmt'] - (float) $inv['Balance'], 2 );
		$paid_h = self::hub_paid_q( $doc );
		if ( $online && abs( $paid_q - $paid_h ) > 0.02 ) {
			$e = new WP_Error(
				'axh_qbo_mismatch',
				$paid_q > $paid_h
					? 'QuickBooks has ' . AXH_Bill::fmt( $paid_q - $paid_h ) . ' more paid on invoice ' . $doc['number'] . ' than the Hub. Look for it under Settings → QuickBooks, or check the payments in both. Online payment is off for it until they agree.'
					: 'The Hub has ' . AXH_Bill::fmt( $paid_h - $paid_q ) . ' more paid (or credited) on invoice ' . $doc['number'] . ' than QuickBooks. Record the same payment, credit or refund in QuickBooks. Online payment is off for it until they agree.',
				array( 'status' => 409 )
			);
			self::fail( $e->get_error_message(), $doc_id );
			self::online_off( $doc_id, $qid );
			return $e;
		}
		// 4. The lines for the mode, so the balance is exactly what's owed.
		$lm = self::lines_for_mode( $doc, $mode, $o, $paid_h, $fee );
		if ( is_wp_error( $lm ) ) {
			self::fail( $lm->get_error_message(), $doc_id );
			self::online_off( $doc_id, $qid );
			return $lm;
		}
		list( $lines ) = $lm;
		$b   = $body + array(
			'Id'                           => (string) $inv['Id'],
			'SyncToken'                    => (string) $inv['SyncToken'],
			'sparse'                       => false,
			'DocNumber'                    => (string) ( $inv['DocNumber'] ?? mb_substr( (string) $doc['number'], 0, 21 ) ),
			'Line'                         => $lines,
			'AllowOnlineCreditCardPayment' => 'card' === $mode && ! empty( $o['card_on'] ),
			'AllowOnlineACHPayment'        => 'bank' === $mode && ! empty( $o['bank_on'] ),
		);
		$res = self::api( 'POST', 'invoice', $b );
		if ( is_wp_error( $res ) ) {
			self::fail( $res->get_error_message(), $doc_id );
			self::online_off( $doc_id, $qid );
			return $res;
		}
		$inv  = $res['Invoice'];
		$link = '';
		if ( $online ) {
			$g = self::fetch_invoice( $inv['Id'], true );
			if ( ! is_wp_error( $g ) ) {
				$inv  = $g;
				$link = (string) ( $g['InvoiceLink'] ?? '' );
			}
		}
		$meta = array( 'qbo_total' => (float) $inv['TotalAmt'], 'qbo_balance' => (float) $inv['Balance'], 'cash_total' => round( (float) $doc['total'], 2 ), 'link' => $link, 'mode_at' => time() );
		if ( $online && $link ) {
			// What this payment page asks, and the Hub balance it pays: a payment is priced from this, not from later settings.
			$meta['ask'] = array( 'mode' => $mode, 'qbo' => round( (float) $inv['Balance'], 2 ), 'cash' => self::prices( $doc )['cash'], 'at' => time() );
		}
		return self::map_set(
			'invoice',
			$doc_id,
			array(
				'qbo_id'     => (string) $inv['Id'],
				'sync_token' => (string) $inv['SyncToken'],
				'mode'       => $mode,
				'meta'       => $meta,
			)
		);
	}

	/**
	 * Bring the Hub's payments into QuickBooks without counting anything twice:
	 *  - one the Hub posted before but never heard back about is found by its tag;
	 *  - one someone also entered in QuickBooks by hand (same amount) is matched to it;
	 *  - the rest are posted, when the Hub keeps the books ($post), and only while
	 *    QuickBooks has no hand-entered payment on the invoice still to sort out.
	 * The invoice is first put back to its full price so every payment fits; the
	 * caller then sets the discount for what was actually paid.
	 */
	protected static function push_payments( $doc, $inv, $cust, $body, $base, $post ) {
		global $wpdb;
		$pt   = AXH_Bill::t( 'bpay' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pt} WHERE doc_id = %d AND kind IN ('payment','credit') AND voided_at IS NULL ORDER BY id ASC", (int) $doc['id'] ), ARRAY_A ); // phpcs:ignore
		$todo = array();
		foreach ( $rows as $p ) {
			if ( 0 !== strpos( (string) $p['reference'], 'qbo:' ) && ! self::map_get( 'payment', (int) $p['id'] ) ) {
				$todo[ (int) $p['id'] ] = $p;
			}
		}
		if ( ! $todo ) {
			return $inv;
		}
		$k      = self::k_of( $doc );
		$theirs = self::their_payments( $inv );
		// Posted before but never confirmed (a timeout)? Found by its tag rather than posted twice.
		foreach ( $theirs as $i => $t ) {
			if ( $t['tag'] ) {
				if ( isset( $todo[ $t['tag'] ] ) ) {
					self::map_set( 'payment', $t['tag'], array( 'qbo_id' => $t['id'], 'meta' => array( 'doc' => (int) $doc['id'] ) ) );
					unset( $todo[ $t['tag'] ] );
				}
				unset( $theirs[ $i ] );
			}
		}
		// Entered in QuickBooks by hand as well? Same amount: it's the same payment.
		foreach ( $todo as $id => $p ) {
			foreach ( $theirs as $i => $t ) {
				if ( '' === $t['online'] && $t['amt'] > 0.004 && abs( $t['amt'] - self::row_q( $p, $k ) ) < 0.011 && in_array( self::qpay_state( $t['id'], $inv['Id'] ), array( '', 'pending', 'ignored' ), true ) ) {
					self::matched( $doc, $p, $t, (string) $inv['Id'] );
					unset( $todo[ $id ], $theirs[ $i ] );
					break;
				}
			}
		}
		$post_rows = array_filter(
			$todo,
			function ( $p ) {
				return 'payment' === $p['kind'];
			}
		);
		if ( ! $post || ! $post_rows ) {
			return $inv;
		}
		foreach ( $theirs as $t ) {
			if ( '' === $t['online'] && $t['amt'] > 0.004 && in_array( self::qpay_state( $t['id'], $inv['Id'] ), array( '', 'pending' ), true ) ) {
				if ( '' === self::qpay_state( $t['id'], $inv['Id'] ) ) {
					self::mark_qpay( $t['id'], $inv['Id'], (int) $doc['id'], 'pending', $t['amt'], $t['date'] );
					self::tell_pending( (int) $doc['id'], $t['amt'] );
				}
				$e = new WP_Error( 'axh_qbo_mismatch', 'QuickBooks has a payment of ' . AXH_Bill::fmt( $t['amt'] ) . ' on invoice ' . $doc['number'] . ' that was entered there by hand. Record it or set it aside under Settings → QuickBooks; the Hub’s own payments go to QuickBooks once that’s settled.', array( 'status' => 409 ) );
				self::fail( $e->get_error_message(), (int) $doc['id'] );
				return $e;
			}
		}
		$full = false;
		foreach ( $post_rows as $p ) {
			if ( ! $full ) {
				// Back to the full price, nothing online, so the payment fits.
				$res = self::api( 'POST', 'invoice', $body + array( 'Id' => (string) $inv['Id'], 'SyncToken' => (string) $inv['SyncToken'], 'sparse' => false, 'DocNumber' => (string) ( $inv['DocNumber'] ?? '' ), 'Line' => $base, 'AllowOnlineCreditCardPayment' => false, 'AllowOnlineACHPayment' => false ) );
				if ( is_wp_error( $res ) ) {
					self::fail( 'Payment not recorded in QuickBooks: ' . $res->get_error_message(), (int) $doc['id'] );
					return $res;
				}
				$inv  = $res['Invoice'];
				$full = true;
			}
			$amt = self::row_q( $p, $k );
			if ( $amt <= 0 ) {
				continue;
			}
			if ( $amt > round( (float) $inv['Balance'], 2 ) + 0.004 ) {
				$e = new WP_Error( 'axh_qbo_mismatch', 'The ' . AXH_Bill::fmt( $amt ) . ' payment recorded here is more than QuickBooks shows owed on invoice ' . $doc['number'] . ' (' . AXH_Bill::fmt( $inv['Balance'] ) . '), so it was not posted. Check the payments in both.', array( 'status' => 409 ) );
				self::fail( $e->get_error_message(), (int) $doc['id'] );
				return $e;
			}
			$over = round( (float) $p['credit'], 2 ); // an overpayment stays with the customer in QuickBooks too, as unapplied credit
			$pay  = array(
				'CustomerRef'   => array( 'value' => (string) $cust ),
				'TotalAmt'      => round( $amt + $over, 2 ),
				'TxnDate'       => $p['received_at'],
				'PaymentRefNum' => mb_substr( (string) $p['reference'], 0, 21 ),
				'PrivateNote'   => mb_substr( self::MARK . ' payment [AXH#' . (int) $p['id'] . '] by ' . $p['method'] . ( $over > 0 ? ' (' . AXH_Bill::fmt( $over ) . ' over the invoice, kept as customer credit)' : '' ) . ( $p['note'] ? ': ' . $p['note'] : '' ), 0, 4000 ),
				'Line'          => array( array( 'Amount' => $amt, 'LinkedTxn' => array( array( 'TxnId' => (string) $inv['Id'], 'TxnType' => 'Invoice' ) ) ) ),
			);
			$res = self::api( 'POST', 'payment', $pay );
			if ( is_wp_error( $res ) ) {
				self::fail( 'Payment not recorded in QuickBooks: ' . $res->get_error_message(), (int) $doc['id'] );
				return $res;
			}
			self::map_set( 'payment', (int) $p['id'], array( 'qbo_id' => (string) $res['Payment']['Id'], 'meta' => array( 'doc' => (int) $doc['id'], 'amount' => $amt ) ) );
			AXH_Bill::event( (int) $doc['id'], (int) $doc['customer_id'], 'qbo', 'Payment of ' . AXH_Bill::fmt( $amt ) . ' recorded in QuickBooks', 0 );
			$inv = self::fetch_invoice( $inv['Id'] );
			if ( is_wp_error( $inv ) ) {
				return $inv;
			}
		}
		return $inv;
	}

	/** Payments on the invoice in QuickBooks that the Hub didn't post (as far as it knows). */
	protected static function their_payments( $inv ) {
		$out = array();
		foreach ( (array) ( $inv['LinkedTxn'] ?? array() ) as $lt ) {
			if ( 'Payment' !== ( $lt['TxnType'] ?? '' ) || self::map_by_qbo( 'payment', $lt['TxnId'] ) ) {
				continue;
			}
			$g = self::api( 'GET', 'payment/' . rawurlencode( $lt['TxnId'] ) );
			if ( is_wp_error( $g ) ) {
				continue;
			}
			$pay = $g['Payment'];
			$out[] = array(
				'id'     => (string) $lt['TxnId'],
				'amt'    => self::linked_amount( $pay, $inv['Id'] ),
				'tag'    => preg_match( '/\[AXH#(\d+)\]/', (string) ( $pay['PrivateNote'] ?? '' ), $m ) ? (int) $m[1] : 0,
				'online' => self::online_kind( $pay ),
				'date'   => substr( (string) ( $pay['TxnDate'] ?? '' ), 0, 10 ),
			);
		}
		return $out;
	}

	/** How much of a QuickBooks payment went to one invoice. */
	protected static function linked_amount( $pay, $qinv ) {
		$amt = 0.0;
		foreach ( (array) ( $pay['Line'] ?? array() ) as $pl ) {
			foreach ( (array) ( $pl['LinkedTxn'] ?? array() ) as $l2 ) {
				if ( 'Invoice' === ( $l2['TxnType'] ?? '' ) && (string) $l2['TxnId'] === (string) $qinv ) {
					$amt += (float) $pl['Amount'];
				}
			}
		}
		return round( $amt, 2 );
	}

	protected static function qpay_state( $pid, $qinv ) {
		$e = self::qpay_entry( $pid, $qinv );
		return $e ? (string) ( $e['state'] ?? '' ) : '';
	}

	/** A Hub payment and a payment entered in QuickBooks by hand are the same one. */
	protected static function matched( $doc, $p, $t, $qinv ) {
		self::map_set( 'payment', (int) $p['id'], array( 'qbo_id' => $t['id'], 'meta' => array( 'doc' => (int) $doc['id'], 'matched' => true ) ) );
		self::mark_qpay( $t['id'], $qinv, (int) $doc['id'], 'matched', $t['amt'], $t['date'] );
		AXH_Bill::event( (int) $doc['id'], (int) $doc['customer_id'], 'qbo', 'Payment of ' . AXH_Bill::fmt( $t['amt'] ) . ' was also entered in QuickBooks; matched, not posted again', 0 );
	}

	/** A payment entered in QuickBooks by hand: is it one the Hub already has? */
	protected static function match_hub( $doc, $t, $qinv ) {
		global $wpdb;
		$pt = AXH_Bill::t( 'bpay' );
		$k  = self::k_of( $doc );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$pt} WHERE doc_id = %d AND kind IN ('payment','credit') AND voided_at IS NULL ORDER BY id ASC", (int) $doc['id'] ), ARRAY_A ) as $p ) { // phpcs:ignore
			if ( 0 === strpos( (string) $p['reference'], 'qbo:' ) || self::map_get( 'payment', (int) $p['id'] ) ) {
				continue;
			}
			if ( abs( self::row_q( $p, $k ) - $t['amt'] ) < 0.011 ) {
				self::matched( $doc, $p, $t, $qinv );
				return (int) $p['id'];
			}
		}
		return 0;
	}

	/** A voided Hub invoice is voided in QuickBooks too, if nothing was paid on it there. */
	protected static function void_in_qbo( $doc, $map ) {
		if ( ! $map || ! $map['qbo_id'] || ! empty( $map['meta']['voided'] ) ) {
			return $map ? $map : new WP_Error( 'axh_qbo_doc', 'Not in QuickBooks.', array( 'status' => 409 ) );
		}
		$inv = self::fetch_invoice( $map['qbo_id'] );
		if ( is_wp_error( $inv ) ) {
			return $inv;
		}
		if ( (float) $inv['Balance'] < (float) $inv['TotalAmt'] - 0.004 ) {
			$e = new WP_Error( 'axh_qbo_paid', 'Invoice ' . $doc['number'] . ' was voided here but has payments in QuickBooks. Void or refund them there, then void the invoice.', array( 'status' => 409 ) );
			self::fail( $e->get_error_message(), (int) $doc['id'] );
			return $e;
		}
		$res = self::api( 'POST', 'invoice', array( 'Id' => (string) $inv['Id'], 'SyncToken' => (string) $inv['SyncToken'] ), array( 'operation' => 'void' ) );
		if ( is_wp_error( $res ) ) {
			self::fail( $res->get_error_message(), (int) $doc['id'] );
			return $res;
		}
		AXH_Bill::event( (int) $doc['id'], (int) $doc['customer_id'], 'qbo', 'Voided in QuickBooks too', 0 );
		return self::map_set( 'invoice', (int) $doc['id'], array( 'mode' => 'void', 'meta' => array( 'voided' => true, 'link' => '' ) ) );
	}

	/* ------------------------------------------------------ gateways */

	/** Pay buttons on the client's invoice page; each opens QuickBooks' payment page. */
	public static function gateways( $g ) {
		if ( ! self::connected() ) {
			return $g;
		}
		$o = self::opt();
		if ( ! empty( $o['card_on'] ) ) {
			$g['qbo_card'] = array(
				'label'  => 'Pay by card',
				'amount' => function ( $doc ) { return self::prices( $doc )['card']; },
				'note'   => function ( $doc ) { return self::cd_applies( $doc ) ? 'Card price' : ''; },
				'start'  => function ( $doc ) { return self::start( $doc, 'card' ); },
			);
		}
		if ( ! empty( $o['bank_on'] ) ) {
			$g['qbo_bank'] = array(
				'label'  => 'Pay by bank transfer',
				'amount' => function ( $doc ) { return self::prices( $doc )['bank']; },
				'note'   => function ( $doc ) {
					$p = self::prices( $doc );
					$s = array();
					if ( $p['cd'] ) {
						$s[] = self::pct_txt( $p['pct'] ) . ' cash discount';
					}
					if ( $p['bank_fee'] > 0 ) {
						$s[] = '+' . self::pct_txt( $p['bank_pct'] ) . ' bank fee';
					}
					return implode( ', ', $s );
				},
				'start'  => function ( $doc ) { return self::start( $doc, 'bank' ); },
			);
		}
		return $g;
	}

	protected static function start( $doc, $mode ) {
		$c = AXH_Bill::customer_row( (int) $doc['customer_id'], false );
		if ( ! $c || ! is_email( $c['email'] ) ) {
			return new WP_Error( 'axh_qbo_email', 'Online payment needs an email address on the invoice. Contact us and we will set it up.', array( 'status' => 409 ) );
		}
		$p   = self::prices( $doc );
		$map = self::sync_doc( (int) $doc['id'], $mode );
		if ( is_wp_error( $map ) ) {
			if ( ! in_array( $map->get_error_code(), array( 'axh_qbo_mismatch', 'axh_qbo_busy' ), true ) ) {
				self::fail( 'Online payment could not start for invoice ' . $doc['number'] . ': ' . $map->get_error_message(), (int) $doc['id'] );
			}
			return new WP_Error( 'axh_qbo_start', 'Online payment is not available right now. Please use another way to pay, or try again shortly.', array( 'status' => 502 ) );
		}
		$want = 'bank' === $mode ? $p['bank'] : $p['card'];
		$bal  = (float) ( $map['meta']['qbo_balance'] ?? -1 );
		if ( abs( $bal - $want ) > 0.02 ) {
			self::fail( 'QuickBooks asked ' . AXH_Bill::fmt( $bal ) . ' for invoice ' . $doc['number'] . ' instead of ' . AXH_Bill::fmt( $want ) . '. Online payment was stopped for it.', (int) $doc['id'] );
			self::stop_online( (int) $doc['id'] );
			return new WP_Error( 'axh_qbo_amount', 'Online payment is not available for this invoice. Please use another way to pay.', array( 'status' => 502 ) );
		}
		if ( empty( $map['meta']['link'] ) ) {
			self::fail( 'QuickBooks did not return a payment link for invoice ' . $doc['number'] . '. Check that QuickBooks Payments is on and the customer has an email.', (int) $doc['id'] );
			self::stop_online( (int) $doc['id'] );
			return new WP_Error( 'axh_qbo_link', 'Online payment is not available for this invoice. Please use another way to pay.', array( 'status' => 502 ) );
		}
		// Look for the payment soon after they leave for QuickBooks.
		if ( ! wp_next_scheduled( 'axh_qbo_poll_soon' ) ) {
			wp_schedule_single_event( time() + 180, 'axh_qbo_poll_soon' );
		}
		return $map['meta']['link'];
	}

	/** Turn off online payment for one invoice, under its lock. */
	protected static function stop_online( $doc_id ) {
		if ( ! self::lock( 'doc_' . (int) $doc_id, 10 ) ) {
			return;
		}
		$m = self::map_get( 'invoice', (int) $doc_id );
		if ( $m && $m['qbo_id'] ) {
			self::online_off( (int) $doc_id, $m['qbo_id'] );
		}
		self::unlock( 'doc_' . (int) $doc_id );
	}

	/* ---------------------------------------------------- payments in */

	public static function schedules( $s ) {
		$s['axh_10min'] = array( 'interval' => 600, 'display' => 'Every 10 minutes' );
		return $s;
	}

	public static function schedule_poll() {
		if ( self::connected() && ! wp_next_scheduled( 'axh_qbo_poll' ) ) {
			wp_schedule_event( time() + 120, 'axh_10min', 'axh_qbo_poll' );
		}
	}

	/** Bring in payments taken online in QuickBooks, and notice when they reach the bank. */
	public static function poll() {
		global $wpdb;
		$sum = array( 'recorded' => 0, 'cleared' => 0, 'pending' => 0, 'busy' => false );
		if ( ! self::connected() ) {
			return $sum;
		}
		if ( ! self::lock( 'poll' ) ) {
			$sum['busy'] = true;
			return $sum;
		}
		$mt   = AXH_DB::table( 'qbo_map' );
		$dt   = AXH_Bill::t( 'bdocs' );
		// Anything still owing, anything with a payment page out, and anything touched lately (a second payment on a paid invoice must not go unseen).
		$rows = $wpdb->get_col( "SELECT m.local_id FROM {$mt} m JOIN {$dt} d ON d.id = m.local_id WHERE m.kind = 'invoice' AND m.mode <> 'void' AND d.status NOT IN ('void','draft') AND (d.balance > 0.004 OR m.mode IN ('card','bank') OR m.synced_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)) ORDER BY m.local_id DESC LIMIT 200" ); // phpcs:ignore
		$stop = time() + 240;
		foreach ( $rows as $id ) {
			if ( time() > $stop ) {
				break;
			}
			$r = self::poll_invoice( (int) $id );
			if ( self::auth_error( $r ) ) {
				break;
			}
			if ( is_array( $r ) ) {
				$sum['recorded'] += $r['recorded'];
				$sum['pending']  += $r['pending'];
			}
		}
		$sum['cleared'] = self::poll_clearing();
		self::set_opt( array( 'last_poll' => current_time( 'mysql' ) ) );
		self::unlock( 'poll' );
		return $sum;
	}

	/**
	 * Look at the payments QuickBooks has on one invoice. Online card and bank
	 * payments become Hub payments. Anything else (a check typed into
	 * QuickBooks) waits for AdaptExec to say what it is, so nothing is
	 * counted twice.
	 */
	public static function poll_invoice( $doc_id ) {
		if ( ! self::lock( 'doc_' . (int) $doc_id, 10 ) ) {
			return array( 'recorded' => 0, 'pending' => 0 );
		}
		$out = self::poll_invoice_locked( (int) $doc_id );
		self::unlock( 'doc_' . (int) $doc_id );
		return $out;
	}

	protected static function poll_invoice_locked( $doc_id ) {
		$map = self::map_get( 'invoice', $doc_id );
		$out = array( 'recorded' => 0, 'pending' => 0 );
		if ( ! $map || ! $map['qbo_id'] ) {
			return $out;
		}
		$inv = self::fetch_invoice( $map['qbo_id'] );
		if ( is_wp_error( $inv ) ) {
			if ( ! self::auth_error( $inv ) ) {
				self::fail( 'Could not check invoice ' . ( $map['meta']['doc_number'] ?? $doc_id ) . ' in QuickBooks: ' . $inv->get_error_message() );
			}
			return $inv;
		}
		$changed = false;
		$linked  = array();
		foreach ( (array) ( $inv['LinkedTxn'] ?? array() ) as $lt ) {
			if ( 'Payment' !== ( $lt['TxnType'] ?? '' ) ) {
				continue;
			}
			$pid            = (string) $lt['TxnId'];
			$linked[ $pid ] = true;
			if ( self::map_by_qbo( 'payment', $pid ) ) {
				continue; // posted by the Hub, or matched to a Hub payment
			}
			$seen = self::map_get( 'qpay', (int) $pid );
			if ( $seen && isset( $seen['meta']['docs'][ (string) $map['qbo_id'] ] ) ) {
				continue; // already brought in, waiting, or set aside
			}
			$p = self::api( 'GET', 'payment/' . rawurlencode( $pid ) );
			if ( is_wp_error( $p ) ) {
				return $p;
			}
			$pay = $p['Payment'];
			if ( preg_match( '/\[AXH#(\d+)\]/', (string) ( $pay['PrivateNote'] ?? '' ), $m ) ) {
				self::map_set( 'payment', (int) $m[1], array( 'qbo_id' => $pid, 'meta' => array( 'doc' => $doc_id ) ) );
				continue; // ours, from a post that timed out
			}
			$amt = self::linked_amount( $pay, $map['qbo_id'] );
			if ( $amt <= 0 ) {
				continue;
			}
			$how  = self::online_kind( $pay );
			$date = (string) ( $pay['TxnDate'] ?? '' );
			if ( $how ) {
				$state = self::record_online( $doc_id, $map, $pay, $amt, $how ) ? 'imported' : 'pending';
			} else {
				$r     = AXH_Bill::doc_row( $doc_id, false );
				$state = $r && self::match_hub( AXH_Bill::shape_doc( $r, 'admin' ), array( 'id' => $pid, 'amt' => $amt, 'date' => substr( $date, 0, 10 ) ), (string) $map['qbo_id'] ) ? 'matched' : 'pending';
			}
			if ( 'matched' !== $state ) {
				self::mark_qpay( $pid, $map['qbo_id'], $doc_id, $state, $amt, $date, $how );
			}
			if ( 'imported' === $state ) {
				$out['recorded']++;
				$changed = true;
				$map     = self::map_get( 'invoice', $doc_id );
			} elseif ( 'pending' === $state ) {
				$out['pending']++;
				self::tell_pending( $doc_id, $amt, $how );
			} else {
				$changed = true;
			}
		}
		// An online payment the Hub counts that QuickBooks no longer has (voided or deleted there).
		$imp  = (array) ( $map['meta']['imported'] ?? array() );
		$gone = false;
		foreach ( $imp as $pid => $a ) {
			if ( is_numeric( $a ) && ! isset( $linked[ (string) $pid ] ) ) {
				self::payment_gone( $doc_id, (string) $pid, (string) $map['qbo_id'], (float) $a );
				$imp[ $pid ] = 'gone';
				$gone        = true;
			}
		}
		if ( $gone ) {
			self::map_set( 'invoice', $doc_id, array( 'meta' => array( 'imported' => $imp ) ) );
		}
		if ( $changed ) {
			// Settle the bank fee and discount lines for what was actually paid.
			self::sync_locked( $doc_id, 'open' );
		}
		return $out;
	}

	/** 'card' or 'bank' for a payment QuickBooks Payments took online; '' for anything typed in by hand. */
	protected static function online_kind( $pay ) {
		if ( ! empty( $pay['CreditCardPayment'] ) ) {
			return 'card';
		}
		$src = strtolower( (string) ( $pay['TxnSource'] ?? '' ) );
		if ( ( $src && false !== strpos( $src, 'intuit' ) ) || ! empty( $pay['ProcessPayment'] ) ) {
			return 'bank';
		}
		return '';
	}

	/** What the Hub decided about one QuickBooks payment on one invoice. $how: 'card' / 'bank' if taken online; null keeps what was known. */
	protected static function mark_qpay( $pid, $qinv, $doc_id, $state, $amt, $date, $how = null ) {
		$cur                    = self::map_get( 'qpay', (int) $pid );
		$docs                   = $cur ? (array) ( $cur['meta']['docs'] ?? array() ) : array();
		$was                    = $docs[ (string) $qinv ] ?? array();
		$docs[ (string) $qinv ] = array( 'doc' => (int) $doc_id, 'state' => $state, 'amount' => round( (float) $amt, 2 ), 'date' => substr( (string) $date, 0, 10 ), 'online' => null === $how ? (string) ( $was['online'] ?? '' ) : (string) $how );
		self::map_set( 'qpay', (int) $pid, array( 'qbo_id' => (string) $pid, 'meta' => array( 'docs' => $docs ) ) );
	}

	protected static function tell_pending( $doc_id, $amt, $how = '' ) {
		$r = AXH_Bill::doc_row( $doc_id, false );
		if ( ! $r ) {
			return;
		}
		if ( $how ) {
			$title = 'Online payment to check: ' . AXH_Bill::fmt( $amt ) . ' on ' . $r['number'];
			$body  = 'The client paid online through QuickBooks, but it is more than the Hub says is owed, or the invoice changed after they opened the payment page. Open Settings → QuickBooks to record it, and refund any difference in QuickBooks Payments.';
		} else {
			$title = 'QuickBooks has a payment the Hub doesn’t: ' . AXH_Bill::fmt( $amt ) . ' on ' . $r['number'];
			$body  = 'It was entered in QuickBooks, not taken online. Open Settings → QuickBooks to record it in the Hub, or set it aside, so nothing is counted twice.';
		}
		AXH_Bill::event( $doc_id, (int) $r['customer_id'], 'qbo', $title . '. Record it or set it aside in Settings → QuickBooks.', 0 );
		if ( class_exists( 'AXH_Notify' ) ) {
			AXH_Notify::managers_add( 'billing_paid', $title, $body, '#/settings', $r['lead_id'] ? (int) $r['lead_id'] : null );
		}
	}

	/** An online payment the Hub counts is gone from QuickBooks: say so once. */
	protected static function payment_gone( $doc_id, $pid, $qinv, $amt ) {
		$r = AXH_Bill::doc_row( $doc_id, false );
		$e = self::qpay_entry( $pid, $qinv );
		if ( $e && 'gone' === ( $e['state'] ?? '' ) ) {
			return; // already said
		}
		if ( $e ) {
			self::mark_qpay( $pid, $qinv, (int) ( $e['doc'] ?? $doc_id ), 'gone', $e['amount'], $e['date'] );
		}
		$msg = 'The online payment of ' . AXH_Bill::fmt( $amt ) . ' on invoice ' . ( $r ? $r['number'] : $doc_id ) . ' was voided or removed in QuickBooks, but the Hub still counts it. If the money didn’t arrive, void that payment in the Hub.';
		self::fail( $msg, $doc_id );
		if ( $r && class_exists( 'AXH_Notify' ) ) {
			AXH_Notify::managers_add( 'billing_paid', 'Online payment reversed in QuickBooks: ' . AXH_Bill::fmt( $amt ) . ' on ' . $r['number'], $msg, '#/billing/doc/' . (int) $doc_id, $r['lead_id'] ? (int) $r['lead_id'] : null );
		}
	}

	/** Turn an online QuickBooks payment into a Hub payment at the Hub price. */
	protected static function record_online( $doc_id, $map, $pay, $amt, $how ) {
		global $wpdb;
		$r = AXH_Bill::doc_row( $doc_id, false );
		if ( ! $r ) {
			return false;
		}
		$doc = AXH_Bill::shape_doc( $r, 'admin' );
		$p   = self::prices( $doc );
		$ref = 'qbo:' . $pay['Id'] . ':' . $map['qbo_id'];
		$pt  = AXH_Bill::t( 'bpay' );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pt} WHERE doc_id = %d AND reference = %s", $doc_id, $ref ) ) ) { // phpcs:ignore
			return true;
		}
		if ( $p['cash'] <= 0.004 ) {
			return false; // nothing owing in the Hub: AdaptExec decides
		}
		// Priced from what the payment page asked when the client opened it, not from today's settings.
		$ask = $map['meta']['ask'] ?? null;
		if ( is_array( $ask ) && $how === ( $ask['mode'] ?? '' ) && (float) ( $ask['qbo'] ?? 0 ) > 0 ) {
			if ( $amt > (float) $ask['qbo'] + 0.02 ) {
				return false;
			}
			$applied = abs( $amt - (float) $ask['qbo'] ) <= 0.02 ? (float) $ask['cash'] : round( $amt * (float) $ask['cash'] / (float) $ask['qbo'], 2 );
		} else {
			$due = 'bank' === $how ? $p['bank'] : $p['card'];
			if ( $due <= 0 || $amt > $due + 0.02 ) {
				return false;
			}
			$applied = abs( $amt - $due ) <= 0.02 ? $p['cash'] : round( $amt * $p['cash'] / $due, 2 );
		}
		if ( $applied <= 0 || $applied > $p['cash'] + 0.02 ) {
			return false; // more than the Hub says is owed now (it changed after the page was opened): AdaptExec decides
		}
		$applied = min( $applied, $p['cash'] );
		$fee     = 'bank' === $how ? max( 0, round( $amt - $applied, 2 ) ) : 0.0;
		$res = AXH_Bill::record_payment(
			array(
				'doc_id'      => $doc_id,
				'amount'      => $applied,
				'method'      => 'bank' === $how ? 'ach' : 'card',
				'reference'   => $ref,
				'received_at' => substr( (string) ( $pay['TxnDate'] ?? gmdate( 'Y-m-d' ) ), 0, 10 ),
				'cleared'     => self::has_deposit( $pay ) ? 1 : 0,
				'note'        => 'Paid online through QuickBooks by ' . ( 'bank' === $how ? 'bank transfer' : 'card' ) . ': ' . AXH_Bill::fmt( $amt ) . ( abs( $amt - $applied ) > 0.004 ? ' (' . AXH_Bill::fmt( $applied ) . ' at the Hub price; the rest is the ' . ( 'bank' === $how ? 'bank fee' : 'card price' ) . ' and stays in QuickBooks)' : '' ) . '.',
			),
			true
		);
		if ( is_wp_error( $res ) ) {
			self::fail( 'Could not record a QuickBooks payment in the Hub: ' . $res->get_error_message(), $doc_id );
			return false;
		}
		$imp                         = (array) ( $map['meta']['imported'] ?? array() );
		$imp[ (string) $pay['Id'] ] = $amt;
		$meta                        = array( 'paid_via' => 'bank' === $how ? 'bank' : 'card', 'imported' => $imp );
		if ( $fee > 0.004 ) {
			$meta['fee_earned'] = round( (float) ( $map['meta']['fee_earned'] ?? 0 ) + $fee, 2 );
		}
		if ( is_array( $ask ) && $how === ( $ask['mode'] ?? '' ) ) {
			// The page was paid (or partly paid): what's left of what it asks.
			$left        = round( (float) $ask['qbo'] - $amt, 2 );
			$meta['ask'] = $left > 0.02 ? array_merge( $ask, array( 'qbo' => $left, 'cash' => max( 0, round( (float) $ask['cash'] - $applied, 2 ) ) ) ) : null;
		}
		self::map_set( 'invoice', $doc_id, array( 'meta' => $meta ) );
		return true;
	}

	/** QuickBooks Payments deposits settled money itself; a linked deposit means it reached the bank. */
	protected static function has_deposit( $pay ) {
		foreach ( (array) ( $pay['LinkedTxn'] ?? array() ) as $l ) {
			if ( 'Deposit' === ( $l['TxnType'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Online payments count once QuickBooks deposits them. If QuickBooks never
	 * shows the deposit, a card payment counts after 3 days and a bank
	 * transfer after 7, as long as it still stands in QuickBooks.
	 */
	protected static function poll_clearing() {
		global $wpdb;
		$pt   = AXH_Bill::t( 'bpay' );
		$rows = $wpdb->get_results( "SELECT id, doc_id, reference, method, received_at FROM {$pt} WHERE reference LIKE 'qbo:%' AND cleared = 0 AND voided_at IS NULL AND kind = 'payment' AND method IN ('card','ach') ORDER BY id DESC LIMIT 50", ARRAY_A ); // phpcs:ignore
		$n    = 0;
		foreach ( $rows as $p ) {
			$parts = explode( ':', (string) $p['reference'] );
			$e     = self::qpay_entry( $parts[1] ?? 0, $parts[2] ?? '' );
			// Only money QuickBooks Payments took online; a check typed into QuickBooks clears like any check.
			if ( ! $e || ! in_array( $e['state'] ?? '', array( 'imported', 'recorded' ), true ) || '' === (string) ( $e['online'] ?? ( 'imported' === ( $e['state'] ?? '' ) ? 'card' : '' ) ) ) {
				continue;
			}
			$g = self::api( 'GET', 'payment/' . rawurlencode( $parts[1] ?? '' ) );
			if ( self::auth_error( $g ) ) {
				break;
			}
			if ( is_wp_error( $g ) && '610' !== self::qcode( $g ) ) {
				continue;
			}
			// Still standing? A voided payment comes back at $0; a deleted one isn't found.
			if ( is_wp_error( $g ) || self::linked_amount( $g['Payment'], $parts[2] ?? '' ) <= 0.004 ) {
				self::payment_gone( (int) $p['doc_id'], (string) ( $parts[1] ?? '' ), (string) ( $parts[2] ?? '' ), (float) $e['amount'] );
				continue;
			}
			$dep = self::has_deposit( $g['Payment'] );
			$age = ( time() - strtotime( $p['received_at'] . ' 00:00:00' ) ) / DAY_IN_SECONDS;
			if ( ! $dep && $age < ( 'ach' === $p['method'] ? 7 : 3 ) ) {
				continue;
			}
			$wpdb->update( $pt, array( 'cleared' => 1, 'cleared_at' => gmdate( 'Y-m-d' ) ), array( 'id' => (int) $p['id'] ) );
			AXH_Bill::recalc( (int) $p['doc_id'] );
			$r = AXH_Bill::doc_row( (int) $p['doc_id'], false );
			AXH_Bill::event( (int) $p['doc_id'], $r ? (int) $r['customer_id'] : 0, 'payment', $dep ? 'Online payment reached the bank (QuickBooks deposit)' : 'Online payment counted as cleared (still standing in QuickBooks after ' . (int) $age . ' days)', 0 );
			$n++;
		}
		return $n;
	}

	/** Payments in QuickBooks waiting for AdaptExec: what they are and on which invoice. */
	public static function pending() {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		$t   = AXH_DB::table( 'qbo_map' );
		$out = array();
		foreach ( (array) $wpdb->get_results( "SELECT * FROM {$t} WHERE kind = 'qpay' ORDER BY id DESC LIMIT 200", ARRAY_A ) as $row ) { // phpcs:ignore
			$m = json_decode( (string) $row['meta'], true );
			foreach ( (array) ( $m['docs'] ?? array() ) as $qinv => $d ) {
				if ( 'pending' !== ( $d['state'] ?? '' ) ) {
					continue;
				}
				$r     = AXH_Bill::doc_row( (int) $d['doc'], false );
				$s     = $r ? self::suggest( AXH_Bill::shape_doc( $r, 'admin' ), (string) $row['qbo_id'], $d ) : array( 'method' => 'check', 'amount' => (float) $d['amount'], 'page' => false );
				$out[] = array( 'payment' => (string) $row['qbo_id'], 'qinv' => (string) $qinv, 'doc' => (int) $d['doc'], 'number' => $r ? $r['number'] : '', 'amount' => (float) $d['amount'], 'date' => (string) $d['date'], 'online' => (string) ( $d['online'] ?? '' ), 'page' => (bool) $s['page'], 'method' => $s['method'], 'hub_amount' => $s['amount'] );
			}
		}
		return $out;
	}

	/** How a waiting QuickBooks payment would go into the Hub: online card or bank money back at the Hub price. */
	protected static function suggest( $doc, $pid, $d ) {
		$how = (string) ( $d['online'] ?? '' );
		$amt = round( (float) $d['amount'], 2 );
		$map = self::map_get( 'invoice', (int) $doc['id'] );
		$ask = $map ? ( $map['meta']['ask'] ?? null ) : null;
		$hit = is_array( $ask ) && abs( $amt - (float) ( $ask['qbo'] ?? 0 ) ) <= 0.02 && ( ! $how || $how === ( $ask['mode'] ?? '' ) );
		if ( ! $how && ! $hit ) {
			return array( 'method' => 'check', 'amount' => $amt, 'page' => false );
		}
		if ( $hit ) {
			// Exactly what the payment page asked: almost certainly paid there.
			$how = (string) $ask['mode'];
			$hub = (float) $ask['cash'];
		} elseif ( 'bank' === $how ) {
			$hub = round( $amt / ( 1 + (float) self::opt()['bank_fee_pct'] / 100 ), 2 );
		} else {
			$hub = round( $amt / self::k_of( $doc ), 2 );
		}
		return array( 'method' => 'bank' === $how ? 'ach' : 'card', 'amount' => min( $amt, $hub ), 'page' => $hit );
	}

	/* ---------------------------------------------------- Hub changes out */

	/**
	 * A payment recorded in the Hub: QuickBooks gets it in the background (when
	 * the Hub keeps the books), and in every case a payment page already out
	 * is re-priced or turned off, so nobody pays the old amount.
	 */
	public static function on_payment( $pid, $in = array() ) {
		if ( ! self::connected() ) {
			return;
		}
		$p = AXH_Bill::payment_row( (int) $pid );
		if ( ! $p || 'payment' !== $p['kind'] || 0 === strpos( (string) $p['reference'], 'qbo:' ) ) {
			return;
		}
		if ( ! empty( self::opt()['push_manual'] ) || self::map_get( 'invoice', (int) $p['doc_id'] ) ) {
			self::queue( (int) $p['doc_id'] );
		}
	}

	/** A voided or refunded payment, or applied credit. */
	public static function on_payment_changed( $pid, $what ) {
		if ( ! self::connected() ) {
			return;
		}
		$p = AXH_Bill::payment_row( (int) $pid );
		if ( ! $p ) {
			return;
		}
		$doc_id = (int) $p['doc_id'];
		$m      = self::map_get( 'payment', (int) $pid );
		$linked = (bool) self::map_get( 'invoice', $doc_id );
		if ( 'credit' === $what ) {
			if ( $linked ) {
				self::fail( 'Customer credit was applied to this invoice in the Hub. Apply the same credit to the invoice in QuickBooks (the customer has it there as unapplied credit); online payment for it waits until they agree.', $doc_id );
			}
		} elseif ( 0 === strpos( (string) $p['reference'], 'qbo:' ) ) {
			self::fail( 'An online payment was ' . ( 'refund' === $what ? 'refunded' : 'voided' ) . ' in the Hub. Make the matching refund in QuickBooks Payments.', $doc_id );
		} elseif ( 'void' === $what && $m && $m['qbo_id'] && empty( $m['meta']['voided'] ) ) {
			if ( self::lock( 'doc_' . $doc_id, 20 ) ) {
				$g   = self::api( 'GET', 'payment/' . rawurlencode( $m['qbo_id'] ) );
				$res = is_wp_error( $g ) ? $g : self::api( 'POST', 'payment', array( 'Id' => (string) $m['qbo_id'], 'SyncToken' => (string) $g['Payment']['SyncToken'], 'sparse' => true ), array( 'operation' => 'void' ) );
				if ( is_wp_error( $res ) ) {
					self::fail( 'The voided payment is still in QuickBooks: ' . $res->get_error_message(), $doc_id );
				} else {
					self::map_set( 'payment', (int) $pid, array( 'meta' => array( 'voided' => true ) ) );
					AXH_Bill::event( $doc_id, (int) $p['customer_id'], 'qbo', 'Payment voided in QuickBooks too', 0 );
				}
				self::unlock( 'doc_' . $doc_id );
			} else {
				self::fail( 'The voided payment is still in QuickBooks (it was busy). Void it there too.', $doc_id );
			}
		} elseif ( 'refund' === $what && $m ) {
			self::fail( 'A payment was refunded in the Hub. Record the refund in QuickBooks too.', $doc_id );
		}
		if ( $linked ) {
			self::queue( $doc_id ); // the balance changed: re-price, and turn off any payment page out
		}
	}

	public static function on_sent( $doc_id ) {
		if ( self::connected() ) {
			wp_schedule_single_event( time() + 5, 'axh_qbo_push', array( (int) $doc_id ) );
		}
	}

	public static function on_doc_changed( $doc_id, $what ) {
		if ( self::connected() && self::map_get( 'invoice', (int) $doc_id ) ) {
			self::queue( (int) $doc_id );
		}
	}

	protected static function queue( $doc_id ) {
		if ( ! wp_next_scheduled( 'axh_qbo_sync', array( (int) $doc_id ) ) ) {
			wp_schedule_single_event( time(), 'axh_qbo_sync', array( (int) $doc_id ) );
			if ( function_exists( 'spawn_cron' ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				spawn_cron();
			}
		}
	}

	public static function cron_push( $doc_id ) {
		$r = self::sync_doc( (int) $doc_id, 'open' );
		if ( is_wp_error( $r ) && 'axh_qbo_busy' === $r->get_error_code() ) {
			wp_schedule_single_event( time() + 60, 'axh_qbo_sync', array( (int) $doc_id ) ); // try again shortly
		}
	}

	/* ------------------------------------------------------------ REST */

	public static function routes() {
		$m = array( 'AXH_Rest', 'can_manage' );
		register_rest_route( self::NS, '/qbo', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_status' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/keys', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_keys' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/connect', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_connect' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/disconnect', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_disconnect' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/settings', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_settings' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/items', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_items' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/poll', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_poll' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/pending', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_pending' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/push/(?P<id>\d+)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_push' ), 'permission_callback' => $m ) );
		register_rest_route( self::NS, '/qbo/doc/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_doc' ), 'permission_callback' => array( 'AXH_Rest', 'can_use' ) ) );
		// Intuit sends the browser back here; the one-time state proves who started it.
		register_rest_route( self::NS, '/qbo/callback', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_callback' ), 'permission_callback' => '__return_true' ) );
	}

	protected static function url_id( $req ) {
		$u = $req->get_url_params();
		return isset( $u['id'] ) ? (int) $u['id'] : 0;
	}

	public static function rest_status( $req = null ) {
		$o = self::opt();
		return rest_ensure_response(
			array(
				'ready'        => self::ready(),
				'has_keys'     => (bool) ( $o['client_id'] && $o['client_secret'] ),
				'client_id'    => $o['client_id'] ? substr( $o['client_id'], 0, 6 ) . '…' : '',
				'connected'    => self::connected(),
				'company'      => $o['company'],
				'realm_id'     => $o['realm_id'],
				'connected_at' => $o['connected_at'],
				'refresh_days' => $o['refresh_exp'] ? max( 0, (int) floor( ( (int) $o['refresh_exp'] - time() ) / DAY_IN_SECONDS ) ) : null,
				'redirect_uri' => self::redirect_uri(),
				'item_id'      => $o['item_id'],
				'item_name'    => $o['item_name'],
				'cd_on'        => (bool) $o['cd_on'],
				'cd_pct'       => (float) $o['cd_pct'],
				'cd_from'      => (int) $o['cd_from'],
				'card_on'      => (bool) $o['card_on'],
				'bank_on'      => (bool) $o['bank_on'],
				'bank_fee_pct' => (float) $o['bank_fee_pct'],
				'push_manual'  => (bool) $o['push_manual'],
				'last_poll'    => $o['last_poll'],
				'errors'       => array_slice( (array) $o['errors'], 0, 8 ),
				'linked'       => self::linked_count(),
				'pending'      => self::pending(),
			)
		);
	}

	protected static function linked_count() {
		global $wpdb;
		if ( ! self::ready() ) {
			return 0;
		}
		$t = AXH_DB::table( 'qbo_map' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE kind = 'invoice'" ); // phpcs:ignore
	}

	public static function rest_keys( $req ) {
		$b  = (array) $req->get_json_params();
		$id = trim( sanitize_text_field( (string) ( $b['client_id'] ?? '' ) ) );
		$se = trim( (string) ( $b['client_secret'] ?? '' ) );
		if ( ! preg_match( '/^[A-Za-z0-9]{20,80}$/', $id ) || ! preg_match( '/^[A-Za-z0-9]{20,80}$/', $se ) ) {
			return new WP_Error( 'axh_qbo_keys', 'Those don’t look like QuickBooks keys. Copy the Client ID and Client Secret from the Production keys page of your Intuit app.', array( 'status' => 400 ) );
		}
		$o = self::opt();
		$f = array( 'client_id' => $id, 'client_secret' => self::enc( $se ) );
		if ( $o['client_id'] && $o['client_id'] !== $id ) {
			// A different app: its connection starts over. The company is compared when it reconnects.
			$f += array( 'access' => '', 'refresh' => '', 'access_exp' => 0, 'refresh_exp' => 0 );
		}
		self::set_opt( $f );
		return self::rest_status();
	}

	public static function rest_connect( $req ) {
		$o = self::opt();
		if ( ! $o['client_id'] || ! $o['client_secret'] ) {
			return new WP_Error( 'axh_qbo_keys', 'Add the app keys first.', array( 'status' => 409 ) );
		}
		$state = wp_generate_password( 32, false, false );
		set_transient( 'axh_qbo_state_' . $state, get_current_user_id(), 20 * MINUTE_IN_SECONDS );
		$url = add_query_arg(
			array(
				'client_id'     => rawurlencode( $o['client_id'] ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::SCOPE ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'state'         => rawurlencode( $state ),
			),
			self::auth_url()
		);
		return rest_ensure_response( array( 'url' => $url ) );
	}

	public static function rest_callback( $req ) {
		global $wpdb;
		$back  = home_url( '/hub/' );
		$state = sanitize_text_field( (string) $req->get_param( 'state' ) );
		$code  = sanitize_text_field( (string) $req->get_param( 'code' ) );
		$realm = preg_replace( '/[^0-9]/', '', (string) $req->get_param( 'realmId' ) );
		$uid   = $state ? (int) get_transient( 'axh_qbo_state_' . $state ) : 0;
		if ( $state ) {
			delete_transient( 'axh_qbo_state_' . $state );
		}
		if ( ! $uid || ! axh_is_manager( $uid ) ) {
			return self::bounce( $back, 'expired' );
		}
		if ( $req->get_param( 'error' ) || ! $code || ! $realm ) {
			return self::bounce( $back, 'declined' );
		}
		$j = self::token_call( array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirect_uri() ) );
		if ( is_wp_error( $j ) ) {
			self::fail( $j->get_error_message() );
			return self::bounce( $back, 'failed' );
		}
		$o = self::opt();
		$f = array( 'realm_id' => $realm, 'connected_at' => current_time( 'mysql' ), 'connected_by' => $uid, 'map_realm' => $realm );
		if ( $o['map_realm'] && $o['map_realm'] !== $realm ) {
			// Another company: its links belong to the other books. Start afresh.
			$t = AXH_DB::table( 'qbo_map' );
			$wpdb->query( "DELETE FROM {$t} WHERE kind IN ('customer','invoice','payment','qpay')" ); // phpcs:ignore
			$f += array( 'item_id' => '', 'item_name' => '', 'disc_item_id' => '', 'fee_item_id' => '' );
		}
		self::set_opt( $f );
		self::store_tokens( $j );
		$ci = self::api( 'GET', 'companyinfo/' . rawurlencode( $realm ) );
		if ( ! is_wp_error( $ci ) ) {
			self::set_opt( array( 'company' => (string) ( $ci['CompanyInfo']['CompanyName'] ?? '' ) ) );
		}
		self::schedule_poll();
		return self::bounce( $back, 'connected' );
	}

	protected static function bounce( $url, $result ) {
		$to = add_query_arg( 'qbo', rawurlencode( $result ), $url ) . '#/settings';
		$r  = new WP_REST_Response( null, 302 );
		$r->header( 'Location', $to );
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	}

	public static function rest_disconnect( $req ) {
		global $wpdb;
		$o = self::opt();
		if ( self::connected() && self::ready() ) {
			// Payment pages still out would take money the Hub can no longer see: turn them off first.
			$t = AXH_DB::table( 'qbo_map' );
			foreach ( (array) $wpdb->get_col( "SELECT local_id FROM {$t} WHERE kind = 'invoice' AND mode IN ('card','bank') ORDER BY id DESC LIMIT 50" ) as $id ) { // phpcs:ignore
				self::stop_online( (int) $id );
			}
		}
		if ( $o['refresh'] ) {
			wp_remote_post(
				defined( 'AXH_QBO_REVOKE' ) ? AXH_QBO_REVOKE : 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke',
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $o['client_id'] . ':' . self::dec( $o['client_secret'] ) ), 'Accept' => 'application/json', 'Content-Type' => 'application/json' ), // phpcs:ignore
					'body'    => wp_json_encode( array( 'token' => self::dec( $o['refresh'] ) ) ),
				)
			);
		}
		self::set_opt( array( 'access' => '', 'refresh' => '', 'access_exp' => 0, 'refresh_exp' => 0 ) );
		wp_clear_scheduled_hook( 'axh_qbo_poll' );
		return self::rest_status();
	}

	public static function rest_settings( $req ) {
		global $wpdb;
		$b = (array) $req->get_json_params();
		$o = self::opt();
		$f = array();
		foreach ( array( 'card_on', 'bank_on', 'push_manual' ) as $k ) {
			if ( array_key_exists( $k, $b ) ) {
				$f[ $k ] = ! empty( $b[ $k ] );
			}
		}
		if ( array_key_exists( 'bank_fee_pct', $b ) ) {
			$f['bank_fee_pct'] = min( 3, max( 0, round( (float) $b['bank_fee_pct'], 2 ) ) );
		}
		if ( array_key_exists( 'cd_pct', $b ) ) {
			$f['cd_pct'] = min( 10, max( 0, round( (float) $b['cd_pct'], 2 ) ) );
		}
		if ( array_key_exists( 'cd_on', $b ) ) {
			$on = ! empty( $b['cd_on'] );
			if ( $on && ! $o['cd_on'] ) {
				// Only invoices made from now on. Each keeps the pricing it was sent with.
				$f['cd_from'] = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id), 0) FROM ' . AXH_Bill::t( 'bdocs' ) ); // phpcs:ignore
			}
			$f['cd_on'] = $on;
		}
		if ( array_key_exists( 'item_id', $b ) && preg_match( '/^\d{1,20}$/', (string) $b['item_id'] ) ) {
			$f['item_id']   = (string) $b['item_id'];
			$f['item_name'] = sanitize_text_field( (string) ( $b['item_name'] ?? '' ) );
		}
		self::set_opt( $f );
		return self::rest_status();
	}

	public static function rest_items( $req ) {
		$r = self::query( 'select * from Item where Active = true maxresults 500' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$out = array();
		foreach ( (array) ( $r['Item'] ?? array() ) as $it ) {
			if ( in_array( $it['Type'] ?? '', array( 'Service', 'NonInventory' ), true ) && ! in_array( $it['Name'] ?? '', array( 'Cash discount', 'Bank transfer fee' ), true ) ) {
				$out[] = array( 'id' => (string) $it['Id'], 'name' => (string) ( $it['FullyQualifiedName'] ?? $it['Name'] ) );
			}
		}
		return rest_ensure_response( array( 'items' => $out ) );
	}

	public static function rest_poll( $req ) {
		$r = self::poll();
		return rest_ensure_response( $r + array( 'status' => self::rest_status()->get_data() ) );
	}

	/** AdaptExec says what a waiting QuickBooks payment is: record it in the Hub, or set it aside. */
	public static function rest_pending( $req ) {
		$b    = (array) $req->get_json_params();
		$pid  = preg_replace( '/[^0-9]/', '', (string) ( $b['payment'] ?? '' ) );
		$qinv = preg_replace( '/[^0-9]/', '', (string) ( $b['qinv'] ?? '' ) );
		$d    = $pid ? self::qpay_entry( $pid, $qinv ) : null;
		if ( ! $d || 'pending' !== ( $d['state'] ?? '' ) ) {
			return new WP_Error( 'axh_not_found', 'That payment is no longer waiting.', array( 'status' => 404 ) );
		}
		$doc_id = (int) $d['doc'];
		if ( ! self::lock( 'doc_' . $doc_id, 10 ) ) {
			return new WP_Error( 'axh_qbo_busy', 'QuickBooks is busy with this invoice. Try again in a moment.', array( 'status' => 503 ) );
		}
		$res = self::pending_locked( $b, $pid, $qinv, $doc_id );
		self::unlock( 'doc_' . $doc_id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::queue( $doc_id );
		return self::rest_status();
	}

	protected static function pending_locked( $b, $pid, $qinv, $doc_id ) {
		global $wpdb;
		$d = self::qpay_entry( $pid, $qinv ); // again, now that nobody else is at it
		if ( ! $d || 'pending' !== ( $d['state'] ?? '' ) ) {
			return new WP_Error( 'axh_not_found', 'That payment is no longer waiting.', array( 'status' => 404 ) );
		}
		$how = (string) ( $d['online'] ?? '' );
		if ( 'record' !== ( $b['action'] ?? '' ) ) {
			self::mark_qpay( $pid, $qinv, $doc_id, 'ignored', $d['amount'], $d['date'] );
			return true;
		}
		$ref = 'qbo:' . $pid . ':' . $qinv;
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . AXH_Bill::t( 'bpay' ) . ' WHERE doc_id = %d AND reference = %s AND voided_at IS NULL', $doc_id, $ref ) ) ) { // phpcs:ignore
			self::mark_qpay( $pid, $qinv, $doc_id, 'recorded', $d['amount'], $d['date'] );
			return true; // already in the Hub
		}
		$r = AXH_Bill::doc_row( $doc_id, false );
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'That invoice is gone from the Hub.', array( 'status' => 404 ) );
		}
		$doc    = AXH_Bill::shape_doc( $r, 'admin' );
		$s      = self::suggest( $doc, $pid, $d );
		$method = is_string( $b['method'] ?? null ) && isset( AXH_Bill::methods()[ $b['method'] ] ) ? $b['method'] : $s['method'];
		$amt    = isset( $b['amount'] ) && is_numeric( $b['amount'] ) ? round( (float) $b['amount'], 2 ) : $s['amount'];
		if ( $amt <= 0 || $amt > (float) $d['amount'] + 0.01 ) {
			return new WP_Error( 'axh_bad_amount', 'Record between $0.01 and ' . AXH_Bill::fmt( $d['amount'] ) . ' (what QuickBooks received).', array( 'status' => 400 ) );
		}
		if ( ! $how && ! empty( $s['page'] ) && in_array( $method, array( 'card', 'ach' ), true ) ) {
			$how = 'ach' === $method ? 'bank' : 'card'; // paid on the payment page after all
		}
		$in = array(
			'doc_id'      => $doc_id,
			'amount'      => $amt,
			'method'      => $method,
			'reference'   => $ref,
			'received_at' => $d['date'] ? $d['date'] : gmdate( 'Y-m-d' ),
			'note'        => ( $how ? 'Paid online through QuickBooks (' . AXH_Bill::fmt( $d['amount'] ) . ')' : 'Entered in QuickBooks (' . AXH_Bill::fmt( $d['amount'] ) . ')' ) . '; recorded in the Hub by ' . wp_get_current_user()->display_name . '.',
		);
		if ( $how ) {
			$in['cleared'] = 0; // counts once QuickBooks deposits it, like any online payment
		}
		$res = AXH_Bill::record_payment( $in );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		self::mark_qpay( $pid, $qinv, $doc_id, 'recorded', $d['amount'], $d['date'], $how );
		if ( $how ) {
			$map  = self::map_get( 'invoice', $doc_id );
			$imp  = $map ? (array) ( $map['meta']['imported'] ?? array() ) : array();
			$meta = array( 'imported' => $imp );
			$meta['imported'][ (string) $pid ] = (float) $d['amount'];
			if ( ! empty( $s['page'] ) ) {
				$meta['ask'] = null; // that page is paid
			}
			if ( 'bank' === $how && (float) $d['amount'] - $amt > 0.004 ) {
				$meta['fee_earned'] = round( (float) ( $map['meta']['fee_earned'] ?? 0 ) + (float) $d['amount'] - $amt, 2 );
			}
			self::map_set( 'invoice', $doc_id, array( 'meta' => $meta ) );
		}
		return true;
	}

	public static function rest_push( $req ) {
		$m = self::sync_doc( self::url_id( $req ), 'open' );
		return is_wp_error( $m ) ? $m : rest_ensure_response( array( 'ok' => true, 'qbo_id' => $m['qbo_id'] ) );
	}

	/** What the Hub knows about one invoice in QuickBooks (for the invoice page in the Hub). */
	public static function rest_doc( $req ) {
		$r = AXH_Bill::doc_row( self::url_id( $req ) );
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$doc = AXH_Bill::shape_doc( $r );
		$map = self::map_get( 'invoice', (int) $r['id'] );
		return rest_ensure_response(
			array(
				'connected' => self::connected(),
				'linked'    => (bool) ( $map && $map['qbo_id'] ),
				'number'    => $map ? ( $map['meta']['doc_number'] ?? '' ) : '',
				'mode'      => $map ? $map['mode'] : '',
				'synced_at' => $map ? $map['synced_at'] : '',
				'prices'    => 'invoice' === $r['type'] && 'void' !== $r['status'] ? self::prices( $doc ) : null,
			)
		);
	}
}

AXH_QBO::init();
