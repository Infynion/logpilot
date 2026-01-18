<?php
/**
 * Log Model.
 *
 * @package    Infynion\LogPilot\Models
 */

namespace Infynion\LogPilot\Models;

/**
 * Class LogModel
 *
 * Handles database operations for logs (CRUD).
 *
 * @package Infynion\LogPilot\Models
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
	 * Upsert log (Insert or Update if exists).
	 *
	 * @param array $data Log data with pre-calculated hash.
	 * @return array Result with log_id and is_new flag.
	 */
	public function upsert( $data ) {
		global $wpdb;

		// Sanitize input
		$error_hash = sanitize_key( $data['error_hash'] );
		$table      = $this->get_table();

		// Try to update occurrence first
		$updated = $this->increment_occurrence( $error_hash, $table );

		if ( $updated ) {
			// Fetch the ID of the updated row
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE error_hash = %s", $error_hash ) );
			return array(
				'log_id' => $id,
				'is_new' => false,
			);
		}

		// Insert new
		$id = $this->insert_new( $data, $table );

		return array(
			'log_id' => $id,
			'is_new' => true,
		);
	}

	/**
	 * Increment occurrence for existing log.
	 *
	 * @param string $hash  Log hash.
	 * @param string $table Table name.
	 * @return bool True if updated.
	 */
	private function increment_occurrence( $hash, $table ) {
		global $wpdb;

		$sql = "UPDATE {$table} 
                SET 
                    occurrences = occurrences + 1, 
                    last_occurred = %s,
                    resolved = 0
                WHERE error_hash = %s";
		
		$result = $wpdb->query( 
			$wpdb->prepare( 
				$sql, 
				current_time( 'mysql' ), 
				$hash 
			) 
		);

		return $result > 0;
	}

	/**
	 * Insert new log entry.
	 *
	 * @param array  $data  Log data.
	 * @param string $table Table name.
	 * @return int Inserted ID.
	 */
	private function insert_new( $data, $table ) {
		global $wpdb;

		$wpdb->insert(
			$table,
			array(
				'error_hash'    => $data['error_hash'],
				'type'          => sanitize_text_field( $data['type'] ),
				'message'       => $data['message'], // Already encrypted string
				'file'          => sanitize_text_field( $data['file'] ),
				'line'          => intval( $data['line'] ),
				'occurrences'   => 1,
				'last_occurred' => current_time( 'mysql' ),
				'created_at'    => current_time( 'mysql' ),
				'resolved'      => 0,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d' )
		);

		return $wpdb->insert_id;
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
