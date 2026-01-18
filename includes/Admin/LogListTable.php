<?php
/**
 * Log List Table.
 *
 * @package    Infynion\LogPilot\Admin
 */

namespace Infynion\LogPilot\Admin;

use Infynion\LogPilot\Models\LogModel;
use Infynion\LogPilot\Utils\Encryption;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class LogListTable
 *
 * Renders the log list using WP_List_Table.
 *
 * @package Infynion\LogPilot\Admin
 */
class LogListTable extends \WP_List_Table {

	/**
	 * Log Model.
	 *
	 * @var LogModel
	 */
	private $log_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => false,
		) );

		$this->log_model = new LogModel();
	}

	/**
	 * Prepare items for the table.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$user_id = get_current_user_id();
		$per_page = 20;
		$current_page = $this->get_pagenum();
		
		// Fetch data from Model
		// Ideally Model supports LIMIT/OFFSET.
		// For now simple implementation fetching all, then array_slice.
		// TODO: Improve Model to support pagination query for performance.
		$all_items = $this->log_model->get_all( true );
		
		// Sort
		// usort logic if needed. DB already sorts by last_occurred DESC.

		$total_items = count( $all_items );
		$this->items = array_slice( $all_items, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'type'          => __( 'Type', 'logpilot' ),
			'message'       => __( 'Message', 'logpilot' ),
			'file'          => __( 'File', 'logpilot' ),
			'occurrences'   => __( 'Occurrences', 'logpilot' ),
			'last_occurred' => __( 'Last Occurred', 'logpilot' ),
			'status'        => __( 'Status', 'logpilot' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'last_occurred' => array( 'last_occurred', false ),
			'type'          => array( 'type', false ),
		);
	}

	/**
	 * Column: checkbox.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="log[]" value="%s" />',
			$item['id']
		);
	}

	/**
	 * Column: type.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_type( $item ) {
		$type = strtoupper( $item['type'] );
		$color = '#72aee6';
		if ( strpos( $type, 'ERROR' ) !== false || strpos( $type, 'FATAL' ) !== false ) {
			$color = '#d63638';
		} elseif ( strpos( $type, 'WARNING' ) !== false ) {
			$color = '#dba617';
		}

		return sprintf( '<span style="color: %s; font-weight: bold;">%s</span>', esc_attr( $color ), esc_html( $type ) );
	}

	/**
	 * Column: message.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_message( $item ) {
		$decrypted = Encryption::decrypt( $item['message'] );
		
		// If explicitly array, format nicely
		if ( is_array( $decrypted ) ) {
			$decrypted = wp_json_encode( $decrypted, JSON_PRETTY_PRINT );
		}
		
		$short_msg = substr( (string) $decrypted, 0, 100 ) . ( strlen( (string) $decrypted ) > 100 ? '...' : '' );
		
		// Row actions
		$actions = array(
			'view' => sprintf( 
				'<a href="?page=%s&action=%s&log_id=%s">%s</a>', 
				$_REQUEST['page'], 
				'view', 
				$item['id'], 
				__( 'View Details', 'logpilot' ) 
			),
		);

		return '<code>' . esc_html( $short_msg ) . '</code>' . $this->row_actions( $actions );
	}

	/**
	 * Column: file.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_file( $item ) {
		$file = $item['file'];
		$line = $item['line'];
		if ( empty( $file ) ) {
			return '-';
		}
		return sprintf( '<small>%s:%d</small>', esc_html( basename( $file ) ), intval( $line ) );
	}

	/**
	 * Column: occurrences.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_occurrences( $item ) {
		return intval( $item['occurrences'] );
	}

	/**
	 * Column: last_occurred.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_last_occurred( $item ) {
		return esc_html( $item['last_occurred'] );
	}

	/**
	 * Column: status.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_status( $item ) {
		return $item['resolved'] == 1 ? '<span class="dashicons dashicons-yes" style="color:green;"></span>' : '<span class="dashicons dashicons-no" style="color:red;"></span>';
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'mark_resolved'   => __( 'Mark Resolved', 'logpilot' ),
			'mark_unresolved' => __( 'Mark Unresolved', 'logpilot' ),
			'delete'          => __( 'Delete', 'logpilot' ),
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		$logs   = isset( $_POST['log'] ) ? array_map( 'intval', $_POST['log'] ) : array();

		if ( empty( $logs ) ) {
			return;
		}

		if ( 'delete' === $action ) {
			$this->log_model->delete( $logs );
		}

		if ( 'mark_resolved' === $action ) {
			$this->log_model->mark_resolved( $logs, 1 );
		}

		if ( 'mark_unresolved' === $action ) {
			$this->log_model->mark_resolved( $logs, 0 );
		}
	}
}
