<?php
/**
 * IP Location Block - DNS lookup
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

class IP_Location_Block_Lkup {

	/**
	 * Converts IP address to in_addr representation
	 *
	 * @link https://stackoverflow.com/questions/14459041/inet-pton-replacement-function-for-php-5-2-17-in-windows
	 *
	 * @param $ip
	 *
	 * @return false|mixed|string
	 */
	private static function inet_pton( $ip ) {
		// available on Windows platforms after PHP 5.3.0, need IPv6 support by PHP
		if ( function_exists( 'inet_pton' ) && ( $ip = @inet_pton( $ip ) ) )
			return $ip;

		// ipv4
		elseif ( FALSE !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			if ( FALSE === strpos( $ip, ':' ) ) {
				$ip = pack( 'N', ip2long( $ip ) );
			}
			else {
				$ip = explode( ':', $ip );
				$ip = pack( 'N', ip2long( $ip[ count( $ip ) - 1 ] ) );
			}
		}

		// ipv6
		elseif ( FALSE !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$ip = explode( ':', $ip );
			$parts = 8 - count( $ip );
			$res = '';
			$replaced = 0;
			foreach ( $ip as $seg ) {
				if ( $seg != '' ) {
					$res .= str_pad( $seg, 4, '0', STR_PAD_LEFT );
				}
				elseif ( $replaced == 0 ) {
					for ( $i = 0; $i <= $parts; ++$i ) {
						$res .= '0000';
					}
					$replaced = 1;
				}
				elseif ( $replaced == 1 ) {
					$res .= '0000';
				}
			}
			$ip = pack( 'H' . strlen( $res ), $res );
		}

		return $ip;
	}

	/**
	 * DNS lookup by ip
	 * @param $ip
	 *
	 * @return mixed|string
	 */
	public static function gethostbyaddr( $ip ) {
		// array( 'nameservers' => array( '8.8.8.8', '8.8.4.4' ) ) // Google public DNS
		// array( 'nameservers' => array( '1.1.1.1', '1.0.0.1' ) ) // APNIC public DNS
		$servers = array( 'nameservers' => apply_filters( 'ip-location-block-dns', array() ) );
		if ( ! empty( $servers['nameservers'] ) ) {
			set_include_path( IP_LOCATION_BLOCK_PATH . 'includes' . PATH_SEPARATOR . get_include_path() );
			require_once IP_LOCATION_BLOCK_PATH . 'includes/Net/DNS2.php';

			$r = new Net_DNS2_Resolver( $servers );

			try {
				$result = $r->query( $ip, 'PTR' );
			}
			catch ( Net_DNS2_Exception $e ) {
				$result = $e->getMessage();
			}

			if ( isset( $result->answer ) ) {
				foreach ( $result->answer as $obj ) {
					if ( 'PTR' === $obj->type ) {
						return $obj->ptrdname;
					}
				}
			}
		}

		// available on Windows platforms after PHP 5.3.0
		if ( function_exists( 'gethostbyaddr' ) )
			$host = @gethostbyaddr( $ip );

		// if not available
		if ( empty( $host ) && function_exists( 'dns_get_record' ) ) {
			// generate in-addr.arpa notation
			if ( FALSE !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ptr = implode( ".", array_reverse( explode( ".", $ip ) ) ) . ".in-addr.arpa";
			}

			elseif ( FALSE !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				$ptr = self::inet_pton( $ip );
				$ptr = implode(".", array_reverse( str_split( bin2hex( $ptr ) ) ) ) . ".ip6.arpa";
			}

			if ( isset( $ptr ) and $ptr = @dns_get_record( $ptr, DNS_PTR ) ) {
				return $ptr[0]['target'];
			}
		}

		return empty( $host ) ? $ip : $host;
	}

}
