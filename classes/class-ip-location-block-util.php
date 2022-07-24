<?php
/**
 * IP Location Block - Utilities
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

class IP_Location_Block_Util {

	/**
	 * Returns the storage dir, if it doesn't exist creates it. Works with recursive option.
	 *
	 * @param null $target
	 *
	 * @return string
	 */
	public static function get_storage_dir( $target = null ) {

		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$file_system   = IP_Location_Block_FS::init( __FUNCTION__ );
		$wp_upload_dir = wp_upload_dir();

		// Root dir
		$dir_path = IP_Location_Block_Util::slashit( IP_Location_Block_Util::slashit( $wp_upload_dir['basedir'] ) . 'ip-location-block' );
		if ( ! $file_system->exists( $dir_path ) ) {
			$file_system->mkdir( $dir_path );
		}

		// Init sub_dir path
		$sub_dir = $dir_path;

		// Create sub dirs
		if ( ! empty( $target ) ) {
			$sub_dirs = explode( DIRECTORY_SEPARATOR, $target );
			foreach ( $sub_dirs as $dir ) {
				$sub_dir = IP_Location_Block_Util::slashit( $sub_dir ) . $dir;
				if ( ! $file_system->exists( $sub_dir ) ) {
					$file_system->mkdir( $sub_dir );
				}
			}
		}

		return IP_Location_Block_Util::slashit( $sub_dir );
	}

	/**
	 *
	 * @param null $dir
	 *
	 * @return string
	 */
	public static function get_databases_storage_dir( $dir = null ) {
		return self::get_storage_dir( 'databases' . DIRECTORY_SEPARATOR . $dir );
	}

	/**
	 * Return the drop ins storage dir
	 *
	 * @param $dropin_name
	 *
	 * @return string
	 */
	public static function get_dropins_storage_dir( $dropin_name ) {
		return self::get_storage_dir( 'dropins' ) . $dropin_name;
	}

	/**
	 * Return local time of day.
	 *
	 * @param bool $timestamp
	 * @param null $fmt
	 *
	 * @return string
	 */
	public static function localdate( $timestamp = false, $fmt = null ) {
		static $offset = null;
		static $format = null;

		null === $offset and $offset = wp_timezone_override_offset() * HOUR_IN_SECONDS; // @since  0.2.8.0
		null === $format and $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return date_i18n( $fmt ? $fmt : $format, $timestamp ? (int) $timestamp + $offset : false );
	}

	/**
	 * Download zip/gz file, un-compress and save it to specified file
	 *
	 * @param $url
	 * @param $args
	 * @param $files
	 * @param $modified
	 *
	 * @return array|bool
	 */
	public static function download_zip( $url, $args, $files, $modified ) {

		$subdir = 'ilb-' . substr( base_convert( md5( $url ), 16, 32 ), 0, 12 );
		$tmp    = self::get_temp_dir( $subdir );
		if ( is_wp_error( $tmp ) ) {
			throw new Exception(
				sprintf( __( 'Unable to extract archive. %s', 'ip-location-block' ), $tmp->get_error_message() )
			);
		}

		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$fs = IP_Location_Block_FS::init( __FUNCTION__ );

		// get extension
		$ext = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
		if ( 'tar.gz' === substr( $url, - 6 ) ) {
			$ext = 'tar';
		}

		// check file (1st parameter includes absolute path in case of array)
		$filename = is_array( $files ) ? $files[0] : (string) $files;
		if ( ! $fs->exists( $filename ) ) {
			$modified = 0;
		}

		// set 'If-Modified-Since' request header
		$args += array(
			'headers' => array(
				'Accept'            => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Encoding'   => 'gzip, deflate',
				'If-Modified-Since' => gmdate( DATE_RFC1123, (int) $modified ),
			),
		);

		// fetch file and get response code & message
		if ( isset( $args['method'] ) && 'GET' === $args['method'] ) {
			$src = wp_remote_get( ( $url = esc_url_raw( $url ) ), $args );
		} else {
			$src = wp_remote_head( ( $url = esc_url_raw( $url ) ), $args );
		}

		if ( is_wp_error( $src ) ) {
			return array(
				'code'    => $src->get_error_code(),
				'message' => $src->get_error_message(),
			);
		}

		$code     = wp_remote_retrieve_response_code( $src );
		$mesg     = wp_remote_retrieve_response_message( $src );
		$data     = wp_remote_retrieve_header( $src, 'last-modified' );
		$modified = $data ? strtotime( $data ) : $modified;

		if ( 304 == $code ) {
			return array(
				'code'     => $code,
				'message'  => __( 'Your database file is up-to-date.', 'ip-location-block' ),
				'filename' => $filename,
				'modified' => $modified,
			);
		} elseif ( 200 != $code ) {
			return array(
				'code'    => $code,
				'message' => $code . ' ' . $mesg,
			);
		}

		try {
			// in case that the server which does not support HEAD method
			if ( isset( $args['method'] ) && 'GET' === $args['method'] ) {
				$data = wp_remote_retrieve_body( $src );

				if ( 'gz' === $ext ) {
					if ( function_exists( 'gzdecode' ) ) { // @since PHP 5.4.0
						if ( false === $fs->put_contents( $filename, gzdecode( $data ) ) ) {
							throw new Exception(
								sprintf( __( 'Unable to write <code>%s</code>. Please check the permission.', 'ip-location-block' ), $filename )
							);
						}
					} else {
						$src = $tmp . basename( $url ); // $src should be removed
						$fs->put_contents( $src, $data );
						if ( true !== ( $ret = self::gzfile( $src, $filename ) ) ) {
							$err = $ret;
						}
					}
				} elseif ( 'tar' === $ext && class_exists( 'PharData', false ) ) { // @since PECL phar 2.0.0

					$name = wp_remote_retrieve_header( $src, 'content-disposition' );
					$name = explode( 'filename=', $name );
					$name = array_pop( $name ); // e.g. GeoLite2-Country_20180102.tar.gz
					$src  = $tmp . $name; // $src should be removed

					// CVE-2015-6833: A directory traversal when extracting ZIP files could be used to overwrite files
					// outside of intended area via a `..` in a ZIP archive entry that is mishandled by extractTo().
					if ( $fs->put_contents( $src, $data ) ) {
						$data = new PharData( $src, FilesystemIterator::SKIP_DOTS ); // get archives

						// make the list of contents to be extracted from archives.
						// when the list doesn't match the contents in archives, extractTo() may be crushed on windows.
						$dst = $data->getSubPathname(); // e.g. GeoLite2-Country_20180102
						foreach ( $files as $key => $val ) {
							$files[ $key ] = $dst . '/' . basename( $val );
						}

						// extract specific files from archives into temporary directory and copy it to the destination.
						$fpath = self::slashit( $tmp . $dst );
						$data->extractTo( $fpath, $files /* NULL */, true );

						// copy extracted files to Geolocation APIs directory
						$dst = dirname( $filename );
						foreach ( $files as $val ) {
							// should the destination be exclusive with LOCK_EX ?
							// $fs->put_contents( $dst.'/'.basename( $val ), $fs->get_contents( $tmp.'/'.$val ) );
							$fs->copy( $fpath . $val, $dst . '/' . basename( $val ), true );
						}
					}
				}
			} // downloaded and unzip
			else {

				// download file
				$src = download_url( $url );

				if ( is_wp_error( $src ) ) {
					throw new Exception(
						$src->get_error_code() . ' ' . $src->get_error_message()
					);
				}

				// unzip file
				if ( 'gz' === $ext ) {
					if ( true !== ( $ret = self::gzfile( $src, $filename ) ) ) {
						$err = $ret;
					}
				} elseif ( 'zip' === $ext && class_exists( 'ZipArchive', false ) ) {

					$ret = $fs->unzip_file( $src, $tmp ); // @since  0.2.5

					if ( is_wp_error( $ret ) ) {
						throw new Exception(
							$ret->get_error_code() . ' ' . $ret->get_error_message()
						);
					}

					$f_path = $tmp . basename( $filename );
					$data   = $fs->get_contents( $f_path );

					if ( false === $data ) {
						throw new Exception(
							sprintf( __( 'Unable to read <code>%s</code>. Please check the permission.', 'ip-location-block' ), $f_path )
						);
					}

					if ( false === $fs->put_contents( $filename, $data ) ) {
						throw new Exception(
							sprintf( __( 'Unable to write <code>%s</code>. Please check the permission.', 'ip-location-block' ), $filename )
						);
					}
				} else {
					throw new Exception( __( 'gz or zip is not supported on your system.', 'ip-location-block' ) );
				}
			}
		} catch ( Exception $e ) {
			$err = array(
				'code'    => $e->getCode(),
				'message' => $e->getMessage(),
			);
		}

		if ( ! empty( $gz ) ) {
			gzclose( $gz );
		}

		if ( ! empty( $tmp ) && $fs->exists( $tmp ) ) {
			$fs->delete( $tmp, true );
		}

		return empty( $err ) ? array(
			'code'     => $code,
			'message'  => sprintf( __( 'Last update: %s', 'ip-location-block' ), IP_Location_Block_Util::localdate( $modified ) ),
			'filename' => $filename,
			'modified' => $modified,
		) : $err;

	}

	/**
	 * Return temporary directory
	 *
	 * @param null $subdir
	 *
	 * @return string|WP_Error|null
	 */
	public static function get_temp_dir( $subdir = null ) {
		$ds  = DIRECTORY_SEPARATOR;
		$dir = \get_temp_dir();
		$dir = apply_filters( 'ip-location-block-temp-dir', $dir, $subdir );
		if ( ! file_exists( $dir ) || ! is_writable( $dir ) ) {
			$uploads = wp_upload_dir();
			$tmpdir  = self::slashit( $uploads['basedir'] ) . 'ip-location-block' . $ds . 'tmp' . $ds;
		} else {
			$tmpdir = self::slashit( $dir );
		}
		if ( null !== $subdir ) {
			$tmpdir .= ltrim( rtrim( str_replace( '/', $ds, $subdir ), $ds ), $ds ) . $ds;
		}
		if ( ! file_exists( $tmpdir ) ) {
			if ( ! wp_mkdir_p( $tmpdir ) ) {
				return new WP_Error( 403, sprintf( 'Temporary directory %s not writable.', $tmpdir ) );
			}
		}

		return $tmpdir;
	}

	/**
	 * Decompresses gz archive and output to the file.
	 *
	 * @param string $src full path to the downloaded file.
	 * @param string $dst full path to extracted file.
	 *
	 * @return array|bool
	 */
	private static function gzfile( $src, $dst ) {
		try {
			if ( false === ( $gz = gzopen( $src, 'r' ) ) ) {
				throw new Exception(
					sprintf( __( 'Unable to read <code>%s</code>. Please check the permission.', 'ip-location-block' ), $src )
				);
			}

			if ( false === ( $fp = @fopen( $dst, 'cb' ) ) ) {
				throw new Exception(
					sprintf( __( 'Unable to write <code>%s</code>. Please check the permission.', 'ip-location-block' ), $dst )
				);
			}

			if ( ! flock( $fp, LOCK_EX ) ) {
				throw new Exception(
					sprintf( __( 'Can\'t lock <code>%s</code>. Please try again after a while.', 'ip-location-block' ), $dst )
				);
			}

			ftruncate( $fp, 0 ); // truncate file

			// same block size in wp-includes/class-http.php
			while ( $data = gzread( $gz, 4096 ) ) {
				fwrite( $fp, $data, strlen( $data ) );
			}
		} catch ( Exception $e ) {
			$err = array(
				'code'    => $e->getCode(),
				'message' => $e->getMessage(),
			);
		}

		if ( ! empty( $fp ) ) {
			fflush( $fp );          // flush output before releasing the lock
			flock( $fp, LOCK_UN ); // release the lock
			fclose( $fp );
		}

		return empty( $err ) ? true : $err;
	}

	/**
	 * Simple comparison of urls
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return bool
	 */
	public static function compare_url( $a, $b ) {
		if ( 'GET' !== $_SERVER['REQUEST_METHOD'] && 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		} // POST, PUT, DELETE

		if ( ! ( $a = @parse_url( $a ) ) ) {
			return false;
		}
		if ( ! ( $b = @parse_url( $b ) ) ) {
			return false;
		}

		// leave scheme to site configuration because is_ssl() doesn’t work behind some load balancers.
		unset( $a['scheme'] );
		unset( $b['scheme'] );

		// $_SERVER['HTTP_HOST'] can't be available in case of malicious url.
		$key = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		if ( empty( $a['host'] ) ) {
			$a['host'] = $key;
		}
		if ( empty( $b['host'] ) ) {
			$b['host'] = $key;
		}

		$key = array_diff( $a, $b );

		return empty( $key );
	}

	/**
	 * Explod with multiple delimiter.
	 *
	 * @param $delimiters
	 * @param $string
	 *
	 * @return array|false|string[]
	 */
	public static function multiexplode( $delimiters, $string ) {
		return is_array( $string ) ? $string : array_filter( explode( $delimiters[0], str_replace( $delimiters, $delimiters[0], $string ) ) );
	}

	/**
	 * HTML/XHTML filter that only allows some elements and attributes
	 *
	 * @param $str
	 * @param bool $allow_tags
	 *
	 * @return string
	 * @see wp-includes/kses.php
	 */
	public static function kses( $str, $allow_tags = true ) {
		is_array( $allow_tags ) or $allow_tags = ( $allow_tags ? $GLOBALS['allowedtags'] : array() );

		// wp_kses() is unavailable on advanced-cache.php
		return wp_kses( $str, $allow_tags );
	}

	/**
	 * Retrieve nonce from queries or referrer
	 *
	 * @param $key
	 *
	 * @return string|string[]|null
	 */
	public static function retrieve_nonce( $key ) {
		if ( isset( $_REQUEST[ $key ] ) ) {
			return preg_replace( '/[^\w]/', '', $_REQUEST[ $key ] );
		}

		if ( preg_match( "/$key(?:=|%3D)([\w]+)/", self::get_referer(), $matches ) ) {
			return preg_replace( '/[^\w]/', '', $matches[1] );
		}

		return null;
	}

	/**
	 * Trace nonce
	 *
	 * @param $nonce
	 */
	public static function trace_nonce( $nonce ) {
		if ( self::is_user_logged_in() && empty( $_REQUEST[ $nonce ] ) &&
		     self::retrieve_nonce( $nonce ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
			// add nonce at add_admin_nonce() to handle the client side redirection.
			self::redirect( esc_url_raw( $_SERVER['REQUEST_URI'] ), 302 );
			exit;
		}
	}

	/**
	 * Retrieve or remove nonce and rebuild query strings.
	 *
	 * @param $location
	 * @param bool $retrieve
	 *
	 * @return string
	 */
	public static function rebuild_nonce( $location, $retrieve = true ) {
		// check if the location is internal
		$url = parse_url( $location );
		$key = IP_Location_Block::get_auth_key();

		if ( empty( $url['host'] ) || $url['host'] === parse_url( home_url(), PHP_URL_HOST ) ) {
			if ( $retrieve ) {
				// it doesn't care a nonce is valid or not, but must be sanitized
				if ( $nonce = self::retrieve_nonce( $key ) ) {
					return esc_url_raw( add_query_arg(
						array(
							$key => false, // delete onece
							$key => $nonce // add again
						),
						$location
					) );
				}
			} else {
				// remove a nonce from existing query
				$location = esc_url_raw( add_query_arg( $key, false, $location ) );
				wp_parse_str( isset( $url['query'] ) ? $url['query'] : '', $query );
				$args = array();
				foreach ( $query as $arg => $val ) { // $val is url decoded
					if ( false !== strpos( $val, $key ) ) {
						$val = urlencode( add_query_arg( $key, false, $val ) );
					}
					$args[] = "$arg=$val";
				}
				$url['query'] = implode( '&', $args );

				return self::unparse_url( $url );
			}
		}

		return $location;
	}

	/**
	 * Convert back to string from a parsed url.
	 *
	 * @source https://php.net/manual/en/function.parse-url.php#106731
	 * @param $url
	 *
	 * @return string
	 */
	private static function unparse_url( $url ) {
		$scheme   = ! empty( $url['scheme'] ) ? $url['scheme'] . '://' : '';
		$host     = ! empty( $url['host'] ) ? $url['host'] : '';
		$port     = ! empty( $url['port'] ) ? ':' . $url['port'] : '';
		$user     = ! empty( $url['user'] ) ? $url['user'] : '';
		$pass     = ! empty( $url['pass'] ) ? ':' . $url['pass'] : '';
		$pass     = ( $user || $pass ) ? $pass . '@' : '';
		$path     = ! empty( $url['path'] ) ? $url['path'] : '';
		$query    = ! empty( $url['query'] ) ? '?' . $url['query'] : '';
		$fragment = ! empty( $url['fragment'] ) ? '#' . $url['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}

	/**
	 * WP alternative function of wp_create_nonce() for mu-plugins
	 *
	 * Creates a cryptographic tied to the action, user, session, and time.
	 * @source wp-includes/pluggable.php
	 *
	 * @param int $action
	 *
	 * @return false|string
	 */
	public static function create_nonce( $action = - 1 ) {
		$uid = self::get_current_user_id();
		$tok = self::get_session_token();
		$exp = self::nonce_tick();

		return substr( self::hash_nonce( $exp . '|' . $action . '|' . $uid . '|' . $tok ), - 12, 10 );
	}

	/**
	 * WP alternative function of wp_verify_nonce() for mu-plugins
	 *
	 * Verify that correct nonce was used with time limit.
	 * @source wp-includes/pluggable.php
	 *
	 * @param $nonce
	 * @param int $action
	 *
	 * @return false|int
	 */
	public static function verify_nonce( $nonce, $action = - 1 ) {
		$uid = self::get_current_user_id();
		$tok = self::get_session_token();
		$exp = self::nonce_tick();

		// Nonce generated 0-12 hours ago
		$expected = substr( self::hash_nonce( $exp . '|' . $action . '|' . $uid . '|' . $tok ), - 12, 10 );
		if ( self::hash_equals( $expected, (string) $nonce ) ) {
			return 1;
		}

		// Nonce generated 12-24 hours ago
		$expected = substr( self::hash_nonce( ( $exp - 1 ) . '|' . $action . '|' . $uid . '|' . $tok ), - 12, 10 );
		if ( self::hash_equals( $expected, (string) $nonce ) ) {
			return 2;
		}

		// Invalid nonce
		return false;
	}

	/**
	 * WP alternative function of wp_hash() for mu-plugins
	 *
	 * Get hash of given string for nonce.
	 * @source wp-includes/pluggable.php
	 *
	 * @param $data
	 * @param string $scheme
	 *
	 * @return false|mixed|string
	 */
	private static function hash_nonce( $data, $scheme = 'nonce' ) {
		$salt = array(
			'auth'        => AUTH_KEY . AUTH_SALT,
			'secure_auth' => SECURE_AUTH_KEY . SECURE_AUTH_SALT,
			'logged_in'   => LOGGED_IN_KEY . LOGGED_IN_SALT,
			'nonce'       => NONCE_KEY . NONCE_SALT,
		);

		return self::hash_hmac( 'md5', $data, apply_filters( 'salt', $salt[ $scheme ], $scheme ) );
	}

	/**
	 * WP alternative function for mu-plugins
	 *
	 * Retrieve the current session token from the logged_in cookie.
	 * @source wp-includes/user.php
	 */
	private static function get_session_token() {
		// Arrogating logged_in cookie never cause the privilege escalation.
		$cookie = self::parse_auth_cookie( 'logged_in' );

		return ! empty( $cookie['token'] ) ? $cookie['token'] : NONCE_KEY . NONCE_SALT;
	}

	/**
	 * WP alternative function for mu-plugins
	 *
	 * Parse a cookie into its components. It assumes the key including $scheme.
	 * @source wp-includes/pluggable.php
	 *
	 * @param string $scheme
	 *
	 * @return array|false|null
	 */
	private static function parse_auth_cookie( $scheme = 'logged_in' ) {
		static $cache_cookie = null;

		if ( null === $cache_cookie ) {
			$cache_cookie = false;

			// @since 3.0.0 wp_cookie_constants() in wp-includes/default-constants.php
			if ( ! defined( 'COOKIEHASH' ) ) {
				wp_cookie_constants();
			}

			switch ( $scheme ) {
				case 'auth':
					$cookie_name = AUTH_COOKIE;
					break;

				case 'secure_auth':
					$cookie_name = SECURE_AUTH_COOKIE;
					break;

				case "logged_in":
					$cookie_name = LOGGED_IN_COOKIE;
					break;

				default:
					if ( is_ssl() ) {
						$cookie_name = SECURE_AUTH_COOKIE;
						$scheme      = 'secure_auth';
					} else {
						$cookie_name = AUTH_COOKIE;
						$scheme      = 'auth';
					}
			}

			if ( empty( $_COOKIE[ $cookie_name ] ) ) {
				return false;
			}

			$cookie = $_COOKIE[ $cookie_name ];
			$n      = count( $cookie_elements = explode( '|', $cookie ) );

			if ( 4 === $n ) { // @since 4.0.0
				list( $username, $expiration, $token, $hmac ) = $cookie_elements;
				$cache_cookie = compact( 'username', 'expiration', 'token', 'hmac', 'scheme' );
			} elseif ( 3 === $n ) { // @before 4.0.0
				list( $username, $expiration, $hmac ) = $cookie_elements;
				$cache_cookie = compact( 'username', 'expiration', 'hmac', 'scheme' );
			} else {
				return false;
			}
		}

		return $cache_cookie;
	}

	/**
	 * WP alternative function for mu-plugins
	 *
	 * Retrieve user info by a given field
	 * @source wp-includes/pluggable.php @since  0.2.8.0
	 *
	 * @param $field
	 * @param $value
	 *
	 * @return false|WP_User
	 */
	public static function get_user_by( $field, $value ) {
		if ( function_exists( 'get_user_by' ) ) {
			return get_user_by( $field, $value );
		}

		$userdata = WP_User::get_data_by( $field, $value ); // wp-includes/class-wp-user.php @since 3.3.0

		if ( ! $userdata ) {
			return false;
		}

		$user = new WP_User;
		$user->init( $userdata );

		return $user;
	}

	/**
	 * WP alternative function for mu-plugins
	 *
	 * Validates authentication cookie.
	 * @source wp-includes/pluggable.php
	 *
	 * @param string $scheme
	 *
	 * @return false|WP_User|null
	 */
	private static function validate_auth_cookie( $scheme = 'logged_in' ) {
		static $cache_user = null;

		if ( null === $cache_user ) {
			if ( ! ( $cookie = self::parse_auth_cookie( $scheme ) ) ) {
				return $cache_user = false;
			}

			$scheme   = $cookie['scheme'];
			$username = $cookie['username'];
			$hmac     = $cookie['hmac'];
			$token    = isset( $cookie['token'] ) ? $cookie['token'] : null;
			$expired  = $expiration = $cookie['expiration'];

			// Allow a grace period for POST and Ajax requests
			if ( defined( 'DOING_AJAX' ) || 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				$expired += HOUR_IN_SECONDS;
			}

			// Quick check to see if an honest cookie has expired
			if ( $expired < time() ) {
				return $cache_user = false;
			}

			if ( ! ( $cache_user = self::get_user_by( 'login', $username ) ) ) // wp-includes/pluggable.php @since  0.2.8.0
			{
				return $cache_user = false;
			}

			$pass_frag = substr( $cache_user->user_pass, 8, 4 );

			if ( is_null( $token ) ) { // @before 4.0.0
				$key  = self::hash_nonce( $username . $pass_frag . '|' . $expiration, $scheme );
				$hash = hash_hmac( 'md5', $username . '|' . $expiration, $key );
			} else { // @since 4.0.0
				// If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
				$key  = self::hash_nonce( $username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme );
				$algo = function_exists( 'hash' ) ? 'sha256' : 'sha1';
				$hash = self::hash_hmac( $algo, $username . '|' . $expiration . '|' . $token, $key );
			}

			if ( ! self::hash_equals( $hash, $hmac ) ) {
				return $cache_user = false;
			}

			if ( class_exists( 'WP_Session_Tokens', false ) ) { // @since 4.0.0
				$manager = WP_Session_Tokens::get_instance( $cache_user->ID );
				if ( ! $manager->verify( $token ) ) {
					return $cache_user = false;
				}
			}
		}

		return $cache_user;
	}

	/**
	 * WP alternative function for mu-plugins
	 *
	 * Get the time-dependent variable for nonce creation.
	 * @source wp_nonce_tick() in wp-includes/pluggable.php
	 */
	private static function nonce_tick() {
		return ceil( time() / ( apply_filters( 'nonce_life', DAY_IN_SECONDS ) / 2 ) );
	}

	/**
	 * WP alternative function of hash_equals() for mu-plugins
	 *
	 * Timing attack safe string comparison.
	 * @source https://php.net/manual/en/function.hash-equals.php#115635
	 * @see https://php.net/manual/en/language.operators.increment.php
	 * @see wp-includes/compat.php
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return bool
	 */
	private static function hash_equals( $a, $b ) {
		// PHP 5 >= 5.6.0 or wp-includes/compat.php
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $a, $b );
		}

		if ( ( $i = strlen( $a ) ) !== strlen( $b ) ) {
			return false;
		}

		$exp = $a ^ $b; // length of both $a and $b are same
		$ret = 0;

		while ( -- $i >= 0 ) {
			$ret |= ord( $exp[ $i ] );
		}

		return ! $ret;
	}

	/**
	 * WP alternative function of hash_hmac() for mu-plugins
	 *
	 * Generate a keyed hash value using the HMAC method.
	 * @source https://php.net/manual/en/function.hash-hmac.php#93440
	 *
	 * @param $algo
	 * @param $data
	 * @param $key
	 * @param bool $raw_output
	 *
	 * @return false|mixed|string
	 */
	public static function hash_hmac( $algo, $data, $key, $raw_output = false ) {
		// PHP 5 >= 5.1.2, PECL hash >= 1.1 or wp-includes/compat.php
		if ( function_exists( 'hash_hmac' ) ) {
			return hash_hmac( $algo, $data, $key, $raw_output );
		}

		$packs = array( 'md5' => 'H32', 'sha1' => 'H40' );

		if ( ! isset( $packs[ $algo ] ) ) {
			return false;
		}

		$pack = $packs[ $algo ];

		if ( strlen( $key ) > 64 ) {
			$key = pack( $pack, $algo( $key ) );
		}

		$key = str_pad( $key, 64, chr( 0 ) );

		$ipad = ( substr( $key, 0, 64 ) ^ str_repeat( chr( 0x36 ), 64 ) );
		$opad = ( substr( $key, 0, 64 ) ^ str_repeat( chr( 0x5C ), 64 ) );

		$hmac = $algo( $opad . pack( $pack, $algo( $ipad . $data ) ) );

		return $raw_output ? pack( $pack, $hmac ) : $hmac;
	}

	/**
	 * WP alternative function of wp_sanitize_redirect() for mu-plugins
	 *
	 * Sanitizes a URL for use in a redirect.
	 * @source wp-includes/pluggable.php
	 *
	 * @param $matches
	 *
	 * @return string
	 */
	private static function sanitize_utf8_in_redirect( $matches ) {
		return urlencode( $matches[0] );
	}

	/**
	 * Sanitize redirect
	 *
	 * @param $location
	 *
	 * @return string|string[]
	 */
	private static function sanitize_redirect( $location ) {
		$regex    = '/
			(
				(?: [\xC2-\xDF][\x80-\xBF]        # double-byte sequences   110xxxxx 10xxxxxx
				|   \xE0[\xA0-\xBF][\x80-\xBF]    # triple-byte sequences   1110xxxx 10xxxxxx * 2
				|   [\xE1-\xEC][\x80-\xBF]{2}
				|   \xED[\x80-\x9F][\x80-\xBF]
				|   [\xEE-\xEF][\x80-\xBF]{2}
				|   \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
				|   [\xF1-\xF3][\x80-\xBF]{3}
				|   \xF4[\x80-\x8F][\x80-\xBF]{2}
			){1,40}                               # ...one or more times
			)/x';
		$location = preg_replace_callback( $regex, array( __CLASS__, 'sanitize_utf8_in_redirect' ), $location );
		$location = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:%!*\[\]()@]|i', '', $location );
		$location = self::kses_no_null( $location ); // wp-includes/kses.php

		// remove %0d and %0a from location
		$strip = array( '%0d', '%0a', '%0D', '%0A' );

		return self::deep_replace( $strip, $location ); // wp-includes/formatting.php
	}

	/**
	 * WP alternative function of wp_redirect() for mu-plugins
	 *
	 * Redirects to another page.
	 * @source wp-includes/pluggable.php
	 *
	 * @param $location
	 * @param int $status
	 *
	 * @return bool
	 */
	public static function redirect( $location, $status = 302 ) {
		// retrieve nonce from referer and add it to the location
		$location = self::rebuild_nonce( $location, true );
		$location = self::sanitize_redirect( $location );

		if ( $location ) {
			if ( ! self::is_IIS() && PHP_SAPI != 'cgi-fcgi' ) {
				status_header( $status );
			} // This causes problems on IIS and some FastCGI setups

			header( "Location: $location", true, $status );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * WP alternative function of wp_redirect() for mu-plugins
	 *
	 * Performs a safe (local) redirect, using redirect().
	 * @source wp-includes/pluggable.php
	 *
	 * @param $location
	 * @param int $status
	 */
	public static function safe_redirect( $location, $status = 302 ) {
		// Need to look at the URL the way it will end up in wp_redirect()
		$location = self::sanitize_redirect( $location );

		// Filters the redirect fallback URL for when the provided redirect is not safe (local).
		$location = self::validate_redirect( $location, apply_filters( 'wp_safe_redirect_fallback', admin_url(), $status ) );

		self::redirect( $location, $status );
	}

	/**
	 * WP alternative function of wp_validate_redirect() for mu-plugins
	 *
	 * Validates a URL for use in a redirect.
	 * @source wp-includes/pluggable.php
	 *
	 * @param $location
	 * @param string $default
	 *
	 * @return mixed|string
	 */
	private static function validate_redirect( $location, $default = '' ) {
		// browsers will assume 'http' is your protocol, and will obey a redirect to a URL starting with '//'
		if ( substr( $location = trim( $location ), 0, 2 ) == '//' ) {
			$location = 'http:' . $location;
		}

		// In php 5 parse_url may fail if the URL query part contains http://, bug #38143
		$test = ( $cut = strpos( $location, '?' ) ) ? substr( $location, 0, $cut ) : $location;

		// @-operator is used to prevent possible warnings in PHP < 5.3.3.
		$lp = @parse_url( $test );

		// Give up if malformed URL
		if ( false === $lp ) {
			return $default;
		}

		// Allow only http and https schemes. No data:, etc.
		if ( isset( $lp['scheme'] ) && ! ( 'http' == $lp['scheme'] || 'https' == $lp['scheme'] ) ) {
			return $default;
		}

		// Reject if certain components are set but host is not. This catches urls like https:host.com for which parse_url does not set the host field.
		if ( ! isset( $lp['host'] ) && ( isset( $lp['scheme'] ) || isset( $lp['user'] ) || isset( $lp['pass'] ) || isset( $lp['port'] ) ) ) {
			return $default;
		}

		// Reject malformed components parse_url() can return on odd inputs.
		foreach ( array( 'user', 'pass', 'host' ) as $component ) {
			if ( isset( $lp[ $component ] ) && strpbrk( $lp[ $component ], ':/?#@' ) ) {
				return $default;
			}
		}

		return $location;
	}

	/**
	 * WP alternative function of wp_get_raw_referer() for mu-plugins
	 *
	 * Retrieves unvalidated referer from '_wp_http_referer' or HTTP referer.
	 * @source wp-includes/functions.php
	 * @uses wp_unslash() can be replaced with stripslashes() in this context because the target value is 'string'.
	 */
	private static function get_raw_referer() {
		if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
			return /*wp_unslash*/ stripslashes( $_REQUEST['_wp_http_referer'] );
		} // wp-includes/formatting.php

		elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			return /*wp_unslash*/ stripslashes( $_SERVER['HTTP_REFERER'] );
		} // wp-includes/formatting.php

		return false;
	}

	/**
	 * WP alternative function of wp_get_referer() for mu-plugins
	 *
	 * Retrieve referer from '_wp_http_referer' or HTTP referer.
	 * @source wp-includes/functions.php
	 */
	public static function get_referer() {
		$ref = self::get_raw_referer(); // wp-includes/functions.php
		$req = /*wp_unslash*/
			stripslashes( $_SERVER['REQUEST_URI'] );

		if ( $ref && $ref !== $req && $ref !== home_url() . $req ) {
			return self::validate_redirect( $ref, false );
		}

		return false;
	}

	/**
	 * WP alternative function of is_user_logged_in() for mu-plugins
	 *
	 * Checks if the current visitor is a logged in user.
	 * @source wp-includes/pluggable.php
	 */
	public static function is_user_logged_in() {
		static $logged_in = null;

		if ( null === $logged_in ) {
			if ( did_action( 'init' ) ) {
				$logged_in = is_user_logged_in(); // @since  0.2.0.0
			} else {
				$settings   = IP_Location_block::get_option();
				$timing_off = isset( $settings['validation']['timing'] ) ? ( 0 === ( (int) $settings['validation']['timing'] ) ) : 0;
				if ( $timing_off ) {
					$logged_in = function_exists( 'is_user_logged_in' ) && is_user_logged_in(); // @since  0.2.0.0
				} else {
					$user      = self::validate_auth_cookie();
					$logged_in = $user ? $user->exists() : false; // @since 3.4.0
				}
			}
		}


		return $logged_in;
	}

	/**
	 * WP alternative function of get_current_user_id() for mu-plugins
	 *
	 * Get the current user's ID.
	 * @source wp-includes/user.php
	 */
	public static function get_current_user_id() {
		static $user_id = null;

		if ( null === $user_id ) {
			if ( did_action( 'init' ) ) {
				$user_id = get_current_user_id(); // @since MU 3.0.0
			} else {
				$user    = self::validate_auth_cookie();
				$user_id = $user ? $user->ID : 0; // @since  0.2.0.0
			}
		}

		return $user_id;
	}

	/**
	 * WP alternative function current_user_can() for mu-plugins
	 *
	 * Whether the current user has a specific capability.
	 * @source wp-includes/capabilities.php
	 *
	 * @param $capability
	 *
	 * @return bool
	 */
	public static function current_user_can( $capability ) {
		if ( did_action( 'init' ) ) {
			return current_user_can( $capability );
		} // @since  0.2.0.0

		return ( $user = self::validate_auth_cookie() ) ? $user->has_cap( $capability ) : false; // @since  0.2.0.0
	}

	/**
	 * Check if the current user has the capabilities.
	 *
	 * @param $caps
	 *
	 * @return bool
	 */
	public static function current_user_has_caps( $caps ) {
		$user = self::get_user_by( 'id', self::get_current_user_id() );
		if ( is_object( $user ) ) {
			foreach ( $caps as $cap ) {
				if ( $user->has_cap( $cap ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * WP alternative function get_allowed_mime_types() for mu-plugins
	 *
	 * Retrieve the file type from the file name.
	 * @source wp-includes/functions.php @since  0.2.0.4
	 *
	 * @param null $user
	 *
	 * @return mixed|void
	 */
	public static function get_allowed_mime_types( $user = null ) {
		$type = wp_get_mime_types();

		unset( $type['swf'], $type['exe'] );
		if ( ! self::current_user_can( 'unfiltered_html' ) ) {
			unset( $type['htm|html'] );
		}

		return apply_filters( 'upload_mimes', $type, $user );
	}

	/**
	 * WP alternative function wp_check_filetype_and_ext() for mu-plugins
	 *
	 * Attempt to determine the real file type of a file.
	 * @source wp-includes/functions.php @since 3.0.0
	 *
	 * @param $fileset
	 * @param $mode
	 * @param $mimeset
	 *
	 * @return bool
	 */
	public static function check_filetype_and_ext( $fileset, $mode, $mimeset ) {
		$src = @$fileset['tmp_name'];
		$dst = str_replace( "\0", '', urldecode( @$fileset['name'] ) );

		// We can't do any further validation without a file to work with
		if ( ! @file_exists( $src ) ) {
			return true;
		}

		// check extension at the tail in blacklist
		if ( 2 === (int) $mode ) {
			$type = pathinfo( $dst, PATHINFO_EXTENSION );
			if ( $type && false !== stripos( $mimeset['black_list'], $type ) ) {
				return false;
			}
		}

		// check extension at the tail in whitelist
		$type = wp_check_filetype( $dst, $mimeset['white_list'] );
		if ( 1 === (int) $mode ) {
			if ( ! $type['type'] ) {
				return false;
			}
		}

		// check images using GD (it doesn't care about extension if it's a real image file)
		if ( 0 === strpos( $type['type'], 'image/' ) && function_exists( 'getimagesize' ) ) {
			$info = @getimagesize( $src ); // 0:width, 1:height, 2:type, 3:string
			if ( ! $info || $info[0] > 9000 || $info[1] > 9000 ) { // max: EOS 5Ds
				return false;
			}
		}

		return true;
	}

	/**
	 * Arrange $_FILES array
	 *
	 * @see https://php.net/manual/features.file-upload.multiple.php#53240
	 *
	 * @param $files
	 *
	 * @return array
	 */
	public static function arrange_files( $files ) {
		if ( ! is_array( $files['name'] ) ) {
			return array( $files );
		}

		$file_array = array();
		$file_count = count( $files['name'] );
		$file_keys  = array_keys( $files );

		for ( $i = 0; $i < $file_count; ++ $i ) {
			foreach ( $file_keys as $key ) {
				$file_array[ $i ][ $key ] = $files[ $key ][ $i ];
			}
		}

		return $file_array;
	}

	/**
	 *
	 * Remove slash at the end of string.
	 * @source wp-includes/formatting.php
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public static function unslashit( $string ) {
		if ( ! is_string( $string ) ) {
			return $string;
		}

		return rtrim( $string, '/\\' );
	}

	/**
	 * Add slash at the end of string.
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public static function slashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}

	/**
	 * WP alternative function of wp_kses_no_null() for advanced-cache.php
	 *
	 * Removes any NULL characters in $string.
	 * @source wp-includes/kses.php
	 *
	 * @param $string
	 *
	 * @return string|string[]|null
	 */
	private static function kses_no_null( $string ) {
		$string = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string );
		$string = preg_replace( '/\\\\+0+/', '', $string );

		return $string;
	}

	/**
	 * WP alternative function of _deep_replace() for advanced-cache.php
	 *
	 * Perform a deep string replace operation to ensure the values in $search are no longer present.
	 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that str_replace would return
	 * @source wp-includes/formatting.php
	 *
	 * @param $search
	 * @param $subject
	 *
	 * @return string|string[]
	 */
	private static function deep_replace( $search, $subject ) {
		$subject = (string) $subject;

		$count = 1;
		while ( $count ) {
			$subject = str_replace( $search, '', $subject, $count );
		}

		return $subject;
	}

	/**
	 * Remove `HOST` and `HOST=...` from `UA and qualification`
	 *
	 * @param $ua_list
	 *
	 * @return string|string[]|null
	 */
	public static function mask_qualification( $ua_list ) {
		return preg_replace( array( '/HOST[^,]*?/', '/\*[:#]!?\*,?/' ), array( '*', '' ), $ua_list );
	}

	/**
	 * https://codex.wordpress.org/WordPress_Feeds
	 * @param $request_uri
	 *
	 * @return bool
	 */
	public static function is_feed( $request_uri ) {
		return /* function_exists( 'is_feed' ) ? is_feed() : */ ( isset( $_GET['feed'] ) ?
			( preg_match( '!(?:comments-)?(?:feed|rss|rss2|rdf|atom)$!', $_GET['feed'] ) ? true : false ) :
			( preg_match( '!(?:comments/)?(?:feed|rss|rss2|rdf|atom)/?$!', $request_uri ) ? true : false )
		);
	}

	/**
	 * Whether the server software is IIS or something else
	 *
	 * @source wp-includes/vers.php
	 */
	private static function is_IIS() {
		$_is_apache = ( strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache' ) !== false || strpos( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) !== false );
		$_is_IIS    = ! $_is_apache && ( strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) !== false || strpos( $_SERVER['SERVER_SOFTWARE'], 'ExpressionDevServer' ) !== false );

		return $_is_IIS ? substr( $_SERVER['SERVER_SOFTWARE'], strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS/' ) + 14 ) : false;
	}

	/**
	 * Check proxy variable
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_community_events/get_unsafe_client_ip/
	 */
	public static function get_proxy_var() {
		foreach (
			array(
				'HTTP_X_FORWARDED_FOR',
				'HTTP_CF_CONNECTING_IP',
				'HTTP_X_REAL_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED'
			) as $var
		) {
			if ( isset( $_SERVER[ $var ] ) ) {
				return $var;
			}
		}

		return null;
	}

	/**
	 * Pick up all the IPs in HTTP_X_FORWARDED_FOR, HTTP_CLIENT_IP and etc.
	 *
	 * @param array $ips array of candidate IP addresses
	 * @param string $vars comma separated keys in $_SERVER for http header ('HTTP_...')
	 *
	 * @return array  $ips  array of candidate IP addresses
	 */
	public static function retrieve_ips( $ips = array(), $vars = null ) {
		foreach ( explode( ',', $vars ) as $var ) {
			if ( isset( $_SERVER[ $var ] ) ) {
				foreach ( explode( ',', $_SERVER[ $var ] ) as $ip ) {
					if ( ! in_array( $ip = trim( $ip ), $ips, true ) && ! self::is_private_ip( $ip ) ) {
						array_unshift( $ips, $ip );
					}
				}
			}
		}

		return $ips;
	}

	/**
	 * Get client IP address
	 *
	 * @param string $vars comma separated keys in $_SERVER for http header ('HTTP_...')
	 *
	 * @return string $ip   IP address
	 * @link   https://docs.aws.amazon.com/elasticloadbalancing/latest/classic/x-forwarded-headers.html
	 * @link   https://github.com/zendframework/zend-http/blob/master/src/PhpEnvironment/RemoteAddress.php
	 */
	public static function get_client_ip( $vars = null ) {
		foreach ( explode( ',', $vars ) as $var ) {
			if ( isset( $_SERVER[ $var ] ) ) {
				$ips = array_map( 'trim', explode( ',', $_SERVER[ $var ] ) );
				while ( $var = array_pop( $ips ) ) {
					if ( ! self::is_private_ip( $var ) ) {
						return $var;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1'; // for CLI
	}

	/**
	 * Check the client IP address behind the VPN proxy
	 *
	 */
	public static function get_proxy_ip( $ip ) {
		// Chrome datasaver
		if ( isset( $_SERVER['HTTP_VIA'], $_SERVER['HTTP_FORWARDED'] ) && false !== strpos( $_SERVER['HTTP_VIA'], 'Chrome-Compression-Proxy' ) ) {
			// require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-lkup.php';
			// if ( FALSE !== strpos( 'google', IP_Location_Block_Lkup::gethostbyaddr( $ip ) ) )
			$proxy = preg_replace( '/^for=.*?([a-f\d\.:]+).*$/', '$1', $_SERVER['HTTP_FORWARDED'] );
		} // Puffin browser
		elseif ( isset( $_SERVER['HTTP_X_PUFFIN_UA'], $_SERVER['HTTP_USER_AGENT'] ) && false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'Puffin' ) ) {
			$proxy = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$proxy = trim( end( $proxy ) ); // or trim( $proxy[0] )
		}

		return empty( $proxy ) ? $ip : $proxy;
	}

	/**
	 * Check the IP address is private or not
	 *
	 * @link https://en.wikipedia.org/wiki/Localhost
	 * @link https://en.wikipedia.org/wiki/Private_network
	 * @link https://en.wikipedia.org/wiki/Reserved_IP_addresses
	 *
	 * 10.0.0.0/8 reserved for Private-Use Networks [RFC1918]
	 * 127.0.0.0/8 reserved for Loopback [RFC1122]
	 * 172.16.0.0/12 reserved for Private-Use Networks [RFC1918]
	 * 192.168.0.0/16 reserved for Private-Use Networks [RFC1918]
	 *
	 * @param $ip
	 *
	 * @return bool
	 */
	public static function is_private_ip( $ip ) {
		// https://php.net/manual/en/filter.filters.flags.php
		return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Get IP address of the host server
	 *
	 * @link https://php.net/manual/en/reserved.variables.server.php#88418
	 */
	public static function get_server_ip() {
		return isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : ( (int) self::is_IIS() >= 7 ?
			( isset( $_SERVER['LOCAL_ADDR'] ) ? $_SERVER['LOCAL_ADDR'] : null ) : null );
	}

	/**
	 * Get the list of registered actions
	 *
	 * @param bool $ajax
	 *
	 * @return array
	 */
	public static function get_registered_actions( $ajax = false ) {
		$installed = array();

		global $wp_filter;
		foreach ( $wp_filter as $key => $val ) {
			if ( $ajax && false !== strpos( $key, 'wp_ajax_' ) ) {
				if ( 0 === strpos( $key, 'wp_ajax_nopriv_' ) ) {
					$key = substr( $key, 15 ); // 'wp_ajax_nopriv_'
					$val = 2;                  // without privilege
				} else {
					$key = substr( $key, 8 );  // 'wp_ajax_'
					$val = 1;                  // with privilege
				}
				$installed[ $key ] = isset( $installed[ $key ] ) ? $installed[ $key ] | $val : $val;
			} elseif ( false !== strpos( $key, 'admin_post_' ) ) {
				if ( 0 === strpos( $key, 'admin_post_nopriv_' ) ) {
					$key = substr( $key, 18 ); // 'admin_post_nopriv_'
					$val = 2;                  // without privilege
				} else {
					$key = substr( $key, 11 ); // 'admin_post_'
					$val = 1;                  // with privilege
				}
				$installed[ $key ] = isset( $installed[ $key ] ) ? $installed[ $key ] | $val : $val;
			}
		}

		unset( $installed['ip_location_block'] );

		return $installed;
	}

	/**
	 * Get the list of multisite managed by the specific user
	 *
	 * This function should be called after 'init' hook is fired.
	 */
	public static function get_sites_of_user() {
		$sites = array( preg_replace( '/^https?:/', '', home_url() ) );

		foreach ( get_blogs_of_user( self::get_current_user_id(), current_user_can( 'manage_network_options' ) ) as $site ) { // @since 3.0.0
			if ( ! in_array( $url = preg_replace( '/^https?:/', '', $site->siteurl ), $sites, true ) ) {
				$sites[] = $url;
			}
		}

		return $sites;
	}

	/**
	 * Anonymize IP address in string
	 *
	 * @param $subject
	 * @param bool $strict
	 *
	 * @return string|string[]|null
	 */
	public static function anonymize_ip( $subject, $strict = true ) {
		return $strict ?
			preg_replace( '/(:)*[0-9a-f\*]{0,4}$/', '$1***', $subject, 1 ) :
			preg_replace(
				array(
					'/([0-9a-f]{3,})[0-9a-f]{3,}/',           // loose pattern for IPv[4|6]
					'/((?:[0-9]{1,3}[-_x\.]){3,})[0-9]+/',    // loose pattern for IPv4
					'/((?:[0-9a-f]+[-:]+)+)[0-9a-f:\*]{2,}/', // loose pattern for IPv6
				),
				'$1***',
				$subject
			);
	}

	/**
	 * Generates cryptographically secure pseudo-random bytes
	 *
	 * @param int $length
	 *
	 * @return string|null
	 */
	private static function random_bytes( $length = 64 ) {
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'includes/random_compat/random.php';
		}

		// align length
		$length = max( 64, $length ) - ( $length % 2 );

		try {
			$str = bin2hex( random_bytes( $length / 2 ) );
		} catch ( \Exception $e ) {
			$str = null;
		}

		if ( empty( $str ) && function_exists( 'openssl_random_pseudo_bytes' ) ) {
			$str = bin2hex( openssl_random_pseudo_bytes( $length / 2 ) );
		}

		if ( empty( $str ) ) {
			for ( $i = 0; $i < $length; $i ++ ) {
				$str .= chr( ( mt_rand( 1, 36 ) <= 26 ) ? mt_rand( 97, 122 ) : mt_rand( 48, 57 ) );
			}
		}

		return $str;
	}

	/**
	 * Manipulate emergency login link
	 *
	 * @param $link
	 *
	 * @return false|mixed|string
	 */
	private static function hash_link( $link ) {
		return self::hash_hmac(
			function_exists( 'hash' ) ? 'sha256' /* 32 bytes (256 bits) */ : 'sha1' /* 20 bytes (160 bits) */,
			$link, NONCE_SALT, true
		);
	}

	// used at `admin_ajax_callback()` in class-ip-location-block-admin.php
	public static function generate_link( $context ) {
		$link = self::random_bytes();
		$hash = bin2hex( self::hash_link( $link ) );

		/**
		 * Verify the consistency of `self::hash_hmac()`
		 *   key from external: self::verify_link( $link )
		 *   key from internal: self::verify_link( 'link', 'hash' )
		 */
		$settings               = IP_Location_Block::get_option();
		$settings['login_link'] = array(
			'link' => $hash,
			'hash' => bin2hex( self::hash_link( $hash ) ),
		);

		if ( $context->is_network_admin() && $settings['network_wide'] ) {
			$context->update_multisite_settings( $settings );
		} else {
			IP_Location_Block::update_option( $settings );
		}

		return add_query_arg( IP_Location_Block::PLUGIN_NAME . '-key', $link, wp_login_url() );
	}

	// used at `admin_ajax_callback()` in class-ip-location-block-admin.php
	public static function delete_link( $context ) {
		$settings               = IP_Location_Block::get_option();
		$settings['login_link'] = array( 'link' => null, 'hash' => null );

		if ( $context->is_network_admin() && $settings['network_wide'] ) {
			$context->update_multisite_settings( $settings );
		} else {
			IP_Location_Block::update_option( $settings );
		}
	}

	// used at `tab_setup()` in tab-settings.php
	public static function get_link() {
		$settings = IP_Location_Block::get_option();

		return $settings['login_link']['link'] ? $settings['login_link']['link'] : false;
	}

	// used at `validate_login()` in class-ip-location-block.php
	public static function verify_link( $link, $hash = null ) {
		return self::hash_equals( self::hash_link( $link ), pack( 'H*', $hash ? $hash : self::get_link() ) ); // hex2bin() for PHP 5.4+
	}


	/**
	 * Parses asn in default format.
	 * e.g:
	 * 1. AS81281 Provider Name -> AS81281
	 * 2. 9239 -> AS9239
	 * 3. AS1111 -> AS1111
	 */
	public static function parse_asn( $asn ) {
		$asn = str_replace( 'AS', '', strtok( $asn, ' ' ) );

		return sprintf( 'AS%s', $asn );
	}

}

// Some plugins need this when this plugin is installed as mu-plugins
if ( ! function_exists( 'get_userdata' ) ) :
	/**
	 * Retrieve user info by user ID.
	 *
	 * @param int $user_id User ID
	 *
	 * @return WP_User|false WP_User object on success, false on failure.
	 * @since 0.71
	 *
	 */
	function get_userdata( $user_id ) {
		return IP_Location_Block_Util::get_user_by( 'id', $user_id );
	}
endif;
