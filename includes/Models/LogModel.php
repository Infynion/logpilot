<?php
/**
 * Log Model.
 *
 * @package    Infynion\SystemLogger\Models
 */

namespace Infynion\SystemLogger\Models;

/**
 * Class LogModel
 *
 * Handles database operations for logs (CRUD).
 *
 * @package Infynion\SystemLogger\Models
 */
class LogModel {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	private $table_name = 'infynion_logpilot_logs';

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private function get_table() {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}

	/**
	 * Insert a new log or increment occurrences if it exists.
	 *
	 * @param array $data Log data.
	 * @return array Result with is_new flag and log_id.
	 */
	public function insert_or_increment( $data ) {
		global $wpdb;
		$table   = $this->get_table();
		$message = sanitize_text_field( $data['message'] );
		$file    = sanitize_text_field( $data['file'] ?? '' );
		$line    = absint( $data['line'] ?? 0 );
		$type    = sanitize_text_field( $data['type'] );

		// Create a unique hash for each distinct error.
		// NOTE: We hash the encrypted message if passed, or plain?
		// To match previous logic, we hash specific fields.
		// However, message is encrypted passed in. Ideally we hash the RAW params before encryption?
		// But here we receive potentially encrypted data.
		// Let's assume the LoggerService generates the hash or we rely on message being unique.
		// Wait, if message is encrypted with random IV, it will be different every time!
		// So we CANNOT use encrypted message for the hash.
		// Solution: Hash MUST be passed in or generated from raw data BEFORE encryption.
		// Let's accept 'error_hash' in $data.
		
		if ( empty( $data['error_hash'] ) ) {
			// Fallback (unsafe if message is encrypted).
			$hash = hash( 'sha256', "{$type}|{$message}|{$file}|{$line}" );
		} else {
			$hash = $data['error_hash'];
		}

		// Try to increment an existing log entry.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} 
			 SET occurrences = occurrences + 1, last_occurred = %s, resolved = 0
			 WHERE error_hash = %s",
				current_time( 'mysql' ),
				$hash
			)
		);

		// If no row was updated, insert a new one.
		if ( ! $updated ) {
			$result = $wpdb->insert( $table, array(
				'error_hash'    => $hash,
				'type'          => $type,
				'message'       => $message,
				'file'          => $file,
				'line'          => $line,
				'occurrences'   => 1,
				'created_at'    => current_time( 'mysql' ),
				'last_occurred' => current_time( 'mysql' ),
			) );

			$log_id = $wpdb->insert_id;
			
			// Trigger custom hook for extensibility
			if ( $log_id ) {
				do_action( 'infynion/log_saved', $log_id, true, $data );
			}

			return array(
				'is_new' => true,
				'log_id' => (int) $log_id,
			);
		}

		// If updated, fetch the existing log ID by hash.
		$log_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE error_hash = %s LIMIT 1", $hash )
		);
		
		if ( $log_id ) {
			do_action( 'infynion/log_saved', $log_id, false, $data );
		}

		return array(
			'is_new' => false,
			'log_id' => (int) $log_id,
		);
	}

	/**
	 * Get log by ID.
	 *
	 * @param int $id Log ID.
	 * @return array|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		$table = $this->get_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A );
	}

	/**
	 * Get all logs.
	 *
	 * @param bool $include_resolved Whether to include resolved logs.
	 * @return array
	 */
	public function get_all( $include_resolved = false ) {
		global $wpdb;
		$table = $this->get_table();
		$where = $include_resolved ? '1=1' : 'resolved = 0';
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY last_occurred DESC", ARRAY_A );
	}

	/**
	 * Delete logs by IDs.
	 *
	 * @param array $ids Log IDs.
	 * @return int|false Number of rows affected or false.
	 */
	public function delete( $ids ) {
		global $wpdb;
		$table = $this->get_table();
		$ids   = array_map( 'intval', (array) $ids );

		if ( empty( $ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($placeholders)", ...$ids ) );
	}

	/**
	 * Mark logs as resolved/unresolved.
	 *
	 * @param array $ids Log IDs.
	 * @param int   $state 1 for resolved, 0 for unresolved.
	 * @return int|false
	 */
	public function mark_resolved( $ids, $state = 1 ) {
		global $wpdb;
		$table = $this->get_table();
		$ids   = array_map( 'intval', (array) $ids );
		$state = intval( $state );

		if ( empty( $ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET resolved = %d WHERE id IN ($placeholders)", $state, ...$ids ) );
	}
	
	/**
	 * Cleanup old logs.
	 * 
	 * @param int $days Retention days.
	 * @return int|false Number of deleted rows.
	 */
	public function cleanup_older_than( $days ) {
		global $wpdb;
		$table = $this->get_table();
		$threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_occurred < %s", $threshold ) );
	}
}
