<?php
/**
 * IP Location Block - IP Address Geolocation API Class
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

/**
 * Service type
 *
 */
define( 'IP_LOCATION_BLOCK_API_TYPE_IPV4', 1 ); // can handle IPv4
define( 'IP_LOCATION_BLOCK_API_TYPE_IPV6', 2 ); // can handle IPv6
define( 'IP_LOCATION_BLOCK_API_TYPE_BOTH', 3 ); // can handle both IPv4 and IPv6

/**
 * Class IP_Location_Block_API
 * Base api class
 */
abstract class IP_Location_Block_API {

	/**
	 * The provider name
	 * @var bool
	 */
	protected $provider = '';

	/**
	 * The site options
	 * @var array|mixed|string
	 */
	protected $options = array();

	/**
	 * Supports
	 * @var bool
	 */
	protected $supports = array();

	/**
	 * These values must be instantiated in child class
	 *
	 *//*
	protected $template = array(
		'type' => IP_LOCATION_BLOCK_API_TYPE_[IPV4 | IPV6 | BOTH],
		'url' => 'http://example.com/%API_KEY%/%API_FORMAT%/%API_OPTION%/%API_IP%';
		'api' => array(
			'%API_IP%'     => '', // should be set in build_url()
			'%API_KEY%'    => '', // should be set in __construct()
			'%API_FORMAT%' => '', // may be set in child class
			'%API_OPTION%' => '', // may be set in child class
		),
		'transform' => array(
			'errorMessage' => '',
			'countryCode'  => '',
			'countryName'  => '',
			'regionName'   => '',
			'cityName'     => '',
			'latitude'     => '',
			'longitude'    => '',
			'asn'          => null,
		)
	);*/

	/**
	 * IP_Location_Block_API constructor.
	 *
	 * @param  string  $provider
	 * @param $options
	 */
	protected function __construct( $provider, $options = array() ) {
		$api_key = self::get_api_key( $provider, $options );
		if ( is_string( $api_key ) ) {
			$this->template['api']['%API_KEY%'] = $api_key;
		}
		$this->provider = $provider;
		$this->options  = $options;

		$_provider = IP_Location_Block_Provider::get_provider( $provider );
		if ( isset( $_provider['supports'] ) && is_array( $_provider['supports'] ) ) {
			$this->supports = $_provider['supports'];
		}
	}

	/**
	 * Build URL from template
	 *
	 * @param $ip
	 * @param $template
	 *
	 * @return string|string[]
	 */
	protected static function build_url( $ip, $template ) {
		$template['api']['%API_IP%'] = $ip;

		return str_replace(
			array_keys( $template['api'] ),
			array_values( $template['api'] ),
			$template['url']
		);
	}

