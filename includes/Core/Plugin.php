<?php
/**
 * Main Plugin Class.
 *
 * @package    Infynion\SystemLogger\Core
 */

namespace Infynion\SystemLogger\Core;

/**
 * Class Plugin
 *
 * The main plugin container class that defines the core functionality,
 * loads dependencies, and sets the hooks for the admin area and
 * the public-facing side of the site.
 *
 * @package Infynion\SystemLogger\Core
 */
class Plugin {

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->define_constants();
		$this->register_hooks();
	}

	/**
	 * Define any internal constants if needed.
	 *
	 * @since    1.0.0
	 */
	private function define_constants() {
		// e.g., define('INFYNION_DEBUG', true);
	}

	/**
	 * Register all of the hooks related to the plugin functionality.
	 *
	 * @since    1.0.0
	 */
	private function register_hooks() {
		$this->register_activation_hook();
		$this->register_deactivation_hook();
		$this->init_services();
	}

	/**
	 * Register the plugin activation hook.
	 *
	 * @since    1.0.0
	 */
	private function register_activation_hook() {
		register_activation_hook( LOGPILOT_PATH . 'logpilot.php', array( Activator::class, 'activate' ) );
	}

	/**
	 * Register the plugin deactivation hook.
	 *
	 * @since    1.0.0
	 */
	private function register_deactivation_hook() {
		register_deactivation_hook( LOGPILOT_PATH . 'logpilot.php', array( Deactivator::class, 'deactivate' ) );
	}

	/**
	 * Initialize all services and bind them to the appropriate WordPress actions/filters.
	 *
	 * Uses the 'services' array to define the list of classes to instantiate.
	 *
	 * @since    1.0.0
	 */
	private function init_services() {
		$services = [
			\Infynion\SystemLogger\Services\DatabaseService::class,
			\Infynion\SystemLogger\Services\LoggerService::class,
			\Infynion\SystemLogger\Services\ErrorHandler::class,
			\Infynion\SystemLogger\Services\NotificationService::class,
			\Infynion\SystemLogger\Services\CleanupService::class,
			\Infynion\SystemLogger\Admin\AdminManager::class,
		];

		foreach ( $services as $service_class ) {
			if ( class_exists( $service_class ) && method_exists( $service_class, 'register' ) ) {
				$service = new $service_class();
				$service->register();
			}
		}
	}
}
