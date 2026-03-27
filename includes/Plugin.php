<?php
/**
 * Main plugin bootstrap file.
 *
 * @package Infynion\Logpilot
 * @since   1.0.0
 */

namespace Infynion\Logpilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrapper.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Main instance of the plugin.
	 *
	 * @var Plugin|null
	 * @since 1.0.0
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin classes and hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		$database = new Database();
		$logger   = new Logger( $database );
		$logger->register_hooks();

		add_action( 'logpilot_daily_cleanup', array( $database, 'delete_old_logs' ) );
		if ( ! wp_next_scheduled( 'logpilot_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'logpilot_daily_cleanup' );
		}

		if ( is_admin() ) {
			$ui = new Admin\UI( $database, $logger );
			$ui->register_hooks();
		}
	}
}
