<?php
/**
 * IP Location Block API class library for Maxmind
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

class_exists( 'IP_Location_Block_API', false ) or die;

/**
 * URL and Path for Maxmind GeoLite2 database
 *
 * https://www.maxmind.com/en/open-source-data-and-api-for-ip-geolocation
 * https://stackoverflow.com/questions/9416508/php-untar-gz-without-exec
 * https://php.net/manual/phardata.extractto.php
 */
define( 'IP_LOCATION_BLOCK_GEOLITE2_DB_IP', 'GeoLite2-Country.mmdb' );
define( 'IP_LOCATION_BLOCK_GEOLITE2_DB_ASN', 'GeoLite2-ASN.mmdb' );
define( 'IP_LOCATION_BLOCK_GEOLITE2_ZIP_IP', 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz' );
define( 'IP_LOCATION_BLOCK_GEOLITE2_ZIP_ASN', 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-ASN&license_key=%s&suffix=tar.gz' );
define( 'IP_LOCATION_BLOCK_GEOLITE2_DOWNLOAD', 'https://dev.maxmind.com/geoip/geoip2/geolite2/' );

/**
 * Class for Maxmind
 *
 * URL         : https://dev.maxmind.com/geoip/geoip2/
 * Term of use : https://dev.maxmind.com/geoip/geoip2/geolite2/#License
 * Licence fee : Creative Commons Attribution-ShareAlike 4.0 International License
 * Input type  : IP address (IPv4, IPv6)
 * Output type : array
 */
class IP_Location_Block_API_GeoLite2 extends IP_Location_Block_API {

	private function location_country( $record ) {
		return array( 'countryCode' => $record->country->isoCode );
	}

	private function location_city( $record ) {
		return array(
			'countryCode' => $record->country->isoCode,
			'countryName' => $record->country->names['en'],
			'cityName'    => $record->city->names['en'],
			'latitude'    => $record->location->latitude,
			'longitude'   => $record->location->longitude,
		);
	}

	private function location_asnumber( $record ) {
		return array( 'asn' => IP_Location_Block_Util::parse_asn( $record->autonomousSystemNumber ) );
	}

	public function get_location( $ip, $args = array() ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return array( 'errorMessage' => 'illegal format' );
		}

		require_once IP_Location_Block_Util::slashit( dirname( __FILE__ ) ) . 'vendor/autoload.php';

		// setup database file and function
		$settings = IP_Location_Block::get_option();

		if ( empty( $args['asn'] ) ) {
			$file = apply_filters( 'ip-location-block-geolite2-path',
				( ! empty( $settings['GeoLite2']['ip_path'] ) ?
					$settings['GeoLite2']['ip_path'] :
					$this->get_db_dir() . IP_LOCATION_BLOCK_GEOLITE2_DB_IP
				)
			);
			try {
				$reader = new GeoIp2\Database\Reader( $file );
				if ( 'GeoLite2-Country' === $reader->metadata()->databaseType ) {
					$res = $this->location_country( $reader->country( $ip ) );
				} else {
					$res = $this->location_city( $reader->city( $ip ) );
				}
			} catch ( Exception $e ) {
				$res = array( 'countryCode' => null );
			}
		} else {
			$file = ! empty( $settings['GeoLite2']['asn_path'] ) ? $settings['GeoLite2']['asn_path'] : $this->get_db_dir() . IP_LOCATION_BLOCK_GEOLITE2_DB_ASN;
			try {
				$reader = new GeoIp2\Database\Reader( $file );
				$res    = $this->location_asnumber( $reader->asn( $ip ) );
			} catch ( Exception $e ) {
				$res = array( 'asn' => null );
			}
		}

		return $res;
	}

	/**
	 * Return the database dir
	 * @return string
	 */
	private function get_db_dir() {
		return apply_filters( 'ip-location-block-geolite2-dir', IP_Location_Block_Util::get_databases_storage_dir( 'GeoLite2' ) );
	}

	/**
	 * Return download url with api key
	 *
	 * @param $type
	 * @param  string  $key
	 *
	 * @return mixed|void|null
	 */
	private function get_api_url( $type, $key = '' ) {
		$url = null;
		if ( 'country' === $type ) {
			$url = apply_filters( 'ip-location-block-geolite2-zip-ip', sprintf( IP_LOCATION_BLOCK_GEOLITE2_ZIP_IP, $key ) );
		} elseif ( 'asn' === $type ) {
			$url = apply_filters( 'ip-location-block-geolite2-zip-asn', sprintf( IP_LOCATION_BLOCK_GEOLITE2_ZIP_ASN, $key ) );
		}

		return $url;
	}

	/**
	 * Download database
	 *
	 * @param $args
	 *
	 * @return array
	 */
	public function download( $args ) {
		$dir = $this->get_db_dir();
		$db  = isset( $this->options[ $this->provider ] ) ? $this->options[ $this->provider ] : array();

		$ip_path   = isset( $ip_path ) ? $ip_path : '';
		$ip_path_d = ! empty( $ip_path ) ? dirname( $ip_path ) : '';
		$ip_last   = isset( $db['ip_last'] ) ? $db['ip_last'] : '';

		// IPv4 & IPv6
		if ( $dir !== $ip_path_d . '/' ) {
			$ip_path = $dir . IP_LOCATION_BLOCK_GEOLITE2_DB_IP;
		}

		// Set API Key
		$api_key = isset( $this->options['providers'][ $this->provider ] ) ? $this->options['providers'][ $this->provider ] : null;

		// filter database file
		$ip_path   = apply_filters( 'ip-location-block-geolite2-path', $ip_path );
		$res['ip'] = IP_Location_Block_Util::download_zip(
			$this->get_api_url( 'country', $api_key ),
			$args + array( 'method' => 'GET' ),
			array( $ip_path, 'COPYRIGHT.txt', 'LICENSE.txt' ), // 1st parameter should include absolute path
			$ip_last
		);

		if ( ! empty( $res['ip']['filename'] ) ) {
			$db['ip_path'] = $res['ip']['filename'];
		}
		if ( ! empty( $res['ip']['modified'] ) ) {
			$db['ip_last'] = $res['ip']['modified'];
		}

		if ( ! empty( $this->options['use_asn'] ) || ! empty( $db['asn_path'] ) ) {
			// ASN for IPv4 and IPv6
			if ( $dir !== dirname( $db['asn_path'] ) . '/' ) {
				$db['asn_path'] = $dir . IP_LOCATION_BLOCK_GEOLITE2_DB_ASN;
			}
			$res['asn'] = IP_Location_Block_Util::download_zip(
				$this->get_api_url( 'asn', $api_key ),
				$args + array( 'method' => 'GET' ),
				array( $db['asn_path'], 'COPYRIGHT.txt', 'LICENSE.txt' ), // 1st parameter should include absolute path
				$db['asn_last']
			);

			if ( ! empty( $res['asn']['filename'] ) ) {
				$db['asn_path'] = $res['asn']['filename'];
			}
			if ( ! empty( $res['asn']['modified'] ) ) {
				$db['asn_last'] = $res['asn']['modified'];
			}
		}

		return $res;
	}

	public function get_attribution() {
		return 'This product includes GeoLite2 data created by MaxMind, available from <a class="ip-location-block-link" href="https://www.maxmind.com" rel=noreferrer target=_blank>https://www.maxmind.com</a>. (<a href="https://creativecommons.org/licenses/by-sa/4.0/" title="Creative Commons &mdash; Attribution-ShareAlike 4.0 International &mdash; CC BY-SA 4.0" rel=noreferrer target=_blank>CC BY-SA 4.0</a>)';
	}

	public function add_settings_field( $field, $section, $option_slug, $option_name, $options, $callback, $str_path, $str_last ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$fs = IP_Location_Block_FS::init( __FILE__ . '(' . __FUNCTION__ . ')' );

		$db  = $options[ $field ];
		$dir = $this->get_db_dir();
		$msg = __( 'Database file does not exist.', 'ip-location-block' );

		$ip_path   = isset( $ip_path ) ? $ip_path : '';
		$ip_path_d = ! empty( $ip_path ) ? dirname( $ip_path ) : '';
		$ip_last   = isset( $db['ip_last'] ) ? $db['ip_last'] : '';

		// IPv4 & IPv6
		if ( $dir !== $ip_path_d . DIRECTORY_SEPARATOR ) {
			$ip_path = $dir . IP_LOCATION_BLOCK_GEOLITE2_DB_IP;
		}

		// filter database file
		$ip_path = apply_filters( 'ip-location-block-geolite2-path', $ip_path );

		if ( $ip_path && $fs->exists( $ip_path ) ) {
			if ( empty( $ip_last ) ) {
				$ip_last = filemtime( $ip_path );
			}
			$date = sprintf( $str_last, IP_Location_Block_Util::localdate( $ip_last ) );
		} else {
			if ( empty( $options['providers']['GeoLite2'] ) ) {
				$date = __( 'GeoLite2 not configured. Key is missing.', 'ip-location-block' );
			} else {
				$date = $msg;
			}
		}

		add_settings_field(
			$option_name . $field . '_ip',
			"$field $str_path<br />(<a rel='noreferrer' href='" . IP_LOCATION_BLOCK_GEOLITE2_DOWNLOAD . "' title='" . IP_LOCATION_BLOCK_GEOLITE2_ZIP_IP . "'>IPv4 and IPv6</a>)",
			$callback,
			$option_slug,
			$section,
			array(
				'type'      => 'text',
				'option'    => $option_name,
				'field'     => $field,
				'sub-field' => 'ip_path',
				'value'     => $ip_path,
				'disabled'  => true,
				'after'     => '<br /><p id="ip-location-block-' . $field . '-ip" style="margin-left: 0.2em">' . $date . '</p>',
			)
		);

		if ( ! empty( $db['use_asn'] ) || ! empty( $db['asn_path'] ) ) :

			// ASN for IPv4 and IPv6
			if ( $dir !== dirname( $db['asn_path'] ) . '/' ) {
				$db['asn_path'] = $dir . IP_LOCATION_BLOCK_GEOLITE2_DB_ASN;
			}

			if ( $fs->exists( $db['asn_path'] ) ) {
				$date = sprintf( $str_last, IP_Location_Block_Util::localdate( $db['asn_last'] ) );
			} else {
				$date = $msg;
			}

			add_settings_field(
				$option_name . $field . '_asn',
				"$field $str_path<br />(<a rel='noreferrer' href='" . IP_LOCATION_BLOCK_GEOLITE2_DOWNLOAD . "' title='" . IP_LOCATION_BLOCK_GEOLITE2_ZIP_ASN . "'>ASN for IPv4 and IPv6</a>)",
				$callback,
				$option_slug,
				$section,
				array(
					'type'      => 'text',
					'option'    => $option_name,
					'field'     => $field,
					'sub-field' => 'asn_path',
					'value'     => $db['asn_path'],
					'disabled'  => true,
					'after'     => '<br /><p id="ip-location-block-' . $field . '-asn" style="margin-left: 0.2em">' . $date . '</p>',
				)
			);

		endif; // ! empty( $db['use_asn'] ) || ! empty( $db['asn_path'] )

	}
}

/**
 * Register API
 */
IP_Location_Block_Provider::register_addon( array(
	'GeoLite2' => array(
		'key'      => '',
		'type'     => 'IPv4, IPv6 / Apache License, Version 2.0',
		'link'     => 'https://dev.maxmind.com/geoip/geolite2-free-geolocation-data',
		'supports' => array( 'ipv4', 'ipv6', 'asn', 'asn_database' ),
		'limits'   => array( __( 'System memory', 'ip-location-block' ) ),
		'requests' => array( 'total' => - 1, 'term' => '' ),
		'api_auth' => IP_Location_Block_Provider::API_AUTH_REQUIRED,
		'local'    => true,
	),
) );