	/**
	 * Fetch service provider to get geolocation information
	 *
	 * @param $ip
	 * @param $args
	 * @param $template
	 *
	 * @return array|false|string[]
	 */
	protected function fetch_provider( $ip, $args ) {

		if ( isset( $args['asn'] ) ) {
			unset( $args['asn'] ); // Make sure we don't pass 'asn' to apis, it should be only used with local providers.
		}

		$template   = isset( $this->template ) ? $this->template : array();
		$cacheKey   = md5( $ip . ( isset( $template['url'] ) ? $template['url'] : '' ) );
		$cacheGroup = 'ip-location-block';

		$cache = wp_cache_get( $cacheKey, $cacheGroup );
		if ( false !== $cache ) {
			return $cache;
		}

		// check supported type of IP address
		if ( ! ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && ( $template['type'] & IP_LOCATION_BLOCK_API_TYPE_IPV4 ) ) &&
		     ! ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && ( $template['type'] & IP_LOCATION_BLOCK_API_TYPE_IPV6 ) ) ) {
			return false;
		}

		// build query
		$tmp = self::build_url( $ip, $template );
		// https://codex.wordpress.org/Function_Reference/wp_remote_get
		$res = wp_remote_get( $tmp, $args ); // @since  2.7.0

		if ( is_wp_error( $res ) ) {
			return array( 'errorMessage' => $res->get_error_message() );
		}
		$tmp = wp_remote_retrieve_header( $res, 'content-type' );
		$res = wp_remote_retrieve_body( $res );

		// clear decoded data
		$data = array();

		// extract content type
		// ex: "Content-type: text/plain; charset=utf-8"
		if ( $tmp ) {
			$tmp = explode( '/', $tmp, 2 );
			$tmp = explode( ';', $tmp[1], 2 );
			$tmp = trim( $tmp[0] );
		}

		switch ( $tmp ) {
			// decode json
			case 'json':
			case 'html':  // ipinfo.io, Xhanch
			case 'plain': // geoPlugin
				$data = json_decode( $res, true ); // PHP 5 >= 5.2.0, PECL json >= 1.2.0
				if ( null === $data ) // ipinfo.io (get_country)
				{
					$data[ $template['transform']['countryCode'] ] = trim( $res );
				}
				break;

			// decode xml
			case 'xml':
				$tmp = '/\<(.+?)\>(?:\<\!\[CDATA\[)?([^\>]*?)(?:\]\]\>)?\<\/\\1\>/i';
				if ( preg_match_all( $tmp, $res, $matches ) !== false ) {
					if ( is_array( $matches[1] ) && ! empty( $matches[1] ) ) {
						foreach ( $matches[1] as $key => $val ) {
							$data[ $val ] = $matches[2][ $key ];
						}
					}
				}
				break;

			// unknown format
			default:
				return array( 'errorMessage' => "unsupported content type: $tmp" );
		}

		// transformation
		$res = array();
		foreach ( $template['transform'] as $key => $val ) {
			if ( ! empty( $val ) && ! empty( $data[ $val ] ) ) {
				$res[ $key ] = is_string( $data[ $val ] ) ? esc_html( $data[ $val ] ) : $data[ $val ];
			}
		}

		// if country code is '-' or 'UNDEFINED' then error.
		if ( isset( $res['countryCode'] ) && is_string( $res['countryCode'] ) ) {
			$res['countryCode'] = preg_match( '/^[A-Z]{2}/', $res['countryCode'], $matches ) ? $matches[0] : null;
		}

		$final = self::post_process( $res, $data );
		wp_cache_set( $cacheKey, $final, $cacheGroup );

		return $final;
	}

	/**
	 * Get geolocation information from service provider
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|false|string[]
	 */
	public function get_location( $ip, $args = array() ) {
		$data = $this->fetch_provider( $ip, $args );

		return $data;
	}

	/**
	 * Get only country code
	 *
	 * Override this method if a provider supports this feature for quick response.
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return false|mixed|string|null
	 */
	public function get_country( $ip, $args = array() ) {
		$res = $this->get_location( $ip, $args );

		return false === $res ? false : ( empty( $res['countryCode'] ) ? null : $res['countryCode'] );
	}

	/**
	 * This function can be overrided to modify the original response.
	 *
	 * @param $processed
	 * @param $original
	 *
	 * @return mixed
	 */
	protected static function post_process( $processed, $original ) {
		return $processed;
	}

	/**
	 * Check the support for certain feature.
	 *
	 * @param $feature
	 *
	 * @return bool
	 */
	public function supports( $feature ) {
		return is_array( $this->supports ) && in_array( $feature, $this->supports );
	}

	/**
	 * Convert provider name to class name
	 *
	 * @param $provider
	 *
	 * @return string|null
	 */
	public static function get_class_name( $provider ) {
		if ( 'Maxmind' === $provider ) {
			$provider = 'GeoLite2';
		}

		$provider = str_replace( ' ', '', $provider );
		$provider = 'IP_Location_Block_API_' . preg_replace( '/[\W]/', '', $provider );

		return class_exists( $provider, false ) ? $provider : null;
	}

	/**
	 * Get option key
	 *
	 * @param $provider
	 * @param $options
	 *
	 * @return mixed|null
	 */
	public static function get_api_key( $provider, $options ) {

		if ( empty( $provider ) || empty( $options['providers'] ) ) {
			return null;
		}

		$providers = array();
		if ( ! empty( $options['providers'] ) && is_array( $options['providers'] ) ) {
			$providers = array_change_key_case( $options['providers'], CASE_LOWER );
		}
		$provider = strtolower( $provider );

		return empty( $providers[ $provider ] ) ? null : $providers[ $provider ];
	}

	/**
	 * Instance of inherited object
	 * @var static[]
	 */
	private static $instance = array();

	/**
	 * @param $provider
	 * @param $options
	 *
	 * @return static|null
	 */
	public static function get_instance( $provider, $options ) {
		if ( $name = self::get_class_name( $provider ) ) {
			if ( empty( self::$instance[ $name ] ) ) {
				return self::$instance[ $name ] = new $name( $provider, $options );
			} else {
				return self::$instance[ $name ];
			}
		}

		return null;
	}
}

