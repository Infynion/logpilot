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
	public string $table_name = 'logpilot_logs';

	/**
	 * Table name for storing AI fix suggestions.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	public string $suggestions_table = 'logpilot_ai_suggestions';

	/**
	 * Table name for storing file backups before applying fixes.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	public string $backups_table = 'logpilot_file_backups';

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

		$suggestions_table = $wpdb->prefix . esc_sql( 'logpilot_ai_suggestions' );
		$backups_table     = $wpdb->prefix . esc_sql( 'logpilot_file_backups' );

		$sql .= "CREATE TABLE IF NOT EXISTS {$suggestions_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            log_id BIGINT(20) UNSIGNED NOT NULL,
            prompt_hash CHAR(64) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT 'medium',
            explanation LONGTEXT NOT NULL,
            suggested_code LONGTEXT NOT NULL,
            target_file VARCHAR(255) NOT NULL DEFAULT '',
            is_core_file TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'ready',
            recovery_token CHAR(64) NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY log_id_idx (log_id),
            KEY status_idx (status)
        ) $charset;";

		$sql .= "CREATE TABLE IF NOT EXISTS {$backups_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            suggestion_id BIGINT(20) UNSIGNED NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_hash_before CHAR(64) NOT NULL,
            original_content LONGBLOB NOT NULL,
            backed_up_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY suggestion_file (suggestion_id, file_path),
            KEY suggestion_id_idx (suggestion_id)
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

		$table   = $wpdb->prefix . esc_sql( $this->table_name );
		$message = sanitize_text_field( $error['message'] );
		$file    = sanitize_text_field( $error['file'] ?? '' );
		$line    = absint( $error['line'] ?? 0 );
		$type    = sanitize_text_field( $error['type'] );

		$hash = hash( 'sha256', "{$type}|{$message}|{$file}|{$line}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time logging requires direct writes; caching would corrupt dedup logic.
		$updated = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from a safe, esc_sql()-encoded constant.
				"UPDATE {$table} SET occurrences = occurrences + 1, last_occurred = %s, resolved = 0 WHERE error_hash = %s",
				current_time( 'mysql' ),
				$hash
			)
		);

		if ( ! $updated ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for atomic insert.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lookup immediately after a write; no cache benefit.
		$log_id = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (esc_sql).
				"SELECT id FROM {$table} WHERE error_hash = %s LIMIT 1",
				$hash
			)
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
		$table = $wpdb->prefix . esc_sql( $this->table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-row read by primary key; no benefit to caching transient log records.
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (esc_sql).
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
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

		$table        = $wpdb->prefix . esc_sql( $this->table_name );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Table name uses esc_sql(); $placeholders are integer-only %d tokens; $state and $ids are integer values.
		$query = $wpdb->prepare( "UPDATE {$table} SET resolved = %d WHERE id IN ({$placeholders})", $state, ...$ids );
		$wpdb->query( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		$table = $wpdb->prefix . esc_sql( $this->table_name );
		$date  = gmdate( 'Y-m-d H:i:s', time() - ( $expire_days * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup; no read cache involved.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses esc_sql(); safe to interpolate.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_occurred < %s", $date ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		$table        = $wpdb->prefix . esc_sql( $this->table_name );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Table name uses esc_sql(); $placeholders are integer-only %d tokens; $ids are absint-safe integer IDs.
		$query = $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	// -------------------------------------------------------------------------
	// AI Suggestions
	// -------------------------------------------------------------------------

	/**
	 * Insert a new AI suggestion record.
	 *
	 * @since 1.1.0
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|false The insert_id on success, false on failure.
	 */
	public function insert_suggestion( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->suggestions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert of new suggestion row.
		$result = $wpdb->insert( $table, $data );
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Retrieve a single AI suggestion by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param int $id The suggestion ID.
	 * @return array|null The suggestion row as ARRAY_A, or null if not found.
	 */
	public function get_suggestion( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->suggestions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Look up an existing suggestion for a log/prompt combination.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $log_id      The log entry ID.
	 * @param string $prompt_hash SHA-256 hash of the prompt context.
	 * @return array|null The suggestion row as ARRAY_A, or null if not found.
	 */
	public function get_suggestion_for_log( int $log_id, string $prompt_hash ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->suggestions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE log_id = %d AND prompt_hash = %s ORDER BY created_at DESC LIMIT 1",
				$log_id,
				$prompt_hash
			),
			ARRAY_A
		);
	}

	/**
	 * Update the status of an AI suggestion.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $id     The suggestion ID.
	 * @param string $status The new status value.
	 * @return void
	 */
	public function update_suggestion_status( int $id, string $status ): void {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->suggestions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, array( 'status' => sanitize_text_field( $status ) ), array( 'id' => $id ) );
	}

	/**
	 * Retrieve all suggestions in 'applied_pending' status whose watchdog transient has expired.
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of suggestion rows as ARRAY_A.
	 */
	public function get_pending_watchdog_suggestions(): array {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->suggestions_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$table} WHERE status = 'applied_pending'",
			ARRAY_A
		);
		return $rows ?: array();
	}

	// -------------------------------------------------------------------------
	// File Backups
	// -------------------------------------------------------------------------

	/**
	 * Insert a file backup record before applying a fix.
	 *
	 * @since 1.1.0
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|false The insert_id on success, false on failure.
	 */
	public function insert_backup( array $data ): int|false {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->backups_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert of new backup row.
		$result = $wpdb->insert( $table, $data );
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Retrieve the backup row for a given suggestion ID.
	 *
	 * @since 1.1.0
	 *
	 * @param int $suggestion_id The suggestion ID.
	 * @return array|null The backup row as ARRAY_A, or null if not found.
	 */
	public function get_backup_for_suggestion( int $suggestion_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . esc_sql( $this->backups_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE suggestion_id = %d ORDER BY backed_up_at DESC LIMIT 1",
				$suggestion_id
			),
			ARRAY_A
		);
	}
}
