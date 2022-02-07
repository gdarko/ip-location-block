<?php
/**
 * IP Location Block API class library for IP2Location
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
 * URL and Path for IP2Location database
 *
 */
define( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT', 'IP2LOCATION-LITE-DB1.BIN' );
define( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT', 'IP2LOCATION-LITE-DB1.IPV6.BIN' );
define( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_ZIP', 'https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.BIN.ZIP' );
define( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_ZIP', 'https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP' );
define( 'IP_LOCATION_BLOCK_IP2LOC_DOWNLOAD', 'https://lite.ip2location.com/database/ip-country' );

/**
 * Class for IP2Location
 *
 * URL         : https://www.ip2location.com/
 * Term of use : https://www.ip2location.com/terms
 * Licence fee : Creative Commons Attribution-ShareAlike 4.0 Unported License
 * Input type  : IP address (IPv4)
 * Output type : array
 */
class IP_Location_Block_API_IP2Location extends IP_Location_Block_API {

	protected $transform_table = array(
		'countryCode' => 'countryCode',
		'countryName' => 'countryName',
		'regionName'  => 'regionName',
		'cityName'    => 'cityName',
		'latitude'    => 'latitude',
		'longitude'   => 'longitude',
	);

	/**
	 * Returns location
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|string[]
	 */
	public function get_location( $ip, $args = array() ) {

		$settings = IP_Location_Block::get_option();

		require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

		// setup database file and function
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$type = IP_LOCATION_BLOCK_API_TYPE_IPV4;
			$file = apply_filters( IP_Location_Block::PLUGIN_NAME . '-ip2location-path', empty( $settings['IP2Location']['ipv4_path'] ) ? $this->get_db_dir() . IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT : $settings['IP2Location']['ipv4_path'] );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$type = IP_LOCATION_BLOCK_API_TYPE_IPV6;
			$file = empty( $settings['IP2Location']['ipv6_path'] ) ? $this->get_db_dir() . IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT : $settings['IP2Location']['ipv6_path'];
		} else {
			return array( 'errorMessage' => 'illegal format' );
		}

		try {
			$geo  = new IP2Location\Database( $file );
			$data = $geo->lookup( $ip );
			$res  = array();
			foreach ( $this->transform_table as $key => $val ) {
				if ( isset( $data[ $val ] ) && IP2Location\Database::FIELD_NOT_SUPPORTED !== $data[ $val ] ) {
					$res[ $key ] = $data[ $val ];
				}
			}
			if ( isset( $res['countryCode'] ) && strlen( $res['countryCode'] ) === 2 ) {
				return $res;
			}
		} catch ( Exception $e ) {
			return array( 'errorMessage' => $e->getMessage() );
		}

		return array( 'errorMessage' => 'Not supported' );
	}

	/**
	 * Return db dir
	 * @return string
	 */
	private function get_db_dir() {
		return apply_filters( IP_Location_Block::PLUGIN_NAME . '-ip2location-dir', IP_Location_Block_Util::get_databases_storage_dir( 'IP2Location' ) );
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

		$db = isset( $this->options[ $this->provider ] ) ? $this->options[ $this->provider ] : array();

		// IPv4
		if ( $dir !== dirname( $db['ipv4_path'] ) . '/' ) {
			$db['ipv4_path'] = $dir . IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT;
		}

		$res['ipv4'] = IP_Location_Block_Util::download_zip(
			apply_filters( IP_Location_Block::PLUGIN_NAME . '-ip2location-zip-ipv4', IP_LOCATION_BLOCK_IP2LOC_IPV4_ZIP ),
			$args,
			$db['ipv4_path'],
			$db['ipv4_last']
		);

		// IPv6
		if ( $dir !== dirname( $db['ipv6_path'] ) . '/' ) {
			$db['ipv6_path'] = $dir . IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT;
		}

		$res['ipv6'] = IP_Location_Block_Util::download_zip(
			apply_filters( IP_Location_Block::PLUGIN_NAME . '-ip2location-zip-ipv6', IP_LOCATION_BLOCK_IP2LOC_IPV6_ZIP ),
			$args,
			$db['ipv6_path'],
			$db['ipv6_last']
		);

		if ( ! empty( $res['ipv4']['filename'] ) ) {
			$db['ipv4_path'] = $res['ipv4']['filename'];
		}
		if ( ! empty( $res['ipv6']['filename'] ) ) {
			$db['ipv6_path'] = $res['ipv6']['filename'];
		}
		if ( ! empty( $res['ipv4']['modified'] ) ) {
			$db['ipv4_last'] = $res['ipv4']['modified'];
		}
		if ( ! empty( $res['ipv6']['modified'] ) ) {
			$db['ipv6_last'] = $res['ipv6']['modified'];
		}

		return $res;
	}

	/**
	 * The attribution
	 *
	 * @return string
	 */
	public function get_attribution() {
		return 'This site or product includes IP2Location LITE data available from <a class="ip-location-block-link" href="https://lite.ip2location.com" rel=noreferrer target=_blank>https://lite.ip2location.com</a>. (<a href="https://creativecommons.org/licenses/by-sa/4.0/" title="Creative Commons &mdash; Attribution-ShareAlike 4.0 International &mdash; CC BY-SA 4.0" rel=noreferrer target=_blank>CC BY-SA 4.0</a>)';
	}

	/**
	 * Add settings field
	 *
	 * @param $field
	 * @param $section
	 * @param $option_slug
	 * @param $option_name
	 * @param $options
	 * @param $callback
	 * @param $str_path
	 * @param $str_last
	 */
	public function add_settings_field( $field, $section, $option_slug, $option_name, $options, $callback, $str_path, $str_last ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$fs = IP_Location_Block_FS::init( __FILE__ . '(' . __FUNCTION__ . ')' );

		$db  = $options[ $field ];
		$dir = $this->get_db_dir();
		$msg = __( 'Database file does not exist.', 'ip-location-block' );

		// IPv4
		if ( $dir !== dirname( $db['ipv4_path'] ) . '/' ) {
			$db['ipv4_path'] = $dir . IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT;
		}

		// filter database file
		$db['ipv4_path'] = apply_filters( IP_Location_Block::PLUGIN_NAME . '-ip2location-path', $db['ipv4_path'] );

		if ( $fs->exists( $db['ipv4_path'] ) ) {
			$date = sprintf( $str_last, IP_Location_Block_Util::localdate( $db['ipv4_last'] ) );
		} else {
			$date = $msg;
		}

		add_settings_field(
			$option_name . $field . '_ipv4',
			"$field $str_path<br />(<a rel='noreferrer' href='" . IP_LOCATION_BLOCK_IP2LOC_DOWNLOAD . "' title='" . IP_LOCATION_BLOCK_IP2LOC_IPV4_ZIP . "'>IPv4</a>)",
			$callback,
			$option_slug,
			$section,
			array(
				'type'      => 'text',
				'option'    => $option_name,
				'field'     => $field,
				'sub-field' => 'ipv4_path',
				'value'     => $db['ipv4_path'],
				'disabled'  => true,
				'after'     => '<br /><p id="ip-location-block-' . $field . '-ipv4" style="margin-left: 0.2em">' . $date . '</p>',
			)
		);

		// IPv6
		if ( $dir !== dirname( $db['ipv6_path'] ) . '/' ) {
			$db['ipv6_path'] = $dir . IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT;
		}

		// filter database file
		$db['ipv6_path'] = apply_filters( IP_Location_Block::PLUGIN_NAME . '-ip2location-path-ipv6', $db['ipv6_path'] );

		if ( $fs->exists( $db['ipv6_path'] ) ) {
			$date = sprintf( $str_last, IP_Location_Block_Util::localdate( $db['ipv6_last'] ) );
		} else {
			$date = $msg;
		}

		add_settings_field(
			$option_name . $field . '_ipv6',
			"$field $str_path<br />(<a rel='noreferrer' href='" . IP_LOCATION_BLOCK_IP2LOC_DOWNLOAD . "' title='" . IP_LOCATION_BLOCK_IP2LOC_IPV6_ZIP . "'>IPv6</a>)",
			$callback,
			$option_slug,
			$section,
			array(
				'type'      => 'text',
				'option'    => $option_name,
				'field'     => $field,
				'sub-field' => 'ipv6_path',
				'value'     => $db['ipv6_path'],
				'disabled'  => true,
				'after'     => '<br /><p id="ip-location-block-' . $field . '-ipv6" style="margin-left: 0.2em">' . $date . '</p>',
			)
		);
	}
}

/**
 * Register API
 *
 */
IP_Location_Block_Provider::register_addon( array(
	'IP2Location' => array(
		'key'      => null,
		'type'     => 'IPv4, IPv6 / LGPLv3',
		'link'     => 'https://lite.ip2location.com/',
		'supports' => array( 'ipv4', 'ipv6' ),
		'limits'   => array( 'System memory' ),
		'requests' => array( 'total' => - 1, 'term' => '' ),
		'api_auth' => IP_Location_Block_Provider::API_AUTH_NOT_REQUIRED,
		'local'    => true,
	),
) );
