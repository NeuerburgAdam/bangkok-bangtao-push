<?php
/**
 * Files on a lead (1.10.0): photos of the storefront, their price list, an
 * old proposal, a signed quote. Kept private and encrypted with the same
 * site key as W-9s (AXH_Onboard), in the same deny-all folder, and only the
 * lead's rep and AdaptExec can open them. The file type is checked from its
 * bytes, never trusted from its name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AXH_Files {

	const DB  = '2';
	const NS  = 'axh/v1';
	const MAX = 15728640; // 15 MB

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'axh_files_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$leads = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads ) ) !== $leads ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = AXH_DB::table( 'lead_files' );
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			rep_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			mime varchar(100) NOT NULL,
			size int(10) unsigned NOT NULL DEFAULT 0,
			file varchar(64) NOT NULL,
			kick varchar(80) NOT NULL DEFAULT '',
			src varchar(16) NOT NULL DEFAULT '',
			thumb varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( 'axh_files_db', self::DB, false );
		}
	}

	public static function routes() {
		$auth = array( 'AXH_Rest', 'can_use' );
		register_rest_route( self::NS, '/p1/lead/(?P<id>\d+)/files', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_list' ), 'permission_callback' => $auth ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_upload' ), 'permission_callback' => $auth ),
		) );
		register_rest_route( self::NS, '/p1/files/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_get' ), 'permission_callback' => $auth ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'r_delete' ), 'permission_callback' => $auth ),
		) );
	}

	/** Allowed types: extension => the type its bytes must be. */
	protected static function types() {
		return array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'heic' => 'image/heic',
			'heif' => 'image/heic',
			'docx' => 'zip',
			'xlsx' => 'zip',
			'pptx' => 'zip',
			'txt'  => 'text',
			'csv'  => 'text',
		);
	}

	protected static function office_mime( $ext ) {
		return array(
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		)[ $ext ] ?? 'application/octet-stream';
	}

	/** The real type from the first bytes, or '' when it is not allowed. */
	protected static function sniff( $bytes, $ext ) {
		$want = self::types()[ $ext ] ?? '';
		if ( ! $want ) {
			return '';
		}
		$head = substr( $bytes, 0, 16 );
		switch ( $want ) {
			case 'application/pdf':
				return 0 === strpos( $head, '%PDF-' ) ? $want : '';
			case 'image/jpeg':
				return 0 === strpos( $head, "\xFF\xD8\xFF" ) ? $want : '';
			case 'image/png':
				return 0 === strpos( $head, "\x89PNG\r\n\x1a\n" ) ? $want : '';
			case 'image/webp':
				return ( 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) ) ? $want : '';
			case 'image/heic':
				return ( 'ftyp' === substr( $head, 4, 4 ) && in_array( substr( $head, 8, 4 ), array( 'heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1', 'heif' ), true ) ) ? $want : '';
			case 'zip':
				return 0 === strpos( $head, "PK\x03\x04" ) ? self::office_mime( $ext ) : '';
			case 'text':
				// Plain text only: no NUL bytes and valid UTF-8 (or ASCII).
				$sample = substr( $bytes, 0, 65536 );
				return ( false === strpos( $sample, "\0" ) && ( function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $sample, 'UTF-8' ) : true ) ) ? ( 'csv' === $ext ? 'text/csv' : 'text/plain' ) : '';
		}
		return '';
	}

	protected static function row( $id ) {
		global $wpdb;
		$t = AXH_DB::table( 'lead_files' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $r || ! AXH_DB::get_lead( (int) $r['lead_id'] ) ) {
			return null; // Not this person's lead.
		}
		return $r;
	}

	public static function list_for( $lead_id ) {
		global $wpdb;
		$t    = AXH_DB::table( 'lead_files' );
		$cols = 'id, lead_id, rep_id, name, mime, size, created_at' . ( '2' === get_option( 'axh_files_db' ) ? ', kick, src' : '' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$cols} FROM {$t} WHERE lead_id = %d ORDER BY id DESC LIMIT 500", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$u          = get_userdata( (int) $r['rep_id'] );
			$r['by']    = 'client' === ( $r['src'] ?? '' ) ? 'Client (onboarding)' : ( $u ? $u->display_name : '' );
			$r['image'] = 0 === strpos( $r['mime'], 'image/' ) && 'image/heic' !== $r['mime'];
		}
		unset( $r );
		return $rows;
	}

	public static function r_list( $req ) {
		if ( ! AXH_DB::get_lead( (int) $req['id'] ) ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'files' => self::list_for( (int) $req['id'] ) ) );
	}

	public static function r_upload( $req ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( (int) $req['id'] );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! method_exists( 'AXH_Onboard', 'encrypt' ) ) {
			return new WP_Error( 'axh_no_sodium', 'This server cannot encrypt files (PHP sodium is missing).', array( 'status' => 500 ) );
		}
		$files = $req->get_file_params();
		$f     = $files['file'] ?? null;
		if ( ! $f || ! isset( $f['error'] ) ) {
			$len = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
			if ( $len && $len > wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ) ) {
				return new WP_Error( 'axh_too_big', 'That file is larger than this server accepts.', array( 'status' => 413 ) );
			}
			return new WP_Error( 'axh_no_file', 'Choose a file to add.', array( 'status' => 400 ) );
		}
		if ( UPLOAD_ERR_INI_SIZE === (int) $f['error'] || UPLOAD_ERR_FORM_SIZE === (int) $f['error'] ) {
			return new WP_Error( 'axh_too_big', 'That file is larger than this server accepts.', array( 'status' => 413 ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $f['error'] || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'axh_upload', 'The upload did not finish. Please try again.', array( 'status' => 400 ) );
		}
		$size = (int) filesize( $f['tmp_name'] );
		if ( $size <= 0 || $size > self::MAX ) {
			return new WP_Error( 'axh_too_big', $size ? 'Files can be up to 15 MB. That one is ' . size_format( $size, 1 ) . '.' : 'That file is empty.', array( 'status' => 413 ) );
		}
		$name = sanitize_file_name( wp_basename( (string) $f['name'] ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! isset( self::types()[ $ext ] ) ) {
			return new WP_Error( 'axh_type', 'Add a PDF, photo (JPG, PNG, WebP, HEIC), Word, Excel or PowerPoint file, or a text/CSV file.', array( 'status' => 415 ) );
		}
		$bytes = (string) file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$mime  = self::sniff( $bytes, $ext );
		@unlink( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $mime ) {
			return new WP_Error( 'axh_type_mismatch', 'That file\'s contents do not match its .' . $ext . ' name, so it was not added.', array( 'status' => 415 ) );
		}
		$key = 'axh_files_n_' . get_current_user_id();
		$n   = (int) get_transient( $key );
		if ( $n >= 60 ) {
			return new WP_Error( 'axh_throttled', 'Too many uploads in the last hour. Please try again later.', array( 'status' => 429 ) );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		if ( ! AXH_Onboard::ensure_dir() ) {
			return new WP_Error( 'axh_no_dir', 'The private folder could not be created.', array( 'status' => 500 ) );
		}
		$file = 'lf' . bin2hex( random_bytes( 16 ) ) . '.axh';
		if ( false === file_put_contents( AXH_Onboard::dir() . '/' . $file, AXH_Onboard::encrypt( $bytes ), LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'axh_write', 'Could not save the file. Please try again.', array( 'status' => 500 ) );
		}
		@chmod( AXH_Onboard::dir() . '/' . $file, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$wpdb->insert(
			AXH_DB::table( 'lead_files' ),
			array(
				'lead_id'    => (int) $lead['id'],
				'rep_id'     => get_current_user_id(),
				'name'       => mb_substr( $name, 0, 191 ),
				'mime'       => $mime,
				'size'       => $size,
				'file'       => $file,
				'created_at' => AXH_DB::now(),
			)
		);
		AXH_DB::log( (int) $lead['id'], 'file', 'Added a file: ' . mb_substr( $name, 0, 150 ), size_format( $size, 1 ) );
		return rest_ensure_response( array( 'files' => self::list_for( (int) $lead['id'] ) ) );
	}

	public static function r_get( $req ) {
		$r = self::row( (int) $req['id'] );
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'File not found.', array( 'status' => 404 ) );
		}
		$out = self::output( $r, (bool) $req->get_param( 'th' ), false, (bool) $req->get_param( 'dl' ) );
		return $out; // Only reached with an error; output() exits after sending a file.
	}

	/** Whether a stored name is one of ours (never a path). */
	protected static function safe_name( $f ) {
		return (bool) preg_match( '/^(lf|th)[a-f0-9]{32}\.axh$/', (string) $f );
	}

	public static function safe_file( $f ) {
		return self::safe_name( $f );
	}

	/**
	 * Send a stored file (1.22.0): small files are one sealed box ("AXH1"),
	 * client uploads are a run of sealed pieces ("AXH2") streamed one at a
	 * time. $thumb serves the photo preview when there is one; $public (the
	 * client's onboarding page) only ever serves images.
	 */
	public static function output( $r, $thumb = false, $public = false, $download = false ) {
		$file = $r['file'];
		$mime = $r['mime'];
		$size = (int) $r['size'];
		if ( $thumb && ! empty( $r['thumb'] ) && self::safe_name( $r['thumb'] ) && is_readable( AXH_Onboard::dir() . '/' . $r['thumb'] ) ) {
			$file = $r['thumb'];
			$mime = 'image/jpeg';
			$size = 0;
		} elseif ( $public && ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml' ), true ) || $size > 6291456 ) ) {
			return new WP_Error( 'axh_not_found', 'No preview for this file.', array( 'status' => 404 ) );
		}
		$path = AXH_Onboard::dir() . '/' . $file;
		if ( ! self::safe_name( $file ) || ! is_readable( $path ) ) {
			return new WP_Error( 'axh_missing', 'The file is missing from the private folder.', array( 'status' => 404 ) );
		}
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return new WP_Error( 'axh_missing', 'The file could not be opened.', array( 'status' => 500 ) );
		}
		$magic = fread( $fh, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$plain = null;
		if ( 'AXH2' !== $magic ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$plain = AXH_Onboard::decrypt( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_wp_error( $plain ) ) {
				return $plain;
			}
			$size = strlen( $plain );
		}
		$inline = ! $download && in_array( $mime, array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true );
		if ( function_exists( 'axh_nocache' ) ) {
			axh_nocache();
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . ( 'image/svg+xml' === $mime && ! $public ? 'image/svg+xml' : $mime ) );
		$ascii = preg_replace( '/[^A-Za-z0-9._ -]/', '', $r['name'] );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $r['name'] ) );
		if ( $size > 0 ) {
			header( 'Content-Length: ' . $size );
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox" );
		if ( null !== $plain ) {
			echo $plain; // phpcs:ignore WordPress.Security.EscapeOutput -- a file download.
			exit;
		}
		while ( ! feof( $fh ) ) {
			$len = fread( $fh, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( 4 !== strlen( (string) $len ) ) {
				break;
			}
			$n   = unpack( 'N', $len )[1];
			$seg = AXH_Onboard::decrypt( (string) fread( $fh, $n ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_wp_error( $seg ) ) {
				break;
			}
			echo $seg; // phpcs:ignore WordPress.Security.EscapeOutput -- a file download.
			flush();
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Decrypt a stored file into a temporary file (for making previews). */
	public static function decrypt_to( $path, $dest ) {
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		$out = fopen( $dest, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $out ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}
		$ok    = true;
		$magic = fread( $fh, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( 'AXH2' === $magic ) {
			while ( ! feof( $fh ) ) {
				$len = fread( $fh, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( 4 !== strlen( (string) $len ) ) {
					break;
				}
				$seg = AXH_Onboard::decrypt( (string) fread( $fh, unpack( 'N', $len )[1] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( is_wp_error( $seg ) ) {
					$ok = false;
					break;
				}
				fwrite( $out, $seg ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		} else {
			$plain = AXH_Onboard::decrypt( $magic . (string) stream_get_contents( $fh ) );
			$ok    = ! is_wp_error( $plain ) && false !== fwrite( $out, $plain ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $ok;
	}

	/** Remove one stored file, its preview and its row. */
	public static function remove( $r ) {
		global $wpdb;
		foreach ( array( $r['file'] ?? '', $r['thumb'] ?? '' ) as $f ) {
			if ( self::safe_name( $f ) ) {
				@unlink( AXH_Onboard::dir() . '/' . $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		$wpdb->delete( AXH_DB::table( 'lead_files' ), array( 'id' => (int) $r['id'] ) );
	}

	/** Remove every file on a lead (the lead itself is being deleted). */
	public static function delete_for_lead( $lead_id ) {
		global $wpdb;
		if ( ! get_option( 'axh_files_db' ) ) {
			return;
		}
		$t    = AXH_DB::table( 'lead_files' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE lead_id = %d", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		foreach ( $rows as $r ) {
			foreach ( array( $r['file'] ?? '', $r['thumb'] ?? '' ) as $f ) {
				if ( self::safe_name( $f ) ) {
					@unlink( AXH_Onboard::dir() . '/' . $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
		}
		$wpdb->delete( $t, array( 'lead_id' => (int) $lead_id ) );
	}

	public static function r_delete( $req ) {
		global $wpdb;
		$r = self::row( (int) $req['id'] );
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'File not found.', array( 'status' => 404 ) );
		}
		if ( ! axh_is_manager() && (int) $r['rep_id'] !== get_current_user_id() ) {
			return new WP_Error( 'axh_forbidden', 'Only the person who added a file (or AdaptExec) can remove it.', array( 'status' => 403 ) );
		}
		self::remove( $r );
		AXH_DB::log( (int) $r['lead_id'], 'file', 'Removed a file: ' . mb_substr( $r['name'], 0, 150 ) );
		return rest_ensure_response( array( 'files' => self::list_for( (int) $r['lead_id'] ) ) );
	}
}

AXH_Files::init();

/**
 * Client onboarding (1.22.0): everything AdaptExec needs to start a build,
 * collected from the client on one private page at /k/<token>/ after the
 * quote is accepted.
 *
 * What is asked is worked out from the job itself: the proposal's chosen
 * option and add-ons, and the discovery answers (what they need, the tools to
 * connect, hosting and email, their current platform). A website-only job is
 * never asked for QuickBooks or social media access; a CRM build with Stripe
 * gets the Stripe invite. The rep or AdaptExec can still add or remove items
 * before sending it.
 *
 * Files go into the lead's private, encrypted files (AXH_Files), uploaded in
 * small pieces so large photos and logo packs get through any server limit.
 * Logins, when a client chooses to share one instead of inviting us, are
 * sealed with the site key and only AdaptExec managers can open them; every
 * opening is written to the lead's timeline.
 */
class AXH_Kickoff {

	const DB = '1';
	const NS = 'axh/v1';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ------------------------------------------------------------ install */

	public static function maybe_upgrade() {
		if ( get_option( 'axh_kick_db' ) === self::DB ) {
			return;
		}
		global $wpdb;
		$leads = AXH_DB::table( 'leads' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $leads ) ) !== $leads ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = AXH_DB::table( 'kickoff' );
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			rep_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			token varchar(48) NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'draft',
			config longtext NULL,
			data longtext NULL,
			state longtext NULL,
			secrets longtext NULL,
			progress tinyint(3) unsigned NOT NULL DEFAULT 0,
			sent_at datetime NULL,
			opened_at datetime NULL,
			client_saved_at datetime NULL,
			done_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY lead_id (lead_id),
			UNIQUE KEY token (token)
			) " . $wpdb->get_charset_collate() . ';'
		);
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( 'axh_kick_db', self::DB, false );
			flush_rewrite_rules( false );
		}
	}

	public static function rewrite() {
		add_rewrite_rule( '^k/([a-f0-9]{24,48})/?$', 'index.php?axh_kick=$matches[1]', 'top' );
	}

	public static function query_vars( $v ) {
		$v[] = 'axh_kick';
		return $v;
	}

	/* ----------------------------------------------------------- settings */

	/** Who clients invite, and the names their access guides use. */
	public static function settings() {
		$s = (array) get_option( 'axh_kick', array() );
		return array(
			'email'   => is_email( $s['email'] ?? '' ) ? $s['email'] : 'adam@adaptexec.com',
			'person'  => trim( (string) ( $s['person'] ?? '' ) ) ? trim( (string) $s['person'] ) : 'Adam Neuerburg',
			'meta_id' => preg_replace( '/[^0-9]/', '', (string) ( $s['meta_id'] ?? '' ) ),
			'max_mb'  => max( 10, min( 1024, (int) ( $s['max_mb'] ?? 200 ) ) ),
		);
	}

	/* ------------------------------------------------------------ library */

	/** Project types an onboarding can cover. */
	public static function types() {
		return array(
			'web'    => array( 'Website', 'A new or redesigned website' ),
			'store'  => array( 'Online store', 'Selling products online' ),
			'soft'   => array( 'Custom software / CRM', 'CRM, portal, app or automations' ),
			'brand'  => array( 'Branding / logo', 'Logo, brand colors and style' ),
			'seo'    => array( 'SEO', 'Getting found on Google' ),
			'ads'    => array( 'Google Ads', 'Running paid search campaigns' ),
			'social' => array( 'Social media management', 'Posting and managing their accounts' ),
			'email'  => array( 'Business email', 'Setting up or moving their email' ),
			'host'   => array( 'App hosting', 'Running the software in their own cloud account' ),
		);
	}

	/** Platform guides for one kind of account. {email}, {person}, {meta} are filled in. */
	protected static function platforms( $kind ) {
		$share = 'This one doesn’t have a simple invite. Choose “Share a login” below, or reply to your rep and we’ll do it together on a short call.';
		$p     = array(
			'registrar' => array(
				array( 'godaddy', 'GoDaddy', array( 'Sign in at godaddy.com.', 'Open your name (top right) → Account Settings → Delegate Access.', 'Choose Invite to Access and enter {email}.', 'Pick “Products & Domains”, then Invite.' ) ),
				array( 'namecheap', 'Namecheap', array( 'Sign in at namecheap.com and open Domain List.', 'Press Manage next to your domain → Sharing & Transfer.', 'Under Share Access, add {email} and allow DNS and domain settings.', 'Press Share. (If it asks for a Namecheap username, tell us and we’ll send ours.)' ) ),
				array( 'squarespace', 'Squarespace Domains (formerly Google Domains)', array( 'Sign in at domains.squarespace.com and choose your domain.', 'Open Permissions.', 'Press Add domain manager and enter {email}.', 'Send the invite.' ) ),
				array( 'cloudflare', 'Cloudflare', array( 'Sign in at dash.cloudflare.com.', 'Open Manage Account → Members → Invite.', 'Enter {email}, choose the Administrator role and your domain.', 'Send the invite.' ) ),
				array( 'hostinger', 'Hostinger', array( 'Sign in to hPanel at hostinger.com.', 'Open your account menu (top right) → Account sharing → Grant access.', 'Enter {email} and choose access to your domain (and website).', 'Press Grant access.' ) ),
				array( 'wix', 'Wix', array( 'Sign in at wix.com and open your site’s dashboard.', 'Go to Settings → Roles & Permissions → Invite People.', 'Enter {email}, choose Admin (Co-owner).', 'Send the invite.' ) ),
				array( 'bluehost', 'Bluehost', array( 'Sign in at bluehost.com.', 'If your account shows a Users or account-access page, add {email} there with full access.', 'If you don’t see one, choose “Share a login” below.' ) ),
				array( 'netsol', 'Network Solutions', array( $share ) ),
				array( 'other', 'Somewhere else / not sure', array( 'Tell us where you bought the domain in the note, or check the renewal emails you receive.', 'Most registrars have a “delegate”, “share access” or “users” page: add {email} there.', 'If you can’t find one, choose “Share a login”.' ) ),
			),
			'host'      => array(
				array( 'bluehost', 'Bluehost', array( 'Sign in at bluehost.com.', 'If your account shows a Users or account-access page, add {email} with full access.', 'If you don’t see one, choose “Share a login” below.' ) ),
				array( 'godaddy', 'GoDaddy', array( 'Sign in at godaddy.com.', 'Open your name (top right) → Account Settings → Delegate Access.', 'Choose Invite to Access and enter {email}.', 'Pick “Products & Domains”, then Invite.' ) ),
				array( 'hostinger', 'Hostinger', array( 'Sign in to hPanel.', 'Account menu (top right) → Account sharing → Grant access.', 'Enter {email} and choose your website.', 'Press Grant access.' ) ),
				array( 'siteground', 'SiteGround', array( 'Sign in at siteground.com and open Websites.', 'Press the ⋮ menu next to your site → Add Collaborator.', 'Enter {email} and send the invite.' ) ),
				array( 'wpengine', 'WP Engine', array( 'Sign in to the WP Engine User Portal.', 'Open Users → Add user.', 'Enter {email} and choose Full access.', 'Send the invite.' ) ),
				array( 'kinsta', 'Kinsta', array( 'Sign in to MyKinsta.', 'Open Company settings → Users → Invite users.', 'Enter {email} and choose a developer or administrator role.', 'Send the invite.' ) ),
				array( 'other', 'Another host / not sure', array( 'Look for a Users, Collaborators or Account sharing page and add {email} with full access.', 'If there isn’t one, choose “Share a login” below.' ) ),
			),
			'cms'       => array(
				array( 'wordpress', 'WordPress', array( 'Sign in to your site’s dashboard (yourdomain.com/wp-admin).', 'Go to Users → Add New User.', 'Username: adaptexec · Email: {email} · Role: Administrator.', 'Press Add New User. We get an email to set our own password.' ) ),
				array( 'wix', 'Wix', array( 'Open your site’s dashboard.', 'Settings → Roles & Permissions → Invite People.', 'Enter {email} and choose Admin (Co-owner).', 'Send the invite.' ) ),
				array( 'squarespace', 'Squarespace', array( 'Open your site → Settings → Permissions & Ownership.', 'Press Invite contributor and enter {email}.', 'Choose Administrator, then Invite.' ) ),
				array( 'shopify', 'Shopify', array( 'Open your Shopify admin → Settings → Users.', 'Add a staff member (or accept our collaborator request) with {email}.', 'Give full permissions and send the invite.' ) ),
				array( 'webflow', 'Webflow', array( 'Open your Webflow workspace → Settings → Members.', 'Invite {email} as a member with access to the site.', 'Send the invite.' ) ),
				array( 'godaddy', 'GoDaddy Website Builder', array( 'Sign in at godaddy.com.', 'Open your name (top right) → Account Settings → Delegate Access.', 'Invite {email} with “Products & Domains”.' ) ),
				array( 'other', 'Something else / not sure', array( 'Look for Users, Team or Permissions in the site’s settings and invite {email} as an administrator.', 'If there isn’t one, choose “Share a login” below.' ) ),
			),
			'email'     => array(
				array( 'google', 'Google Workspace (Gmail)', array( 'Sign in at admin.google.com as an admin.', 'Directory → Users → Add new user. Name it “AdaptExec” (e.g. adaptexec@yourdomain).', 'Give it the Super Admin role (Account → Admin roles). We remove it when the work is done.', 'Share that account’s login below. Google only lets people in your own domain be admins.' ) ),
				array( 'microsoft', 'Microsoft 365 (Outlook)', array( 'Sign in at admin.microsoft.com.', 'Users → Active users → Add a user named “AdaptExec”.', 'Give it the Global Administrator role for now. We remove it when the work is done.', 'Share that account’s login below.' ) ),
				array( 'godaddy', 'Email through GoDaddy', array( 'Sign in at godaddy.com → your name → Account Settings → Delegate Access.', 'Invite {email} with “Products & Domains”.' ) ),
				array( 'zoho', 'Zoho Mail', array( 'Sign in to the Zoho Mail Admin Console.', 'Users → Add a user named “AdaptExec” and make them an admin.', 'Share that login below.' ) ),
				array( 'host', 'Through our website host', array( 'Email that comes with hosting (Bluehost, Hostinger, GoDaddy…) is covered by the hosting access above.', 'Mark this done once hosting access is shared.' ) ),
				array( 'none', 'We don’t have business email yet', array( 'Nothing to do here. We’ll set it up and send you the new logins.' ), false ),
			),
			'apphost'   => array(
				array( 'render', 'Render', array( 'Sign in at dashboard.render.com.', 'Open Workspace settings → Members → Invite.', 'Enter {email} and choose Admin.', 'Send the invite.' ) ),
				array( 'vercel', 'Vercel', array( 'Open your team on vercel.com → Settings → Members.', 'Invite {email} as a Member (or Owner).' ) ),
				array( 'netlify', 'Netlify', array( 'Open Team settings → Members → Invite members.', 'Invite {email} as a Developer.' ) ),
				array( 'digitalocean', 'DigitalOcean', array( 'Open Settings → Team → Invite Members.', 'Invite {email} with full access.' ) ),
				array( 'aws', 'Amazon Web Services', array( 'Sign in to the AWS console → IAM → Users → Create user named “adaptexec”.', 'Give it AdministratorAccess (we can narrow it later) and console access.', 'Share that login below. AWS can’t invite outside email addresses directly.' ) ),
				array( 'heroku', 'Heroku', array( 'Open your app → Access → Add collaborator.', 'Enter {email}.' ) ),
				array( 'none', 'We don’t have one: please set it up', array( 'Nothing to do. We’ll create the account in your company’s name and hand it over at launch.' ), false ),
			),
			'ga'        => array( array( 'google', 'Google Analytics', array( 'Sign in at analytics.google.com.', 'Press Admin (the gear, bottom left).', 'Open Property access management, then + → Add users.', 'Enter {email}, choose Administrator, and press Add.' ) ) ),
			'gsc'       => array( array( 'google', 'Google Search Console', array( 'Sign in at search.google.com/search-console and choose your site.', 'Open Settings → Users and permissions.', 'Press Add user, enter {email}, choose Full.', 'Press Add.' ) ) ),
			'gbp'       => array( array( 'google', 'Google Business Profile', array( 'Signed in to Google, search your business name and open your profile.', 'Open the ⋮ menu → Business Profile settings → Managers.', 'Press Add, enter {email}, choose Manager.', 'Press Invite.' ) ) ),
			'gtm'       => array( array( 'google', 'Google Tag Manager', array( 'Sign in at tagmanager.google.com.', 'Open Admin → User Management (account).', 'Press + → Add users, enter {email}, choose Administrator and Publish.', 'Press Invite.' ) ) ),
			'gads'      => array( array( 'google', 'Google Ads', array( 'Sign in at ads.google.com.', 'Press Admin (the gear) → Access and security.', 'Press +, enter {email}, choose Admin.', 'Send the invitation. Add your 10-digit Customer ID in the note.' ) ) ),
			'meta'      => array( array( 'meta', 'Facebook & Instagram (Meta Business Suite)', array( 'Go to business.facebook.com and pick your business.', 'Open Settings → Users → People → Add.', 'Enter {email}, then choose your Facebook Page and Instagram account.', 'Give full control and send the invite.{meta}' ) ) ),
			'linkedin'  => array( array( 'linkedin', 'LinkedIn Page', array( 'Open your LinkedIn Page in admin view.', 'Go to Settings → Manage admins → Add admin.', 'Search for {person} and choose Content admin.', 'Press Save.' ) ) ),
			'stripe'    => array( array( 'stripe', 'Stripe', array( 'Sign in at dashboard.stripe.com.', 'Open Settings → Team and security → Add member.', 'Enter {email}, choose Developer (or Administrator).', 'Send the invite.' ) ) ),
			'twilio'    => array( array( 'twilio', 'Twilio', array( 'Sign in at console.twilio.com.', 'Open Admin → Manage users → Invite users.', 'Enter {email} with a Developer or Administrator role.', 'Send the invite.' ) ) ),
			'mailchimp' => array(
				array( 'mailchimp', 'Mailchimp', array( 'Open your profile → Account & billing → Settings → Users.', 'Press Invite a User, enter {email}, choose Admin.', 'Send the invite.' ) ),
				array( 'constant', 'Constant Contact', array( 'Open My Settings → Users → Add user.', 'Enter {email} as an Account Manager.' ) ),
				array( 'klaviyo', 'Klaviyo', array( 'Open Settings → Users → Add new user.', 'Enter {email} with an Admin role.' ) ),
				array( 'other', 'Something else', array( 'Look for Users or Team in its settings and invite {email}.', 'If there isn’t one, choose “Share a login”.' ) ),
			),
			'booking'   => array(
				array( 'calendly', 'Calendly', array( 'Open Admin center → Users → Invite users.', 'Invite {email}.' ) ),
				array( 'acuity', 'Acuity Scheduling', array( 'Open Business Settings → Users, Roles & Permissions.', 'Add {email} as an admin.' ) ),
				array( 'square', 'Square Appointments', array( 'This connects with your Square sign-in when we set it up. Nothing to send now.' ), false ),
				array( 'other', 'Something else', array( 'Look for Users or Staff in its settings and invite {email} as an admin.', 'If there isn’t one, choose “Share a login”.' ) ),
			),
			'software'  => array(
				array( 'other', 'Your software', array( 'Many systems let you invite a user or create an API key under Settings → Users, Integrations or API.', 'Invite {email} if you can, or share a login below and tell us who your account rep is there.' ) ),
			),
		);
		$out = array();
		foreach ( $p[ $kind ] ?? array() as $row ) {
			$out[] = array(
				'k'     => $row[0],
				'name'  => $row[1],
				'steps' => $row[2],
				'login' => isset( $row[3] ) ? (bool) $row[3] : true,
			);
		}
		return $out;
	}

	/**
	 * Every item a client might be asked for, grouped into sections.
	 * when  = project types it belongs to (any); needs = signals it also
	 * needs (any), e.g. only ask for Stripe when Stripe is part of the job.
	 */
	public static function library() {
		$all_look = array( 'web', 'store', 'brand', 'social', 'ads' );
		$sections = array(
			array(
				'key'   => 'start',
				'title' => 'Project kickoff',
				'intro' => 'Who we work with and the basics we’ll use across your project.',
				'items' => array(
					array( 'key' => 'contacts', 'title' => 'Your project contacts', 'kind' => 'fields', 'why' => 'So the right person hears from us, and approvals never wait on the wrong inbox.', 'fields' => array(
						array( 'k' => 'name', 'l' => 'Main contact', 'ph' => 'Full name' ),
						array( 'k' => 'role', 'l' => 'Their role', 'ph' => 'e.g. Owner, Office manager' ),
						array( 'k' => 'email', 'l' => 'Email', 't' => 'email' ),
						array( 'k' => 'phone', 'l' => 'Phone', 't' => 'tel' ),
						array( 'k' => 'reach', 'l' => 'Best way to reach them', 't' => 'select', 'o' => array( 'Email', 'Text', 'Phone call' ) ),
						array( 'k' => 'approver', 'l' => 'Who approves designs and content? (if different)', 'ph' => 'Name and email' ),
						array( 'k' => 'billing', 'l' => 'Who should receive invoices? (if different)', 'ph' => 'Name and email' ),
					) ),
					array( 'key' => 'business', 'title' => 'Business details', 'kind' => 'fields', 'why' => 'Used in your contract, your invoices and everywhere your business appears.', 'fields' => array(
						array( 'k' => 'legal', 'l' => 'Legal business name', 'ph' => 'As registered, e.g. Summit Ridge Automotive LLC' ),
						array( 'k' => 'public', 'l' => 'Name customers know you by' ),
						array( 'k' => 'founded', 'l' => 'Year founded', 'ph' => 'e.g. 2009' ),
						array( 'k' => 'areas', 'l' => 'Service areas', 'ph' => 'Cities, regions or “nationwide”' ),
						array( 'k' => 'address', 'l' => 'Address to show on your website', 'ph' => 'Leave blank to keep it private', 'when' => array( 'web', 'store', 'seo' ) ),
						array( 'k' => 'phone', 'l' => 'Phone number to show', 't' => 'tel', 'when' => array( 'web', 'store', 'seo' ) ),
						array( 'k' => 'email', 'l' => 'Email address to show', 't' => 'email', 'when' => array( 'web', 'store', 'seo' ) ),
						array( 'k' => 'hours', 'l' => 'Business hours (and holiday hours)', 't' => 'textarea', 'ph' => "Mon–Fri 8–5\nSat 9–1", 'when' => array( 'web', 'store', 'seo' ) ),
						array( 'k' => 'licenses', 'l' => 'License or registration numbers to display', 'ph' => 'e.g. ROC #123456', 'when' => array( 'web', 'seo' ) ),
					) ),
					array( 'key' => 'dates', 'title' => 'Dates and deadlines', 'kind' => 'fields', 'why' => 'So the schedule works around your busy season, events and time away.', 'fields' => array(
						array( 'k' => 'launch', 'l' => 'Target launch date or hard deadline' ),
						array( 'k' => 'away', 'l' => 'Dates you’re unavailable (vacations, busy season, events)', 't' => 'textarea' ),
						array( 'k' => 'changes', 'l' => 'Anything that has changed since we talked?', 't' => 'textarea' ),
					) ),
				),
			),
			array(
				'key'   => 'brand',
				'title' => 'Your brand',
				'intro' => 'Your logo, colors and the details that make it look like you.',
				'items' => array(
					array( 'key' => 'logo', 'title' => 'Logo files', 'kind' => 'upload', 'when' => $all_look, 'accept' => 'image,vector,doc,zip', 'why' => 'Every version you have: full color, one color, white, horizontal, stacked and the icon on its own. Vector files (AI, EPS, SVG or PDF) are best; a large PNG works too.', 'na' => 'We don’t have a logo yet', 'link' => true ),
					array( 'key' => 'colors', 'title' => 'Brand colors', 'kind' => 'colors', 'when' => $all_look, 'why' => 'Hex codes for screens. Add CMYK or Pantone (PMS) for print if you have them.', 'na' => 'We don’t have set colors' ),
					array( 'key' => 'fonts', 'title' => 'Fonts', 'kind' => 'upload', 'when' => array( 'web', 'store', 'brand', 'social', 'ads' ), 'accept' => 'font,zip,doc', 'optional' => true, 'why' => 'The fonts on your signs, print materials and current website. Font names are enough; files help if you own a licensed font.', 'note' => 'Font names (e.g. “Montserrat for headings, Lato for text”)', 'na' => 'Not sure / we don’t have any' ),
					array( 'key' => 'brand_guide', 'title' => 'Brand guidelines', 'kind' => 'upload', 'when' => $all_look, 'accept' => 'doc,image,zip', 'optional' => true, 'why' => 'A style guide or brand book, if you have one.', 'na' => 'We don’t have one', 'link' => true ),
					array( 'key' => 'graphics', 'title' => 'Icons, patterns and graphics', 'kind' => 'upload', 'when' => $all_look, 'accept' => 'image,vector,doc,zip', 'optional' => true, 'why' => 'Any icons, illustrations, patterns or graphic elements you already use.', 'na' => 'We don’t have any', 'link' => true ),
					array( 'key' => 'badges', 'title' => 'Badges, awards and certifications', 'kind' => 'upload', 'when' => array( 'web', 'store', 'seo' ), 'accept' => 'image,vector,doc,zip', 'optional' => true, 'why' => 'Licenses, certifications, association memberships, awards and “as seen in” logos. They build trust on a website fast.', 'note' => 'Which ones, and anything we should say about them', 'na' => 'None to show' ),
					array( 'key' => 'voice', 'title' => 'Voice and tagline', 'kind' => 'fields', 'when' => array( 'web', 'store', 'brand', 'social', 'ads' ), 'optional' => true, 'why' => 'How you sound, so every page and post reads like you.', 'fields' => array(
						array( 'k' => 'tagline', 'l' => 'Tagline or slogan (if you have one)' ),
						array( 'k' => 'tone', 'l' => 'How should you sound?', 't' => 'select', 'o' => array( 'Friendly and down to earth', 'Professional and polished', 'Bold and confident', 'Warm and caring', 'Fun and playful', 'Expert and technical' ) ),
						array( 'k' => 'use', 'l' => 'Words and phrases to use', 't' => 'textarea' ),
						array( 'k' => 'avoid', 'l' => 'Words, claims or topics to avoid', 't' => 'textarea' ),
					) ),
				),
			),
			array(
				'key'   => 'media',
				'title' => 'Photos & video',
				'intro' => 'Real photos of your work, team and place do more for a site than anything else.',
				'items' => array(
					array( 'key' => 'photos', 'title' => 'Photos, organized by service', 'kind' => 'albums', 'when' => array( 'web', 'store', 'social', 'ads' ), 'why' => 'Make an album for each service or type of work (plus your team and your location) and drop the photos in. That way we know exactly what each picture shows. Originals at full size, please.', 'na' => 'We don’t have photos (we’ll need a photographer or stock)', 'link' => true, 'rights' => 'These photos are ours to use, and anyone recognizable in them (including customers in before-and-after photos) has agreed.' ),
					array( 'key' => 'video', 'title' => 'Video', 'kind' => 'upload', 'when' => array( 'web', 'store', 'social', 'ads' ), 'accept' => 'video,zip', 'optional' => true, 'why' => 'Promo videos, testimonials or footage of your work. For long or large videos, a YouTube, Vimeo or Google Drive link is easiest.', 'na' => 'No video', 'link' => true, 'rights' => 'We own these videos or have permission to use them.' ),
				),
			),
			array(
				'key'   => 'story',
				'title' => 'Your story & content',
				'intro' => 'The words that tell customers who you are. Rough notes are fine: we polish them.',
				'items' => array(
					array( 'key' => 'about', 'title' => 'About your business', 'kind' => 'fields', 'when' => array( 'web', 'store', 'brand', 'social', 'seo' ), 'why' => 'Your story, in your own words. Bullet points are perfect.', 'fields' => array(
						array( 'k' => 'story', 'l' => 'How you started, and your story so far', 't' => 'textarea' ),
						array( 'k' => 'mission', 'l' => 'Mission (why you do what you do)', 't' => 'textarea' ),
						array( 'k' => 'vision', 'l' => 'Vision (where you’re headed)', 't' => 'textarea' ),
						array( 'k' => 'different', 'l' => 'What makes you different from competitors?', 't' => 'textarea' ),
						array( 'k' => 'values', 'l' => 'Core values', 't' => 'textarea' ),
						array( 'k' => 'proof', 'l' => 'Numbers worth bragging about', 'ph' => 'e.g. 2,000+ roofs since 2009, 4.9★ on Google, 24-hour response' ),
					) ),
					array( 'key' => 'services', 'title' => 'Services or products', 'kind' => 'rows', 'when' => array( 'web', 'store', 'seo', 'ads' ), 'why' => 'Everything you offer, with a sentence or two each. We turn it into service pages and ads.', 'cols' => array( array( 'k' => 'name', 'l' => 'Service', 'ph' => 'e.g. Brake repair' ), array( 'k' => 'desc', 'l' => 'What it includes', 'ph' => 'A sentence or two', 't' => 'textarea' ), array( 'k' => 'price', 'l' => 'Starting price (optional)', 'ph' => 'e.g. From $149' ) ), 'add' => 'Add a service' ),
					array( 'key' => 'team', 'title' => 'Team members to feature', 'kind' => 'people', 'when' => array( 'web', 'social' ), 'optional' => true, 'why' => 'Who should appear on your team page, with a short bio and a headshot.', 'na' => 'We don’t want a team page', 'add' => 'Add a person' ),
					array( 'key' => 'reviews', 'title' => 'Reviews and testimonials', 'kind' => 'rows', 'when' => array( 'web', 'store', 'social', 'ads' ), 'optional' => true, 'why' => 'Your best customer quotes. Or just tick the box and we’ll pull your best Google reviews.', 'cols' => array( array( 'k' => 'quote', 'l' => 'What they said', 't' => 'textarea' ), array( 'k' => 'name', 'l' => 'Name (or first name and city)' ), array( 'k' => 'src', 'l' => 'Where it’s from', 'ph' => 'Google, Facebook, email…' ) ), 'add' => 'Add a review', 'check' => 'Use our best Google reviews' ),
					array( 'key' => 'faqs', 'title' => 'Frequently asked questions', 'kind' => 'rows', 'when' => array( 'web', 'store', 'seo' ), 'optional' => true, 'why' => 'The questions customers ask most. Great for your site and for Google.', 'cols' => array( array( 'k' => 'q', 'l' => 'Question' ), array( 'k' => 'a', 'l' => 'Answer', 't' => 'textarea' ) ), 'add' => 'Add a question' ),
					array( 'key' => 'collateral', 'title' => 'Brochures, flyers, menus and past materials', 'kind' => 'upload', 'when' => array( 'web', 'store', 'brand', 'social', 'ads' ), 'accept' => 'doc,image,vector,zip', 'optional' => true, 'why' => 'Anything printed or designed before: brochures, flyers, price lists, menus, presentations, earlier ads. Working files or PDFs are both fine.', 'na' => 'Nothing to share', 'link' => true ),
					array( 'key' => 'legal', 'title' => 'Legal pages and disclaimers', 'kind' => 'upload', 'when' => array( 'web', 'store' ), 'accept' => 'doc,zip', 'optional' => true, 'why' => 'Your current privacy policy, terms, refund policy or any wording your industry requires. If you don’t have them, we’ll add standard ones for you to review.', 'note' => 'Anything we must include (licenses, disclosures, HIPAA notices)', 'na' => 'We don’t have any yet', 'link' => true ),
					array( 'key' => 'audience', 'title' => 'Customers, competitors and keywords', 'kind' => 'fields', 'when' => array( 'seo', 'ads', 'social', 'web' ), 'why' => 'Who you want to reach and what they search for.', 'fields' => array(
						array( 'k' => 'ideal', 'l' => 'Your ideal customer', 't' => 'textarea' ),
						array( 'k' => 'competitors', 'l' => 'Main competitors (names or websites)', 't' => 'textarea' ),
						array( 'k' => 'keywords', 'l' => 'What should people be able to find you for?', 't' => 'textarea', 'ph' => 'e.g. “roof repair Mesa”, “emergency plumber”', 'when' => array( 'seo', 'ads', 'web' ) ),
						array( 'k' => 'cities', 'l' => 'Cities or areas to target', 'when' => array( 'seo', 'ads' ) ),
					) ),
				),
			),
			array(
				'key'   => 'web',
				'title' => 'Website & domain',
				'intro' => 'Access to the accounts behind your website. Inviting us is safest: you never have to share a password.',
				'items' => array(
					array( 'key' => 'domain', 'title' => 'Domain (web address)', 'kind' => 'access', 'p' => 'registrar', 'when' => array( 'web', 'store', 'email', 'seo' ), 'needs' => array( 'domain' ), 'why' => 'Where your domain is registered, so we can point it at the new site and set up email records.', 'na' => 'We don’t own a domain yet (please buy one for us)' ),
					array( 'key' => 'hosting', 'title' => 'Current website hosting', 'kind' => 'access', 'p' => 'host', 'when' => array( 'web', 'store' ), 'needs' => array( 'has-site' ), 'why' => 'Where your current site lives, so we can back it up and move or rebuild it without downtime.', 'na' => 'Not sure / no current hosting' ),
					array( 'key' => 'cms', 'title' => 'Current website login', 'kind' => 'access', 'p' => 'cms', 'when' => array( 'web', 'store', 'seo' ), 'needs' => array( 'has-site' ), 'why' => 'Access to the site itself (WordPress, Wix, Squarespace…), so we can copy content and keep anything that’s working.', 'na' => 'We don’t have a website yet' ),
					array( 'key' => 'keep_pages', 'title' => 'Pages and links that must keep working', 'kind' => 'fields', 'when' => array( 'web', 'store', 'seo' ), 'needs' => array( 'has-site' ), 'optional' => true, 'why' => 'Pages Google already ranks, links printed on cards or trucks, and anything people bookmark, so nothing breaks at launch.', 'fields' => array( array( 'k' => 'urls', 'l' => 'Pages or links to keep', 't' => 'textarea', 'ph' => 'One per line' ), array( 'k' => 'keep', 'l' => 'Anything on the current site you want to keep?', 't' => 'textarea' ) ) ),
					array( 'key' => 'forms', 'title' => 'Where website messages should go', 'kind' => 'rows', 'when' => array( 'web', 'store' ), 'why' => 'Who receives each form: contact, quote requests, bookings, job applications.', 'cols' => array( array( 'k' => 'form', 'l' => 'Form', 'ph' => 'e.g. Contact, Quote request' ), array( 'k' => 'to', 'l' => 'Send to (email addresses)', 'ph' => 'name@yourbusiness.com, …' ) ), 'add' => 'Add a form' ),
					array( 'key' => 'ga', 'title' => 'Google Analytics', 'kind' => 'access', 'p' => 'ga', 'login' => false, 'when' => array( 'web', 'store', 'seo', 'ads' ), 'needs' => array( 'has-site', 'seo', 'ads' ), 'why' => 'Keeps your visitor history when the new site launches, and lets us report on what’s working.', 'na' => 'We don’t use it / not sure (we’ll set it up)' ),
					array( 'key' => 'gsc', 'title' => 'Google Search Console', 'kind' => 'access', 'p' => 'gsc', 'login' => false, 'when' => array( 'web', 'store', 'seo' ), 'needs' => array( 'has-site', 'seo' ), 'why' => 'Shows how Google sees your site. We use it to protect your rankings at launch.', 'na' => 'We don’t use it / not sure (we’ll set it up)' ),
					array( 'key' => 'gbp', 'title' => 'Google Business Profile', 'kind' => 'access', 'p' => 'gbp', 'login' => false, 'when' => array( 'seo', 'web', 'ads' ), 'why' => 'Your listing on Google Maps and search. It matters more for local customers than anything else.', 'na' => 'We don’t have one (we’ll help you set it up)' ),
					array( 'key' => 'gtm', 'title' => 'Google Tag Manager', 'kind' => 'access', 'p' => 'gtm', 'login' => false, 'when' => array( 'ads', 'seo' ), 'optional' => true, 'why' => 'Used for tracking calls, forms and ad conversions.', 'na' => 'We don’t use it / not sure' ),
					array( 'key' => 'store_setup', 'title' => 'Store setup', 'kind' => 'fields', 'when' => array( 'store' ), 'why' => 'How you get paid and ship, so checkout works on day one.', 'fields' => array(
						array( 'k' => 'payments', 'l' => 'How should customers pay?', 't' => 'select', 'o' => array( 'Stripe', 'Square', 'PayPal', 'Shopify Payments', 'Not sure: recommend one' ) ),
						array( 'k' => 'shipping', 'l' => 'Shipping (rates, zones, free-shipping rules) or local pickup', 't' => 'textarea' ),
						array( 'k' => 'tax', 'l' => 'Sales tax: where do you collect it?', 't' => 'textarea' ),
						array( 'k' => 'returns', 'l' => 'Returns and refund policy', 't' => 'textarea' ),
					) ),
					array( 'key' => 'products', 'title' => 'Product list and product photos', 'kind' => 'upload', 'when' => array( 'store' ), 'accept' => 'doc,image,zip', 'why' => 'A spreadsheet of products (name, price, sizes or options, description, stock) and the photos for each. Name photos after the product so we can match them.', 'link' => true ),
				),
			),
			array(
				'key'   => 'email',
				'title' => 'Business email',
				'intro' => 'Setting up or moving your company email without losing a message.',
				'items' => array(
					array( 'key' => 'email_host', 'title' => 'Where your email is hosted', 'kind' => 'access', 'p' => 'email', 'when' => array( 'email' ), 'why' => 'Admin access to your email provider, so we can create mailboxes and connect your domain.', 'na' => 'We don’t have business email yet' ),
					array( 'key' => 'mailboxes', 'title' => 'Mailboxes you need', 'kind' => 'rows', 'when' => array( 'email' ), 'why' => 'Each address and who uses it. Shared inboxes (info@, billing@) count too.', 'cols' => array( array( 'k' => 'address', 'l' => 'Email address', 'ph' => 'name@yourbusiness.com' ), array( 'k' => 'who', 'l' => 'Who uses it', 'ph' => 'Name, or “shared”' ), array( 'k' => 'move', 'l' => 'Move old email in?', 't' => 'select', 'o' => array( 'Yes', 'No', 'Not sure' ) ) ), 'add' => 'Add a mailbox' ),
				),
			),
			array(
				'key'   => 'system',
				'title' => 'Your new system',
				'intro' => 'Your people, your records and the paperwork the new system will replace.',
				'items' => array(
					array( 'key' => 'users', 'title' => 'Who needs a login', 'kind' => 'rows', 'when' => array( 'soft' ), 'why' => 'Everyone we should create an account for, and what they should see.', 'cols' => array( array( 'k' => 'name', 'l' => 'Name' ), array( 'k' => 'email', 'l' => 'Email', 't' => 'email' ), array( 'k' => 'role', 'l' => 'Role / what they should see', 'ph' => 'e.g. Owner: everything · Tech: own jobs' ) ), 'add' => 'Add a person' ),
					array( 'key' => 'data', 'title' => 'Records to bring over', 'kind' => 'upload', 'when' => array( 'soft' ), 'needs' => array( 'migration' ), 'accept' => 'doc,zip', 'why' => 'Exports from your current system or spreadsheets: customers, jobs, invoices, products. CSV or Excel is ideal. Most systems have an Export button under Reports or Settings.', 'note' => 'Where each file comes from and how far back it goes', 'na' => 'Nothing to bring over (starting fresh)', 'link' => true ),
					array( 'key' => 'docs', 'title' => 'Forms, templates and paperwork you use today', 'kind' => 'upload', 'when' => array( 'soft' ), 'accept' => 'doc,image,zip', 'why' => 'Quotes, invoices, work orders, intake forms, contracts, checklists, even photos of paper forms. We rebuild them inside the system.', 'na' => 'Nothing to share', 'link' => true ),
					array( 'key' => 'rules', 'title' => 'Rules the system must follow', 'kind' => 'fields', 'when' => array( 'soft' ), 'optional' => true, 'why' => 'Pricing rules, job statuses, approval steps or anything that must always happen a certain way.', 'fields' => array( array( 'k' => 'statuses', 'l' => 'Stages or statuses work moves through', 't' => 'textarea', 'ph' => 'e.g. New → Quoted → Scheduled → Done → Paid' ), array( 'k' => 'pricing', 'l' => 'Pricing or discount rules', 't' => 'textarea' ), array( 'k' => 'other', 'l' => 'Anything else that must always happen', 't' => 'textarea' ) ) ),
					array( 'key' => 'qbo', 'title' => 'QuickBooks', 'kind' => 'connect', 'when' => array( 'soft', 'web', 'store' ), 'needs' => array( 'quickbooks' ), 'why' => 'We connect QuickBooks securely during setup: you’ll just sign in and press Connect when we ask. Nothing to share now; tell us who your QuickBooks admin is.', 'fields' => array( array( 'k' => 'admin', 'l' => 'QuickBooks admin (name and email)' ), array( 'k' => 'version', 'l' => 'Which QuickBooks?', 't' => 'select', 'o' => array( 'QuickBooks Online', 'QuickBooks Desktop', 'Not sure' ) ) ) ),
					array( 'key' => 'stripe', 'title' => 'Stripe', 'kind' => 'access', 'p' => 'stripe', 'when' => array( 'soft', 'web', 'store' ), 'needs' => array( 'stripe' ), 'why' => 'So we can connect payments and test them before launch.', 'na' => 'We don’t have Stripe yet (please set it up)' ),
					array( 'key' => 'square', 'title' => 'Square', 'kind' => 'connect', 'when' => array( 'soft', 'web', 'store' ), 'needs' => array( 'square' ), 'why' => 'Square connects with your sign-in during setup. Nothing to share now.', 'fields' => array( array( 'k' => 'owner', 'l' => 'Who owns the Square account? (name and email)' ) ) ),
					array( 'key' => 'workspace', 'title' => 'Google Workspace or Microsoft 365', 'kind' => 'connect', 'when' => array( 'soft' ), 'needs' => array( 'google-workspace', 'microsoft-365' ), 'why' => 'Calendars, email and files connect with an admin’s approval during setup. Nothing to share now.', 'fields' => array( array( 'k' => 'admin', 'l' => 'Who is your Google or Microsoft admin? (name and email)' ), array( 'k' => 'which', 'l' => 'Which do you use?', 't' => 'select', 'o' => array( 'Google Workspace', 'Microsoft 365', 'Both', 'Not sure' ) ) ) ),
					array( 'key' => 'twilio', 'title' => 'Texting (Twilio)', 'kind' => 'access', 'p' => 'twilio', 'when' => array( 'soft' ), 'needs' => array( 'twilio' ), 'why' => 'For text messages from the system. If you don’t have an account, we’ll set one up in your name.', 'na' => 'We don’t have one (please set it up)' ),
					array( 'key' => 'mailing', 'title' => 'Email marketing', 'kind' => 'access', 'p' => 'mailchimp', 'when' => array( 'soft', 'web', 'store', 'social' ), 'needs' => array( 'mailchimp' ), 'why' => 'So sign-ups and customers flow into your list automatically.', 'na' => 'We don’t use one' ),
					array( 'key' => 'booking_tool', 'title' => 'Booking or scheduling tool', 'kind' => 'access', 'p' => 'booking', 'when' => array( 'web', 'soft', 'store' ), 'needs' => array( 'booking' ), 'why' => 'So bookings show on your site and flow into your system.', 'na' => 'We don’t use one (please recommend one)' ),
					array( 'key' => 'industry_sw', 'title' => 'Your industry software', 'kind' => 'access', 'p' => 'software', 'when' => array( 'soft', 'web' ), 'needs' => array( 'industry-software' ), 'why' => 'Access to the software we’re connecting to (an invite, an API key or a login), and the name of your account rep there if you have one.', 'na' => 'Not needed after all' ),
					array( 'key' => 'app_host', 'title' => 'Cloud hosting account', 'kind' => 'access', 'p' => 'apphost', 'when' => array( 'soft', 'host' ), 'needs' => array( 'app-hosting' ), 'why' => 'If your system should run in your own cloud account (Render, Vercel, AWS and so on), invite us to it. Otherwise we set it up and hand it over.', 'na' => 'We don’t have one: please set it up' ),
					array( 'key' => 'other_access', 'title' => 'Other systems we need into', 'kind' => 'rows', 'when' => array( 'soft' ), 'optional' => true, 'why' => 'Anything else we’ll connect to or move data out of, and how we can get access.', 'cols' => array( array( 'k' => 'system', 'l' => 'System' ), array( 'k' => 'how', 'l' => 'How we can get access', 'ph' => 'e.g. invited you, or “please call me”' ) ), 'add' => 'Add a system' ),
				),
			),
			array(
				'key'   => 'marketing',
				'title' => 'Marketing accounts',
				'intro' => 'Access to the accounts we’ll be managing for you.',
				'items' => array(
					array( 'key' => 'gads', 'title' => 'Google Ads', 'kind' => 'access', 'p' => 'gads', 'login' => false, 'when' => array( 'ads' ), 'why' => 'Admin access to your Google Ads account so we can build and manage campaigns. Ad spend is billed to you by Google.', 'na' => 'We don’t have an account (please create one for us)' ),
					array( 'key' => 'meta', 'title' => 'Facebook & Instagram', 'kind' => 'access', 'p' => 'meta', 'when' => array( 'social' ), 'why' => 'Access to your Page and Instagram through Meta Business Suite, so we can post and manage them.', 'na' => 'We don’t have these accounts yet' ),
					array( 'key' => 'linkedin', 'title' => 'LinkedIn Page', 'kind' => 'access', 'p' => 'linkedin', 'login' => false, 'when' => array( 'social' ), 'optional' => true, 'why' => 'Admin access to your company Page so we can post.', 'na' => 'We don’t use LinkedIn' ),
					array( 'key' => 'social_more', 'title' => 'Other social accounts we’ll manage', 'kind' => 'rows', 'when' => array( 'social' ), 'optional' => true, 'why' => 'TikTok, YouTube, X, Pinterest, Nextdoor, Yelp… For accounts without an invite option, use the secure login box on each row we follow up on, or ask your rep.', 'cols' => array( array( 'k' => 'platform', 'l' => 'Platform' ), array( 'k' => 'handle', 'l' => 'Account name or link' ), array( 'k' => 'access', 'l' => 'How we get access', 't' => 'select', 'o' => array( 'I’ve invited you', 'I’ll share a login', 'Please call me' ) ) ), 'add' => 'Add an account', 'secret' => true ),
					array( 'key' => 'social_links', 'title' => 'Your social profiles', 'kind' => 'fields', 'when' => array( 'web', 'store', 'social' ), 'optional' => true, 'why' => 'Links for your website’s footer and contact page.', 'fields' => array(
						array( 'k' => 'facebook', 'l' => 'Facebook', 't' => 'url' ), array( 'k' => 'instagram', 'l' => 'Instagram', 't' => 'url' ), array( 'k' => 'linkedin', 'l' => 'LinkedIn', 't' => 'url' ), array( 'k' => 'youtube', 'l' => 'YouTube', 't' => 'url' ), array( 'k' => 'tiktok', 'l' => 'TikTok', 't' => 'url' ), array( 'k' => 'x', 'l' => 'X (Twitter)', 't' => 'url' ), array( 'k' => 'reviews', 'l' => 'Google reviews link', 't' => 'url' ), array( 'k' => 'other', 'l' => 'Other (Yelp, Nextdoor, Houzz…)', 't' => 'textarea' ),
					) ),
					array( 'key' => 'posting', 'title' => 'How you’d like posting to work', 'kind' => 'fields', 'when' => array( 'social' ), 'why' => 'So posts sound like you and go out the way you want.', 'fields' => array(
						array( 'k' => 'approve', 'l' => 'Who approves posts before they go out?' ),
						array( 'k' => 'topics', 'l' => 'Topics, promotions or events to feature', 't' => 'textarea' ),
						array( 'k' => 'avoid', 'l' => 'Anything we should never post about?', 't' => 'textarea' ),
					) ),
					array( 'key' => 'campaigns', 'title' => 'Past ads and campaign ideas', 'kind' => 'upload', 'when' => array( 'ads', 'social' ), 'accept' => 'doc,image,video,zip', 'optional' => true, 'why' => 'Ads you’ve run, what worked and didn’t, results reports, and any ideas you’ve been sitting on.', 'note' => 'What worked, what didn’t, and ideas you’d like to try', 'na' => 'Nothing yet', 'link' => true ),
				),
			),
		);
		return apply_filters( 'axh_kickoff_library', $sections );
	}

	/** item key => item (with its section key), for lookups. */
	public static function items() {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( self::library() as $sec ) {
				foreach ( $sec['items'] as $it ) {
					$it['sec']           = $sec['key'];
					$map[ $it['key'] ] = $it;
				}
			}
		}
		return $map;
	}

	/* ----------------------------------------------- what the job needs */

	/**
	 * Read the job: the discovery answers and the proposal (accepted first,
	 * else the latest one sent). Returns types, signals and the reasons for
	 * each, in plain words, for the Hub to show.
	 */
	public static function signals( $lead_id, $lead = null ) {
		global $wpdb;
		$types   = array();
		$signals = array();
		$why     = array();
		$add     = function ( $bag, $key, $reason ) use ( &$types, &$signals, &$why ) {
			if ( 'type' === $bag ) {
				$types[ $key ] = true;
			} else {
				$signals[ $key ] = true;
			}
			if ( ! isset( $why[ $bag . ':' . $key ] ) ) {
				$why[ $bag . ':' . $key ] = $reason;
			}
		};

		// The discovery questionnaire.
		$d = $wpdb->get_row( $wpdb->prepare( 'SELECT payload FROM ' . AXH_DB::table( 'discovery' ) . ' WHERE lead_id = %d', (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		$p = $d && $d['payload'] ? json_decode( $d['payload'], true ) : array();
		$p = is_array( $p ) ? $p : array();
		$need_map = array(
			'A new website' => 'web', 'A website redesign' => 'web', 'An online store' => 'store', 'A custom CRM' => 'soft',
			'Project-management / operations software' => 'soft', 'A client or member portal' => 'soft', 'A custom app or other software' => 'soft',
			'Automations between tools' => 'soft', 'SEO / getting found locally' => 'seo', 'Google Ads' => 'ads', 'Social media management' => 'social', 'Branding or a logo' => 'brand',
		);
		foreach ( (array) ( $p['goals']['need'] ?? array() ) as $n ) {
			if ( isset( $need_map[ $n ] ) ) {
				$add( 'type', $need_map[ $n ], 'Discovery: “' . $n . '”' );
				if ( 'store' === $need_map[ $n ] ) {
					$add( 'type', 'web', 'Discovery: “' . $n . '”' );
				}
			}
		}
		$ongoing = array( 'Monthly SEO' => 'seo', 'Google Ads management' => 'ads', 'Social media management' => 'social', 'Website care (updates and backups)' => 'web' );
		foreach ( (array) ( $p['decision']['ongoing'] ?? array() ) as $n ) {
			if ( isset( $ongoing[ $n ] ) ) {
				$add( 'type', $ongoing[ $n ], 'Discovery, ongoing help: “' . $n . '”' );
			}
		}
		$int = array(
			'QuickBooks' => 'quickbooks', 'Stripe' => 'stripe', 'Square' => 'square', 'Google Workspace' => 'google-workspace', 'Gmail' => 'google-workspace',
			'Google Calendar' => 'google-workspace', 'Google Drive / Sheets' => 'google-workspace', 'Microsoft 365' => 'microsoft-365', 'Twilio / SMS' => 'twilio',
			'Mailchimp or similar' => 'mailchimp', 'Online booking / scheduling' => 'booking', 'Church management software' => 'industry-software', 'Industry software (name it below)' => 'industry-software',
		);
		foreach ( (array) ( $p['data']['integrations'] ?? array() ) as $n ) {
			if ( isset( $int[ $n ] ) ) {
				$add( 'sig', $int[ $n ], 'Discovery, connect to: “' . $n . '”' );
			}
		}
		if ( trim( (string) ( $p['data']['industry_software'] ?? '' ) ) ) {
			$add( 'sig', 'industry-software', 'Discovery: uses ' . mb_substr( (string) $p['data']['industry_software'], 0, 60 ) );
		}
		foreach ( (array) ( $p['data']['migrate'] ?? array() ) as $n ) {
			if ( false === strpos( (string) $n, 'Nothing' ) ) {
				$add( 'sig', 'migration', 'Discovery: records to bring over' );
			}
		}
		if ( in_array( 'Take bookings / appointments', (array) ( $p['web']['site_goals'] ?? array() ), true ) ) {
			$add( 'sig', 'booking', 'Discovery: the website takes bookings' );
		}
		foreach ( (array) ( $p['web']['hosting_help'] ?? array() ) as $n ) {
			if ( 0 === strpos( (string) $n, 'Business email' ) ) {
				$add( 'type', 'email', 'Discovery: wants business email set up' );
			}
		}
		$site = trim( (string) ( $p['stack']['website_url'] ?? '' ) );
		$lead = $lead ? $lead : $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AXH_DB::table( 'leads' ) . ' WHERE id = %d', (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		if ( '' === $site && $lead ) {
			$site = trim( (string) ( $lead['website'] ?? '' ) );
		}
		if ( '' !== $site && 'No current site' !== ( $p['web']['content_move'] ?? '' ) ) {
			$add( 'sig', 'has-site', 'They have a website: ' . mb_substr( $site, 0, 60 ) );
			$add( 'sig', 'domain', 'They have a website, so they own a domain' );
		}
		if ( 'Yes' === ( $p['web']['domain'] ?? '' ) ) {
			$add( 'sig', 'domain', 'Discovery: they own their domain' );
		}

		// The proposal: the option they chose and the add-ons switched on.
		$doc = null;
		if ( class_exists( 'AXH_Bill' ) && method_exists( 'AXH_Bill', 'doc_row' ) ) {
			$bd  = AXH_DB::table( 'bdocs' );
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$bd} WHERE lead_id = %d AND type IN ('proposal','estimate') ORDER BY (status = 'accepted') DESC, (status <> 'draft') DESC, id DESC LIMIT 1", (int) $lead_id ) ); // phpcs:ignore
			if ( $ids ) {
				$raw = AXH_Bill::doc_row( (int) $ids[0], false );
				$doc = $raw && method_exists( 'AXH_Bill', 'shape_doc' ) ? AXH_Bill::shape_doc( $raw, 'admin' ) : null;
			}
		}
		$from = '';
		if ( $doc ) {
			$from  = ( 'accepted' === $doc['status'] ? 'Accepted ' : '' ) . $doc['type'] . ' ' . $doc['number'];
			$lines = method_exists( 'AXH_Bill', 'lines_for' ) ? AXH_Bill::lines_for( $doc ) : (array) $doc['lines'];
			$texts = array( (string) $doc['title'] );
			foreach ( (array) $lines as $l ) {
				$texts[] = trim( ( $l['name'] ?? '' ) . ' ' . ( $l['desc'] ?? '' ) );
			}
			$kw = array(
				'type:web'    => '/\b(website|web ?site|web design|landing page|wordpress|redesign|home ?page|service pages?)\b/i',
				'type:store'  => '/\b(e-?commerce|online store|shopify|woocommerce|product catalog|checkout)\b/i',
				'type:soft'   => '/\b(crm|portal|dashboard|software|app|application|automation|integration|database|project.management|scheduling system)\b/i',
				'type:brand'  => '/\b(logo|branding|brand identity|brand kit|style guide|business cards?)\b/i',
				'type:seo'    => '/\b(seo|search engine|google business profile|local search|rankings?)\b/i',
				'type:ads'    => '/\b(google ads|ppc|pay.per.click|paid search|ad campaign)\b/i',
				'type:social' => '/\b(social media|instagram|facebook|tiktok|content calendar|posting)\b/i',
				'type:email'  => '/\b(business email|email setup|mailbox(es)?|email migration|google workspace setup|microsoft 365 setup)\b/i',
				'type:host'   => '/\b(render|vercel|netlify|aws|digitalocean|heroku|cloud hosting|app hosting|deploy(ment)?)\b/i',
				'sig:app-hosting'      => '/\b(render|vercel|netlify|aws|digitalocean|heroku|cloud hosting|app hosting)\b/i',
				'sig:quickbooks'       => '/\b(quickbooks|qbo)\b/i',
				'sig:stripe'           => '/\bstripe\b/i',
				'sig:square'           => '/\bsquare\b/i',
				'sig:twilio'           => '/\b(twilio|sms|text messag\w*)\b/i',
				'sig:mailchimp'        => '/\b(mailchimp|constant contact|klaviyo|newsletter|email marketing)\b/i',
				'sig:booking'          => '/\b(booking|online scheduling|appointments?|calendly)\b/i',
				'sig:migration'        => '/\b(migrat\w*|data import|import (of )?(existing )?(customers|records|data))\b/i',
				'sig:google-workspace' => '/\b(google workspace|gmail|google calendar)\b/i',
				'sig:microsoft-365'    => '/\b(microsoft 365|office 365|outlook)\b/i',
				'sig:domain'           => '/\b(domain|dns)\b/i',
			);
			foreach ( $texts as $t ) {
				if ( '' === $t ) {
					continue;
				}
				foreach ( $kw as $key => $re ) {
					if ( preg_match( $re, $t, $m ) ) {
						list( $bag, $k ) = explode( ':', $key );
						$add( 'type' === $bag ? 'type' : 'sig', $k, $from . ': “' . mb_substr( $t, 0, 70 ) . '”' );
					}
				}
			}
		}
		// Selling their store or building a site means a domain is involved.
		if ( isset( $types['web'] ) || isset( $types['store'] ) || isset( $types['email'] ) ) {
			$signals['domain'] = true;
		}
		// Signals that are project types too, for "needs" checks.
		foreach ( array( 'seo', 'ads' ) as $tk ) {
			if ( isset( $types[ $tk ] ) ) {
				$signals[ $tk ] = true;
			}
		}
		return array(
			'types'    => array_keys( $types ),
			'signals'  => array_keys( $signals ),
			'why'      => $why,
			'proposal' => $doc ? array( 'id' => (int) $doc['id'], 'number' => $doc['number'], 'status' => $doc['status'], 'title' => $doc['title'] ) : null,
		);
	}

	/** The items a job like this needs, with the reason each is there. */
	public static function suggest( $types, $signals ) {
		$out = array();
		foreach ( self::items() as $k => $it ) {
			if ( ! empty( $it['when'] ) && ! array_intersect( $it['when'], $types ) ) {
				continue;
			}
			if ( ! empty( $it['needs'] ) && ! array_intersect( $it['needs'], $signals ) ) {
				continue;
			}
			$out[] = $k;
		}
		return $out;
	}

	/* ------------------------------------------------------------ records */

	protected static function decode( $row ) {
		if ( ! $row ) {
			return null;
		}
		foreach ( array( 'config', 'data', 'state' ) as $k ) {
			$v         = json_decode( (string) ( $row[ $k ] ?? '' ), true );
			$row[ $k ] = is_array( $v ) ? $v : array();
		}
		return $row;
	}

	/** The onboarding on a lead the signed-in person can see. */
	public static function for_lead( $lead_id ) {
		global $wpdb;
		if ( ! AXH_DB::get_lead( (int) $lead_id ) ) {
			return null;
		}
		return self::decode( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AXH_DB::table( 'kickoff' ) . ' WHERE lead_id = %d', (int) $lead_id ), ARRAY_A ) ); // phpcs:ignore
	}

	/** The onboarding behind a client link. The token is the credential. */
	public static function by_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^a-f0-9]/', '', (string) $token );
		if ( strlen( $token ) < 24 ) {
			return null;
		}
		$row = self::decode( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AXH_DB::table( 'kickoff' ) . ' WHERE token = %s', $token ), ARRAY_A ) ); // phpcs:ignore
		if ( ! $row || 'closed' === $row['status'] ) {
			return null;
		}
		$row['lead'] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AXH_DB::table( 'leads' ) . ' WHERE id = %d', (int) $row['lead_id'] ), ARRAY_A ); // phpcs:ignore
		return $row['lead'] ? $row : null;
	}

	/** The items switched on for this onboarding, custom requests included, in library order. */
	public static function active_items( $row ) {
		$cfg  = (array) $row['config'];
		$on   = array_flip( (array) ( $cfg['items'] ?? array() ) );
		$out  = array();
		$all  = self::items();
		foreach ( $all as $k => $it ) {
			if ( isset( $on[ $k ] ) ) {
				$out[ $k ] = $it;
			}
		}
		foreach ( (array) ( $cfg['custom'] ?? array() ) as $c ) {
			$k = 'c_' . preg_replace( '/[^a-z0-9]/', '', (string) ( $c['id'] ?? '' ) );
			if ( 'c_' === $k ) {
				continue;
			}
			$out[ $k ] = array(
				'key'      => $k,
				'sec'      => 'extra',
				'title'    => (string) ( $c['title'] ?? 'Request' ),
				'kind'     => in_array( $c['kind'] ?? '', array( 'upload', 'text', 'access' ), true ) ? $c['kind'] : 'text',
				'why'      => (string) ( $c['help'] ?? '' ),
				'p'        => 'software',
				'link'     => true,
				'accept'   => 'image,vector,doc,zip,video,font',
				'optional' => ! empty( $c['optional'] ),
				'na'       => 'Not applicable',
			);
		}
		$types = (array) ( $cfg['types'] ?? array() );
		// Fields inside an item that belong to other project types are dropped.
		foreach ( $out as $k => $it ) {
			if ( ! empty( $it['fields'] ) ) {
				$out[ $k ]['fields'] = array_values( array_filter( $it['fields'], function ( $f ) use ( $types ) {
					return empty( $f['when'] ) || array_intersect( $f['when'], $types );
				} ) );
			}
		}
		return $out;
	}

	protected static function files_for( $row, $public = false ) {
		global $wpdb;
		if ( ! get_option( 'axh_files_db' ) ) {
			return array();
		}
		$t    = AXH_DB::table( 'lead_files' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, mime, size, kick, src, thumb, created_at FROM {$t} WHERE lead_id = %d AND kick <> '' ORDER BY id ASC LIMIT 2000", (int) $row['lead_id'] ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $f ) {
			$out[] = array(
				'id'    => (int) $f['id'],
				'name'  => $f['name'],
				'size'  => (int) $f['size'],
				'mime'  => $f['mime'],
				'item'  => $f['kick'],
				'thumb' => '' !== (string) $f['thumb'] || in_array( $f['mime'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml' ), true ) && (int) $f['size'] < 6291456,
				'by'    => 'client' === $f['src'] ? 'client' : 'team',
				'at'    => $f['created_at'],
			);
		}
		return $out;
	}

	/** Which logins are stored (never the logins themselves). */
	protected static function secret_meta( $row ) {
		$s   = self::secrets( $row );
		$out = array();
		foreach ( $s as $k => $v ) {
			$out[ $k ] = array( 'at' => $v['at'] ?? '', 'user' => self::mask( (string) ( $v['user'] ?? '' ) ) );
		}
		return $out;
	}

	protected static function mask( $u ) {
		if ( '' === $u ) {
			return '';
		}
		if ( false !== strpos( $u, '@' ) ) {
			list( $a, $b ) = explode( '@', $u, 2 );
			return mb_substr( $a, 0, 2 ) . str_repeat( '•', max( 2, min( 6, mb_strlen( $a ) - 2 ) ) ) . '@' . $b;
		}
		return mb_substr( $u, 0, 2 ) . str_repeat( '•', max( 3, min( 8, mb_strlen( $u ) - 2 ) ) );
	}

	protected static function secrets( $row ) {
		$blob = (string) ( $row['secrets'] ?? '' );
		if ( '' === $blob || ! method_exists( 'AXH_Onboard', 'decrypt' ) ) {
			return array();
		}
		$plain = AXH_Onboard::decrypt( (string) base64_decode( $blob ) ); // phpcs:ignore
		if ( is_wp_error( $plain ) ) {
			return array();
		}
		$v = json_decode( $plain, true );
		return is_array( $v ) ? $v : array();
	}

	protected static function put_secrets( $id, $secrets ) {
		global $wpdb;
		$blob = $secrets ? base64_encode( AXH_Onboard::encrypt( wp_json_encode( $secrets ) ) ) : ''; // phpcs:ignore
		$wpdb->update( AXH_DB::table( 'kickoff' ), array( 'secrets' => $blob, 'updated_at' => AXH_DB::now() ), array( 'id' => (int) $id ) );
	}

	/** Whether one item has what it needs. */
	public static function item_done( $it, $row, $files, $smeta ) {
		$k  = $it['key'];
		$d  = (array) ( $row['data'][ $k ] ?? array() );
		$st = (array) ( $row['state'][ $k ] ?? array() );
		$rv = (array) ( $row['state']['_review'][ $k ] ?? array() );
		if ( 'fix' === ( $rv['s'] ?? '' ) && empty( $st['fixed'] ) ) {
			return false;
		}
		if ( 'ok' === ( $rv['s'] ?? '' ) || ! empty( $st['na'] ) ) {
			return true;
		}
		$has = function ( $v ) {
			if ( is_array( $v ) ) {
				foreach ( $v as $x ) {
					if ( is_array( $x ) ? implode( '', array_map( 'strval', array_filter( $x, 'is_scalar' ) ) ) !== '' : '' !== trim( (string) $x ) ) {
						return true;
					}
				}
				return false;
			}
			return '' !== trim( (string) $v );
		};
		$nfiles = 0;
		foreach ( $files as $f ) {
			if ( $f['item'] === $k || 0 === strpos( $f['item'], $k . ':' ) ) {
				$nfiles++;
			}
		}
		switch ( $it['kind'] ) {
			case 'upload':
			case 'albums':
				return $nfiles > 0 || $has( $d['link'] ?? '' );
			case 'access':
				$mode = '' !== (string) ( $d['mode'] ?? '' ) ? (string) $d['mode'] : 'invite';
				return ( 'invite' === $mode && ! empty( $d['done'] ) ) || ( 'login' === $mode && isset( $smeta[ $k ] ) ) || ( 'none' === ( $d['platform'] ?? '' ) );
			case 'colors':
				return $has( $d['colors'] ?? array() ) || ! empty( $d['from_logo'] );
			case 'rows':
			case 'people':
				return $has( $d['rows'] ?? array() ) || ! empty( $d['check'] );
			case 'text':
				return $has( $d['text'] ?? '' ) || $nfiles > 0;
			case 'connect':
			case 'fields':
			default:
				foreach ( $d as $fk => $v ) {
					if ( '_' !== substr( (string) $fk, 0, 1 ) && $has( $v ) ) {
						return true;
					}
				}
				return false;
		}
	}

	/** Progress: share of the required items done (optional ones only add). */
	public static function progress( $row, $items = null, $files = null, $smeta = null ) {
		$items = null === $items ? self::active_items( $row ) : $items;
		$files = null === $files ? self::files_for( $row ) : $files;
		$smeta = null === $smeta ? self::secret_meta( $row ) : $smeta;
		$t     = 0;
		$n     = 0;
		$done  = array();
		foreach ( $items as $k => $it ) {
			$ok = self::item_done( $it, $row, $files, $smeta );
			if ( $ok ) {
				$done[] = $k;
			}
			if ( empty( $it['optional'] ) ) {
				$t++;
				if ( $ok ) {
					$n++;
				}
			}
		}
		return array( 'pct' => $t ? (int) floor( $n / $t * 100 ) : 0, 'done' => $n, 'total' => $t, 'items' => $done );
	}

	/** Everything the client page needs, and nothing it doesn't. */
	protected static function client_view( $row ) {
		$set   = self::settings();
		$items = self::active_items( $row );
		$files = self::files_for( $row, true );
		$smeta = self::secret_meta( $row );
		$lib   = array();
		foreach ( self::library() as $sec ) {
			$list = array();
			foreach ( $sec['items'] as $it ) {
				if ( isset( $items[ $it['key'] ] ) ) {
					$list[] = self::client_item( $items[ $it['key'] ] );
				}
			}
			if ( $list ) {
				$lib[] = array( 'key' => $sec['key'], 'title' => $sec['title'], 'intro' => $sec['intro'], 'items' => $list );
			}
		}
		$extra = array();
		foreach ( $items as $k => $it ) {
			if ( 'extra' === $it['sec'] ) {
				$extra[] = self::client_item( $it );
			}
		}
		if ( $extra ) {
			$lib[] = array( 'key' => 'extra', 'title' => 'A few more things', 'intro' => 'Specific to your project.', 'items' => $extra );
		}
		$review = array();
		foreach ( (array) ( $row['state']['_review'] ?? array() ) as $k => $r ) {
			if ( isset( $items[ $k ] ) && in_array( $r['s'] ?? '', array( 'ok', 'fix' ), true ) ) {
				$review[ $k ] = array( 's' => $r['s'], 'note' => (string) ( $r['note'] ?? '' ) );
			}
		}
		$rep = ! empty( $row['lead']['rep_id'] ) ? get_userdata( (int) $row['lead']['rep_id'] ) : null;
		$cfg = (array) $row['config'];
		return array(
			'sections' => $lib,
			'data'     => (object) array_intersect_key( (array) $row['data'], $items ),
			'state'    => (object) array_intersect_key( (array) $row['state'], $items ),
			'review'   => (object) $review,
			'files'    => $files,
			'secrets'  => (object) $smeta,
			'invite'   => $set['email'],
			'person'   => $set['person'],
			'metaId'   => $set['meta_id'],
			'company'  => (string) $row['lead']['company'],
			'contact'  => axh_first_name( $row['lead']['contact_name'] ?? '' ),
			'rep'      => $rep ? $rep->display_name : 'AdaptExec',
			'repEmail' => $rep ? $rep->user_email : $set['email'],
			'note'     => (string) ( $cfg['note'] ?? '' ),
			'due'      => (string) ( $cfg['due'] ?? '' ),
			'types'    => array_values( array_intersect_key( wp_list_pluck( self::types(), 0 ), array_flip( (array) ( $cfg['types'] ?? array() ) ) ) ),
			'status'   => $row['status'],
			'maxMb'    => $set['max_mb'],
			'chunk'    => self::chunk_size(),
		);
	}

	/** An item as the client page uses it. */
	protected static function client_item( $it ) {
		$c = array(
			'key'      => $it['key'],
			'title'    => $it['title'],
			'kind'     => $it['kind'],
			'why'      => $it['why'] ?? '',
			'optional' => ! empty( $it['optional'] ),
		);
		foreach ( array( 'na', 'link', 'note', 'rights', 'fields', 'cols', 'add', 'check', 'accept' ) as $k ) {
			if ( isset( $it[ $k ] ) ) {
				$c[ $k ] = $it[ $k ];
			}
		}
		if ( 'access' === $it['kind'] ) {
			$c['platforms'] = self::platforms( $it['p'] ?? 'software' );
			$c['login']     = ! isset( $it['login'] ) || $it['login'];
		}
		return $c;
	}

	protected static function chunk_size() {
		$max = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : 2097152;
		return max( 524288, min( 4194304, $max - 131072 ) );
	}

	/* ------------------------------------------------- create and update */

	/** Pre-fill from what we already know, so the client never types it twice. */
	protected static function prefill( $lead ) {
		global $wpdb;
		$d = $wpdb->get_row( $wpdb->prepare( 'SELECT payload FROM ' . AXH_DB::table( 'discovery' ) . ' WHERE lead_id = %d', (int) $lead['id'] ), ARRAY_A ); // phpcs:ignore
		$p = $d && $d['payload'] ? json_decode( $d['payload'], true ) : array();
		$p = is_array( $p ) ? $p : array();
		$g = function ( $s, $k ) use ( $p ) {
			$v = $p[ $s ][ $k ] ?? '';
			return is_array( $v ) ? '' : trim( (string) $v );
		};
		$out = array(
			'contacts' => array_filter( array( 'name' => (string) ( $lead['contact_name'] ?? '' ), 'role' => (string) ( $lead['title'] ?? '' ), 'email' => (string) ( $lead['email'] ?? '' ), 'phone' => (string) ( $lead['phone'] ?? '' ) ) ),
			'business' => array_filter( array( 'legal' => $g( 'business', 'legal_name' ), 'public' => $g( 'business', 'trade_name' ) ? $g( 'business', 'trade_name' ) : (string) $lead['company'], 'areas' => $g( 'business', 'service_area' ), 'founded' => ( (int) $g( 'business', 'years' ) > 0 && (int) $g( 'business', 'years' ) < 200 ) ? (string) ( (int) gmdate( 'Y' ) - (int) $g( 'business', 'years' ) ) : '' ) ),
			'dates'    => array_filter( array( 'launch' => $g( 'decision', 'go_live' ) ) ),
			'photos'   => array_filter( array( 'link' => $g( 'content', 'photo_link' ) ) ),
			'collateral' => array_filter( array( 'note' => $g( 'content', 'content_ready' ) ) ),
			'fonts'    => array_filter( array( 'note' => $g( 'content', 'brand_assets' ) ) ),
			'audience' => array_filter( array( 'competitors' => $g( 'examples', 'competitors' ), 'ideal' => '' ) ),
		);
		$plat = strtolower( $g( 'stack', 'website_platform' ) );
		foreach ( array( 'wordpress', 'wix', 'squarespace', 'shopify', 'webflow', 'godaddy' ) as $k ) {
			if ( false !== strpos( $plat, $k ) ) {
				$out['cms'] = array( 'platform' => $k, 'mode' => 'invite' );
			}
		}
		if ( $g( 'data', 'industry_software' ) ) {
			$out['industry_sw'] = array( 'note' => 'Software: ' . $g( 'data', 'industry_software' ) );
		}
		return array_filter( $out );
	}

	protected static function clean_config( $in, $old = array() ) {
		$types = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['types'] ?? array() ) ), array_keys( self::types() ) ) );
		$items = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['items'] ?? array() ) ), array_keys( self::items() ) ) );
		$cust  = array();
		foreach ( array_slice( (array) ( $in['custom'] ?? array() ), 0, 20 ) as $c ) {
			$title = mb_substr( sanitize_text_field( (string) ( $c['title'] ?? '' ) ), 0, 120 );
			if ( '' === $title ) {
				continue;
			}
			$id     = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $c['id'] ?? '' ) ) );
			$cust[] = array(
				'id'       => $id ? mb_substr( $id, 0, 12 ) : substr( md5( $title . wp_rand() ), 0, 8 ),
				'title'    => $title,
				'help'     => mb_substr( sanitize_textarea_field( (string) ( $c['help'] ?? '' ) ), 0, 800 ),
				'kind'     => in_array( $c['kind'] ?? '', array( 'upload', 'text', 'access' ), true ) ? $c['kind'] : 'text',
				'optional' => ! empty( $c['optional'] ),
			);
		}
		$due = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['due'] ?? '' ) ) ? (string) $in['due'] : '';
		return array(
			'types'  => $types,
			'items'  => $items,
			'custom' => $cust,
			'note'   => mb_substr( sanitize_textarea_field( (string) ( $in['note'] ?? '' ) ), 0, 1500 ),
			'due'    => $due,
		);
	}

	/** Create or update the onboarding on a lead. */
	public static function save_config( $lead_id, $in ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( (int) $lead_id );
		if ( ! $lead ) {
			return null;
		}
		$t   = AXH_DB::table( 'kickoff' );
		$now = AXH_DB::now();
		$row = self::for_lead( $lead_id );
		$cfg = self::clean_config( $in, $row ? $row['config'] : array() );
		if ( $row ) {
			$wpdb->update( $t, array( 'config' => wp_json_encode( $cfg ), 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) );
		} else {
			$wpdb->insert(
				$t,
				array(
					'lead_id'    => (int) $lead_id,
					'rep_id'     => (int) $lead['rep_id'],
					'created_by' => get_current_user_id(),
					'token'      => bin2hex( random_bytes( 16 ) ),
					'status'     => 'draft',
					'config'     => wp_json_encode( $cfg ),
					'data'       => wp_json_encode( (object) self::prefill( $lead ) ),
					'state'      => '{}',
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
			AXH_DB::log( (int) $lead_id, 'note', 'Client onboarding set up', count( $cfg['items'] ) + count( $cfg['custom'] ) . ' items to collect.' );
		}
		$row = self::for_lead( $lead_id );
		self::touch_progress( $row );
		return self::for_lead( $lead_id );
	}

	protected static function touch_progress( $row ) {
		global $wpdb;
		if ( ! $row ) {
			return 0;
		}
		$p = self::progress( $row );
		$wpdb->update( AXH_DB::table( 'kickoff' ), array( 'progress' => $p['pct'] ), array( 'id' => (int) $row['id'] ) );
		return $p['pct'];
	}

	/** One save at a time per onboarding, so two tabs never undo each other. */
	protected static function lock( $id ) {
		global $wpdb;
		$name = 'axh_kick_' . (int) $id . '_' . substr( md5( $wpdb->prefix ), 0, 8 );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) ) ? $name : '';
	}

	protected static function unlock( $name ) {
		global $wpdb;
		if ( $name ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}

	/**
	 * Clean one answer by its part: lists (rows, colors, albums) must be lists of
	 * small records of text, ticks are true/false, everything else is text.
	 * Record ids are short [a-z0-9] so they are safe anywhere they are used.
	 * Returns null for a value of the wrong shape (it is not saved).
	 */
	protected static function clean_part( $part, $v ) {
		if ( in_array( $part, array( 'rows', 'colors', 'albums' ), true ) ) {
			if ( ! is_array( $v ) ) {
				return null;
			}
			$out = array();
			foreach ( array_slice( array_values( $v ), 0, 300 ) as $rec ) {
				if ( ! is_array( $rec ) ) {
					continue;
				}
				$r = array();
				foreach ( array_slice( $rec, 0, 12, true ) as $k => $x ) {
					$k = mb_substr( sanitize_key( (string) $k ), 0, 40 );
					if ( '' === $k || is_array( $x ) || is_object( $x ) ) {
						continue;
					}
					$r[ $k ] = 'id' === $k ? substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $x ) ), 0, 16 ) : mb_substr( sanitize_textarea_field( (string) $x ), 0, 5000 );
				}
				if ( 'albums' === $part && empty( $r['id'] ) ) {
					continue;
				}
				$out[] = $r;
			}
			return $out;
		}
		if ( in_array( $part, array( 'done', 'rights', 'check', 'from_logo' ), true ) ) {
			return ! empty( $v ) && 'false' !== $v;
		}
		if ( is_array( $v ) || is_object( $v ) ) {
			return null;
		}
		return mb_substr( sanitize_textarea_field( (string) $v ), 0, 5000 );
	}

	/** Clean one answer: text up to 5,000 characters, rows as lists of short text. */
	protected static function clean_value( $v, $depth = 0 ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_array( $v ) ) {
			if ( $depth > 2 ) {
				return array();
			}
			$out = array();
			foreach ( array_slice( $v, 0, 300, true ) as $k => $x ) {
				$key         = is_int( $k ) ? $k : mb_substr( sanitize_key( $k ), 0, 40 );
				$out[ $key ] = self::clean_value( $x, $depth + 1 );
			}
			return $out;
		}
		return mb_substr( sanitize_textarea_field( (string) $v ), 0, 5000 );
	}

	/**
	 * Save the client's answers, one at a time. $changes maps "item.part"
	 * to a value; $states maps an item to its client state (na, fixed).
	 */
	public static function save_answers( $row, $changes, $states ) {
		global $wpdb;
		$lock  = self::lock( $row['id'] );
		$fresh = self::by_token( $row['token'] );
		if ( ! $fresh ) {
			self::unlock( $lock );
			return null;
		}
		$items = self::active_items( $fresh );
		$data  = (array) $fresh['data'];
		$state = (array) $fresh['state'];
		$n     = 0;
		foreach ( (array) $changes as $id => $v ) {
			if ( ++$n > 300 || ! preg_match( '/^([a-z0-9_]{1,40})\.([a-z0-9_]{1,40})$/', (string) $id, $m ) || ! isset( $items[ $m[1] ] ) ) {
				continue;
			}
			$clean = self::clean_part( $m[2], $v );
			if ( null === $clean ) {
				continue;
			}
			if ( ! isset( $data[ $m[1] ] ) || ! is_array( $data[ $m[1] ] ) ) {
				$data[ $m[1] ] = array();
			}
			$data[ $m[1] ][ $m[2] ] = $clean;
			// An answer changed after we asked for changes: show it as fixed.
			if ( 'fix' === ( $state['_review'][ $m[1] ]['s'] ?? '' ) ) {
				$state[ $m[1] ]['fixed'] = true;
			}
		}
		foreach ( (array) $states as $k => $s ) {
			$k = sanitize_key( $k );
			if ( ! isset( $items[ $k ] ) ) {
				continue;
			}
			$s = (array) $s;
			if ( array_key_exists( 'na', $s ) ) {
				if ( ! empty( $s['na'] ) ) {
					$state[ $k ]['na'] = true;
				} else {
					unset( $state[ $k ]['na'] );
				}
			}
		}
		$now = AXH_DB::now();
		$upd = array(
			'data'            => wp_json_encode( (object) $data ),
			'state'           => wp_json_encode( (object) $state ),
			'client_saved_at' => $now,
			'updated_at'      => $now,
		);
		if ( in_array( $fresh['status'], array( 'draft', 'sent' ), true ) ) {
			$upd['status'] = 'active';
		}
		$wpdb->update( AXH_DB::table( 'kickoff' ), $upd, array( 'id' => (int) $fresh['id'] ) );
		self::unlock( $lock );
		$fresh = self::by_token( $row['token'] );
		return self::touch_progress( $fresh );
	}

	/* ------------------------------------------------------------ uploads */

	/** File groups a client may upload: extension => group. */
	protected static function ext_groups() {
		return array(
			'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'webp' => 'image', 'gif' => 'image', 'heic' => 'image', 'heif' => 'image', 'tif' => 'image', 'tiff' => 'image', 'psd' => 'image',
			'svg' => 'vector', 'ai' => 'vector', 'eps' => 'vector',
			'pdf' => 'doc', 'docx' => 'doc', 'doc' => 'doc', 'xlsx' => 'doc', 'xls' => 'doc', 'csv' => 'doc', 'pptx' => 'doc', 'txt' => 'doc', 'rtf' => 'doc', 'pages' => 'doc', 'numbers' => 'doc', 'key' => 'doc',
			'ttf' => 'font', 'otf' => 'font', 'woff' => 'font', 'woff2' => 'font',
			'zip' => 'zip',
			'mp4' => 'video', 'mov' => 'video', 'm4v' => 'video', 'webm' => 'video',
		);
	}

	/** The real type from a file's first bytes, or '' when it does not match its name. */
	protected static function sniff( $head, $ext ) {
		$h4 = substr( $head, 0, 4 );
		switch ( $ext ) {
			case 'jpg':
			case 'jpeg':
				return 0 === strpos( $head, "\xFF\xD8\xFF" ) ? 'image/jpeg' : '';
			case 'png':
				return 0 === strpos( $head, "\x89PNG\r\n\x1a\n" ) ? 'image/png' : '';
			case 'gif':
				return 0 === strpos( $head, 'GIF8' ) ? 'image/gif' : '';
			case 'webp':
				return ( 'RIFF' === $h4 && 'WEBP' === substr( $head, 8, 4 ) ) ? 'image/webp' : '';
			case 'heic':
			case 'heif':
				return 'ftyp' === substr( $head, 4, 4 ) ? 'image/heic' : '';
			case 'tif':
			case 'tiff':
				return in_array( $h4, array( "II*\0", "MM\0*" ), true ) ? 'image/tiff' : '';
			case 'psd':
				return '8BPS' === $h4 ? 'image/vnd.adobe.photoshop' : '';
			case 'svg':
				$t = strtolower( substr( $head, 0, 2048 ) );
				return ( false !== strpos( $t, '<svg' ) || ( false !== strpos( $t, '<?xml' ) && false === strpos( $t, '<html' ) ) ) ? 'image/svg+xml' : '';
			case 'ai':
				return ( 0 === strpos( $head, '%PDF-' ) || 0 === strpos( $head, '%!PS' ) ) ? 'application/postscript' : '';
			case 'eps':
				return ( 0 === strpos( $head, '%!PS' ) || "\xC5\xD0\xD3\xC6" === $h4 ) ? 'application/postscript' : '';
			case 'pdf':
				return 0 === strpos( $head, '%PDF-' ) ? 'application/pdf' : '';
			case 'docx':
			case 'xlsx':
			case 'pptx':
			case 'zip':
			case 'pages':
			case 'numbers':
			case 'key':
				if ( 0 !== strpos( $head, "PK\x03\x04" ) && 0 !== strpos( $head, "PK\x05\x06" ) ) {
					return '';
				}
				$m = array( 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'zip' => 'application/zip' );
				return $m[ $ext ] ?? 'application/zip';
			case 'doc':
			case 'xls':
				return "\xD0\xCF\x11\xE0" === $h4 ? ( 'doc' === $ext ? 'application/msword' : 'application/vnd.ms-excel' ) : '';
			case 'rtf':
				return 0 === strpos( $head, '{\\rtf' ) ? 'application/rtf' : '';
			case 'csv':
			case 'txt':
				return false === strpos( substr( $head, 0, 4096 ), "\0" ) ? ( 'csv' === $ext ? 'text/csv' : 'text/plain' ) : '';
			case 'ttf':
				return in_array( $h4, array( "\x00\x01\x00\x00", 'true' ), true ) ? 'font/ttf' : '';
			case 'otf':
				return 'OTTO' === $h4 ? 'font/otf' : '';
			case 'woff':
				return 'wOFF' === $h4 ? 'font/woff' : '';
			case 'woff2':
				return 'wOF2' === $h4 ? 'font/woff2' : '';
			case 'mp4':
			case 'm4v':
			case 'mov':
				return 'ftyp' === substr( $head, 4, 4 ) || 'moov' === substr( $head, 4, 4 ) || 'wide' === substr( $head, 4, 4 ) || 'mdat' === substr( $head, 4, 4 ) ? ( 'mov' === $ext ? 'video/quicktime' : 'video/mp4' ) : '';
			case 'webm':
				return "\x1A\x45\xDF\xA3" === $h4 ? 'video/webm' : '';
		}
		return '';
	}

	/**
	 * One piece of an upload. Pieces arrive in order; each is encrypted on its
	 * own and appended, so any file size works within the server's per-request
	 * limit and nothing is ever held in memory whole.
	 */
	public static function receive_chunk( $row, $req ) {
		global $wpdb;
		if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! method_exists( 'AXH_Onboard', 'encrypt' ) ) {
			return new WP_Error( 'axh_no_sodium', 'Uploads are not available on this server right now.', array( 'status' => 500 ) );
		}
		$p     = $req->get_params();
		$item  = sanitize_text_field( (string) ( $p['item'] ?? '' ) );
		$base  = explode( ':', $item )[0];
		$items = self::active_items( $row );
		if ( ! isset( $items[ $base ] ) || ! preg_match( '/^[a-z0-9_]{1,40}(:[a-z0-9]{1,16})?$/', $item ) ) {
			return new WP_Error( 'axh_item', 'That part of the checklist is not open for uploads.', array( 'status' => 400 ) );
		}
		$uid   = preg_replace( '/[^a-f0-9]/', '', (string) ( $p['uid'] ?? '' ) );
		$idx   = (int) ( $p['idx'] ?? -1 );
		$total = (int) ( $p['total'] ?? 0 );
		$size  = (int) ( $p['size'] ?? 0 );
		$max   = self::settings()['max_mb'] * 1048576;
		if ( strlen( $uid ) < 16 || $idx < 0 || $total < 1 || $idx >= $total || $total > 4096 || $size < 1 ) {
			return new WP_Error( 'axh_chunk', 'The upload got mixed up. Please try that file again.', array( 'status' => 400 ) );
		}
		if ( $size > $max ) {
			return new WP_Error( 'axh_too_big', 'Files can be up to ' . self::settings()['max_mb'] . ' MB. For bigger files (long videos), paste a Google Drive, Dropbox or YouTube link instead.', array( 'status' => 413 ) );
		}
		$files = $req->get_file_params();
		$f     = $files['chunk'] ?? null;
		if ( ! $f || UPLOAD_ERR_OK !== (int) ( $f['error'] ?? 1 ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'axh_upload', 'A piece of the upload did not arrive. It will try again.', array( 'status' => 400 ) );
		}
		$bytes = (string) file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		@unlink( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		// A budget per link: 6 GB an hour is far beyond any real logo-and-photos upload.
		$bk = 'axh_kupb_' . md5( $row['token'] );
		$bn = (int) get_transient( $bk ) + strlen( $bytes );
		if ( $bn > 6 * 1073741824 ) {
			return new WP_Error( 'axh_throttled', 'That’s a lot of uploading in one hour. Please try again later, or share a link instead.', array( 'status' => 429 ) );
		}
		set_transient( $bk, $bn, HOUR_IN_SECONDS );
		$tkey = 'axh_kup_' . md5( $row['token'] . '|' . $uid );
		$st   = get_transient( $tkey );

		if ( 0 === $idx ) {
			// Throttle per link: a few thousand pieces an hour is plenty.
			$rk = 'axh_kupn_' . md5( $row['token'] );
			$rn = (int) get_transient( $rk );
			if ( $rn > 3000 ) {
				return new WP_Error( 'axh_throttled', 'Too many uploads in the last hour. Please try again later.', array( 'status' => 429 ) );
			}
			set_transient( $rk, $rn + 1, HOUR_IN_SECONDS );
			$name = sanitize_file_name( wp_basename( (string) ( $p['name'] ?? 'file' ) ) );
			$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			$grp  = self::ext_groups()[ $ext ] ?? '';
			$acc  = explode( ',', (string) ( $items[ $base ]['accept'] ?? 'image,vector,doc,zip' ) );
			if ( 'albums' === $items[ $base ]['kind'] || 'people' === $items[ $base ]['kind'] ) {
				$acc = array( 'image', 'zip', 'video' );
			}
			if ( ! $grp || ! in_array( $grp, $acc, true ) ) {
				return new WP_Error( 'axh_type', '“' . $name . '” isn’t a file type this item takes. Try a PDF, image, vector, Office or ZIP file, or share a link.', array( 'status' => 415 ) );
			}
			$mime = self::sniff( $bytes, $ext );
			if ( ! $mime ) {
				return new WP_Error( 'axh_type_mismatch', '“' . $name . '” doesn’t look like a real .' . $ext . ' file, so it wasn’t added.', array( 'status' => 415 ) );
			}
			if ( ! AXH_Onboard::ensure_dir() ) {
				return new WP_Error( 'axh_no_dir', 'The private folder could not be created.', array( 'status' => 500 ) );
			}
			if ( is_array( $st ) && ! empty( $st['file'] ) && AXH_Files::safe_file( $st['file'] ) ) {
				@unlink( AXH_Onboard::dir() . '/' . $st['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a restarted upload replaces its half-written file.
				self::pending_file( $row, $st['file'], false );
			}
			self::sweep_pending( $row );
			$st = array( 'file' => 'lf' . bin2hex( random_bytes( 16 ) ) . '.axh', 'name' => mb_substr( $name, 0, 191 ), 'ext' => $ext, 'mime' => $mime, 'size' => $size, 'got' => 0, 'next' => 0, 'item' => $item, 'total' => $total );
			self::pending_file( $row, $st['file'], true );
			file_put_contents( AXH_Onboard::dir() . '/' . $st['file'], 'AXH2', LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			@chmod( AXH_Onboard::dir() . '/' . $st['file'], 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( ! is_array( $st ) || $idx !== (int) $st['next'] || $total !== (int) $st['total'] ) {
			if ( is_array( $st ) && $idx < (int) $st['next'] ) {
				return rest_ensure_response( array( 'ok' => true, 'next' => (int) $st['next'] ) ); // A retried piece we already have.
			}
			return new WP_Error( 'axh_chunk', 'The upload got out of step. Please add that file again.', array( 'status' => 409 ) );
		}
		$seg = AXH_Onboard::encrypt( $bytes );
		if ( false === file_put_contents( AXH_Onboard::dir() . '/' . $st['file'], pack( 'N', strlen( $seg ) ) . $seg, FILE_APPEND | LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'axh_write', 'Could not save the file. Please try again.', array( 'status' => 500 ) );
		}
		$st['got'] += strlen( $bytes );
		$st['next'] = $idx + 1;
		if ( $st['got'] > $max || $st['got'] > $st['size'] + 16 ) {
			@unlink( AXH_Onboard::dir() . '/' . $st['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			delete_transient( $tkey );
			return new WP_Error( 'axh_too_big', 'That file is larger than it said it was.', array( 'status' => 413 ) );
		}
		if ( $st['next'] < $total ) {
			set_transient( $tkey, $st, 6 * HOUR_IN_SECONDS );
			return rest_ensure_response( array( 'ok' => true, 'next' => $st['next'] ) );
		}
		delete_transient( $tkey );
		self::pending_file( $row, $st['file'], false );
		$wpdb->insert(
			AXH_DB::table( 'lead_files' ),
			array(
				'lead_id'    => (int) $row['lead_id'],
				'rep_id'     => 0,
				'name'       => $st['name'],
				'mime'       => $st['mime'],
				'size'       => (int) $st['got'],
				'file'       => $st['file'],
				'kick'       => $st['item'],
				'src'        => 'client',
				'created_at' => AXH_DB::now(),
			)
		);
		$fid = (int) $wpdb->insert_id;
		try {
			self::make_thumb( $fid, $st ); // A preview is a nicety: never let it fail the upload.
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		self::client_activity( $row, 'file', 'Client uploaded ' . $st['name'], ( $items[ $base ]['title'] ?? '' ) . ' · ' . size_format( $st['got'], 1 ) );
		$fresh = self::by_token( $row['token'] );
		$pct   = self::touch_progress( $fresh );
		$file  = null;
		foreach ( self::files_for( $fresh, true ) as $x ) {
			if ( $x['id'] === $fid ) {
				$file = $x;
			}
		}
		return rest_ensure_response( array( 'ok' => true, 'done' => true, 'file' => $file, 'progress' => $pct ) );
	}

	/** Half-finished uploads, so abandoned ones are cleaned up instead of left on disk. */
	protected static function pending_file( $row, $file, $add ) {
		$k    = 'axh_kup_pending_' . (int) $row['id'];
		$list = (array) get_option( $k, array() );
		if ( $add ) {
			$list[ $file ] = time();
		} else {
			unset( $list[ $file ] );
		}
		if ( $list ) {
			update_option( $k, $list, false );
		} else {
			delete_option( $k );
		}
	}

	protected static function sweep_pending( $row ) {
		$k    = 'axh_kup_pending_' . (int) $row['id'];
		$list = (array) get_option( $k, array() );
		$left = array();
		foreach ( $list as $file => $t ) {
			if ( time() - (int) $t > 12 * HOUR_IN_SECONDS ) {
				if ( AXH_Files::safe_file( $file ) ) {
					@unlink( AXH_Onboard::dir() . '/' . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			} else {
				$left[ $file ] = $t;
			}
		}
		if ( count( $left ) !== count( $list ) ) {
			if ( $left ) {
				update_option( $k, $left, false );
			} else {
				delete_option( $k );
			}
		}
	}

	/** A small preview for photos, so a gallery of 200 pictures loads fast. */
	protected static function make_thumb( $fid, $st ) {
		global $wpdb;
		if ( ! in_array( $st['mime'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) || $st['got'] > 41943040 ) {
			return;
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( 'axh-th' );
		if ( ! $tmp ) {
			return;
		}
		$ok = AXH_Files::decrypt_to( AXH_Onboard::dir() . '/' . $st['file'], $tmp );
		if ( ! $ok ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return;
		}
		$ed = wp_get_image_editor( $tmp, array( 'mime_type' => $st['mime'] ) );
		if ( ! is_wp_error( $ed ) ) {
			$ed->resize( 640, 640, false );
			$ed->set_quality( 78 );
			$out = $ed->save( $tmp . '-th.jpg', 'image/jpeg' );
			if ( ! is_wp_error( $out ) && ! empty( $out['path'] ) && is_readable( $out['path'] ) ) {
				$th = 'th' . bin2hex( random_bytes( 16 ) ) . '.axh';
				file_put_contents( AXH_Onboard::dir() . '/' . $th, AXH_Onboard::encrypt( (string) file_get_contents( $out['path'] ) ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$wpdb->update( AXH_DB::table( 'lead_files' ), array( 'thumb' => $th ), array( 'id' => (int) $fid ) );
				@unlink( $out['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/** A timeline entry for something the client did (nobody is signed in). */
	protected static function client_activity( $row, $type, $subject, $body = '' ) {
		global $wpdb;
		$now = AXH_DB::now();
		$wpdb->insert(
			AXH_DB::table( 'activity' ),
			array(
				'lead_id'     => (int) $row['lead_id'],
				'rep_id'      => (int) ( $row['lead']['rep_id'] ?? 0 ),
				'type'        => sanitize_key( $type ),
				'subject'     => mb_substr( sanitize_text_field( $subject ), 0, 191 ),
				'body'        => sanitize_textarea_field( $body ),
				'occurred_at' => $now,
				'created_at'  => $now,
			)
		);
	}

	/* ---------------------------------------------------------- public API */

	public static function routes() {
		$tok  = '(?P<token>[a-f0-9]{24,48})';
		$open = '__return_true';
		register_rest_route( self::NS, '/public/kick/' . $tok . '/save', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'p_save' ), 'permission_callback' => $open ) );
		register_rest_route( self::NS, '/public/kick/' . $tok . '/upload', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'p_upload' ), 'permission_callback' => $open ) );
		register_rest_route( self::NS, '/public/kick/' . $tok . '/secret', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'p_secret' ), 'permission_callback' => $open ) );
		register_rest_route( self::NS, '/public/kick/' . $tok . '/file/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'p_file' ), 'permission_callback' => $open ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'p_file_delete' ), 'permission_callback' => $open ),
		) );
		register_rest_route( self::NS, '/public/kick/' . $tok . '/finish', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'p_finish' ), 'permission_callback' => $open ) );

		$auth   = array( 'AXH_Rest', 'can_use' );
		$manage = function () {
			return axh_is_manager();
		};
		register_rest_route( self::NS, '/kick', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_list' ), 'permission_callback' => $auth ) );
		register_rest_route( self::NS, '/kick/settings', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_settings' ), 'permission_callback' => $auth ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_settings_save' ), 'permission_callback' => $manage ),
		) );
		register_rest_route( self::NS, '/kick/lead/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_get' ), 'permission_callback' => $auth ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_save' ), 'permission_callback' => $auth ),
		) );
		register_rest_route( self::NS, '/kick/lead/(?P<id>\d+)/send', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_send' ), 'permission_callback' => $auth ) );
		register_rest_route( self::NS, '/kick/lead/(?P<id>\d+)/review', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_review' ), 'permission_callback' => $auth ) );
		register_rest_route( self::NS, '/kick/lead/(?P<id>\d+)/status', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_status' ), 'permission_callback' => $auth ) );
		register_rest_route( self::NS, '/kick/lead/(?P<id>\d+)/secret/(?P<item>[a-z0-9_]+)', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'r_secret' ), 'permission_callback' => $manage ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'r_secret_delete' ), 'permission_callback' => $manage ),
		) );
		register_rest_route( self::NS, '/discovery-email/(?P<id>\d+)', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'r_disc_email' ), 'permission_callback' => $auth ) );
	}

	protected static function link_or_404( $req ) {
		$row = self::by_token( (string) $req['token'] );
		if ( ! $row ) {
			return new WP_Error( 'axh_not_found', 'This link is no longer active. Ask your AdaptExec contact for a new one.', array( 'status' => 404 ) );
		}
		return $row;
	}

	protected static function throttle( $row, $what, $max ) {
		$k = 'axh_kt_' . $what . '_' . md5( $row['token'] );
		$n = (int) get_transient( $k );
		if ( $n > $max ) {
			return new WP_Error( 'axh_throttled', 'Too many changes in a short time. Please wait a minute.', array( 'status' => 429 ) );
		}
		set_transient( $k, $n + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	public static function p_save( $req ) {
		$row = self::link_or_404( $req );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$th = self::throttle( $row, 'save', 1200 );
		if ( is_wp_error( $th ) ) {
			return $th;
		}
		$b   = (array) $req->get_json_params();
		$pct = self::save_answers( $row, (array) ( $b['changes'] ?? array() ), (array) ( $b['states'] ?? array() ) );
		if ( null === $pct ) {
			return new WP_Error( 'axh_not_found', 'This link is no longer active.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'progress' => $pct ) );
	}

	public static function p_upload( $req ) {
		$row = self::link_or_404( $req );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return self::receive_chunk( $row, $req );
	}

	/** Store (or remove) a login the client chose to share. Write-only from the page. */
	public static function p_secret( $req ) {
		$row = self::link_or_404( $req );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$th = self::throttle( $row, 'secret', 60 );
		if ( is_wp_error( $th ) ) {
			return $th;
		}
		if ( ! method_exists( 'AXH_Onboard', 'encrypt' ) || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return new WP_Error( 'axh_no_sodium', 'Secure storage is not available right now. Please invite us instead.', array( 'status' => 500 ) );
		}
		$b    = (array) $req->get_json_params();
		$item = sanitize_key( (string) ( $b['item'] ?? '' ) );
		$sub  = preg_replace( '/[^a-z0-9]/', '', (string) ( $b['sub'] ?? '' ) );
		$its  = self::active_items( $row );
		if ( ! isset( $its[ $item ] ) ) {
			return new WP_Error( 'axh_item', 'That part of the checklist is not open.', array( 'status' => 400 ) );
		}
		$key  = $sub ? $item . ':' . $sub : $item;
		$lock = self::lock( $row['id'] );
		$row  = self::by_token( $row['token'] );
		$all  = self::secrets( $row );
		if ( ! empty( $b['remove'] ) ) {
			unset( $all[ $key ] );
			self::put_secrets( $row['id'], $all );
			self::unlock( $lock );
			self::client_activity( $row, 'note', 'Client removed a shared login', $its[ $item ]['title'] );
		} else {
			$pass = (string) ( $b['pass'] ?? '' );
			$user = mb_substr( sanitize_text_field( (string) ( $b['user'] ?? '' ) ), 0, 200 );
			if ( '' === $user || '' === $pass ) {
				self::unlock( $lock );
				return new WP_Error( 'axh_secret', 'Add the username (or email) and the password.', array( 'status' => 400 ) );
			}
			$all[ $key ] = array(
				'url'  => mb_substr( esc_url_raw( (string) ( $b['url'] ?? '' ) ), 0, 300 ),
				'user' => $user,
				'pass' => mb_substr( $pass, 0, 300 ),
				'mfa'  => mb_substr( sanitize_text_field( (string) ( $b['mfa'] ?? '' ) ), 0, 200 ),
				'note' => mb_substr( sanitize_textarea_field( (string) ( $b['note'] ?? '' ) ), 0, 600 ),
				'at'   => AXH_DB::now(),
			);
			self::put_secrets( $row['id'], $all );
			self::unlock( $lock );
			self::client_activity( $row, 'note', 'Client shared a login securely', $its[ $item ]['title'] . ' (encrypted; AdaptExec managers can open it)' );
		}
		$fresh = self::by_token( $row['token'] );
		return rest_ensure_response( array( 'ok' => true, 'secrets' => (object) self::secret_meta( $fresh ), 'progress' => self::touch_progress( $fresh ) ) );
	}

	protected static function file_row( $row, $id ) {
		global $wpdb;
		$f = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AXH_DB::table( 'lead_files' ) . " WHERE id = %d AND lead_id = %d AND kick <> ''", (int) $id, (int) $row['lead_id'] ), ARRAY_A ); // phpcs:ignore
		return $f ? $f : null;
	}

	/** Previews on the client page: photos (or their thumbnails) only. */
	public static function p_file( $req ) {
		$row = self::link_or_404( $req );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$f = self::file_row( $row, (int) $req['id'] );
		if ( ! $f ) {
			return new WP_Error( 'axh_not_found', 'File not found.', array( 'status' => 404 ) );
		}
		return AXH_Files::output( $f, true, true ); // Sends the image and exits, or returns why not.
	}

	public static function p_file_delete( $req ) {
		global $wpdb;
		$row = self::link_or_404( $req );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$f = self::file_row( $row, (int) $req['id'] );
		if ( ! $f || 'client' !== $f['src'] ) {
			return new WP_Error( 'axh_not_found', 'File not found.', array( 'status' => 404 ) );
		}
		$base = explode( ':', $f['kick'] )[0];
		if ( 'ok' === ( $row['state']['_review'][ $base ]['s'] ?? '' ) ) {
			return new WP_Error( 'axh_locked', 'AdaptExec has already received this one. Ask your rep if it needs replacing.', array( 'status' => 409 ) );
		}
		AXH_Files::remove( $f );
		self::client_activity( $row, 'file', 'Client removed ' . $f['name'] );
		return rest_ensure_response( array( 'ok' => true, 'progress' => self::touch_progress( self::by_token( $row['token'] ) ) ) );
	}

	public static function p_finish( $req ) {
		global $wpdb;
		$row = self::link_or_404( $req );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$th = self::throttle( $row, 'finish', 8 );
		if ( is_wp_error( $th ) ) {
			return $th;
		}
		$p     = self::progress( $row );
		$first = 'done' !== $row['status'] && 'complete' !== $row['status'];
		$wpdb->update( AXH_DB::table( 'kickoff' ), array( 'status' => 'complete' === $row['status'] ? 'complete' : 'done', 'done_at' => AXH_DB::now(), 'progress' => $p['pct'] ), array( 'id' => (int) $row['id'] ) );
		self::client_activity( $row, 'note', $first ? 'Client finished onboarding' : 'Client sent onboarding updates', $p['done'] . ' of ' . $p['total'] . ' required items in (' . $p['pct'] . '%).' );
		$company = $row['lead']['company'] ? $row['lead']['company'] : 'A client';
		$title   = $company . ( $first ? ' finished their onboarding' : ' updated their onboarding' );
		$body    = $company . ' has sent their project onboarding: ' . $p['done'] . ' of ' . $p['total'] . ' required items (' . $p['pct'] . '%). Open it to download files, see access and mark items received.';
		$link    = '#/kickoff/' . (int) $row['lead_id'];
		// Tell people once an hour at most, however often the button is pressed.
		$nk = 'axh_kick_told_' . (int) $row['id'];
		if ( class_exists( 'AXH_Notify' ) && ( $first || ! get_transient( $nk ) ) ) {
			set_transient( $nk, 1, HOUR_IN_SECONDS );
			$rep = (int) ( $row['lead']['rep_id'] ?? 0 );
			if ( $rep ) {
				AXH_Notify::add( $rep, 'discovery_completed', $title, $body, $link, (int) $row['lead_id'] );
			}
			AXH_Notify::managers_add( 'discovery_submitted', $title, $body, $link, (int) $row['lead_id'], array(), $rep ? array( $rep ) : array() );
		}
		return rest_ensure_response( array( 'ok' => true, 'status' => 'done', 'progress' => $p['pct'] ) );
	}

	/* ------------------------------------------------------------- Hub API */

	public static function url( $token ) {
		return home_url( '/k/' . rawurlencode( $token ) . '/' );
	}

	/** The onboarding as the Hub shows it. Logins: only whether one is stored. */
	protected static function hub_view( $row ) {
		$items = self::active_items( $row );
		$files = self::files_for( $row );
		$smeta = self::secret_meta( $row );
		$prog  = self::progress( $row, $items, $files, $smeta );
		$cfg   = (array) $row['config'];
		$list  = array();
		foreach ( $items as $k => $it ) {
			$list[] = array_merge(
				self::client_item( $it ),
				array(
					'sec'    => $it['sec'],
					'done'   => in_array( $k, $prog['items'], true ),
					'data'   => (object) ( $row['data'][ $k ] ?? array() ),
					'state'  => (object) ( $row['state'][ $k ] ?? array() ),
					'review' => (object) ( $row['state']['_review'][ $k ] ?? array() ),
					'secret' => isset( $smeta[ $k ] ) ? $smeta[ $k ] : null,
					'subs'   => array_values( array_filter( array_keys( $smeta ), function ( $s ) use ( $k ) {
						return 0 === strpos( $s, $k . ':' );
					} ) ),
				)
			);
		}
		return array(
			'id'        => (int) $row['id'],
			'status'    => $row['status'],
			'url'       => self::url( $row['token'] ),
			'config'    => array( 'types' => (array) ( $cfg['types'] ?? array() ), 'items' => (array) ( $cfg['items'] ?? array() ), 'custom' => (array) ( $cfg['custom'] ?? array() ), 'note' => (string) ( $cfg['note'] ?? '' ), 'due' => (string) ( $cfg['due'] ?? '' ) ),
			'items'     => $list,
			'files'     => $files,
			'progress'  => $prog,
			'sent_at'   => $row['sent_at'],
			'opened_at' => $row['opened_at'],
			'saved_at'  => $row['client_saved_at'],
			'done_at'   => $row['done_at'],
			'created_at' => $row['created_at'],
		);
	}

	protected static function library_view() {
		$out = array();
		foreach ( self::library() as $sec ) {
			$items = array();
			foreach ( $sec['items'] as $it ) {
				$items[] = array( 'key' => $it['key'], 'title' => $it['title'], 'kind' => $it['kind'], 'why' => $it['why'] ?? '', 'when' => $it['when'] ?? array(), 'needs' => $it['needs'] ?? array(), 'optional' => ! empty( $it['optional'] ) );
			}
			$out[] = array( 'key' => $sec['key'], 'title' => $sec['title'], 'items' => $items );
		}
		return $out;
	}

	public static function r_get( $req ) {
		$id   = (int) $req['id'];
		$lead = AXH_DB::get_lead( $id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$row = self::for_lead( $id );
		$sig = self::signals( $id, $lead );
		$sig['items'] = self::suggest( $sig['types'], $sig['signals'] );
		return rest_ensure_response(
			array(
				'lead'     => array( 'id' => (int) $lead['id'], 'company' => $lead['company'], 'contact' => $lead['contact_name'], 'email' => $lead['email'], 'phone' => $lead['phone'], 'stage' => $lead['stage'] ),
				'kick'     => $row ? self::hub_view( $row ) : null,
				'library'  => self::library_view(),
				'types'    => self::types(),
				'suggest'  => $sig,
				'settings' => self::settings(),
				'manager'  => axh_is_manager(),
				'mail'     => self::mail_defaults( $lead, 'kick' ),
			)
		);
	}

	public static function r_save( $req ) {
		$b   = (array) $req->get_json_params();
		$row = self::save_config( (int) $req['id'], (array) ( $b['config'] ?? array() ) );
		if ( ! $row ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		return self::r_get( $req );
	}

	/** Email the client their link, from the Hub, branded, replying to the rep. */
	public static function r_send( $req ) {
		global $wpdb;
		$id   = (int) $req['id'];
		$lead = AXH_DB::get_lead( $id );
		$row  = self::for_lead( $id );
		if ( ! $lead || ! $row ) {
			return new WP_Error( 'axh_not_found', 'Set up the onboarding first.', array( 'status' => 404 ) );
		}
		$b  = (array) $req->get_json_params();
		$to = sanitize_email( (string) ( $b['to'] ?? $lead['email'] ) );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'axh_email', 'Add the client’s email address.', array( 'status' => 400 ) );
		}
		$sent = self::mail_client( $lead, $to, (string) ( $b['subject'] ?? '' ), (string) ( $b['message'] ?? '' ), self::url( $row['token'] ), 'kick' );
		if ( ! $sent ) {
			return new WP_Error( 'axh_mail', 'The email didn’t go out. Copy the link and send it yourself, or check the site’s mail settings.', array( 'status' => 500 ) );
		}
		$upd = array( 'sent_at' => AXH_DB::now() );
		if ( 'draft' === $row['status'] ) {
			$upd['status'] = 'sent';
		}
		$wpdb->update( AXH_DB::table( 'kickoff' ), $upd, array( 'id' => (int) $row['id'] ) );
		AXH_DB::log( $id, 'email', 'Onboarding link emailed to ' . $to, 'Sent from the Hub by ' . wp_get_current_user()->display_name . '.' );
		return self::r_get( $req );
	}

	/** Email the discovery questionnaire link (1.22.0). */
	public static function r_disc_email( $req ) {
		$id   = (int) $req['id'];
		$lead = AXH_DB::get_lead( $id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$b  = (array) $req->get_json_params();
		$to = sanitize_email( (string) ( $b['to'] ?? $lead['email'] ) );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'axh_email', 'Add the client’s email address.', array( 'status' => 400 ) );
		}
		$token = AXH_DB::discovery_token( $id );
		if ( ! $token || ! self::mail_client( $lead, $to, (string) ( $b['subject'] ?? '' ), (string) ( $b['message'] ?? '' ), axh_discovery_url( $token ), 'disc' ) ) {
			return new WP_Error( 'axh_mail', 'The email didn’t go out. Copy the link and send it yourself.', array( 'status' => 500 ) );
		}
		AXH_DB::log( $id, 'email', 'Questionnaire link emailed to ' . $to, 'Sent from the Hub by ' . wp_get_current_user()->display_name . '.' );
		return rest_ensure_response( array( 'ok' => true, 'url' => axh_discovery_url( $token ) ) );
	}

	/** Default words for the two client emails, for the Hub's compose box. */
	public static function mail_defaults( $lead, $kind ) {
		$first   = axh_first_name( $lead['contact_name'] ?? '' );
		$company = (string) $lead['company'];
		if ( 'disc' === $kind ) {
			return array(
				'subject' => 'A few questions for your proposal' . ( $company ? ' · ' . $company : '' ),
				'message' => ( $first ? 'Hi ' . $first . ",\n\n" : '' ) . "Here's the short questionnaire we talked about. Your answers are what AdaptExec uses to write your proposal, so you won't need a long sales call.\n\nIt takes about 10 to 15 minutes, nothing is required, and it saves as you go, so you can stop and come back any time.",
			);
		}
		return array(
			'subject' => 'Let’s get your project started' . ( $company ? ' · ' . $company : '' ),
			'message' => ( $first ? 'Hi ' . $first . ",\n\n" : '' ) . "Thank you for choosing AdaptExec. To start building, we need a few things from you: your logo and photos, some details about your business and access to a couple of accounts.\n\nIt's all on one secure checklist made for your project. Drag and drop your files, and it saves as you go, so you can finish it in a few sittings.",
		);
	}

	protected static function mail_client( $lead, $to, $subject, $message, $url, $kind ) {
		if ( ! class_exists( 'AXH_Mail' ) ) {
			return false;
		}
		$def     = self::mail_defaults( $lead, $kind );
		$subject = trim( sanitize_text_field( $subject ) ) ? trim( sanitize_text_field( $subject ) ) : $def['subject'];
		$message = trim( sanitize_textarea_field( $message ) ) ? sanitize_textarea_field( $message ) : $def['message'];
		$paras   = array_values( array_filter( array_map( 'trim', preg_split( "/\n\s*\n/", $message ) ) ) );
		$me      = wp_get_current_user();
		$headers = array();
		if ( $me && is_email( $me->user_email ) ) {
			$headers[] = 'Reply-To: ' . $me->display_name . ' <' . $me->user_email . '>';
		}
		return AXH_Mail::send(
			$to,
			$subject,
			array(
				'heading'   => 'disc' === $kind ? 'Your project questionnaire' : 'Your project onboarding',
				'preheader' => $paras ? $paras[0] : $subject,
				'paras'     => $paras,
				'cta'       => array( 'disc' === $kind ? 'Answer the questions' : 'Open your checklist', $url ),
				'cta_note'  => 'A private link for ' . ( $lead['company'] ? $lead['company'] : 'you' ) . '. No password needed.',
				'tag'       => '',
				'contact'   => array_filter( array( 'name' => $me ? $me->display_name : '', 'phone' => $me ? (string) get_user_meta( $me->ID, 'axh_phone', true ) : '', 'email' => $me ? $me->user_email : '' ) ),
				'footer'    => 'You received this because you’re working with AdaptExec on a project. Questions? Just reply to this email.',
			),
			$headers
		);
	}

	public static function r_review( $req ) {
		global $wpdb;
		$row = self::for_lead( (int) $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$b    = (array) $req->get_json_params();
		$item = sanitize_key( (string) ( $b['item'] ?? '' ) );
		$its  = self::active_items( $row );
		if ( ! isset( $its[ $item ] ) ) {
			return new WP_Error( 'axh_item', 'That item is not on this checklist.', array( 'status' => 400 ) );
		}
		$s     = in_array( $b['s'] ?? '', array( 'ok', 'fix' ), true ) ? $b['s'] : '';
		$lock  = self::lock( $row['id'] );
		$row   = self::for_lead( (int) $req['id'] );
		$state = (array) $row['state'];
		if ( $s ) {
			$state['_review'][ $item ] = array( 's' => $s, 'note' => mb_substr( sanitize_textarea_field( (string) ( $b['note'] ?? '' ) ), 0, 800 ), 'by' => get_current_user_id(), 'at' => AXH_DB::now() );
		} else {
			unset( $state['_review'][ $item ] );
		}
		unset( $state[ $item ]['fixed'] );
		$wpdb->update( AXH_DB::table( 'kickoff' ), array( 'state' => wp_json_encode( (object) $state ), 'updated_at' => AXH_DB::now() ), array( 'id' => (int) $row['id'] ) );
		self::unlock( $lock );
		if ( 'fix' === $s ) {
			AXH_DB::log( (int) $row['lead_id'], 'note', 'Onboarding: asked the client to update “' . $its[ $item ]['title'] . '”', (string) ( $b['note'] ?? '' ) );
		}
		self::touch_progress( self::for_lead( (int) $req['id'] ) );
		return self::r_get( $req );
	}

	public static function r_status( $req ) {
		global $wpdb;
		$row = self::for_lead( (int) $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$b  = (array) $req->get_json_params();
		$st = in_array( $b['status'] ?? '', array( 'complete', 'active', 'closed' ), true ) ? $b['status'] : '';
		if ( ! $st ) {
			return new WP_Error( 'axh_status', 'Unknown status.', array( 'status' => 400 ) );
		}
		$upd = array( 'status' => $st, 'updated_at' => AXH_DB::now() );
		if ( ! empty( $b['new_link'] ) ) {
			$upd['token'] = bin2hex( random_bytes( 16 ) );
		}
		$wpdb->update( AXH_DB::table( 'kickoff' ), $upd, array( 'id' => (int) $row['id'] ) );
		$say = array( 'complete' => 'Onboarding marked complete', 'active' => ! empty( $b['new_link'] ) ? 'Onboarding link replaced (the old link stops working)' : 'Onboarding reopened', 'closed' => 'Onboarding link switched off' );
		AXH_DB::log( (int) $row['lead_id'], 'note', $say[ $st ] );
		return self::r_get( $req );
	}

	/** Open a shared login. Managers only, and every opening is recorded. */
	public static function r_secret( $req ) {
		$row = self::for_lead( (int) $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$key = sanitize_text_field( (string) $req['item'] ) . ( $req->get_param( 'sub' ) ? ':' . preg_replace( '/[^a-z0-9]/', '', (string) $req->get_param( 'sub' ) ) : '' );
		$all = self::secrets( $row );
		if ( ! isset( $all[ $key ] ) ) {
			return new WP_Error( 'axh_not_found', 'No login is stored for that item.', array( 'status' => 404 ) );
		}
		$its = self::active_items( $row );
		AXH_DB::log( (int) $row['lead_id'], 'note', 'Opened the shared login for ' . ( $its[ explode( ':', $key )[0] ]['title'] ?? $key ), 'By ' . wp_get_current_user()->display_name . '.' );
		axh_nocache();
		return rest_ensure_response( array( 'login' => $all[ $key ] ) );
	}

	public static function r_secret_delete( $req ) {
		$row = self::for_lead( (int) $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
		}
		$key  = sanitize_text_field( (string) $req['item'] ) . ( $req->get_param( 'sub' ) ? ':' . preg_replace( '/[^a-z0-9]/', '', (string) $req->get_param( 'sub' ) ) : '' );
		$lock = self::lock( $row['id'] );
		$row  = self::for_lead( (int) $req['id'] );
		$all  = self::secrets( $row );
		unset( $all[ $key ] );
		self::put_secrets( $row['id'], $all );
		self::unlock( $lock );
		AXH_DB::log( (int) $row['lead_id'], 'note', 'Deleted a shared login from the Hub', 'By ' . wp_get_current_user()->display_name . '.' );
		return self::r_get( $req );
	}

	public static function r_list( $req ) {
		global $wpdb;
		$k = AXH_DB::table( 'kickoff' );
		$l = AXH_DB::table( 'leads' );
		$w = axh_is_manager() ? '' : $wpdb->prepare( ' AND l.rep_id = %d', get_current_user_id() );
		$rows = (array) $wpdb->get_results( "SELECT k.lead_id, k.status, k.progress, k.sent_at, k.opened_at, k.client_saved_at, k.done_at, k.created_at, l.company, l.rep_id FROM {$k} k JOIN {$l} l ON l.id = k.lead_id WHERE 1=1{$w} ORDER BY COALESCE(k.client_saved_at, k.created_at) DESC LIMIT 300", ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$u        = get_userdata( (int) $r['rep_id'] );
			$r['rep'] = $u ? $u->display_name : '';
		}
		unset( $r );
		return rest_ensure_response( array( 'rows' => $rows ) );
	}

	public static function r_settings( $req ) {
		return rest_ensure_response( array( 'settings' => self::settings() ) );
	}

	public static function r_settings_save( $req ) {
		$b = (array) $req->get_json_params();
		$s = array(
			'email'   => sanitize_email( (string) ( $b['email'] ?? '' ) ),
			'person'  => mb_substr( sanitize_text_field( (string) ( $b['person'] ?? '' ) ), 0, 80 ),
			'meta_id' => preg_replace( '/[^0-9]/', '', (string) ( $b['meta_id'] ?? '' ) ),
			'max_mb'  => (int) ( $b['max_mb'] ?? 200 ),
		);
		update_option( 'axh_kick', $s, false );
		return rest_ensure_response( array( 'settings' => self::settings() ) );
	}

	/* -------------------------------------------------------- client page */

	public static function serve() {
		$token = get_query_var( 'axh_kick' );
		if ( ! $token ) {
			return;
		}
		if ( function_exists( 'axh_nocache' ) ) {
			axh_nocache();
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		$row = self::by_token( $token );
		if ( ! $row ) {
			status_header( 404 );
			include AXH_DIR . 'templates/share-missing.php';
			exit;
		}
		$internal = AXH_Portal::is_internal_viewer( (int) ( $row['lead']['rep_id'] ?? 0 ) );
		if ( ! $internal && ! AXH_Portal::is_preview_bot() && empty( $row['opened_at'] ) ) {
			global $wpdb;
			$wpdb->update( AXH_DB::table( 'kickoff' ), array( 'opened_at' => AXH_DB::now(), 'status' => in_array( $row['status'], array( 'draft', 'sent' ), true ) ? 'active' : $row['status'] ), array( 'id' => (int) $row['id'] ) );
			self::client_activity( $row, 'view', 'Client opened their onboarding checklist' );
		}
		$cfg             = self::client_view( $row );
		$cfg['token']    = $row['token'];
		$cfg['rest']     = esc_url_raw( rest_url( self::NS . '/public/kick/' . $row['token'] ) );
		$cfg['internal'] = $internal;
		self::page( $cfg );
		exit;
	}

	/** The client's onboarding page: AdaptExec's own design system, served standalone. */
	protected static function page( $cfg ) {
		$company = $cfg['company'] ? $cfg['company'] : 'your business';
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#121418">
<title>Project onboarding · <?php echo esc_html( $company ); ?></title>
<?php AXH_Portal::share_tags( array( 'title' => 'Your project onboarding · AdaptExec', 'description' => 'Everything we need to start building, on one secure page.', 'url' => home_url( '/' ) ) ); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style><?php echo axh_client_ui_css(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
.kx-hero{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:36px;align-items:center}
.kx-hero .ax-h{max-width:13ch}
.kx-stat{position:relative;z-index:1;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:22px;text-align:center}
.kx-stat b{display:block;font-family:var(--disp);font-weight:500;font-size:58px;line-height:1;margin-top:-92px;position:relative}
.kx-stat small{display:block;color:rgba(255,255,255,.6);font-size:13px;margin-top:56px}
.kx-stat .kx-due{display:inline-flex;margin-top:12px;font-size:12.5px;font-weight:600;color:#FFD9C2;background:rgba(239,93,15,.18);border:1px solid rgba(239,93,15,.35);border-radius:999px;padding:5px 11px}
.kx-team{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.kx-team span{display:inline-flex;align-items:center;gap:9px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:999px;padding:6px 14px 6px 6px;font-size:13px;color:rgba(255,255,255,.82)}
.kx-team i{width:28px;height:28px;border-radius:50%;background:var(--o);display:grid;place-items:center;font-style:normal;font-weight:700;font-size:12px;color:#fff}
.kx-types{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}
.kx-types span{font-size:12px;font-weight:600;letter-spacing:.04em;color:rgba(255,255,255,.75);border:1px solid rgba(255,255,255,.16);border-radius:999px;padding:4px 10px}
.kx-letter{display:flex;gap:16px;align-items:flex-start;padding:20px 22px;margin:20px 0 0}
.kx-letter i{flex:none;width:42px;height:42px;border-radius:50%;background:var(--ink);color:#fff;display:grid;place-items:center;font-style:normal;font-weight:700}
.kx-letter p{white-space:pre-line;color:var(--slate)}
.kx-letter b{display:block;font-size:13px;color:var(--mute);font-weight:600;margin-bottom:4px}
.kx-alert{margin:20px 0 0;padding:18px 20px;border-radius:14px;background:var(--warn-wash);border:1px solid #F3D9A6}
.kx-alert h3{font-size:15px;margin-bottom:8px;color:#7A4E0B}
.kx-alert button{display:block;background:none;border:0;padding:4px 0;text-align:left;color:#7A4E0B;cursor:pointer;font-size:14px}
.kx-alert button span{opacity:.75}
.kx-sec{margin-top:34px;scroll-margin-top:90px}
.kx-sec__h{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:14px;padding:0 4px}
.kx-sec__h .ax-h{font-size:clamp(30px,4vw,42px)}
.kx-sec__h p{color:var(--mute);font-size:14px;margin-top:4px;max-width:60ch}
.kx-sec__c{flex:none;font-size:12.5px;font-weight:700;color:var(--mute);background:var(--paper);border:1px solid var(--line);border-radius:999px;padding:6px 12px;white-space:nowrap}
.kx-sec__c.ok{color:var(--ok);border-color:#BFE6CF;background:var(--ok-wash)}
.kx-item{margin-bottom:12px;scroll-margin-top:90px;transition:box-shadow .2s,border-color .2s}
.kx-item.is-open{border-color:#D9D4CB}
.kx-item.is-hot{box-shadow:0 0 0 3px rgba(239,93,15,.25),var(--sh)}
.kx-ih{width:100%;display:flex;align-items:center;gap:14px;padding:18px 20px;background:none;border:0;text-align:left;cursor:pointer;border-radius:var(--r)}
.kx-st{flex:none;width:30px;height:30px;border-radius:50%;border:2px solid #D3CEC5;display:grid;place-items:center;background:var(--paper);transition:all .2s}
.kx-item.is-done .kx-st{background:var(--ok);border-color:var(--ok)}
.kx-item.is-fix .kx-st{background:#F59E0B;border-color:#F59E0B}
.kx-item.is-skip .kx-st{background:var(--line);border-color:var(--line)}
.kx-ih b{flex:1;min-width:0;font-size:16px;font-weight:600;line-height:1.35}
.kx-ih b small{display:block;font-size:13px;font-weight:500;color:var(--mute);margin-top:2px}
.kx-chip{flex:none;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;border-radius:999px;padding:4px 9px;color:var(--mute);background:var(--wash)}
.kx-chip.ok{color:var(--ok);background:var(--ok-wash)}
.kx-chip.fix{color:#92400E;background:#FEF3C7}
.kx-chip.rcv{color:#fff;background:var(--ok)}
.kx-cv{flex:none;color:var(--soft);transition:transform .2s}
.kx-item.is-open .kx-cv{transform:rotate(180deg)}
.kx-ib{display:none;padding:0 22px 22px 64px}
.kx-item.is-open .kx-ib{display:block}
.kx-why{color:var(--slate);font-size:14.5px;margin-bottom:16px;max-width:70ch}
.kx-fix{background:#FEF3C7;border:1px solid #FDE68A;color:#78350F;border-radius:10px;padding:10px 13px;font-size:14px;margin-bottom:14px}
.kx-drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;text-align:center;border:2px dashed #D8D3CA;border-radius:14px;padding:26px 18px;background:repeating-linear-gradient(135deg,#FCFBF9 0 12px,#FAF8F5 12px 24px);cursor:pointer;transition:all .15s}
.kx-drop:hover{border-color:var(--o);background:var(--o-wash)}
.kx-drop.is-over,body.is-drag .kx-drop{border-color:var(--o);background:var(--o-wash)}
.kx-drop.is-over{transform:scale(1.01);box-shadow:0 0 0 6px rgba(239,93,15,.12)}
.kx-drop svg{color:var(--o)}
.kx-drop b{font-size:15px}
.kx-drop span{font-size:13px;color:var(--mute)}
.kx-drop input{position:absolute;inset:0;opacity:0;cursor:pointer}
.kx-drop--sm{padding:16px 12px;flex-direction:row;gap:10px}
.kx-files{display:grid;grid-template-columns:repeat(auto-fill,minmax(132px,1fr));gap:10px;margin-top:12px}
.kx-file{position:relative;border:1px solid var(--line);border-radius:12px;overflow:hidden;background:var(--paper)}
.kx-file__img{aspect-ratio:4/3;background:var(--wash) center/cover no-repeat;display:grid;place-items:center;color:var(--soft)}
.kx-file__img em{font-style:normal;font-weight:800;font-size:13px;letter-spacing:.06em;color:var(--o-ink);background:var(--o-wash);border-radius:6px;padding:4px 8px}
.kx-file__n{padding:7px 9px 8px;font-size:12px;line-height:1.3}
.kx-file__n b{display:block;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kx-file__n span{color:var(--soft)}
.kx-file__x{position:absolute;top:6px;right:6px;width:26px;height:26px;border-radius:50%;border:0;background:rgba(18,20,24,.7);color:#fff;cursor:pointer;display:grid;place-items:center;opacity:0;transition:opacity .15s}
.kx-file:hover .kx-file__x,.kx-file:focus-within .kx-file__x{opacity:1}
.kx-file__p{position:absolute;left:0;right:0;bottom:0;height:4px;background:rgba(0,0,0,.08)}
.kx-file__p i{display:block;height:100%;background:var(--o);transition:width .2s}
.kx-file.is-err{border-color:#FCA5A5}
.kx-file.is-err .kx-file__n span{color:#B91C1C}
.kx-sub{margin-top:16px}
.kx-sub label{display:block;font-size:13.5px;font-weight:600;margin-bottom:6px}
.kx-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 16px}
.kx-grid .wide{grid-column:1/-1}
.kx-grid label{display:block;font-size:13.5px;font-weight:600;margin-bottom:6px}
.kx-check{display:flex;align-items:flex-start;gap:10px;margin-top:14px;font-size:14px;color:var(--slate);cursor:pointer}
.kx-check input{margin-top:3px;width:18px;height:18px;accent-color:var(--o);flex:none}
.kx-rows{display:grid;gap:10px}
.kx-row{display:grid;gap:8px;align-items:start;background:var(--wash);border:1px solid var(--line2);border-radius:12px;padding:12px}
.kx-row__x{border:0;background:none;color:var(--soft);cursor:pointer;width:36px;height:36px;border-radius:9px;display:grid;place-items:center}
.kx-row__x:hover{background:#fff;color:#B91C1C}
.kx-row .ax-ta{min-height:64px}
.kx-person{display:grid;grid-template-columns:110px minmax(0,1fr) 36px;gap:12px}
.kx-person .kx-drop{padding:10px;min-height:110px;border-radius:50%;aspect-ratio:1;width:110px}
.kx-person .kx-drop span{font-size:11.5px}
.kx-face{width:110px;height:110px;border-radius:50%;background:var(--wash) center/cover;position:relative;border:1px solid var(--line)}
.kx-face button{position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;border:0;background:var(--ink);color:#fff;cursor:pointer;display:grid;place-items:center}
.kx-albums{display:grid;gap:14px}
.kx-album{border:1px solid var(--line);border-radius:14px;padding:14px;background:var(--paper)}
.kx-album__h{display:flex;gap:10px;align-items:center;margin-bottom:10px}
.kx-album__h input{flex:1;font-weight:600}
.kx-album__h small{color:var(--mute);white-space:nowrap}
.kx-sugs{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.kx-sugs button{border:1px dashed #CFCAC1;background:var(--paper);border-radius:999px;padding:7px 13px;font-size:13px;cursor:pointer;color:var(--slate)}
.kx-sugs button:hover{border-color:var(--o);color:var(--o-ink);border-style:solid}
.kx-sw{display:flex;flex-wrap:wrap;gap:12px}
.kx-color{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:12px;padding:8px 10px 8px 8px;background:var(--paper)}
.kx-color input[type=color]{width:44px;height:44px;border:0;border-radius:10px;padding:0;background:none;cursor:pointer}
.kx-color input[type=color]::-webkit-color-swatch-wrapper{padding:0}
.kx-color input[type=color]::-webkit-color-swatch{border:1px solid rgba(0,0,0,.08);border-radius:10px}
.kx-color .ax-in{width:120px;padding:9px 11px;font-family:ui-monospace,Menlo,monospace;font-size:14px;text-transform:uppercase}
.kx-color .kx-cname{width:130px;text-transform:none;font-family:inherit}
.kx-seg{display:inline-flex;flex-wrap:wrap;border:1.5px solid var(--line);border-radius:12px;overflow:hidden;background:var(--paper);margin-bottom:16px}
.kx-seg button{border:0;border-left:1.5px solid var(--line);background:none;padding:11px 16px;font-size:14px;font-weight:600;color:var(--mute);cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.kx-seg button:first-child{border-left:0}
.kx-seg button.on{background:var(--ink);color:#fff}
.kx-seg button em{font-style:normal;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;background:var(--o);color:#fff;border-radius:999px;padding:2px 7px}
.kx-plats{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.kx-plats button{border:1.5px solid var(--line);background:var(--paper);border-radius:10px;padding:8px 13px;font-size:13.5px;cursor:pointer;color:var(--slate)}
.kx-plats button.on{border-color:var(--o);background:var(--o-wash);color:var(--ink);font-weight:600}
.kx-steps{list-style:none;counter-reset:s;display:grid;gap:10px;margin:4px 0 14px}
.kx-steps li{counter-increment:s;position:relative;padding-left:40px;font-size:14.5px;line-height:1.5}
.kx-steps li:before{content:counter(s);position:absolute;left:0;top:-1px;width:27px;height:27px;border-radius:50%;background:var(--ink);color:#fff;font-size:12.5px;font-weight:700;display:grid;place-items:center}
.kx-copy{display:inline-flex;align-items:center;gap:6px;font-family:ui-monospace,Menlo,monospace;font-size:13px;background:var(--o-wash);color:var(--o-ink);border:1px solid var(--o-line);border-radius:7px;padding:1px 8px;cursor:pointer;vertical-align:baseline}
.kx-copy:hover{background:#FFE6D6}
.kx-lock{border:1px solid var(--line);border-radius:14px;padding:16px;background:linear-gradient(180deg,#FBFAF8,#fff)}
.kx-lock__h{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--slate);margin-bottom:14px}
.kx-lock__h svg{flex:none;color:var(--ok);margin-top:1px}
.kx-pw{position:relative}
.kx-pw button{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:none;color:var(--mute);font-size:12.5px;font-weight:600;cursor:pointer;padding:6px}
.kx-saved{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:var(--ok-wash);border:1px solid #BFE6CF;border-radius:12px;padding:12px 14px;color:#14532D;font-size:14px}
.kx-saved .sp{flex:1}
.kx-saved button{border:0;background:none;color:#14532D;font-weight:600;cursor:pointer;text-decoration:underline;font-size:13.5px}
.kx-na{display:inline-flex;align-items:center;gap:8px;margin-top:16px;border:0;background:none;color:var(--mute);font-size:13.5px;cursor:pointer;padding:4px 0}
.kx-na:hover{color:var(--ink)}
.kx-na input{width:16px;height:16px;accent-color:var(--mute)}
[hidden]{display:none!important}
.kx-ok{float:right;font-size:12px;font-weight:600;color:var(--ok);opacity:0;transition:opacity .25s}
.kx-ok.on{opacity:1}
.kx-info{display:flex;gap:10px;align-items:flex-start;background:#EFF6FF;border:1px solid #BFDBFE;color:#1E3A8A;border-radius:12px;padding:12px 14px;font-size:14px;margin-bottom:14px}
.kx-finish{margin-top:40px;padding:34px;text-align:center}
.kx-finish .ax-h{font-size:clamp(34px,5vw,54px);margin:10px 0 10px}
.kx-finish p{color:var(--mute);max-width:56ch;margin:0 auto 18px}
.kx-left{display:grid;gap:6px;max-width:520px;margin:0 auto 20px;text-align:left}
.kx-left button{display:flex;gap:10px;align-items:center;border:1px solid var(--line);background:var(--paper);border-radius:10px;padding:10px 12px;cursor:pointer;font-size:14px;text-align:left}
.kx-left button i{width:8px;height:8px;border-radius:50%;background:var(--o);flex:none}
.kx-thanks{margin-top:20px;background:var(--ok-wash);border:1px solid #BFE6CF;border-radius:16px;padding:20px 22px;display:flex;gap:16px;align-items:flex-start}
.kx-thanks svg{flex:none;color:var(--ok)}
.kx-thanks b{display:block;font-size:16px;color:#14532D}
.kx-thanks p{color:#166534;font-size:14px}
.kx-dragnote{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);opacity:0;z-index:90;background:var(--ink);color:#fff;border-radius:999px;padding:12px 20px;font-size:14px;font-weight:600;box-shadow:0 12px 30px -10px rgba(0,0,0,.5);transition:all .2s;pointer-events:none}
body.is-drag .kx-dragnote{opacity:1;transform:translateX(-50%)}
.kx-railsec{display:block}
@media (max-width:980px){.kx-hero{grid-template-columns:minmax(0,1fr)}.kx-hero>*{min-width:0}.kx-stat{display:flex;flex-wrap:wrap;align-items:center;gap:6px 16px;text-align:left}.kx-stat svg{width:96px;height:96px}.kx-stat b{margin:0 0 0 -112px;font-size:30px;width:96px;text-align:center}.kx-stat small{margin:0;flex:1;min-width:120px}.kx-stat .kx-due{margin-top:0}}
@media (max-width:640px){.kx-color{flex-wrap:wrap;width:100%}.kx-color .ax-in{flex:1;width:auto;min-width:0}.kx-color .kx-cname{width:100%;flex-basis:100%;order:3}.kx-sec__h{flex-wrap:wrap;align-items:flex-start}.kx-ib{padding:0 16px 18px}.kx-ih{padding:16px}.kx-grid{grid-template-columns:1fr}.kx-person{grid-template-columns:80px minmax(0,1fr) 36px}.kx-person .kx-drop,.kx-face{width:80px;height:80px;min-height:80px}.kx-files{grid-template-columns:repeat(auto-fill,minmax(104px,1fr))}.kx-file__x{opacity:1}.kx-seg{display:flex}.kx-seg button{flex:1;justify-content:center;padding:11px 8px}.kx-finish{padding:26px 18px}}
</style>
</head>
<body>
<header class="ax-bar"><div class="ax-bar__in">
	<span class="ax-mark"><?php echo axh_logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<span class="ax-for"><i></i><span>Project onboarding for</span> <b><?php echo esc_html( $company ); ?></b></span>
	<span class="ax-save" id="ax-save" role="status" aria-live="polite"><s></s><em>All changes saved</em></span>
</div></header>
<?php if ( ! empty( $cfg['internal'] ) ) : ?>
<div class="ax-pv">You’re viewing <?php echo esc_html( $company ); ?>’s checklist. Anything you change here saves to it.</div>
<?php endif; ?>
<main class="ax-wrap" id="kx"></main>
<div class="kx-dragnote">Drop your files onto any upload box</div>
<script>
(function () {
	'use strict';
	var C = <?php echo wp_json_encode( $cfg ); ?>;
	var D = C.data && !Array.isArray(C.data) ? C.data : {};
	var ST = C.state && !Array.isArray(C.state) ? C.state : {};
	var RV = C.review && !Array.isArray(C.review) ? C.review : {};
	var SEC = C.secrets && !Array.isArray(C.secrets) ? C.secrets : {};
	var FILES = C.files || [];
	var app = document.getElementById('kx');
	var KEY = 'axh-kick-' + C.token;
	var OPEN = {};
	var ITEMS = {};
	C.sections.forEach(function (s) { s.items.forEach(function (it) { it.sec = s.key; ITEMS[it.key] = it; }); });

	var I = {
		check: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
		ok: '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
		chev: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
		up: '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>',
		x: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
		lock: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
		bang: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>',
		dash: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#9AA1AB" stroke-width="3" stroke-linecap="round"><path d="M6 12h12"/></svg>',
		arrow: '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
		party: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14l-3-3"/></svg>',
		info: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/></svg>'
	};
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function first(s) { return String(s || '').split(/\s+/)[0]; }
	function rid() { return Math.random().toString(16).slice(2, 10); }
	function kb(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
	function dget(k, p) { return D[k] && D[k][p] !== undefined ? D[k][p] : ''; }

	/* --------------------------------------------------------- saving */
	var pending = {}, pstates = {}, timer = null, inflight = null, again = false, retryN = 0, dead = false, uploads = 0;
	var local = {};
	try { local = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}
	if (!local.at || Date.now() - local.at > 3 * 86400000) local = {};
	if (local.pending) Object.keys(local.pending).forEach(function (id) { var p = id.split('.'); if (p.length === 2 && ITEMS[p[0]]) { if (!D[p[0]] || Array.isArray(D[p[0]])) D[p[0]] = {}; D[p[0]][p[1]] = local.pending[id]; pending[id] = local.pending[id]; } });
	if (local.pstates) Object.keys(local.pstates).forEach(function (k) { if (ITEMS[k]) { ST[k] = ST[k] || {}; if (local.pstates[k].na) ST[k].na = true; else delete ST[k].na; pstates[k] = local.pstates[k]; } });
	function persist() { try { localStorage.setItem(KEY, JSON.stringify({ pending: pending, pstates: pstates, at: Date.now() })); } catch (e) {} }
	var saveEl = document.getElementById('ax-save');
	function status(kind, msg) { saveEl.className = 'ax-save' + (kind === 'busy' ? ' is-busy' : kind === 'off' ? ' is-off' : ''); saveEl.querySelector('em').textContent = msg; }
	function idle() { if (uploads) status('busy', 'Uploading ' + uploads + ' file' + (uploads === 1 ? '' : 's') + '…'); else if (!Object.keys(pending).length && !Object.keys(pstates).length) status('ok', 'All changes saved'); }
	function set(k, p, v, delay) {
		if (!D[k] || Array.isArray(D[k])) D[k] = {};
		D[k][p] = v; pending[k + '.' + p] = v; persist();
		if (!dead) status('busy', 'Saving…');
		clearTimeout(timer); timer = setTimeout(flush, delay == null ? 800 : delay);
		refresh(k);
	}
	function setNa(k, on) {
		ST[k] = ST[k] || {}; if (on) ST[k].na = true; else delete ST[k].na;
		pstates[k] = { na: !!on }; persist();
		clearTimeout(timer); timer = setTimeout(flush, 150);
		paintItem(k); meters();
	}
	function api(path, opts) {
		return fetch(C.rest + path, opts).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (r.status === 404) dead = true;
				if (!r.ok) { var e = new Error(j && j.message ? j.message : 'Something went wrong.'); e.status = r.status; throw e; }
				return j;
			});
		});
	}
	function flush() {
		clearTimeout(timer);
		if (dead) return Promise.resolve();
		if (inflight) { again = true; return inflight; }
		var ids = Object.keys(pending), sk = Object.keys(pstates);
		if (!ids.length && !sk.length) { idle(); return Promise.resolve(); }
		var ch = {}, sts = {};
		ids.forEach(function (id) { ch[id] = pending[id]; });
		sk.forEach(function (k) { sts[k] = pstates[k]; });
		status('busy', 'Saving…');
		inflight = api('/save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ changes: ch, states: sts }), credentials: 'omit' }).then(function (d) {
			ids.forEach(function (id) { if (JSON.stringify(pending[id]) === JSON.stringify(ch[id])) delete pending[id]; });
			sk.forEach(function (k) { if (JSON.stringify(pstates[k]) === JSON.stringify(sts[k])) delete pstates[k]; });
			persist(); retryN = 0;
			var seen = {};
			ids.forEach(function (id) { seen[id.split('.')[0]] = 1; }); sk.forEach(function (k) { seen[k] = 1; });
			Object.keys(seen).forEach(okFlash);
			idle();
		}).catch(function (e) {
			if (dead) { status('off', 'This link is no longer active'); return; }
			retryN++;
			status('off', navigator.onLine === false ? 'Offline · saved on this device' : 'Saved on this device · retrying');
			timer = setTimeout(flush, [2000, 5000, 10000, 20000, 30000][Math.min(retryN - 1, 4)]);
		}).then(function () { inflight = null; if (again) { again = false; return flush(); } });
		return inflight;
	}
	function sendNow() {
		if (dead || (!Object.keys(pending).length && !Object.keys(pstates).length)) return;
		try { fetch(C.rest + '/save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ changes: pending, states: pstates }), keepalive: true, credentials: 'omit' }); } catch (e) {}
	}
	window.addEventListener('pagehide', sendNow);
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') sendNow(); });
	window.addEventListener('online', function () { flush(); });
	window.addEventListener('beforeunload', function (e) { if (uploads) { e.preventDefault(); e.returnValue = ''; } });
	function okFlash(k) {
		var el = app.querySelector('[data-okf="' + k + '"]');
		if (!el) return;
		el.classList.add('on'); clearTimeout(el._t); el._t = setTimeout(function () { el.classList.remove('on'); }, 1600);
	}

	/* ------------------------------------------------------ item state */
	function filesOf(k, sub) {
		return FILES.filter(function (f) { return sub === undefined ? (f.item === k || f.item.indexOf(k + ':') === 0) : f.item === k + ':' + sub; });
	}
	function has(v) {
		if (Array.isArray(v)) return v.some(function (x) { return x && typeof x === 'object' ? Object.keys(x).some(function (q) { return q !== 'id' && String(x[q] || '').trim(); }) : String(x == null ? '' : x).trim() !== ''; });
		return String(v == null ? '' : v).trim() !== '';
	}
	function done(it) {
		var k = it.key, d = D[k] || {}, rv = RV[k] || {}, st = ST[k] || {};
		if (rv.s === 'fix' && !st.fixed) return false;
		if (rv.s === 'ok' || st.na) return true;
		var nf = filesOf(k).filter(function (f) { return !f.local; }).length;
		switch (it.kind) {
			case 'upload': case 'albums': return nf > 0 || has(d.link);
			case 'access': return ((d.mode || 'invite') === 'invite' && !!d.done) || (d.mode === 'login' && !!SEC[k]) || d.platform === 'none';
			case 'colors': return has((Array.isArray(d.colors) ? d.colors : []).map(function (c) { return c && c.hex; })) || !!d.from_logo;
			case 'rows': case 'people': return has(Array.isArray(d.rows) ? d.rows : []) || !!d.check;
			case 'text': return has(d.text) || nf > 0;
			default: return Object.keys(d).some(function (q) { return q.charAt(0) !== '_' && has(d[q]); });
		}
	}
	function phase(it) {
		var rv = RV[it.key] || {}, st = ST[it.key] || {};
		if (rv.s === 'fix' && !st.fixed) return 'fix';
		if (rv.s === 'ok') return 'rcv';
		if (st.na) return 'skip';
		return done(it) ? 'done' : 'todo';
	}
	function summary(it) {
		var k = it.key, d = D[k] || {}, ph = phase(it), nf = filesOf(k).length;
		if (ph === 'fix') return 'Needs a quick update';
		if (ph === 'rcv') return 'Received by AdaptExec';
		if (ph === 'skip') return it.na || 'Not applicable';
		if (it.kind === 'access') { if (d.mode === 'login' && SEC[k]) return 'Login shared securely'; if ((d.mode || 'invite') === 'invite' && d.done) return 'Invite sent'; if (d.platform === 'none') return 'We’ll set it up'; return d.platform ? 'Waiting for the invite' : ''; }
		if (nf) return nf + ' file' + (nf === 1 ? '' : 's') + ' added';
		if (has(d.link)) return 'Link shared';
		if (it.kind === 'rows' || it.kind === 'people') { var n = (Array.isArray(d.rows) ? d.rows : []).filter(function (r) { return has([r]); }).length; return n ? n + ' added' : (d.check ? 'Done' : ''); }
		return ph === 'done' ? 'Added' : '';
	}
	function counts() {
		var t = 0, n = 0, left = [];
		Object.keys(ITEMS).forEach(function (k) { var it = ITEMS[k], ok = done(it); if (!it.optional) { t++; if (ok) n++; else left.push(it); } });
		return { t: t, n: n, pct: t ? Math.floor(n / t * 100) : 0, left: left };
	}

	/* ------------------------------------------------------- rendering */
	function ring(pct, size, dark) {
		var r = (size - 10) / 2, c = 2 * Math.PI * r;
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '" aria-hidden="true"><circle cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" fill="none" stroke="' + (dark ? 'rgba(255,255,255,.12)' : '#EFECE6') + '" stroke-width="9"/>' +
			'<circle data-ring cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" fill="none" stroke="#EF5D0F" stroke-width="9" stroke-linecap="round" stroke-dasharray="' + c.toFixed(1) + '" stroke-dashoffset="' + (c * (1 - pct / 100)).toFixed(1) + '" transform="rotate(-90 ' + size / 2 + ' ' + size / 2 + ')" style="transition:stroke-dashoffset .5s"/></svg>';
	}
	function dueTxt() {
		if (!C.due) return '';
		var d = new Date(C.due + 'T12:00:00');
		return isNaN(d) ? '' : 'Please finish by ' + d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
	}
	function render() {
		var c = counts(), fixes = Object.keys(ITEMS).filter(function (k) { return phase(ITEMS[k]) === 'fix'; });
		var who = C.contact ? 'Hi ' + esc(C.contact) + '. ' : '';
		var doneNow = C.status === 'done' || C.status === 'complete';
		var html = '<div class="ax-hero kx-hero ax-fade"><div><div class="ax-k">Project onboarding</div>' +
			'<h1 class="ax-h">Let’s get your project started</h1>' +
			'<p>' + who + 'Everything we need from you to start building is right here: your files, a few details and access to the accounts we’ll work in. Drag files straight onto any box, and every answer saves the moment you give it, so you can finish in a few sittings.</p>' +
			(C.types && C.types.length ? '<div class="kx-types">' + C.types.map(function (t) { return '<span>' + esc(t) + '</span>'; }).join('') + '</div>' : '') +
			'<div class="kx-team"><span><i>' + esc(first(C.rep).charAt(0)) + '</i>' + esc(C.rep) + ' · your rep</span>' + (C.person && C.person !== C.rep ? '<span><i style="background:#2A2E36">' + esc(first(C.person).charAt(0)) + '</i>' + esc(C.person) + ' · AdaptExec</span>' : '') + '</div></div>' +
			'<div class="kx-stat">' + ring(c.pct, 156, true) + '<b data-m="pct">' + c.pct + '%</b><small data-m="cnt">' + c.n + ' of ' + c.t + ' required items done</small>' + (dueTxt() ? '<span class="kx-due">' + esc(dueTxt()) + '</span>' : '') + '</div></div>';
		if (doneNow) html += '<div class="kx-thanks ax-fade">' + I.party + '<div><b>Thank you! We have it from here.</b><p>' + esc(C.rep) + ' and the AdaptExec team have what you sent. You can keep adding files or changing answers here any time, and we’ll see it straight away.</p></div></div>';
		if (C.note) html += '<div class="ax-card kx-letter"><i>' + esc(first(C.rep).charAt(0)) + '</i><div><b>A note from ' + esc(C.rep) + '</b><p>' + esc(C.note) + '</p></div></div>';
		if (fixes.length) html += '<div class="kx-alert"><h3>' + fixes.length + ' item' + (fixes.length === 1 ? ' needs' : 's need') + ' a quick update</h3>' + fixes.map(function (k) { return '<button type="button" data-jump="' + k + '"><b>' + esc(ITEMS[k].title) + '</b> <span>· ' + esc((RV[k] || {}).note || 'see the note') + '</span></button>'; }).join('') + '</div>';
		html += '<div class="ax-layout" style="margin-top:28px">' + railHtml(c) + '<div>' +
			'<div class="ax-mob" style="margin:0 -16px 8px;top:62px"><div class="ax-mob__bar"><div class="ax-mob__t" data-m="cnt3">' + c.n + ' of ' + c.t + ' required items done</div><div class="ax-mob__p"><i data-m="bar" style="width:' + c.pct + '%"></i></div></div><b data-m="pct3" style="font-size:13px">' + c.pct + '%</b></div>' +
			C.sections.map(sectionHtml).join('') + finishHtml(c) + '</div></div>';
		app.innerHTML = html;
		app.querySelectorAll('textarea.ax-ta').forEach(grow);
	}
	function railHtml(c) {
		return '<aside class="ax-rail"><div class="ax-card ax-rail__card"><div class="ax-ring">' + ring(c.pct, 64) + '<div><b data-m="pct2">' + c.pct + '%</b><span data-m="cnt2">' + c.n + ' of ' + c.t + ' required</span></div></div><ul class="ax-nav" data-rail>' + railList() + '</ul>' +
			'<div class="ax-rail__foot">Saves as you go. Logins you share are encrypted, and only AdaptExec can open them.</div></div></aside>';
	}
	function railList() {
		return C.sections.map(function (s, i) {
			var t = s.items.length, n = s.items.filter(function (it) { return done(it); }).length;
			return '<li><button type="button" data-sec="' + s.key + '" class="' + (n >= t ? 'is-done' : n ? 'is-part' : '') + '"' + (n && n < t ? ' style="--p:' + Math.round(n / t * 100) + '%"' : '') + '><i>' + (n >= t ? I.check : (i + 1)) + '</i><span>' + esc(s.title) + '</span><small>' + n + '/' + t + '</small></button></li>';
		}).join('');
	}
	function sectionHtml(s) {
		var t = s.items.length, n = s.items.filter(done).length;
		return '<section class="kx-sec" id="sec-' + s.key + '"><div class="kx-sec__h"><div><h2 class="ax-h">' + esc(s.title) + '</h2><p>' + esc(s.intro || '') + '</p></div><span class="kx-sec__c' + (n >= t ? ' ok' : '') + '" data-secc="' + s.key + '">' + (n >= t ? 'All done' : n + ' of ' + t + ' done') + '</span></div>' +
			s.items.map(function (it) { return itemHtml(it); }).join('') + '</section>';
	}
	var FIRST = {};
	C.sections.forEach(function (s) { for (var i = 0; i < s.items.length; i++) { var p0 = phase(s.items[i]); if (p0 === 'todo' && !s.items[i].optional) { FIRST[s.items[i].key] = 1; break; } } });
	function itemHtml(it) {
		var ph = phase(it), open = OPEN[it.key] !== undefined ? OPEN[it.key] : (ph === 'fix' || !!FIRST[it.key]);
		OPEN[it.key] = open;
		return '<article class="ax-card kx-item is-' + ph + (open ? ' is-open' : '') + '" id="it-' + it.key + '" data-item="' + it.key + '">' + headHtml(it, ph) + '<div class="kx-ib">' + bodyHtml(it) + '</div></article>';
	}
	function headHtml(it, ph) {
		var chip = ph === 'rcv' ? '<span class="kx-chip rcv">Received</span>' : ph === 'fix' ? '<span class="kx-chip fix">Update needed</span>' : ph === 'done' ? '<span class="kx-chip ok">Done</span>' : ph === 'skip' ? '<span class="kx-chip">Skipped</span>' : (it.optional ? '<span class="kx-chip">Optional</span>' : '<span class="kx-chip" style="color:var(--o-ink);background:var(--o-wash)">To do</span>');
		var mark = ph === 'done' || ph === 'rcv' ? I.check : ph === 'fix' ? I.bang : ph === 'skip' ? I.dash : '';
		var sm = summary(it);
		return '<button type="button" class="kx-ih" data-toggle="' + it.key + '" aria-expanded="' + (!!OPEN[it.key]) + '"><span class="kx-st">' + mark + '</span><b>' + esc(it.title) + (sm ? '<small>' + esc(sm) + '</small>' : '') + '</b>' + chip + '<span class="kx-cv">' + I.chev + '</span></button>';
	}
	function paintItem(k) {
		var el = document.getElementById('it-' + k), it = ITEMS[k];
		if (!el || !it) return;
		var ph = phase(it);
		el.className = 'ax-card kx-item is-' + ph + (OPEN[k] ? ' is-open' : '');
		var h = el.querySelector('.kx-ih'), tmp = document.createElement('div');
		tmp.innerHTML = headHtml(it, ph);
		h.parentNode.replaceChild(tmp.firstChild, h);
	}
	function repaintBody(k) {
		var el = document.getElementById('it-' + k);
		if (!el) return;
		var b = el.querySelector('.kx-ib');
		b.innerHTML = bodyHtml(ITEMS[k]);
		b.querySelectorAll('textarea.ax-ta').forEach(grow);
	}
	function refresh(k) { paintItem(k); meters(); }
	function meters() {
		var c = counts();
		['pct', 'pct2', 'pct3'].forEach(function (m) { var e = app.querySelector('[data-m="' + m + '"]'); if (e) e.textContent = c.pct + '%'; });
		var e3 = app.querySelector('[data-m="cnt3"]'); if (e3) e3.textContent = c.n + ' of ' + c.t + ' required items done';
		var bar = app.querySelector('[data-m="bar"]'); if (bar) bar.style.width = c.pct + '%';
		var e1 = app.querySelector('[data-m="cnt"]'); if (e1) e1.textContent = c.n + ' of ' + c.t + ' required items done';
		var e2 = app.querySelector('[data-m="cnt2"]'); if (e2) e2.textContent = c.n + ' of ' + c.t + ' required';
		app.querySelectorAll('[data-ring]').forEach(function (rg) { var len = parseFloat(rg.getAttribute('stroke-dasharray')); rg.setAttribute('stroke-dashoffset', (len * (1 - c.pct / 100)).toFixed(1)); });
		var rail = app.querySelector('[data-rail]'); if (rail) rail.innerHTML = railList();
		C.sections.forEach(function (s) {
			var el = app.querySelector('[data-secc="' + s.key + '"]'); if (!el) return;
			var t = s.items.length, n = s.items.filter(done).length;
			el.className = 'kx-sec__c' + (n >= t ? ' ok' : ''); el.textContent = n >= t ? 'All done' : n + ' of ' + t + ' done';
		});
		var fl = app.querySelector('[data-left]');
		if (fl) fl.innerHTML = leftHtml(c);
	}

	/* -------------------------------------------------------- item bodies */
	function bodyHtml(it) {
		var k = it.key, rv = RV[k] || {}, st = ST[k] || {}, out = '';
		if (rv.s === 'fix' && rv.note) out += '<div class="kx-fix"><b>' + esc(C.rep) + ' asked:</b> ' + esc(rv.note) + '</div>';
		if (rv.s === 'ok') out += '<div class="kx-info" style="background:var(--ok-wash);border-color:#BFE6CF;color:#14532D">' + I.ok + '<span>AdaptExec has received this. Thank you!</span></div>';
		out += '<span class="kx-ok" data-okf="' + k + '">' + I.ok + ' Saved</span>';
		if (it.why) out += '<p class="kx-why">' + esc(it.why) + '</p>';
		if (st.na) {
			out += '<p class="kx-why" style="color:var(--mute)">Marked as: <b>' + esc(it.na || 'Not applicable') + '</b>.</p>';
		} else {
			switch (it.kind) {
				case 'upload': out += uploadHtml(it); break;
				case 'albums': out += albumsHtml(it); break;
				case 'fields': out += fieldsHtml(it); break;
				case 'connect': out += '<div class="kx-info">' + I.info + '<span>Nothing to send now. We connect this with your sign-in during setup.</span></div>' + fieldsHtml(it); break;
				case 'rows': out += rowsHtml(it); break;
				case 'people': out += peopleHtml(it); break;
				case 'colors': out += colorsHtml(it); break;
				case 'access': out += accessHtml(it); break;
				case 'text': out += '<textarea class="ax-ta" data-f="' + k + '.text" placeholder="Type here">' + esc(dget(k, 'text')) + '</textarea><div class="kx-sub">' + dropHtml(k, '', true) + filesHtml(k) + '</div>'; break;
			}
		}
		if (it.na) out += '<label class="kx-na"><input type="checkbox" data-na="' + k + '"' + (st.na ? ' checked' : '') + '> ' + esc(it.na) + '</label>';
		return out;
	}
	function acceptTxt(it) {
		var a = (it.accept || 'image,vector,doc,zip').split(','), m = { image: 'photos', vector: 'vector (AI, EPS, SVG)', doc: 'PDF, Word, Excel', zip: 'ZIP', font: 'fonts', video: 'video' };
		return a.map(function (x) { return m[x] || x; }).join(', ');
	}
	function dropHtml(k, sub, small, it) {
		var id = esc(k + (sub ? ':' + sub : ''));
		return '<label class="kx-drop' + (small ? ' kx-drop--sm' : '') + '" data-drop="' + id + '">' + I.up + '<div><b>' + (small ? 'Add files' : 'Drag files here, or click to choose') + '</b>' + (small ? '' : '<span style="display:block">' + esc(it ? acceptTxt(it) : 'Photos') + ' · up to ' + C.maxMb + ' MB each</span>') + '</div><input type="file" multiple data-pick="' + id + '" aria-label="Choose files"></label>';
	}
	function fileCard(f) {
		var ext = (f.name.split('.').pop() || '').toUpperCase().slice(0, 5);
		var bg = f.local && f.preview ? f.preview : (f.thumb && !f.local ? C.rest + '/file/' + f.id : '');
		return '<div class="kx-file' + (f.err ? ' is-err' : '') + '" data-fid="' + esc(f.id || f.uid) + '"><div class="kx-file__img"' + (bg ? ' style="background-image:url(\'' + esc(bg) + '\')"' : '') + '>' + (bg ? '' : '<em>' + esc(ext) + '</em>') + '</div>' +
			'<div class="kx-file__n"><b title="' + esc(f.name) + '">' + esc(f.name) + '</b><span>' + (f.err ? esc(f.err) : f.local ? (f.pct || 0) + '%' : kb(f.size)) + '</span></div>' +
			(f.local && !f.err ? '<div class="kx-file__p"><i style="width:' + (f.pct || 0) + '%"></i></div>' : '') +
			((f.by === 'client' && !f.local) || f.err ? '<button type="button" class="kx-file__x" data-rm="' + esc(f.id || '') + '" data-rmu="' + esc(f.uid || '') + '" aria-label="Remove ' + esc(f.name) + '">' + I.x + '</button>' : '') + '</div>';
	}
	function filesHtml(k, sub) {
		var list = filesOf(k, sub);
		return '<div class="kx-files" data-files="' + esc(k + (sub !== undefined ? ':' + sub : '')) + '">' + list.map(fileCard).join('') + '</div>';
	}
	function uploadHtml(it) {
		var k = it.key;
		return dropHtml(k, '', false, it) + filesHtml(k, undefined) +
			(it.link ? '<div class="kx-sub"><label>Or share a link (Google Drive, Dropbox, WeTransfer…)</label><input class="ax-in" type="url" data-f="' + k + '.link" value="' + esc(dget(k, 'link')) + '" placeholder="https://"></div>' : '') +
			(it.note ? '<div class="kx-sub"><label>' + esc(it.note) + '</label><textarea class="ax-ta" data-f="' + k + '.note" style="min-height:70px">' + esc(dget(k, 'note')) + '</textarea></div>' : '') +
			(it.rights ? '<label class="kx-check"><input type="checkbox" data-fc="' + k + '.rights"' + (dget(k, 'rights') ? ' checked' : '') + '> ' + esc(it.rights) + '</label>' : '');
	}
	function albumsHtml(it) {
		var k = it.key, albums = (Array.isArray(dget(k, 'albums')) ? dget(k, 'albums') : []).filter(function (a) { return a && /^[a-z0-9]{1,16}$/.test(String(a.id || '')); });
		var used = albums.map(function (a) { return String(a.name || '').toLowerCase(); });
		var svc = Array.isArray(dget('services', 'rows')) ? dget('services', 'rows').map(function (r) { return r.name; }).filter(Boolean) : [];
		var sug = svc.concat(['Our work', 'Before & after', 'Our team', 'Our location', 'Products']).filter(function (n, i, a) { return a.indexOf(n) === i && used.indexOf(String(n).toLowerCase()) < 0; }).slice(0, 8);
		return '<div class="kx-albums">' + albums.map(function (a) {
			var n = filesOf(k, a.id).length;
			return '<div class="kx-album" data-album="' + esc(a.id) + '"><div class="kx-album__h"><input class="ax-in" type="text" value="' + esc(a.name) + '" placeholder="Album name, e.g. Kitchen remodels" data-aname="' + esc(a.id) + '" aria-label="Album name"><small>' + n + ' photo' + (n === 1 ? '' : 's') + '</small>' + (n ? '' : '<button type="button" class="kx-row__x" data-adel="' + esc(a.id) + '" aria-label="Remove album">' + I.x + '</button>') + '</div>' +
				dropHtml(k, a.id, true) + filesHtml(k, a.id) + '</div>';
		}).join('') + '</div>' +
			'<div class="kx-sugs">' + sug.map(function (s) { return '<button type="button" data-aadd="' + esc(s) + '">+ ' + esc(s) + '</button>'; }).join('') + '<button type="button" data-aadd="">+ New album</button></div>' +
			(albums.length ? '' : '<p style="font-size:13px;color:var(--mute);margin-top:10px">Start with an album for each service you offer. Then drop the photos for that service into it.</p>') +
			unsortedHtml(it, albums) +
			'<div class="kx-sub"><label>Or share a folder link (Google Drive, Dropbox, iCloud…)</label><input class="ax-in" type="url" data-f="' + k + '.link" value="' + esc(dget(k, 'link')) + '" placeholder="https://"></div>' +
			(it.rights ? '<label class="kx-check"><input type="checkbox" data-fc="' + k + '.rights"' + (dget(k, 'rights') ? ' checked' : '') + '> ' + esc(it.rights) + '</label>' : '');
	}
	/* Photos not in any album (or dropped without one): kept and shown, never lost. */
	function unsortedHtml(it, albums) {
		var k = it.key, ids = albums.map(function (a) { return a.id; });
		var loose = FILES.filter(function (f) { if (f.item === k) return true; if (f.item.indexOf(k + ':') !== 0) return false; return ids.indexOf(f.item.slice(k.length + 1)) < 0; });
		return '<div class="kx-album" style="margin-top:14px;border-style:dashed"><div class="kx-album__h"><b style="flex:1;font-size:14px">' + (albums.length ? 'Not sure which album? Drop them here' : 'Or just drop all your photos here') + '</b>' + (loose.length ? '<small>' + loose.length + ' photo' + (loose.length === 1 ? '' : 's') + '</small>' : '') + '</div>' +
			dropHtml(k, '', true) + '<div class="kx-files">' + loose.map(fileCard).join('') + '</div></div>';
	}
	function inputHtml(f, id, v) {
		var t = f.t || 'text';
		if (t === 'textarea') return '<textarea class="ax-ta" data-f="' + id + '" placeholder="' + esc(f.ph || '') + '" style="min-height:84px">' + esc(v) + '</textarea>';
		if (t === 'select') return '<select class="ax-sel" data-f="' + id + '"><option value="">Choose…</option>' + (f.o || []).map(function (o) { return '<option' + (v === o ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select>';
		return '<input class="ax-in" type="' + (t === 'email' ? 'email' : t === 'tel' ? 'tel' : t === 'url' ? 'url' : 'text') + '" data-f="' + id + '" value="' + esc(v) + '" placeholder="' + esc(f.ph || (t === 'url' ? 'https://' : '')) + '">';
	}
	function fieldsHtml(it) {
		return '<div class="kx-grid">' + (it.fields || []).map(function (f) {
			var id = it.key + '.' + f.k;
			return '<div class="' + (f.t === 'textarea' ? 'wide' : '') + '"><label for="f-' + id + '">' + esc(f.l) + '</label>' + inputHtml(f, id, dget(it.key, f.k)).replace('data-f=', 'id="f-' + id + '" data-f=') + '</div>';
		}).join('') + '</div>';
	}
	function rowsHtml(it) {
		var k = it.key, rows = Array.isArray(dget(k, 'rows')) && dget(k, 'rows').length ? dget(k, 'rows').filter(function (r) { return r && typeof r === 'object'; }) : [{}];
		if (!rows.length) rows = [{}];
		var cols = it.cols || [], tmpl = cols.map(function (c) { return c.t === 'textarea' ? 'minmax(0,1.6fr)' : 'minmax(0,1fr)'; }).join(' ') + ' 36px';
		return (it.check ? '<label class="kx-check" style="margin:0 0 12px"><input type="checkbox" data-fc="' + k + '.check"' + (dget(k, 'check') ? ' checked' : '') + '> ' + esc(it.check) + '</label>' : '') +
			'<div class="kx-rows" data-rows="' + k + '">' + rows.map(function (r, n) {
				return '<div class="kx-row" data-row="' + n + '" style="grid-template-columns:' + tmpl + '">' + cols.map(function (c) {
					var v = r[c.k] || '';
					var inp = c.t === 'textarea' ? '<textarea class="ax-ta" data-rc="' + c.k + '" placeholder="' + esc(c.ph || c.l) + '" aria-label="' + esc(c.l) + '">' + esc(v) + '</textarea>'
						: c.t === 'select' ? '<select class="ax-sel" data-rc="' + c.k + '" aria-label="' + esc(c.l) + '"><option value="">' + esc(c.l) + '…</option>' + (c.o || []).map(function (o) { return '<option' + (v === o ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select>'
						: '<input class="ax-in" type="' + (c.t === 'email' ? 'email' : 'text') + '" data-rc="' + c.k + '" value="' + esc(v) + '" placeholder="' + esc(c.ph || c.l) + '" aria-label="' + esc(c.l) + '">';
					return '<div>' + inp + '</div>';
				}).join('') + '<button type="button" class="kx-row__x" data-rdel="' + k + '" data-n="' + n + '" aria-label="Remove">' + I.x + '</button></div>';
			}).join('') + '</div><button type="button" class="ax-add" data-radd="' + k + '">+ ' + esc(it.add || 'Add another') + '</button>';
	}
	function peopleHtml(it) {
		var k = it.key, rows = Array.isArray(dget(k, 'rows')) && dget(k, 'rows').length ? dget(k, 'rows').filter(function (r) { return r && typeof r === 'object'; }).map(function (r) { return /^[a-z0-9]{1,16}$/.test(String(r.id || '')) ? r : Object.assign({}, r, { id: rid() }); }) : [{ id: rid() }];
		return '<div class="kx-rows" data-rows="' + k + '">' + rows.map(function (r, n) {
			var face = filesOf(k, r.id)[0];
			var faceHtml = face ? '<div class="kx-face" style="background-image:url(\'' + esc(face.local ? face.preview : (face.thumb ? C.rest + '/file/' + face.id : '')) + '\')"><button type="button" data-rm="' + esc(face.id || '') + '" data-rmu="' + esc(face.uid || '') + '" aria-label="Remove photo">' + I.x + '</button></div>'
				: '<label class="kx-drop" data-drop="' + k + ':' + esc(r.id) + '">' + I.up.replace('28', '22').replace('28', '22') + '<span>Headshot</span><input type="file" accept="image/*" data-pick="' + k + ':' + esc(r.id) + '" aria-label="Add a headshot"></label>';
			return '<div class="kx-row kx-person" data-row="' + n + '" data-rid="' + esc(r.id) + '">' + faceHtml + '<div class="kx-grid">' +
				'<div><input class="ax-in" data-rc="name" value="' + esc(r.name || '') + '" placeholder="Name" aria-label="Name"></div><div><input class="ax-in" data-rc="role" value="' + esc(r.role || '') + '" placeholder="Role or title" aria-label="Role"></div>' +
				'<div class="wide"><textarea class="ax-ta" data-rc="bio" placeholder="A short bio (2–3 sentences)" aria-label="Bio" style="min-height:64px">' + esc(r.bio || '') + '</textarea></div></div>' +
				'<button type="button" class="kx-row__x" data-rdel="' + k + '" data-n="' + n + '" aria-label="Remove person">' + I.x + '</button></div>';
		}).join('') + '</div><button type="button" class="ax-add" data-radd="' + k + '">+ ' + esc(it.add || 'Add a person') + '</button>';
	}
	function colorsHtml(it) {
		var k = it.key, list = Array.isArray(dget(k, 'colors')) && dget(k, 'colors').length ? dget(k, 'colors').filter(function (c) { return c && typeof c === 'object'; }) : [{ hex: '', name: '' }];
		if (!list.length) list = [{ hex: '', name: '' }];
		var fromLogo = !!dget(k, 'from_logo');
		return '<div' + (fromLogo ? ' hidden' : '') + '><div class="kx-sw" data-colors="' + k + '">' + list.map(function (c, n) {
			var hex = /^#[0-9a-f]{6}$/i.test(c.hex || '') ? c.hex : '#EF5D0F';
			return '<div class="kx-color" data-row="' + n + '"><input type="color" value="' + esc(hex) + '" data-cc="pick" aria-label="Pick a color"><input class="ax-in" data-cc="hex" value="' + esc(c.hex || '') + '" placeholder="#000000" maxlength="7" aria-label="Hex code"><input class="ax-in kx-cname" data-cc="name" value="' + esc(c.name || '') + '" placeholder="Name (optional)" aria-label="Color name"><button type="button" class="kx-row__x" data-cdel="' + n + '" aria-label="Remove color">' + I.x + '</button></div>';
		}).join('') + '</div><button type="button" class="ax-add" data-cadd="' + k + '">+ Add a color</button>' +
			'</div><div class="kx-grid"' + (fromLogo ? ' hidden' : '') + ' style="margin-top:14px"><div><label>Print colors (CMYK), if you have them</label><input class="ax-in" data-f="' + k + '.cmyk" value="' + esc(dget(k, 'cmyk')) + '" placeholder="e.g. 0 70 100 0"></div><div><label>Pantone (PMS), if you have them</label><input class="ax-in" data-f="' + k + '.pms" value="' + esc(dget(k, 'pms')) + '" placeholder="e.g. PMS 1585 C"></div></div>' +
			'<label class="kx-check"><input type="checkbox" data-fc="' + k + '.from_logo"' + (dget(k, 'from_logo') ? ' checked' : '') + '> Not sure: take the colors from our logo</label>';
	}
	function fillSteps(s) {
		var copy = '<button type="button" class="kx-copy" data-copy="' + esc(C.invite) + '" title="Copy">' + esc(C.invite) + '</button>';
		return esc(s).replace(/\{email\}/g, copy).replace(/\{person\}/g, '<b>' + esc(C.person) + '</b>').replace(/\{meta\}/g, C.metaId ? ' Or add AdaptExec as a partner with Business ID <b>' + esc(C.metaId) + '</b>.' : '');
	}
	function accessHtml(it) {
		var k = it.key, d = D[k] || {}, plats = it.platforms || [], mode = d.mode || 'invite';
		var plat = null; plats.forEach(function (p) { if (p.k === d.platform) plat = p; });
		if (!plat && plats.length === 1) plat = plats[0];
		var canLogin = it.login !== false && (!plat || plat.login !== false);
		var seg = canLogin ? '<div class="kx-seg" role="tablist"><button type="button" role="tab" data-mode="' + k + '" data-v="invite" class="' + (mode !== 'login' ? 'on' : '') + '">Invite us <em>Safest</em></button><button type="button" role="tab" data-mode="' + k + '" data-v="login" class="' + (mode === 'login' ? 'on' : '') + '">' + I.lock.replace(/18/g, '15') + ' Share a login</button></div>' : '';
		var pick = plats.length > 1 ? '<div class="kx-sub" style="margin-top:0"><label>Where is it?</label><div class="kx-plats">' + plats.map(function (p) { return '<button type="button" data-plat="' + k + '" data-v="' + p.k + '" class="' + (d.platform === p.k ? 'on' : '') + '">' + esc(p.name) + '</button>'; }).join('') + '</div></div>' : '';
		var body = '';
		if (mode === 'login' && canLogin) {
			body = SEC[k]
				? '<div class="kx-saved">' + I.lock + '<span><b>Login saved securely</b>' + (SEC[k].user ? ' · ' + esc(SEC[k].user) : '') + '</span><span class="sp"></span><button type="button" data-sec-replace="' + k + '">Replace</button><button type="button" data-sec-rm="' + k + '">Remove</button></div>'
				: lockForm(k);
		} else if (plat) {
			body = '<ol class="kx-steps">' + plat.steps.map(function (s) { return '<li>' + fillSteps(s) + '</li>'; }).join('') + '</ol>' +
				(plat.login === false ? '' : '<label class="kx-check"><input type="checkbox" data-fc="' + k + '.done"' + (d.done ? ' checked' : '') + '> <span><b>Done: I’ve sent the invite</b> to ' + esc(C.invite) + '</span></label>');
		} else if (plats.length > 1) {
			body = '<p style="font-size:14px;color:var(--mute)">Pick where it is and we’ll show you the exact steps.</p>';
		}
		return pick + seg + body + '<div class="kx-sub"><label>Anything we should know? (optional)</label><input class="ax-in" data-f="' + k + '.note" value="' + esc(d.note || '') + '" placeholder="e.g. account name, who to call, two-step code goes to Sarah’s phone"></div>';
	}
	function lockForm(k) {
		return '<div class="kx-lock" data-lock="' + k + '"><div class="kx-lock__h">' + I.lock + '<span>Encrypted the moment you press Save. Only AdaptExec can open it, every opening is recorded, and you can remove it here any time. Tip: change the password after the project, or invite us instead.</span></div>' +
			'<div class="kx-grid"><div class="wide"><label>Login page</label><input class="ax-in" data-l="url" type="url" placeholder="https://" autocomplete="off"></div>' +
			'<div><label>Username or email</label><input class="ax-in" data-l="user" autocomplete="off"></div>' +
			'<div><label>Password</label><div class="kx-pw"><input class="ax-in" data-l="pass" type="password" autocomplete="new-password"><button type="button" data-peek>Show</button></div></div>' +
			'<div class="wide"><label>Two-step codes (if it asks for one)</label><input class="ax-in" data-l="mfa" placeholder="e.g. Codes go to (602) 555-0100, text Sarah"></div></div>' +
			'<div style="margin-top:14px;display:flex;gap:10px;align-items:center"><button type="button" class="ax-btn ax-btn--dark" data-lock-save="' + k + '">' + I.lock + ' Save securely</button><span style="font-size:13px;color:var(--mute)" data-lock-msg></span></div></div>';
	}
	function leftHtml(c) {
		return c.left.length ? '<div class="kx-left">' + c.left.slice(0, 10).map(function (it) { return '<button type="button" data-jump="' + it.key + '"><i></i>' + esc(it.title) + '</button>'; }).join('') + (c.left.length > 10 ? '<span style="font-size:13px;color:var(--mute);padding:4px 2px">and ' + (c.left.length - 10) + ' more</span>' : '') + '</div>' : '<p style="color:var(--ok);font-weight:600">' + I.ok + ' Every required item is in.</p>';
	}
	function finishHtml(c) {
		var sent = C.status === 'done' || C.status === 'complete';
		return '<div class="ax-card kx-finish"><div class="ax-k">' + (sent ? 'Sent' : 'Last step') + '</div><h2 class="ax-h">' + (sent ? 'Added something new?' : 'All set?') + '</h2>' +
			'<p>' + (sent ? 'Everything saves automatically, but press below to let ' + esc(first(C.rep)) + ' know there’s something new to look at.' : 'Send it over when you’re ready. Anything still open can be added later, and we’ll let you know if we need something else.') + '</p>' +
			'<div data-left>' + leftHtml(c) + '</div><button type="button" class="ax-btn ax-btn--p" data-finish style="min-height:52px;padding:0 28px">' + (sent ? 'Let ' + esc(first(C.rep)) + ' know' : 'Send to AdaptExec') + ' ' + I.arrow + '</button>' +
			'<div class="ax-tl" style="margin-top:30px"><div class="' + (sent ? 'ok' : 'on') + '"><b>Onboarding</b>You are here</div><div class="' + (sent ? 'on' : '') + '"><b>Design</b>We shape the look</div><div><b>Build</b>We build it</div><div><b>Launch</b>You go live</div></div></div>';
	}
	function grow(t) { t.style.height = 'auto'; t.style.height = Math.max(parseInt(t.style.minHeight, 10) || 84, Math.min(t.scrollHeight + 2, 480)) + 'px'; }

	/* ------------------------------------------------------------ uploads */
	var queue = [], active = 0;
	function addFiles(target, list) {
		[].forEach.call(list, function (file) {
			var f = { uid: rid() + rid(), name: file.name, size: file.size, item: target, local: true, pct: 0, by: 'client', file: file };
			if (file.size > C.maxMb * 1048576) { f.err = 'Over ' + C.maxMb + ' MB: share a link instead'; }
			else if (/^image\/(jpeg|png|webp|gif)/.test(file.type)) { try { f.preview = URL.createObjectURL(file); } catch (e) {} }
			FILES.push(f);
			if (!f.err) { queue.push(f); uploads++; }
		});
		var base = target.split(':')[0];
		repaintFiles(target); refresh(base); idle();
		pump();
	}
	function pump() { while (active < 2 && queue.length) { active++; sendFile(queue.shift()).then(function () { active--; pump(); }); } }
	function sendFile(f) {
		var size = C.chunk || 2097152, total = Math.max(1, Math.ceil(f.size / size)), idx = 0, tries = 0;
		function next() {
			var blob = f.file.slice(idx * size, Math.min(f.size, (idx + 1) * size));
			var fd = new FormData();
			fd.append('item', f.item); fd.append('uid', f.uid); fd.append('idx', idx); fd.append('total', total); fd.append('size', f.size); fd.append('name', f.name); fd.append('chunk', blob, 'chunk');
			return fetch(C.rest + '/upload', { method: 'POST', body: fd, credentials: 'omit' }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(j.message || 'Upload failed'); e.status = r.status; throw e; } return j; }); })
				.then(function (j) {
					tries = 0; idx++;
					f.pct = Math.round(idx / total * 100); progress(f);
					if (j.done) return j;
					return next();
				}, function (e) {
					if (e.status && e.status !== 429 && e.status < 500 && e.status !== 409) throw e;
					if (++tries > 5) throw e;
					return new Promise(function (res) { setTimeout(res, 1500 * tries); }).then(next);
				});
		}
		return next().then(function (j) {
			var i = FILES.indexOf(f);
			if (j.file && i > -1) { if (f.preview) j.file.preview = f.preview; FILES[i] = j.file; if (f.preview) { FILES[i].local = false; } }
			uploads--; repaintFiles(f.item); refresh(f.item.split(':')[0]); idle();
		}, function (e) {
			f.err = e.message || 'Upload failed'; uploads--;
			repaintFiles(f.item); idle();
		});
	}
	function progress(f) {
		var card = app.querySelector('[data-fid="' + f.uid + '"]');
		if (!card) return;
		var bar = card.querySelector('.kx-file__p i'); if (bar) bar.style.width = f.pct + '%';
		var t = card.querySelector('.kx-file__n span'); if (t) t.textContent = f.pct + '%';
	}
	function repaintFiles(target) {
		var p = target.split(':'), k = p[0], it = ITEMS[k];
		if (!it) return;
		if (it.kind === 'people' || it.kind === 'albums') { var y = window.pageYOffset; repaintBody(k); window.scrollTo(0, y); return; }
		var box = app.querySelector('[data-files="' + k + '"]');
		if (box) box.innerHTML = filesOf(k).map(fileCard).join('');
	}

	/* -------------------------------------------------------------- events */
	function readRows(k) {
		var box = app.querySelector('[data-rows="' + k + '"]'), out = [];
		if (!box) return Array.isArray(dget(k, 'rows')) ? dget(k, 'rows').slice() : [];
		[].forEach.call(box.querySelectorAll(':scope > [data-row]'), function (r) {
			var o = {}; if (r.getAttribute('data-rid')) o.id = r.getAttribute('data-rid');
			[].forEach.call(r.querySelectorAll('[data-rc]'), function (i) { o[i.getAttribute('data-rc')] = i.value; });
			out.push(o);
		});
		return out;
	}
	function readColors(k) {
		var box = app.querySelector('[data-colors="' + k + '"]'), out = [];
		[].forEach.call(box.querySelectorAll('[data-row]'), function (r) { out.push({ hex: r.querySelector('[data-cc="hex"]').value.trim(), name: r.querySelector('[data-cc="name"]').value }); });
		return out;
	}
	function jump(k) {
		var it = ITEMS[k]; if (!it) return;
		OPEN[k] = true; paintItem(k);
		var el = document.getElementById('it-' + k);
		if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); el.classList.add('is-hot'); setTimeout(function () { el.classList.remove('is-hot'); }, 1600); }
	}
	app.addEventListener('click', function (e) {
		var t = e.target.closest ? e.target.closest('button, a, [data-toggle]') : null;
		if (!t) return;
		var k;
		if ((k = t.getAttribute('data-toggle'))) { OPEN[k] = !OPEN[k]; paintItem(k); return; }
		if ((k = t.getAttribute('data-jump'))) { jump(k); return; }
		if ((k = t.getAttribute('data-sec'))) { var s = document.getElementById('sec-' + k); if (s) s.scrollIntoView({ behavior: 'smooth' }); return; }
		if (t.hasAttribute('data-copy')) {
			var v = t.getAttribute('data-copy');
			(navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { var o = t.textContent; t.textContent = 'Copied'; setTimeout(function () { t.textContent = o; }, 1200); }, function () {});
			return;
		}
		if ((k = t.getAttribute('data-mode'))) { set(k, 'mode', t.getAttribute('data-v'), 200); repaintBody(k); return; }
		if ((k = t.getAttribute('data-plat'))) { set(k, 'platform', t.getAttribute('data-v'), 200); repaintBody(k); return; }
		if ((k = t.getAttribute('data-radd'))) { var rows = readRows(k); rows.push(ITEMS[k].kind === 'people' ? { id: rid() } : {}); set(k, 'rows', rows, 600); repaintBody(k); var rs = app.querySelectorAll('[data-rows="' + k + '"] > [data-row]'); var last = rs[rs.length - 1]; if (last) { var fi = last.querySelector('input, textarea'); if (fi) fi.focus(); } return; }
		if ((k = t.getAttribute('data-rdel'))) { var r2 = readRows(k); r2.splice(+t.getAttribute('data-n'), 1); set(k, 'rows', r2, 300); repaintBody(k); return; }
		if ((k = t.getAttribute('data-cadd'))) { var cs = readColors(k); cs.push({ hex: '', name: '' }); set(k, 'colors', cs, 600); repaintBody(k); return; }
		if (t.hasAttribute('data-cdel')) { var ck = t.closest('[data-colors]').getAttribute('data-colors'), c2 = readColors(ck); c2.splice(+t.getAttribute('data-cdel'), 1); set(ck, 'colors', c2, 300); repaintBody(ck); return; }
		if (t.hasAttribute('data-aadd')) {
			var it = t.closest('[data-item]').getAttribute('data-item'), al = Array.isArray(dget(it, 'albums')) ? dget(it, 'albums').slice() : [];
			al.push({ id: rid(), name: t.getAttribute('data-aadd') }); set(it, 'albums', al, 300); repaintBody(it);
			if (!t.getAttribute('data-aadd')) { var ins = app.querySelectorAll('#it-' + it + ' [data-aname]'); if (ins.length) ins[ins.length - 1].focus(); }
			return;
		}
		if ((k = t.getAttribute('data-adel'))) { var ik = t.closest('[data-item]').getAttribute('data-item'), a2 = (dget(ik, 'albums') || []).filter(function (a) { return a.id !== k; }); set(ik, 'albums', a2, 300); repaintBody(ik); return; }
		if (t.hasAttribute('data-rm') || t.hasAttribute('data-rmu')) {
			var id = t.getAttribute('data-rm'), uid = t.getAttribute('data-rmu');
			var f = FILES.filter(function (x) { return (id && String(x.id) === id) || (uid && x.uid === uid); })[0];
			if (!f) return;
			if (!id) { FILES.splice(FILES.indexOf(f), 1); repaintFiles(f.item); return; }
			t.disabled = true;
			api('/file/' + id, { method: 'DELETE', credentials: 'omit' }).then(function () { FILES.splice(FILES.indexOf(f), 1); repaintFiles(f.item); refresh(f.item.split(':')[0]); }, function (er) { t.disabled = false; alertMsg(er.message); });
			return;
		}
		if (t.hasAttribute('data-peek')) { var pw = t.parentNode.querySelector('input'); pw.type = pw.type === 'password' ? 'text' : 'password'; t.textContent = pw.type === 'password' ? 'Show' : 'Hide'; return; }
		if ((k = t.getAttribute('data-lock-save'))) {
			var box = t.closest('[data-lock]'), body = { item: k };
			box.querySelectorAll('[data-l]').forEach(function (i) { body[i.getAttribute('data-l')] = i.value; });
			var msg = box.querySelector('[data-lock-msg]');
			if (!body.user || !body.pass) { msg.textContent = 'Add the username and the password.'; return; }
			t.disabled = true; msg.textContent = 'Encrypting…';
			api('/secret', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'omit' }).then(function (j) {
				SEC = j.secrets || SEC; if (!D[k]) D[k] = {}; D[k].mode = 'login'; set(k, 'mode', 'login', 100); repaintBody(k); refresh(k);
			}, function (er) { t.disabled = false; msg.textContent = er.message; });
			return;
		}
		if ((k = t.getAttribute('data-sec-replace'))) { var b2 = t.closest('.kx-saved'); var tmp = document.createElement('div'); tmp.innerHTML = lockForm(k); b2.parentNode.replaceChild(tmp.firstChild, b2); return; }
		if ((k = t.getAttribute('data-sec-rm'))) {
			t.disabled = true;
			api('/secret', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ item: k, remove: true }), credentials: 'omit' }).then(function (j) { SEC = j.secrets || {}; repaintBody(k); refresh(k); }, function (er) { t.disabled = false; alertMsg(er.message); });
			return;
		}
		if (t.hasAttribute('data-finish')) {
			t.disabled = true; var lbl = t.innerHTML; t.textContent = 'Sending…';
			Promise.resolve(flush()).then(function () { return api('/finish', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}', credentials: 'omit' }); }).then(function () {
				C.status = 'done'; render(); window.scrollTo({ top: 0, behavior: 'smooth' });
			}, function (er) { t.disabled = false; t.innerHTML = lbl; alertMsg(er.message); });
		}
	});
	function alertMsg(m) { status('off', m || 'Something went wrong'); setTimeout(idle, 5000); }
	app.addEventListener('input', function (e) {
		var t = e.target, id;
		if (t.tagName === 'TEXTAREA') grow(t);
		if ((id = t.getAttribute('data-f')) && t.tagName !== 'SELECT') { var p = id.split('.'); set(p[0], p[1], t.value); return; }
		if (t.hasAttribute('data-rc')) { var rk = t.closest('[data-rows]').getAttribute('data-rows'); set(rk, 'rows', readRows(rk)); return; }
		if (t.hasAttribute('data-cc')) {
			var row = t.closest('[data-row]'), ck = t.closest('[data-colors]').getAttribute('data-colors');
			if (t.getAttribute('data-cc') === 'pick') row.querySelector('[data-cc="hex"]').value = t.value.toUpperCase();
			if (t.getAttribute('data-cc') === 'hex' && /^#[0-9a-f]{6}$/i.test(t.value.trim())) row.querySelector('[data-cc="pick"]').value = t.value.trim();
			set(ck, 'colors', readColors(ck), 600); return;
		}
		if ((id = t.getAttribute('data-aname'))) { var ak = t.closest('[data-item]').getAttribute('data-item'), al = (dget(ak, 'albums') || []).map(function (a) { return a.id === id ? { id: a.id, name: t.value } : a; }); set(ak, 'albums', al); }
	});
	app.addEventListener('change', function (e) {
		var t = e.target, id;
		if ((id = t.getAttribute('data-f')) && t.tagName === 'SELECT') { var p = id.split('.'); set(p[0], p[1], t.value, 200); return; }
		if (t.tagName === 'SELECT' && t.hasAttribute('data-rc')) { var rk = t.closest('[data-rows]').getAttribute('data-rows'); set(rk, 'rows', readRows(rk), 200); return; }
		if ((id = t.getAttribute('data-fc'))) { var q = id.split('.'); set(q[0], q[1], t.checked, 150); if (q[1] === 'from_logo') repaintBody(q[0]); return; }
		if ((id = t.getAttribute('data-na'))) { setNa(id, t.checked); repaintBody(id); return; }
		if ((id = t.getAttribute('data-pick')) && t.files && t.files.length) { addFiles(id, t.files); t.value = ''; }
	});
	app.addEventListener('focusout', function (e) { if (e.target && (e.target.hasAttribute('data-f') || e.target.hasAttribute('data-rc') || e.target.hasAttribute('data-aname')) && Object.keys(pending).length) flush(); });

	/* Drag and drop: every upload box lights up while files are dragged over the page. */
	var depth = 0;
	function isFiles(e) { return e.dataTransfer && [].indexOf.call(e.dataTransfer.types || [], 'Files') > -1; }
	document.addEventListener('dragenter', function (e) { if (!isFiles(e)) return; depth++; document.body.classList.add('is-drag'); var z = e.target.closest && e.target.closest('[data-drop]'); if (z) z.classList.add('is-over'); });
	document.addEventListener('dragleave', function (e) { if (!isFiles(e)) return; depth = Math.max(0, depth - 1); if (!depth) document.body.classList.remove('is-drag'); var z = e.target.closest && e.target.closest('[data-drop]'); if (z && !z.contains(e.relatedTarget)) z.classList.remove('is-over'); });
	document.addEventListener('dragover', function (e) { if (isFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = e.target.closest && e.target.closest('[data-drop]') ? 'copy' : 'none'; } });
	document.addEventListener('drop', function (e) {
		if (!isFiles(e)) return;
		e.preventDefault(); depth = 0; document.body.classList.remove('is-drag');
		app.querySelectorAll('.is-over').forEach(function (z) { z.classList.remove('is-over'); });
		var z = e.target.closest && e.target.closest('[data-drop]');
		if (z && e.dataTransfer.files.length) addFiles(z.getAttribute('data-drop'), e.dataTransfer.files);
	});

	if (Object.keys(pending).length || Object.keys(pstates).length) setTimeout(flush, 400);
	render();
})();
</script>
</body>
</html>
		<?php
	}
}

AXH_Kickoff::init();
