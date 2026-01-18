<?php
/**
 * Plugin Name:       Logpilot by Infynion
 * Plugin URI:        https://infynion.com
 * Description:       A robust system logger that tracks PHP errors, exceptions, and custom logs in the database.
 * Version:           1.0.0
 * Author:            Infynion
 * Author URI:        https://infynion.com
 * License:           GPL-2.0+
 * Text Domain:       logpilot
 * Domain Path:       /languages
 *
 * @package           Infynion\LogPilot
 */

namespace Infynion\LogPilot;

use Infynion\LogPilot\Core\Plugin;
use Infynion\LogPilot\Core\Autoloader;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The plugin version.
 */
define( 'LOGPILOT_VERSION', '1.0.0' );

/**
 * The absolute path to the plugin directory.
 */
define( 'LOGPILOT_PATH', plugin_dir_path( __FILE__ ) );

/**
 * The URL to the plugin directory.
 */
define( 'LOGPILOT_URL', plugin_dir_url( __FILE__ ) );

// Include the autoloader.
require_once LOGPILOT_PATH . 'includes/Core/Autoloader.php';

/**
 * Initialize the simple PSR-4 autoloader.
 */
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->add_namespace( 'Infynion\LogPilot', LOGPILOT_PATH . 'includes' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_logpilot() {
	$plugin = new Plugin();
	$plugin->run();
}

run_logpilot();
