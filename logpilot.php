<?php
/**
 * Plugin Name: Logpilot - Advanced System Logger & Error Monitor
 * Plugin URI:  https://github.com/Infynion/logpilot
 * Description: A secure, high-performance logpilot and error monitor that captures exceptions, PHP errors, database issues, and API failures.
 * Version:     1.0.0
 * Author:      Infynion
 * Author URI:  https://github.com/Infynion/logpilot
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: logpilot
 * Domain Path: /languages
 *
 * @package Infynion\Logpilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'LOGPILOT_VERSION', '1.0.0' );
define( 'LOGPILOT_FILE', __FILE__ );
define( 'LOGPILOT_PATH', plugin_dir_path( LOGPILOT_FILE ) );
define( 'LOGPILOT_URL', plugin_dir_url( LOGPILOT_FILE ) );

// Require the Composer autoloader.
if ( file_exists( LOGPILOT_PATH . 'vendor/autoload.php' ) ) {
	require_once LOGPILOT_PATH . 'vendor/autoload.php';
}

/**
 * Bootstraps the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function logpilot_init() {
	if ( class_exists( '\Infynion\Logpilot\Plugin' ) ) {
		$plugin = \Infynion\Logpilot\Plugin::get_instance();
		$plugin->init();
	}
}
add_action( 'plugins_loaded', 'logpilot_init' );

/**
 * Deactivation Hook
 *
 * @since 1.0.0
 * @return void
 */
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'logpilot_daily_cleanup' );
	}
);

/**
 * Activation hook to install tables.
 *
 * @since 1.0.0
 * @return void
 */
register_activation_hook(
	__FILE__,
	function () {
		if ( class_exists( '\Infynion\Logpilot\Database' ) ) {
			\Infynion\Logpilot\Database::create_table();
		}
	}
);