/**
 * Class for IPLocationBlock
 *
 * URL         : https://iplocationblock.com/
 * Term of use : https://iplocationblock.com/terms-of-use
 * Licence fee : free
 * Rate limit  : none
 * Sample URL  : https://api.iplocationblock.com/v1/77.29.188.15
 * Input type  : IP address (IPv4, IPv6)
 * Output type : xml
 */
class IP_Location_Block_API_iplocationblock extends IP_Location_Block_API {
	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'https://api.iplocationblock.com/v1/%API_IP%?api_key=%API_KEY%',
		'api'       => array(),
		'transform' => array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'regionName'  => 'region',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		)
	);
}

/**
 * Class for IP-API.com
 *
 * URL         : http://ip-api.com/
 * Term of use : http://ip-api.com/docs/#usage_limits
 * Licence fee : free for non-commercial use
 * Rate limit  : 240 requests per minute
 * Sample URL  : http://ip-api.com/json/2a00:1210:fffe:200::1
 * Sample URL  : http://ip-api.com/xml/yahoo.co.jp
 * Input type  : IP address (IPv4, IPv6 with limited coverage) / domain name
 * Output type : json, xml
 */
class IP_Location_Block_API_IPAPIcom extends IP_Location_Block_API {
	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'http://ip-api.com/%API_FORMAT%/%API_IP%',
		'api'       => array(
			'%API_FORMAT%' => 'json',
		),
		'transform' => array(
			'errorMessage' => 'error',
			'countryCode'  => 'countryCode',
			'countryName'  => 'country',
			'regionName'   => 'regionName',
			'cityName'     => 'city',
			'latitude'     => 'lat',
			'longitude'    => 'lon',
			'asn'          => 'as',
		)
	);

	/**
	 * Returns the location
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|false|string[]
	 */
	public function get_location( $ip, $args = array() ) {
		$res = parent::get_location( $ip, $args );

		if ( ! empty( $res['asn'] ) ) {
			$res['asn'] = IP_Location_Block_Util::parse_asn( $res['asn'] );
		}

		return $res;
	}
}

/**
 * Class for GeoIPLookup.net
 *
 * URL         : http://geoiplookup.net/
 * Term of use : http://geoiplookup.net/terms-of-use.php
 * Licence fee : free
 * Rate limit  : none
 * Sample URL  : http://api.geoiplookup.net/?query=2a00:1210:fffe:200::1
 * Input type  : IP address (IPv4, IPv6)
 * Output type : xml
 */
class IP_Location_Block_API_GeoIPLookup extends IP_Location_Block_API {
	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'http://api.geoiplookup.net/?query=%API_IP%',
		'api'       => array(),
		'transform' => array(
			'countryCode' => 'countrycode',
			'countryName' => 'countryname',
			'regionName'  => 'countryname',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		)
	);
}

