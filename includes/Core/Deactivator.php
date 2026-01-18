<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    Infynion\LogPilot\Core
 */

namespace Infynion\LogPilot\Core;

/**
 * Class Deactivator
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package Infynion\LogPilot\Core
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear scheduled cron jobs.
		wp_clear_scheduled_hook( 'infynion_daily_log_cleanup' );
		wp_clear_scheduled_hook( 'infynion_weekly_log_reminder' );
	}
}
