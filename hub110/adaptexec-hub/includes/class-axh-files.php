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

	const DB  = '1';
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
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, lead_id, rep_id, name, mime, size, created_at FROM {$t} WHERE lead_id = %d ORDER BY id DESC LIMIT 200", (int) $lead_id ), ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$u          = get_userdata( (int) $r['rep_id'] );
			$r['by']    = $u ? $u->display_name : '';
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
		if ( ! preg_match( '/^lf[a-f0-9]{32}\.axh$/', $r['file'] ) || ! is_readable( AXH_Onboard::dir() . '/' . $r['file'] ) ) {
			return new WP_Error( 'axh_missing', 'The file is missing from the private folder.', array( 'status' => 404 ) );
		}
		$bytes = AXH_Onboard::decrypt( (string) file_get_contents( AXH_Onboard::dir() . '/' . $r['file'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}
		$inline = in_array( $r['mime'], array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp' ), true );
		axh_nocache();
		nocache_headers();
		header( 'Content-Type: ' . $r['mime'] );
		$ascii = preg_replace( '/[^A-Za-z0-9._ -]/', '', $r['name'] );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $r['name'] ) );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox" );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput -- a file download.
		exit;
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
		$wpdb->delete( AXH_DB::table( 'lead_files' ), array( 'id' => (int) $r['id'] ) );
		if ( preg_match( '/^lf[a-f0-9]{32}\.axh$/', $r['file'] ) ) {
			@unlink( AXH_Onboard::dir() . '/' . $r['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		AXH_DB::log( (int) $r['lead_id'], 'file', 'Removed a file: ' . mb_substr( $r['name'], 0, 150 ) );
		return rest_ensure_response( array( 'files' => self::list_for( (int) $r['lead_id'] ) ) );
	}
}

AXH_Files::init();