/**
 * Class for ipinfo.io
 *
 * URL         : https://ipinfo.io/
 * Term of use : https://ipinfo.io/developers#terms
 * Licence fee : free
 * Rate limit  : 1,000 lookups daily
 * Sample URL  : https://ipinfo.io/124.83.187.140/json
 * Sample URL  : https://ipinfo.io/124.83.187.140/country
 * Input type  : IP address (IPv4)
 * Output type : json
 */
class IP_Location_Block_API_ipinfoio extends IP_Location_Block_API {

	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'https://ipinfo.io/%API_IP%?token=%API_KEY%',
		'api'       => array(),
		'transform' => array(
			'countryCode' => 'country',
			'countryName' => 'country',
			'regionName'  => 'region',
			'cityName'    => 'city',
			'latitude'    => 'loc',
			'longitude'   => 'loc',
			'asn'         => 'org',
		)
	);

	/**
	 * Returns the location
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|false|string[]
	 */
	public function get_location( $ip, $args = array() ) {
		$res = parent::get_location( $ip, $args );
		if ( ! empty( $res ) ) {
			if ( ! empty( $res['latitude'] ) ) {
				$loc = explode( ',', $res['latitude'] );
				if ( count( $loc ) == 2 ) {
					$res['latitude']  = $loc[0];
					$res['longitude'] = $loc[1];
				}
			}

			if ( ! empty( $res['asn'] ) ) {
				$res['asn'] = IP_Location_Block_Util::parse_asn( $res['asn'] );
			}
		}

		return $res;
	}

	/**
	 * Returns the country
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return false|mixed|string|null
	 */
	public function get_country( $ip, $args = array() ) {
		$this->template['api']['%API_FORMAT%'] = '';
		$this->template['api']['%API_OPTION%'] = 'country';

		return parent::get_country( $ip, $args );
	}
}

/**
 * Class for ipapi
 *
 * URL         : https://ipapi.com/
 * Term of use : https://ipapi.com/terms
 * Licence fee : free to use the API
 * Rate limit  : 10,000 reqests per month
 * Sample URL  : http://api.ipapi.com/2a00:1210:fffe:200::1?access_key=...
 * Input type  : IP address (IPv4, IPv6)
 * Output type : json
 */
class IP_Location_Block_API_ipapi extends IP_Location_Block_API {

	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'http://api.ipapi.com/%API_IP%?access_key=%API_KEY%',
		'api'       => array(),
		'transform' => array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
			'error'       => 'error',
		)
	);

	/**
	 * Returns the location
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|false|string[]
	 */
	public function get_location( $ip, $args = array() ) {
		$res = parent::get_location( $ip, $args );
		if ( isset( $res['countryName'] ) ) {
			$res['countryCode'] = esc_html( $res['countryCode'] );
			$res['countryName'] = esc_html( $res['countryName'] );
			$res['latitude']    = esc_html( $res['latitude'] );
			$res['longitude']   = esc_html( $res['longitude'] );

			return $res;
		} else {
			return array( 'errorMessage' => esc_html( $res['error']['info'] ) );
		}
	}
}

/**
 * Class for Ipdata.co
 *
 * URL         : https://ipdata.co/
 * Term of use : https://ipdata.co/terms.html
 * Licence fee : free
 * Rate limit  : 1,500 lookups free daily
 * Sample URL  : https://api.ipdata.co/8.8.8.8?api-key=...
 * Input type  : IP address (IPv4, IPv6)
 * Output type : json
 */
class IP_Location_Block_API_Ipdataco extends IP_Location_Block_API {
	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'https://api.ipdata.co/%API_IP%?api-key=%API_KEY%',
		'api'       => array(),
		'transform' => array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'regionName'  => 'region',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		)
	);
}

/**
 * Class for ipstack
 *
 * URL         : https://ipstack.com/
 * Term of use : https://ipstack.com/terms
 * Licence fee : free for registered user
 * Rate limit  : 10,000 queries per month for free (https can be available for premium users)
 * Sample URL  : http://api.ipstack.com/186.116.207.169?access_key=YOUR_ACCESS_KEY&output=json&legacy=1
 * Input type  : IP address (IPv4, IPv6) / domain name
 * Output type : json, xml
 */
