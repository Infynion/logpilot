<?php
/**
 * Cleanup Service.
 *
 * @package    Infynion\SystemLogger\Services
 */

namespace Infynion\SystemLogger\Services;

use Infynion\SystemLogger\Models\LogModel;

/**
 * Class CleanupService
 *
 * Handles automatic cleanup of old logs.
 *
 * @package Infynion\SystemLogger\Services
 */
class CleanupService {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infynion_daily_log_cleanup', array( $this, 'cleanup' ) );
	}

	/**
	 * Execute cleanup.
	 *
	 * @return void
	 */
	public function cleanup() {
		$days = (int) get_option( 'infynion_error_log_expire', 30 );
		if ( $days <= 0 ) {
			return;
		}

		$model = new LogModel();
		$model->cleanup_older_than( $days );
	}
}
