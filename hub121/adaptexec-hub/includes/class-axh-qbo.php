<?php
/**
 * QuickBooks Online (1.21.0).
 *
 * Connects Billing to AdaptExec's own QuickBooks company:
 *  - invoices go to QuickBooks with the client as a QuickBooks customer;
 *  - the client pays online on QuickBooks' own payment page (card or bank
 *    transfer), and the Hub records the payment when QuickBooks has it;
 *  - checks, cash and Zelle recorded in the Hub are recorded in QuickBooks
 *    against the same invoice, so the books and the Hub agree.
 *
 * Cash discount pricing: on invoices created after it was switched on, the
 * client-facing price is the card price (the Hub's total grossed up by the
 * discount). Paying by check, cash, Zelle or bank transfer takes the
 * discount off; bank transfer then adds its own fee. Inside the Hub every
 * total, payment and commission stays at the cash price.
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

	/** Methods that take the cash discount. */
	const CASHLIKE = array( 'check', 'cash', 'zelle', 'ach', 'other' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 12 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'axh_bill_gateways', array( __CLASS__, 'gateways' ) );
		add_filter( 'axh_bill_display_doc', array( __CLASS__, 'display_doc' ), 10, 2 );
		add_action( 'axh_bill_payment_recorded', array( __CLASS__, 'on_payment' ), 10, 2 );
		add_action( 'axh_bill_doc_sent', array( __CLASS__, 'on_sent' ) );
		add_action( 'axh_qbo_push', array( __CLASS__, 'cron_push' ) );
		add_action( 'axh_qbo_poll', array( __CLASS__, 'poll' ) );
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

	/** Remember the last few problems so AdaptExec can see them in Settings. */
	protected static function fail( $msg, $doc_id = 0 ) {
		$o           = self::opt();
		$e           = (array) $o['errors'];
		array_unshift( $e, array( 'at' => current_time( 'mysql' ), 'msg' => mb_substr( (string) $msg, 0, 400 ), 'doc' => (int) $doc_id ) );
		$o['errors'] = array_slice( $e, 0, 20 );
		self::save_opt( $o );
		if ( $doc_id && class_exists( 'AXH_Bill' ) ) {
			$r = AXH_Bill::doc_row( $doc_id, false );
			if ( $r ) {
				AXH_Bill::event( $doc_id, (int) $r['customer_id'], 'qbo', 'QuickBooks: ' . mb_substr( (string) $msg, 0, 300 ), 0 );
			}
		}
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
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$j    = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || empty( $j['access_token'] ) ) {
			$why = is_array( $j ) ? ( $j['error_description'] ?? ( $j['error'] ?? '' ) ) : '';
			return new WP_Error( 'axh_qbo_token', 'QuickBooks did not accept the request' . ( $why ? ': ' . $why : '' ) . ' (' . $code . ').', array( 'status' => 502 ) );
		}
		return $j;
	}

	protected static function store_tokens( $j ) {
		$o                = self::opt();
		$o['access']      = self::enc( $j['access_token'] );
		$o['access_exp']  = time() + (int) ( $j['expires_in'] ?? 3600 ) - 120;
		if ( ! empty( $j['refresh_token'] ) ) {
			$o['refresh']     = self::enc( $j['refresh_token'] );
			$o['refresh_exp'] = time() + (int) ( $j['x_refresh_token_expires_in'] ?? 8640000 );
		}
		self::save_opt( $o );
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
		if ( get_transient( 'axh_qbo_refreshing' ) && ! $force ) {
			usleep( 800000 );
			$o = self::opt();
			if ( $o['access'] && time() < (int) $o['access_exp'] ) {
				return self::dec( $o['access'] );
			}
		}
		set_transient( 'axh_qbo_refreshing', 1, 20 );
		$j = self::token_call( array( 'grant_type' => 'refresh_token', 'refresh_token' => self::dec( $o['refresh'] ) ) );
		delete_transient( 'axh_qbo_refreshing' );
		if ( is_wp_error( $j ) ) {
			self::fail( 'Could not refresh the connection. Reconnect QuickBooks in Settings. ' . $j->get_error_message() );
			return $j;
		}
		self::store_tokens( $j );
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
			$err  = is_array( $j ) ? ( $j['Fault']['Error'][0] ?? array() ) : array();
			$msg  = trim( ( $err['Message'] ?? '' ) . ( ! empty( $err['Detail'] ) ? ' — ' . $err['Detail'] : '' ) );
			return new WP_Error( 'axh_qbo_api', 'QuickBooks: ' . ( $msg ? $msg : 'error ' . $code ), array( 'status' => 502, 'qbo_code' => (string) ( $err['code'] ?? '' ) ) );
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
			$o['item_id']   = (string) $it['Id'];
			$o['item_name'] = (string) $it['Name'];
			self::save_opt( $o );
		}
		foreach ( array( 'disc_item_id' => 'Cash discount', 'fee_item_id' => 'Bank transfer fee' ) as $key => $name ) {
			if ( $o[ $key ] ) {
				continue;
			}
			$id = self::ensure_item( $name, $o['item_id'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$o[ $key ] = $id;
			self::save_opt( $o );
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
		$c = self::api( 'POST', 'item', array( 'Name' => $name, 'Type' => 'Service', 'Taxable' => false, 'IncomeAccountRef' => array( 'value' => (string) $acct ), 'Description' => $name . ' (AdaptExec Hub)' ) );
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
				'Notes'       => 'Added by AdaptExec Hub.',
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
			if ( is_wp_error( $r ) && '6240' === (string) ( $r->get_error_data()['qbo_code'] ?? '' ) ) {
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

	/** Does cash discount pricing apply to this invoice? Only to ones made after it was switched on. */
	public static function cd_applies( $doc ) {
		$o = self::opt();
		return ! empty( $o['cd_on'] ) && (float) $o['cd_pct'] > 0 && 'invoice' === ( $doc['type'] ?? '' ) && (int) $doc['id'] > (int) $o['cd_from'];
	}

	protected static function k() {
		$p = min( 10, max( 0, (float) self::opt()['cd_pct'] ) );
		return 1 / ( 1 - $p / 100 );
	}

	/**
	 * The card-price view of an invoice: every rate grossed up so the
	 * discount brings it back to exactly the Hub's price.
	 */
	public static function card_view( $doc ) {
		$k     = self::k();
		$lines = array();
		foreach ( AXH_Bill::lines_for( $doc ) as $l ) {
			$l['rate'] = round( (float) $l['rate'] * $k, 2 );
			if ( 'amt' === ( $l['disc_type'] ?? 'pct' ) ) {
				$l['disc'] = round( (float) $l['disc'] * $k, 2 );
			}
			$lines[] = $l;
		}
		$d = $doc;
		if ( 'amt' === ( $doc['discount_type'] ?? 'pct' ) ) {
			$d['discount_value'] = round( (float) $doc['discount_value'] * $k, 2 );
		}
		$tot = AXH_Bill::calc( $lines, $d );
		return array( 'lines' => $lines, 'doc' => $d, 'tot' => $tot );
	}

	/** Amounts the client sees for each way of paying the open balance. */
	public static function prices( $doc ) {
		$o    = self::opt();
		$cash = max( 0, round( (float) $doc['total'] - self::paid_cash( $doc ), 2 ) );
		$cd   = self::cd_applies( $doc );
		$card = $cash;
		if ( $cd ) {
			$cv   = self::card_view( $doc );
			$card = round( $cv['tot']['total'] * ( (float) $doc['total'] > 0 ? $cash / (float) $doc['total'] : 0 ), 2 );
		}
		$fee  = ! empty( $o['bank_on'] ) ? round( $cash * (float) $o['bank_fee_pct'] / 100, 2 ) : 0.0;
		return array(
			'cd'       => $cd,
			'pct'      => (float) $o['cd_pct'],
			'cash'     => $cash,
			'card'     => $card,
			'discount' => round( $card - $cash, 2 ),
			'bank'     => round( $cash + $fee, 2 ),
			'bank_fee' => $fee,
			'bank_pct' => (float) $o['bank_fee_pct'],
		);
	}

	protected static function paid_cash( $doc ) {
		return isset( $doc['amount_paid'] ) ? (float) $doc['amount_paid'] : AXH_Bill::invoice_paid( (int) $doc['id'] );
	}

	/* ------------------------------------------------------- display */

	/**
	 * What the client sees: on a cash-discount invoice with nothing paid yet
	 * (or paid by card), the card price is the price on the page. The real
	 * figures ride along in _cd so the page can show the discount.
	 */
	public static function display_doc( $doc, $opts = array() ) {
		if ( ! is_array( $doc ) || 'invoice' !== ( $doc['type'] ?? '' ) || ! self::cd_applies( $doc ) || 'void' === ( $doc['status'] ?? '' ) ) {
			return $doc;
		}
		$map    = self::map_get( 'invoice', (int) $doc['id'] );
		$bycard = $map && 'card' === ( $map['meta']['paid_via'] ?? '' );
		$paid   = (float) ( $doc['amount_paid'] ?? 0 );
		$p      = self::prices( $doc );
		if ( $paid > 0.004 && ! $bycard ) {
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
		$doc['amount_paid']    = $bycard ? $doc['total'] - max( 0, $p['card'] ) : 0.0;
		$doc['balance']        = $bycard ? max( 0, $p['card'] ) : $doc['total'];
		$doc['_cd']            = array_merge( $p, array( 'view' => 'card' ) );
		return $doc;
	}

	/* ---------------------------------------------------- invoices out */

	protected static function line_desc( $l ) {
		$d = trim( (string) ( $l['name'] ?? '' ) );
		if ( ! empty( $l['desc'] ) ) {
			$d .= ( $d ? "\n" : '' ) . trim( (string) $l['desc'] );
		}
		return mb_substr( $d, 0, 4000 );
	}

	/** The QuickBooks lines for an invoice in a given payment mode. */
	protected static function build_lines( $doc, $mode, $o ) {
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
			$out[] = array(
				'LineNum'             => ++$n,
				'Description'         => self::line_desc( $l ) . ( $plain ? '' : "\n(includes a line discount)" ),
				'Amount'              => $net,
				'DetailType'          => 'SalesItemLineDetail',
				'SalesItemLineDetail' => array(
					'ItemRef'    => array( 'value' => (string) $o['item_id'] ),
					'Qty'        => $plain ? (float) $l['qty'] : 1,
					'UnitPrice'  => $plain ? (float) $l['rate'] : $net,
					'TaxCodeRef' => array( 'value' => 'NON' ),
				),
			);
		}
		if ( $tot['discount'] > 0.004 ) {
			$out[] = array( 'Amount' => $tot['discount'], 'DetailType' => 'DiscountLineDetail', 'DiscountLineDetail' => array( 'PercentBased' => false ) );
		}
		if ( $tot['tax'] > 0.004 ) {
			$out[] = array(
				'LineNum'             => ++$n,
				'Description'         => ( $doc['tax_label'] ? $doc['tax_label'] : 'Sales tax' ) . ' (' . rtrim( rtrim( number_format( (float) $doc['tax_rate'], 3 ), '0' ), '.' ) . '%, from AdaptExec Hub)',
				'Amount'              => $tot['tax'],
				'DetailType'          => 'SalesItemLineDetail',
				'SalesItemLineDetail' => array( 'ItemRef' => array( 'value' => (string) $o['item_id'] ), 'Qty' => 1, 'UnitPrice' => $tot['tax'], 'TaxCodeRef' => array( 'value' => 'NON' ) ),
			);
		}
		$total = $tot['total'];
		$cash  = round( (float) $doc['total'], 2 );
		if ( $cd && in_array( $mode, array( 'bank', 'cash' ), true ) ) {
			$disc  = round( $total - $cash, 2 );
			$out[] = array(
				'LineNum'             => ++$n,
				'Description'         => 'Cash discount (' . rtrim( rtrim( number_format( (float) $o['cd_pct'], 2 ), '0' ), '.' ) . '%) for paying by ' . ( 'bank' === $mode ? 'bank transfer' : 'check, cash or Zelle' ),
				'Amount'              => -$disc,
				'DetailType'          => 'SalesItemLineDetail',
				'SalesItemLineDetail' => array( 'ItemRef' => array( 'value' => (string) $o['disc_item_id'] ), 'Qty' => 1, 'UnitPrice' => -$disc, 'TaxCodeRef' => array( 'value' => 'NON' ) ),
			);
			$total = $cash;
		}
		if ( 'bank' === $mode && ! empty( $o['bank_on'] ) && (float) $o['bank_fee_pct'] > 0 ) {
			$fee   = round( $cash * (float) $o['bank_fee_pct'] / 100, 2 );
			$out[] = array(
				'LineNum'             => ++$n,
				'Description'         => 'Bank transfer fee (' . rtrim( rtrim( number_format( (float) $o['bank_fee_pct'], 2 ), '0' ), '.' ) . '%)',
				'Amount'              => $fee,
				'DetailType'          => 'SalesItemLineDetail',
				'SalesItemLineDetail' => array( 'ItemRef' => array( 'value' => (string) $o['fee_item_id'] ), 'Qty' => 1, 'UnitPrice' => $fee, 'TaxCodeRef' => array( 'value' => 'NON' ) ),
			);
			$total = round( $total + $fee, 2 );
		}
		return array( $out, round( $total, 2 ) );
	}

	/**
	 * Create or update the invoice in QuickBooks for the given payment mode:
	 *  card – card price, card only;  bank – cash price + bank fee, bank only;
	 *  cash – cash price, no online payment (settled by check, cash or Zelle);
	 *  open – card price, no online payment yet (just sent).
	 * Returns the map row (with meta.link when online payment is on).
	 */
	public static function push_invoice( $doc_id, $mode = 'open' ) {
		if ( ! self::connected() ) {
			return new WP_Error( 'axh_qbo_off', 'QuickBooks is not connected.', array( 'status' => 409 ) );
		}
		$r = AXH_Bill::doc_row( $doc_id, false );
		if ( ! $r || 'invoice' !== $r['type'] || in_array( $r['status'], array( 'draft', 'void' ), true ) ) {
			return new WP_Error( 'axh_qbo_doc', 'Only a sent invoice goes to QuickBooks.', array( 'status' => 409 ) );
		}
		$doc  = AXH_Bill::shape_doc( $r, 'admin' );
		$o    = self::items();
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		$cust = self::customer_for( $doc );
		if ( is_wp_error( $cust ) ) {
			return $cust;
		}
		$c    = AXH_Bill::customer_row( (int) $doc['customer_id'], false );
		$map  = self::map_get( 'invoice', (int) $doc_id );
		list( $lines, $total ) = self::build_lines( $doc, $mode, $o );
		$body = array(
			'CustomerRef'                  => array( 'value' => (string) $cust ),
			'Line'                         => $lines,
			'TxnDate'                      => $doc['issue_date'] ? $doc['issue_date'] : gmdate( 'Y-m-d' ),
			'AllowOnlineCreditCardPayment' => 'card' === $mode && ! empty( $o['card_on'] ),
			'AllowOnlineACHPayment'        => 'bank' === $mode && ! empty( $o['bank_on'] ),
			'PrivateNote'                  => 'From AdaptExec Hub, invoice ' . $doc['number'] . '. Change it in the Hub: edits here are replaced.',
			'CustomerMemo'                 => array( 'value' => mb_substr( 'Invoice ' . $doc['number'] . ( $doc['title'] ? ' — ' . $doc['title'] : '' ), 0, 1000 ) ),
		);
		if ( $doc['due_date'] ) {
			$body['DueDate'] = $doc['due_date'];
		}
		if ( $c && is_email( $c['email'] ) ) {
			$body['BillEmail'] = array( 'Address' => $c['email'] );
		}
		$num = mb_substr( (string) $doc['number'], 0, 21 );
		if ( $map && $map['qbo_id'] ) {
			$cur = self::api( 'GET', 'invoice/' . rawurlencode( $map['qbo_id'] ) );
			if ( is_wp_error( $cur ) ) {
				return $cur;
			}
			$inv     = $cur['Invoice'];
			$paid_q  = round( (float) $inv['TotalAmt'] - (float) $inv['Balance'], 2 );
			$settled = (float) $inv['Balance'] <= 0.004;
			if ( $settled || ( $paid_q > 0.004 && ( $total < $paid_q - 0.004 || in_array( $mode, array( 'card', 'bank' ), true ) ) ) ) {
				// Money is already on it in QuickBooks: leave its lines alone
				// (a check that closes it at the cash price is the exception).
				return self::map_set( 'invoice', (int) $doc_id, array( 'sync_token' => (string) $inv['SyncToken'], 'meta' => array( 'qbo_total' => (float) $inv['TotalAmt'], 'locked' => true ) ) );
			}
			$body['Id']        = (string) $inv['Id'];
			$body['SyncToken'] = (string) $inv['SyncToken'];
			$body['sparse']    = false;
			$body['DocNumber'] = $inv['DocNumber'] ?? $num;
			$res               = self::api( 'POST', 'invoice', $body );
		} else {
			$body['DocNumber'] = $num;
			$res               = self::api( 'POST', 'invoice', $body );
			if ( is_wp_error( $res ) && '6140' === (string) ( $res->get_error_data()['qbo_code'] ?? '' ) ) {
				unset( $body['DocNumber'] ); // number already used in QuickBooks: let it number this one
				$res = self::api( 'POST', 'invoice', $body );
			}
		}
		if ( is_wp_error( $res ) ) {
			self::fail( $res->get_error_message(), (int) $doc_id );
			return $res;
		}
		$inv  = $res['Invoice'];
		$link = '';
		if ( in_array( $mode, array( 'card', 'bank' ), true ) ) {
			$g    = self::api( 'GET', 'invoice/' . rawurlencode( $inv['Id'] ), null, array( 'include' => 'invoiceLink' ) );
			$link = is_wp_error( $g ) ? '' : (string) ( $g['Invoice']['InvoiceLink'] ?? '' );
			if ( ! is_wp_error( $g ) ) {
				$inv = $g['Invoice'];
			}
		}
		$first = ! $map;
		$map   = self::map_set(
			'invoice',
			(int) $doc_id,
			array(
				'qbo_id'     => (string) $inv['Id'],
				'sync_token' => (string) $inv['SyncToken'],
				'mode'       => $mode,
				'meta'       => array( 'qbo_total' => (float) $inv['TotalAmt'], 'cash_total' => round( (float) $doc['total'], 2 ), 'link' => $link, 'doc_number' => (string) ( $inv['DocNumber'] ?? '' ), 'mode_at' => time() ),
			)
		);
		if ( $first ) {
			AXH_Bill::event( (int) $doc_id, (int) $doc['customer_id'], 'qbo', 'Added to QuickBooks as invoice ' . ( $inv['DocNumber'] ?? $inv['Id'] ), 0 );
		}
		return $map;
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
						$s[] = rtrim( rtrim( number_format( $p['pct'], 2 ), '0' ), '.' ) . '% cash discount';
					}
					if ( $p['bank_fee'] > 0 ) {
						$s[] = '+' . rtrim( rtrim( number_format( $p['bank_pct'], 2 ), '0' ), '.' ) . '% bank fee';
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
		$map = self::map_get( 'invoice', (int) $doc['id'] );
		if ( $map && ! empty( $map['meta']['locked'] ) && ! empty( $map['meta']['link'] ) ) {
			return $map['meta']['link'];
		}
		$map = self::push_invoice( (int) $doc['id'], $mode );
		if ( is_wp_error( $map ) ) {
			return new WP_Error( 'axh_qbo_start', 'Online payment is not available right now. Please use another way to pay, or try again shortly.', array( 'status' => 502 ) );
		}
		if ( empty( $map['meta']['link'] ) ) {
			self::fail( 'QuickBooks did not return a payment link for invoice ' . $doc['number'] . '. Check that QuickBooks Payments is on and the customer has an email.', (int) $doc['id'] );
			return new WP_Error( 'axh_qbo_link', 'Online payment is not available for this invoice. Please use another way to pay.', array( 'status' => 502 ) );
		}
		// Look for the payment soon after they leave for QuickBooks.
		if ( ! wp_next_scheduled( 'axh_qbo_poll' ) || wp_next_scheduled( 'axh_qbo_poll' ) > time() + 300 ) {
			wp_schedule_single_event( time() + 180, 'axh_qbo_poll' );
		}
		return $map['meta']['link'];
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

	/** Bring in payments taken in QuickBooks, and notice when they have reached the bank. */
	public static function poll() {
		global $wpdb;
		if ( ! self::connected() || get_transient( 'axh_qbo_polling' ) ) {
			return array( 'recorded' => 0, 'cleared' => 0 );
		}
		set_transient( 'axh_qbo_polling', 1, 120 );
		$mt   = AXH_DB::table( 'qbo_map' );
		$dt   = AXH_Bill::t( 'bdocs' );
		$rows = $wpdb->get_results( "SELECT m.local_id, m.qbo_id, m.mode, m.meta FROM {$mt} m JOIN {$dt} d ON d.id = m.local_id WHERE m.kind = 'invoice' AND d.status NOT IN ('void','draft') AND (d.balance > 0.004 OR d.updated_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)) LIMIT 200", ARRAY_A ); // phpcs:ignore
		$rec  = 0;
		foreach ( $rows as $m ) {
			$rec += self::poll_invoice( (int) $m['local_id'] );
		}
		$clr = self::poll_clearing();
		$o              = self::opt();
		$o['last_poll'] = current_time( 'mysql' );
		self::save_opt( $o );
		delete_transient( 'axh_qbo_polling' );
		return array( 'recorded' => $rec, 'cleared' => $clr );
	}

	/** Record any QuickBooks payment on this invoice the Hub does not have yet. */
	public static function poll_invoice( $doc_id ) {
		global $wpdb;
		$map = self::map_get( 'invoice', $doc_id );
		if ( ! $map || ! $map['qbo_id'] ) {
			return 0;
		}
		$g = self::api( 'GET', 'invoice/' . rawurlencode( $map['qbo_id'] ) );
		if ( is_wp_error( $g ) ) {
			self::fail( $g->get_error_message(), $doc_id );
			return 0;
		}
		$inv   = $g['Invoice'];
		$pt    = AXH_Bill::t( 'bpay' );
		$count = 0;
		foreach ( (array) ( $inv['LinkedTxn'] ?? array() ) as $lt ) {
			if ( 'Payment' !== ( $lt['TxnType'] ?? '' ) ) {
				continue;
			}
			$pid = (string) $lt['TxnId'];
			$ref = 'qbo:' . $pid;
			$mt  = AXH_DB::table( 'qbo_map' );
			// Ours (a check recorded in the Hub) or already brought in.
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$mt} WHERE kind = 'payment' AND qbo_id = %s", $pid ) ) || $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pt} WHERE reference = %s", $ref ) ) ) { // phpcs:ignore
				continue;
			}
			$p = self::api( 'GET', 'payment/' . rawurlencode( $pid ) );
			if ( is_wp_error( $p ) ) {
				self::fail( $p->get_error_message(), $doc_id );
				continue;
			}
			$pay = $p['Payment'];
			$amt = 0.0;
			foreach ( (array) ( $pay['Line'] ?? array() ) as $pl ) {
				foreach ( (array) ( $pl['LinkedTxn'] ?? array() ) as $l2 ) {
					if ( 'Invoice' === ( $l2['TxnType'] ?? '' ) && (string) $l2['TxnId'] === (string) $map['qbo_id'] ) {
						$amt += (float) $pl['Amount'];
					}
				}
			}
			if ( $amt <= 0 ) {
				continue;
			}
			$count += self::record_online( $doc_id, $map, $pay, $amt ) ? 1 : 0;
		}
		return $count;
	}

	/** Turn an online QuickBooks payment into a Hub payment at the cash price. */
	protected static function record_online( $doc_id, $map, $pay, $amt ) {
		$r = AXH_Bill::doc_row( $doc_id, false );
		if ( ! $r ) {
			return false;
		}
		$mode    = in_array( $map['mode'], array( 'bank', 'card' ), true ) ? $map['mode'] : 'card';
		$qtotal  = (float) ( $map['meta']['qbo_total'] ?? 0 );
		$ctotal  = (float) ( $map['meta']['cash_total'] ?? $r['total'] );
		$ratio   = $qtotal > 0 ? $ctotal / $qtotal : 1;
		$balance = max( 0, round( (float) $r['total'] - AXH_Bill::invoice_paid( $doc_id ), 2 ) );
		$applied = round( $amt * $ratio, 2 );
		if ( abs( $applied - $balance ) <= 0.05 || $applied > $balance ) {
			$applied = $balance;
		}
		if ( $applied <= 0 ) {
			return false;
		}
		$deposit = self::has_deposit( $pay );
		$how     = 'bank' === $mode ? 'bank transfer' : 'card';
		$res     = AXH_Bill::record_payment(
			array(
				'doc_id'      => $doc_id,
				'amount'      => $applied,
				'method'      => 'bank' === $mode ? 'ach' : 'card',
				'reference'   => 'qbo:' . $pay['Id'],
				'received_at' => substr( (string) ( $pay['TxnDate'] ?? gmdate( 'Y-m-d' ) ), 0, 10 ),
				'cleared'     => $deposit ? 1 : 0,
				'note'        => 'Paid online through QuickBooks by ' . $how . ': ' . AXH_Bill::fmt( $amt ) . ( abs( $amt - $applied ) > 0.004 ? ' (' . AXH_Bill::fmt( $applied ) . ' at the Hub price; the rest is the ' . ( 'bank' === $mode ? 'bank fee' : 'card price' ) . ' and stays in QuickBooks)' : '' ) . '.',
			),
			true
		);
		if ( is_wp_error( $res ) ) {
			self::fail( 'Could not record a QuickBooks payment in the Hub: ' . $res->get_error_message(), $doc_id );
			return false;
		}
		self::map_set( 'invoice', $doc_id, array( 'meta' => array( 'paid_via' => 'card' === $mode ? 'card' : 'bank' ) ) );
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

	protected static function poll_clearing() {
		global $wpdb;
		$pt   = AXH_Bill::t( 'bpay' );
		$rows = $wpdb->get_results( "SELECT id, doc_id, reference, received_at FROM {$pt} WHERE reference LIKE 'qbo:%' AND cleared = 0 AND voided_at IS NULL AND kind = 'payment' LIMIT 50", ARRAY_A ); // phpcs:ignore
		$n    = 0;
		foreach ( $rows as $p ) {
			$g = self::api( 'GET', 'payment/' . rawurlencode( substr( $p['reference'], 4 ) ) );
			if ( is_wp_error( $g ) || ! self::has_deposit( $g['Payment'] ) ) {
				continue;
			}
			$wpdb->update( $pt, array( 'cleared' => 1, 'cleared_at' => gmdate( 'Y-m-d' ) ), array( 'id' => (int) $p['id'] ) );
			AXH_Bill::recalc( (int) $p['doc_id'] );
			$r = AXH_Bill::doc_row( (int) $p['doc_id'], false );
			AXH_Bill::event( (int) $p['doc_id'], $r ? (int) $r['customer_id'] : 0, 'payment', 'Online payment reached the bank (QuickBooks deposit)', 0 );
			$n++;
		}
		return $n;
	}

	/* ---------------------------------------------------- payments out */

	/**
	 * A payment recorded in the Hub (check, cash, Zelle…) goes to QuickBooks
	 * against the same invoice. When it settles the invoice, the cash discount
	 * line is added first so the QuickBooks invoice closes at the cash price.
	 */
	public static function on_payment( $pid, $in = array() ) {
		$o = self::opt();
		if ( ! self::connected() || empty( $o['push_manual'] ) ) {
			return;
		}
		$p = AXH_Bill::payment_row( (int) $pid );
		if ( ! $p || 'payment' !== $p['kind'] || 0 === strpos( (string) $p['reference'], 'qbo:' ) ) {
			return;
		}
		$r = AXH_Bill::doc_row( (int) $p['doc_id'], false );
		if ( ! $r || 'invoice' !== $r['type'] ) {
			return;
		}
		$doc     = AXH_Bill::shape_doc( $r, 'admin' );
		$cd      = self::cd_applies( $doc );
		$settled = (float) $r['balance'] <= 0.004;
		$cashy   = in_array( $p['method'], self::CASHLIKE, true );
		$map     = self::map_get( 'invoice', (int) $r['id'] );
		if ( ! $map || ( $cd && $settled && $cashy ) || ( $map && empty( $map['meta']['locked'] ) && in_array( $map['mode'], array( 'card', 'bank' ), true ) ) ) {
			$map = self::push_invoice( (int) $r['id'], $cd && $settled && $cashy ? 'cash' : 'open' );
			if ( is_wp_error( $map ) ) {
				self::fail( 'Payment not recorded in QuickBooks: ' . $map->get_error_message(), (int) $r['id'] );
				return;
			}
		}
		$amt = (float) $p['amount'] - (float) $p['credit'];
		if ( $cd && ! $cashy ) {
			$amt = round( $amt * self::k(), 2 ); // a card taken elsewhere pays the card price
		}
		$cust = self::customer_for( $doc );
		if ( is_wp_error( $cust ) ) {
			self::fail( 'Payment not recorded in QuickBooks: ' . $cust->get_error_message(), (int) $r['id'] );
			return;
		}
		$body = array(
			'CustomerRef'    => array( 'value' => (string) $cust ),
			'TotalAmt'       => $amt,
			'TxnDate'        => $p['received_at'],
			'PaymentRefNum'  => mb_substr( (string) $p['reference'], 0, 21 ),
			'PrivateNote'    => mb_substr( 'Recorded in AdaptExec Hub (' . $p['method'] . ')' . ( $p['note'] ? ': ' . $p['note'] : '' ), 0, 4000 ),
			'Line'           => array( array( 'Amount' => $amt, 'LinkedTxn' => array( array( 'TxnId' => (string) $map['qbo_id'], 'TxnType' => 'Invoice' ) ) ) ),
		);
		$res = self::api( 'POST', 'payment', $body );
		if ( is_wp_error( $res ) ) {
			self::fail( 'Payment not recorded in QuickBooks: ' . $res->get_error_message(), (int) $r['id'] );
			return;
		}
		self::map_set( 'payment', (int) $pid, array( 'qbo_id' => (string) $res['Payment']['Id'], 'meta' => array( 'doc' => (int) $r['id'] ) ) );
		AXH_Bill::event( (int) $r['id'], (int) $r['customer_id'], 'qbo', 'Payment of ' . AXH_Bill::fmt( $amt ) . ' recorded in QuickBooks', 0 );
	}

	/** A newly sent invoice goes to QuickBooks so receivables are right there too. */
	public static function on_sent( $doc_id ) {
		if ( self::connected() && ! self::map_get( 'invoice', (int) $doc_id ) ) {
			wp_schedule_single_event( time() + 5, 'axh_qbo_push', array( (int) $doc_id ) );
		}
	}

	public static function cron_push( $doc_id ) {
		if ( self::map_get( 'invoice', (int) $doc_id ) ) {
			return;
		}
		$m = self::push_invoice( (int) $doc_id, 'open' );
		if ( is_wp_error( $m ) && 'axh_qbo_doc' !== $m->get_error_code() ) {
			self::fail( $m->get_error_message(), (int) $doc_id );
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
		if ( $o['client_id'] && $o['client_id'] !== $id ) {
			// A different app: the old connection belongs to the old keys.
			$o['access'] = $o['refresh'] = '';
			$o['realm_id'] = $o['company'] = '';
		}
		$o['client_id']     = $id;
		$o['client_secret'] = self::enc( $se );
		self::save_opt( $o );
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
		if ( $o['realm_id'] && $o['realm_id'] !== $realm ) {
			// Another company: start its links afresh rather than mix two sets of books.
			$o['item_id'] = $o['disc_item_id'] = $o['fee_item_id'] = $o['item_name'] = '';
		}
		$o['realm_id']     = $realm;
		$o['connected_at'] = current_time( 'mysql' );
		$o['connected_by'] = $uid;
		self::save_opt( $o );
		self::store_tokens( $j );
		$ci = self::api( 'GET', 'companyinfo/' . rawurlencode( $realm ) );
		if ( ! is_wp_error( $ci ) ) {
			$o            = self::opt();
			$o['company'] = (string) ( $ci['CompanyInfo']['CompanyName'] ?? '' );
			self::save_opt( $o );
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
		$o = self::opt();
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
		$o['access']      = '';
		$o['refresh']     = '';
		$o['access_exp']  = 0;
		$o['refresh_exp'] = 0;
		self::save_opt( $o );
		wp_clear_scheduled_hook( 'axh_qbo_poll' );
		return self::rest_status();
	}

	public static function rest_settings( $req ) {
		$b = (array) $req->get_json_params();
		$o = self::opt();
		foreach ( array( 'card_on', 'bank_on', 'push_manual' ) as $k ) {
			if ( array_key_exists( $k, $b ) ) {
				$o[ $k ] = ! empty( $b[ $k ] );
			}
		}
		if ( array_key_exists( 'bank_fee_pct', $b ) ) {
			$o['bank_fee_pct'] = min( 3, max( 0, round( (float) $b['bank_fee_pct'], 2 ) ) );
		}
		if ( array_key_exists( 'cd_pct', $b ) ) {
			$o['cd_pct'] = min( 10, max( 0, round( (float) $b['cd_pct'], 2 ) ) );
		}
		if ( array_key_exists( 'cd_on', $b ) ) {
			$on = ! empty( $b['cd_on'] );
			if ( $on && ! $o['cd_on'] ) {
				// Only invoices made from now on: what clients have already seen stays as it is.
				global $wpdb;
				$o['cd_from'] = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id), 0) FROM ' . AXH_Bill::t( 'bdocs' ) ); // phpcs:ignore
			}
			$o['cd_on'] = $on;
		}
		if ( array_key_exists( 'item_id', $b ) && preg_match( '/^\d{1,20}$/', (string) $b['item_id'] ) ) {
			$o['item_id']   = (string) $b['item_id'];
			$o['item_name'] = sanitize_text_field( (string) ( $b['item_name'] ?? '' ) );
		}
		self::save_opt( $o );
		return self::rest_status();
	}

	public static function rest_items( $req ) {
		$r = self::query( "select * from Item where Active = true maxresults 500" );
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
		delete_transient( 'axh_qbo_polling' );
		return rest_ensure_response( self::poll() + array( 'status' => self::rest_status()->get_data() ) );
	}

	public static function rest_push( $req ) {
		$m = self::push_invoice( self::url_id( $req ), 'open' );
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
				'prices'    => 'invoice' === $r['type'] ? self::prices( $doc ) : null,
			)
		);
	}
}

AXH_QBO::init();