class IP_Location_Block_API_ipstack extends IP_Location_Block_API {
	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'http://api.ipstack.com/%API_IP%?access_key=%API_KEY%&output=%API_FORMAT%',
		'api'       => array(
			'%API_FORMAT%' => 'json',
		),
		'transform' => array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'regionName'  => 'region_name',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		)
	);
}

/**
 * Class for IPInfoDB
 *
 * URL         : https://ipinfodb.com/
 * Term of use :
 * Licence fee : free (need to regist to get API key)
 * Rate limit  : 2 queries/second for registered user
 * Sample URL  : https://api.ipinfodb.com/v3/ip-city/?key=...&format=xml&ip=124.83.187.140
 * Sample URL  : https://api.ipinfodb.com/v3/ip-country/?key=...&format=xml&ip=yahoo.co.jp
 * Input type  : IP address (IPv4, IPv6) / domain name
 * Output type : json, xml
 */
class IP_Location_Block_API_IPInfoDB extends IP_Location_Block_API {

	/**
	 * The template
	 * @var array
	 */
	protected $template = array(
		'type'      => IP_LOCATION_BLOCK_API_TYPE_BOTH,
		'url'       => 'https://api.ipinfodb.com/v3/%API_OPTION%/?key=%API_KEY%&format=%API_FORMAT%&ip=%API_IP%',
		'api'       => array(
			'%API_FORMAT%' => 'xml',
			'%API_OPTION%' => 'ip-city',
		),
		'transform' => array(
			'countryCode' => 'countryCode',
			'countryName' => 'countryName',
			'regionName'  => 'regionName',
			'cityName'    => 'cityName',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		)
	);

	/**
	 * IP_Location_Block_API_IPInfoDB constructor.
	 *
	 * @param $provider
	 * @param  null  $api_key
	 */
	public function __construct( $provider, $api_key = null ) {
		// sanitization
		parent::__construct( $provider, preg_replace( '/\W/', '', $api_key ) );
	}

	/**
	 * Returns the country
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return false|mixed|string|null
	 */
	public function get_country( $ip, $args = array() ) {
		$this->template['api']['%API_OPTION%'] = 'ip-country';

		return parent::get_country( $ip, $args );
	}
}

/**
 * Class for Cache
 *
 * Input type  : IP address (IPv4, IPv6)
 * Output type : array
 */
class IP_Location_Block_API_Cache extends IP_Location_Block_API {

	/**
	 * Memory cache
	 * @var array
	 */
	protected static $memcache = array();

	/**
	 * Update c ache
	 *
	 * @param $hook
	 * @param $validate
	 * @param $settings
	 * @param  bool  $countup
	 *
	 * @return array
	 */
	public static function update_cache( $hook, $validate, $settings, $countup = true ) {
		$time  = $_SERVER['REQUEST_TIME'];
		$cache = self::get_cache( $ip = $validate['ip'], $settings['cache_hold'] );

		if ( $cache ) {
			$fail = isset( $validate['fail'] ) ? $validate['fail'] : 0;
			$call = $cache['reqs'] + ( $countup ? 1 : 0 ); // prevent duplicate count up
			$last = $cache['last'];
			$view = $cache['view'];
		} else { // if new cache then reset these values
			$fail = 0;
			$call = 1;
			$last = $time;
			$view = 1;
		}

		if ( $cache && 'public' === $hook ) {
			if ( $time - $last > $settings['behavior']['time'] ) {
				$view = 1;
			} else {
				++ $view;
			}
			$last = $time;
		}

		$cache = array(
			'time' => $time,
			'ip'   => $ip,
			'hook' => $hook,
			'asn'  => $validate['asn'], // @since 3.0.4
			'code' => $validate['code'],
			'auth' => $validate['auth'], // get_current_user_id() > 0
			'fail' => $fail, // $validate['auth'] ? 0 : $fail,
			'reqs' => $settings['save_statistics'] ? $call : 0,
			'last' => $last,
			'view' => $view,
			'host' => isset( $validate['host'] ) && $validate['host'] !== $ip ? $validate['host'] : '',
		);

		// do not update cache while installing geolocation databases
		if ( $settings['cache_hold'] && ! ( $validate['auth'] && 'ZZ' === $validate['code'] ) ) {
			IP_Location_Block_Logs::update_cache( $cache );
		}

		return self::$memcache[ $ip ] = $cache;
	}

