<?php
/**
 * IP Location Block
 *
 * A WordPress plugin that blocks undesired access based on geolocation of IP address.
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 *
 * Plugin Name:       IP Location Block (mu)
 * Plugin URI:        https://wordpress.org/plugins/ip-location-block/
 * Description:       It blocks any spams, login attempts and malicious access to the admin area posted from outside your nation, and also prevents zero-day exploit.
 * Version:           1.0.0
 * Author:            darkog
 * Author URI:        https://iplocationblock.com/
 * Text Domain:       ip-location-block
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}


if ( ! class_exists( 'IP_Location_Block', false ) ) {

	// Avoud redirection loop
	if ( 'wp-login.php' === basename( $_SERVER['SCRIPT_NAME'] ) && site_url() !== home_url() ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$plugin_basename = 'ip-location-block/ip-location-block.php';
	if ( is_plugin_active( $plugin_basename ) || is_plugin_active_for_network( $plugin_basename ) ) {
		// Load plugin class
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_basename ) ) {
			require WP_PLUGIN_DIR . '/' . $plugin_basename;
			$options = IP_Location_Block::get_option();
			// check setup had already done
			if ( version_compare( $options['version'], IP_LOCATION_BLOCK_VERSION ) >= 0 && $options['matching_rule'] >= 0 ) {
				// Remove instanciation
				remove_action( 'plugins_loaded', 'ip_location_block_update' );
				remove_action( 'plugins_loaded', array( 'IP_Location_Block', 'get_instance' ) );
				// Upgrade then instanciate immediately
				IP_Location_Block::get_instance();
			}
		} else {
			add_action( 'admin_notices', 'ip_location_block_mu_notice' );
		}
	}
	/**
	 * Show global notice.
	 */
	function ip_location_block_mu_notice() {
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo sprintf(
			__( 'Can\'t find IP Location Block in your plugins directory. Please remove <code>%s</code> or re-install %s.', 'ip-location-block' ),
			__FILE__,
			'<a href="https://wordpress.org/plugins/ip-location-block/" title="IP Location Block &mdash; WordPress Plugins">IP Location Block</a>'
		);
		echo '</p></div>' . "\n";
	}
}
