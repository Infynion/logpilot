<?php
/**
 * Database Service.
 *
 * @package    Infynion\LogPilot\Services
 */

namespace Infynion\LogPilot\Services;

/**
 * Class DatabaseService
 *
 * Handles database schema creation and updates.
 *
 * @package Infynion\LogPilot\Services
 */
class DatabaseService {

	/**
	 * The name of the table without prefix.
	 *
	 * @var string
	 */
	private $table_name = 'infynion_logpilot_logs';

	/**
	 * Register the service.
	 *
	 * @return void
	 */
	public function register() {
		// This service is mostly used by Activator, but could hook into 'plugins_loaded' for db updates (versions).
		add_action( 'plugins_loaded', array( $this, 'update_db_check' ) );
	}

	/**
	 * Check if database update is needed.
	 *
	 * @return void
	 */
	public function update_db_check() {
		if ( get_site_option( 'logpilot_db_version' ) !== '1.1.0' ) {
			$this->create_table();
			update_site_option( 'logpilot_db_version', '1.1.0' );
		}
	}

	/**
	 * Creates a database table to store error logs.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            error_hash CHAR(64) NOT NULL,
            type VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            file VARCHAR(255) NULL,
            line INT UNSIGNED NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            request_uri VARCHAR(255) NULL,
            request_method VARCHAR(10) NULL,
            user_agent TEXT NULL,
            client_ip VARCHAR(45) NULL,
            occurrences INT UNSIGNED DEFAULT 1,
            last_occurred DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved TINYINT(1) DEFAULT 0,
            PRIMARY KEY(id),
            UNIQUE KEY error_hash (error_hash),
            KEY type_idx (type),
            KEY user_id (user_id)
        ) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}
}