	/**
	 * Clear cache
	 */
	public static function clear_cache() {
		IP_Location_Block_Logs::clear_cache();
		self::$memcache = array();
	}

	/**
	 * Return the cache
	 *
	 * @param $ip
	 * @param  bool  $use_cache
	 *
	 * @return array|mixed|null
	 */
	public static function get_cache( $ip, $use_cache = true ) {
		if ( isset( self::$memcache[ $ip ] ) ) {
			return self::$memcache[ $ip ];
		} else {
			return $use_cache ? self::$memcache[ $ip ] = IP_Location_Block_Logs::search_cache( $ip ) : null;
		}
	}

	/**
	 * Return the location
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|string[]
	 */
	public function get_location( $ip, $args = array() ) {
		if ( $cache = self::get_cache( $ip ) ) {
			return array( 'countryCode' => $cache['code'] );
		} else {
			return array( 'errorMessage' => 'not in the cache' );
		}
	}

	/**
	 * Returns the country
	 *
	 * @param $ip
	 * @param  array  $args
	 *
	 * @return array|false|mixed|string|null
	 */
	public function get_country( $ip, $args = array() ) {
		return ( $cache = self::get_cache( $ip ) ) ? ( isset( $args['cache'] ) ? $cache : $cache['code'] ) : null;
	}
}

/**
 * Provider support class
 */
class IP_Location_Block_Provider {

	const API_AUTH_OPTIONAL = 1;
	const API_AUTH_REQUIRED = 2;
	const API_AUTH_NOT_REQUIRED = 3;


