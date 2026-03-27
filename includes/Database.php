<?php
/**
 * Database handler file.
 *
 * @package Infynion\Logpilot
 * @since   1.0.0
 */

namespace Infynion\Logpilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database operations for Logpilot.
 *
 * @since 1.0.0
 */
class Database {

	/**
	 * Table name for storing error logs.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $table_name = 'logpilot_errors';

	/**
	 * Creates the custom log table upon activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$instance = new self();
		$table    = $wpdb->prefix . $instance->table_name;
		$charset  = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            error_hash CHAR(64) NOT NULL,
            type VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            file VARCHAR(255) NULL,
            line INT UNSIGNED NULL,
            occurrences INT UNSIGNED DEFAULT 1,
            last_occurred DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved TINYINT(1) DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY error_hash (error_hash),
            KEY type_idx (type)
        ) $charset;";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}

	/**
	 * Insert or increment a log entry based on its hash signature.
	 *
	 * @since 1.0.0
	 *
	 * @param array $error the error details containing message, file, line, and type.
	 * @return array Array containing 'is_new' and 'log_id'.
	 */
	public function insert_or_increment( array $error ): array {
		global $wpdb;

		$table   = $wpdb->prefix . $this->table_name;
		$message = sanitize_text_field( $error['message'] );
		$file    = sanitize_text_field( $error['file'] ?? '' );
		$line    = absint( $error['line'] ?? 0 );
		$type    = sanitize_text_field( $error['type'] );

		$hash = hash( 'sha256', "{$type}|{$message}|{$file}|{$line}" );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET occurrences = occurrences + 1, last_occurred = %s, resolved = 0 WHERE error_hash = %s",
				current_time( 'mysql' ),
				$hash
			)
		);

		if ( ! $updated ) {
			$result = $wpdb->insert(
				$table,
				array(
					'error_hash'    => $hash,
					'type'          => $type,
					'message'       => $message,
					'file'          => $file,
					'line'          => $line,
					'occurrences'   => 1,
					'created_at'    => current_time( 'mysql' ),
					'last_occurred' => current_time( 'mysql' ),
				)
			);

			return array(
				'is_new' => (bool) $result,
				'log_id' => (int) $wpdb->insert_id,
			);
		}

		$log_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE error_hash = %s LIMIT 1", $hash )
		);

		return array(
			'is_new' => false,
			'log_id' => (int) $log_id,
		);
	}

	/**
	 * Retrieve a specific log by its numeric ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The ID of the log to fetch.
	 * @return array|object|null The log row database object or array, or null if not found.
	 */
	public function get_log( int $id ) {
		global $wpdb;
		$table = $wpdb->prefix . $this->table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Update the resolution state of multiple logs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $ids   Array of log IDs to update.
	 * @param int   $state The state to set (1 for resolved, 0 for unresolved).
	 * @return void
	 */
	public function update_resolve_state( array $ids, int $state ): void {
		global $wpdb;
		if ( empty( $ids ) ) {
			return;
		}

		$table        = $wpdb->prefix . $this->table_name;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$query = $wpdb->prepare(
			"UPDATE {$table} SET resolved = %d WHERE id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$state,
			...$ids
		);

		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Deletes logs older than the configured expiration days.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function delete_old_logs(): void {
		global $wpdb;
		$expire_days = get_option( 'logpilot_expire', 7 );
		if ( empty( $expire_days ) ) {
			return; // 0 means no expiration.
		}

		$table = $wpdb->prefix . $this->table_name;
		$date  = gmdate( 'Y-m-d H:i:s', time() - ( $expire_days * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE last_occurred < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date
			)
		);
	}

	/**
	 * Delete multiple logs by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param array $ids Array of numeric log IDs to delete.
	 * @return void
	 */
	public function delete_logs( array $ids ): void {
		global $wpdb;
		if ( empty( $ids ) ) {
			return;
		}

		$table        = $wpdb->prefix . $this->table_name;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$query = $wpdb->prepare(
			"DELETE FROM {$table} WHERE id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$ids
		);

		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
