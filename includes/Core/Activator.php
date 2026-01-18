<?php
/**
 * Fired during plugin activation.
 *
 * @package    Infynion\SystemLogger\Core
 */

namespace Infynion\SystemLogger\Core;

use Infynion\SystemLogger\Services\DatabaseService;

/**
 * Class Activator
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package Infynion\SystemLogger\Core
 */
class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Create the database table.
		$db_service = new DatabaseService();
		$db_service->create_table();

		// Schedule cron jobs if not already scheduled (though ideally handled in Services).
		if ( ! wp_next_scheduled( 'infynion_daily_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'infynion_daily_log_cleanup' );
		}
	}
}