	/**
	 * List of providers
	 * @var array[]
	 */
	protected static $providers = array(

		'IP Location Block' => array(
			'key'      => '',
			'type'     => 'IPv4, IPv6 / free for non-commercial use',
			'link'     => 'https://iplocationblock.com/pricing',
			'supports' => array( 'ipv4', 'ipv6', 'asn' ),
			'requests' => array( 'total' => 15000, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_OPTIONAL,
			'local'    => false,
		),

		'IP-API.com' => array(
			'key'      => '',
			'type'     => 'IPv4, IPv6 / free for non-commercial use',
			'link'     => 'https://members.ip-api.com/#pricing',
			'supports' => array( 'ipv4', 'ipv6', 'asn' ),
			'limits'   => array( 'Up to 45 requests / minute' ),
			'requests' => array( 'total' => - 1, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_OPTIONAL,
			'local'    => false,

		),

		'GeoIPLookup' => array(
			'key'      => null,
			'type'     => 'IPv4, IPv6 / free',
			'link'     => 'http://geoiplookup.net/',
			'supports' => array( 'ipv4', 'ipv6' ),
			'limits'   => array( 'Up to 1000 requests / hour' ),
			'requests' => array( 'total' => - 1, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_NOT_REQUIRED,
			'local'    => false,
		),

		'IPInfoDB' => array(
			'key'      => '',
			'type'     => 'IPv4, IPv6 / free',
			'link'     => 'https://ipinfodb.com/',
			'supports' => array( 'ipv4', 'ipv6' ),
			'limits'   => array( 'Up to 2 requests / second' ),
			'requests' => array( 'total' => - 1, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_REQUIRED,
			'local'    => false,
		),

		'ipinfo.io' => array(
			'key'      => '',
			'type'     => 'IPv4, IPv6 / free for non-commercial use',
			'link'     => 'https://ipinfo.io/pricing',
			'supports' => array( 'ipv4', 'ipv6' ),
			'requests' => array( 'total' => 50000, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_REQUIRED,
			'local'    => false,
		),

		'ipapi' => array(
			'key'      => '',
			'type'     => 'IPv4, IPv6 / free for non-commercial use',
			'link'     => 'https://ipapi.com/',
			'supports' => array( 'ipv4', 'ipv6' ),
			'requests' => array( 'total' => 1000, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_REQUIRED,
			'local'    => false,
		),

		'ipstack' => array(
			'key'      => '',
			'type'     => 'IPv4, IPv6 / free for non-commercial use',
			'link'     => 'https://ipstack.com/',
			'supports' => array( 'ipv4', 'ipv6' ),
			'requests' => array( 'total' => 100, 'term' => 'month' ),
			'api_auth' => self::API_AUTH_REQUIRED,
			'local'    => false,
		),

	);

	/**
	 * Internals
	 * @var array[]
	 */
	protected static $internals = array(
		'Cache' => array(
			'key'      => null,
			'type'     => 'IPv4, IPv6',
			'link'     => null,
			'supports' => array(),
		),
	);

	/**
	 * Register and get addon provider class information
	 *
	 * @param $api
	 */
	public static function register_addon( $api ) {
		self::$internals += $api;
	}

	/**
	 * Return addon providers
	 *
	 * @param  array  $providers
	 * @param  false  $force
	 *
	 * @return array
	 */
	public static function get_addons( $providers = array(), $force = false ) {
		$apis = array();

		foreach ( self::$internals as $key => $val ) {
			if ( 'Cache' !== $key && ( $force || ! isset( $providers[ $key ] ) || ! empty( $providers[ $key ] ) ) ) {
				$apis[] = $key;
			}
		}

		return $apis;
	}

	/**
	 * Returns the pairs of provider name and API key
	 *
	 * @param  string  $key
	 * @param  bool  $rand
	 * @param  bool  $cache
	 * @param  bool  $all
	 *
	 * @return array
	 */
	public static function get_providers( $key = 'key', $rand = false, $cache = false, $all = true ) {
		// add internal DB
		$list = array();
		foreach ( self::$internals as $provider => $tmp ) {
			if ( 'Cache' !== $provider || $cache ) {
				$list[ $provider ] = $tmp[ $key ];
			}
		}

		if ( $all ) {
			$tmp = array_keys( self::$providers );

			// randomize
			if ( $rand ) {
				shuffle( $tmp );
			}

			foreach ( $tmp as $name ) {
				$list[ $name ] = self::$providers[ $name ][ $key ];
			}
		}

		return $list;
	}

	/**
	 * Returns providers name list which are checked in settings
	 *
	 * @param $settings
	 * @param  bool  $rand
	 * @param  bool  $cache
	 * @param  bool  $all
	 *
	 * @return array
	 */
	public static function get_valid_providers( $settings, $rand = true, $cache = true, $all = false ) {
		$list      = array();
		$providers = $settings['providers']; // list of not selected and selected with api key
		$cache     &= $settings['cache_hold']; // exclude `Cache` when `IP address cache` is disabled

		foreach ( self::get_providers( 'key', $rand, $cache, empty( $settings['restrict_api'] ) || $all ) as $name => $key ) {
			// ( if $name has api key )         || ( if $name that does not need api key is selected )
			if ( ! empty( $providers[ $name ] ) || ( ! isset( $providers[ $name ] ) && null === $key ) ) {
				$list[] = $name;
			}
		}

		return $list;
	}

	/**
	 * Return provider
	 *
	 * @param $name
	 *
	 * @return array
	 */
	public static function get_provider( $name ) {
		$all = self::all();

		return isset( $all[ $name ] ) ? $all[ $name ] : array();
	}

	/**
	 * Supports specific feature
	 *
	 * @param $name
	 * @param  array|string  $feature
	 *
	 * @return bool
	 */
	public static function supports( $name, $feature ) {
		$all = self::all();
		if ( isset( $all[ $name ]['supports'] ) && is_array( $all[ $name ]['supports'] ) ) {
			if ( is_array( $feature ) ) {
				return count( array_intersect( $feature, $all[ $name ]['supports'] ) ) > 0;
			} else {
				return in_array( $feature, $all[ $name ]['supports'] );
			}
		}

		return false;
	}

	/**
	 * Return providers with asn support
	 */
	public static function get_providers_by_feature( $feature ) {
		$providers = array();
		foreach ( self::all() as $key => $provider ) {
			if ( self::supports( $key, $feature ) ) {
				$providers[ $key ] = $provider;
			}
		}

		return $providers;
	}


	/**
	 * Return all the providers including internal.
	 */
	public static function all() {
		static $providers = null;
		if ( is_null( $providers ) ) {
			$providers = array_merge( self::$providers, self::$internals );
		}

		return $providers;
	}


	/**
	 * Format provider meta
	 *
	 * @param $provider
	 * @param $meta_key
	 *
	 * @return mixed|string
	 */
	public static function format_provider_meta( $provider, $meta_key ) {

		$providers = self::all();

		$value = isset( $providers[ $provider ][ $meta_key ] ) ? $providers[ $provider ][ $meta_key ] : '';

		switch ( $meta_key ) {
			case 'requests':
				$total = isset( $value['total'] ) ? $value['total'] : '';
				$term  = isset( $value['term'] ) ? $value['term'] : '';
				if ( is_numeric( $total ) ) {
					if ( $total < 0 ) {
						$total = __( 'Unlimited', 'ip-location-block' );
						$term  = '';
					} else {
						$total = number_format( $total );
					}
				}
				if ( ! empty( $total ) ) {
					$value = empty( $term ) ? sprintf( '%s', $total ) : sprintf( '%s / %s', $total, $term );
				}
				break;
			case 'limits':
				if ( empty( $value ) ) {
					$value = __( 'None known', 'ip-location-block' );
				} elseif ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				break;
			case 'signup-button':
				if ( ! empty( $providers[ $provider ]['link'] ) && ! is_null( $providers[ $provider ]['key'] ) ) {
					$value = sprintf( '<a href="%s" target="_blank" class="button button-secondary button-small">%s</a>', $providers[ $provider ]['link'], __( 'Register', 'ip-location-block' ) );
				}
				break;
			case 'name':

				if ( 'IP LOCATION BLOCK' === strtoupper( $provider ) ) {
					$value = sprintf( '%s <span class="ip-location-block-recommended">%s</span>', $provider, __('(Recommended)', 'ip-location-block') );
				} else {
					$value = $provider;
				}

				break;

		}

		return $value;
	}

}

/**
 * Load additional plugins
 * @url https://iplocationblock.com/cloudflare-cloudfront-api-class-library/
 */
if ( class_exists( 'IP_Location_Block', false ) ) {
	// Avoid "The plugin does not have a valid header" on activation under WP4.0
	if ( is_plugin_active( IP_LOCATION_BLOCK_BASE ) ) {
		$dir     = IP_Location_Block_Util::slashit(
			apply_filters( 'ip-location-block-api-dir', IP_Location_Block_Util::get_storage_dir( 'apis' ) )
		);
		$plugins = ( is_dir( $dir ) ? scandir( $dir, defined( 'SCANDIR_SORT_DESCENDING' ) ? SCANDIR_SORT_DESCENDING : 1 ) : false );
		if ( false !== $plugins ) {
			$exclude = array( '.', '..' );
			foreach ( $plugins as $plugin ) {
				if ( ! in_array( $plugin, $exclude, true ) && is_dir( $dir . $plugin ) ) {
					$plugin_path = sprintf( '%s%s%sclass-%s.php', $dir, $plugin, DIRECTORY_SEPARATOR, $plugin );
					if ( file_exists( $plugin_path ) ) {
						require_once $plugin_path;
					}
				}
			}
		}
	}
}
