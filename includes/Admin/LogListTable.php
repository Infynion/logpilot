<?php
/**
 * Log Data List Table view.
 *
 * @package Infynion\Logpilot
 * @since   1.0.0
 */

namespace Infynion\Logpilot\Admin;

use Infynion\Logpilot\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List Table for Logpilot entries.
 *
 * @since 1.0.0
 */
class LogListTable extends \WP_List_Table {

	/**
	 * Injected Database controller.
	 *
	 * @var Database
	 * @since 1.0.0
	 */
	private Database $database;

	/**
	 * Default items limit per page.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $per_page = 20;

	/**
	 * Constructor to attach table requirements.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database DB provider.
	 */
	public function __construct( Database $database ) {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
		$this->database = $database;
	}

	/**
	 * Determine data columns available natively.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'            => '<input type="checkbox" />',
			'type'          => __( 'Type', 'logpilot' ),
			'message'       => __( 'Message', 'logpilot' ),
			'occurrences'   => __( 'Occurrences', 'logpilot' ),
			'last_occurred' => __( 'Last Occurred', 'logpilot' ),
		);
	}

	/**
	 * Checkbox rendering for bulk actions context helper.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object $item Database record iteration logic item.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * Iterative table items logic map parser format values.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object $item        Item mapping.
	 * @param string       $column_name Active col property pointer name.
	 * @return string Formatting schema result representation object data array subset.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'occurrences':
			case 'last_occurred':
				return esc_html( $item[ $column_name ] );
			case 'type':
				$type = esc_html( $item['type'] );
				$url  = add_query_arg( array( 'log_id' => $item['id'] ), menu_page_url( 'logpilot', false ) );
				$view = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'View', 'logpilot' ) );
				return sprintf( '<strong>%s</strong><div class="row-actions"><span class="view">%s</span></div>', $type, $view );
			case 'message':
				$text = wp_strip_all_tags( "{$item['file']}:{$item['line']}" );
				return esc_html( strlen( $text ) > 50 ? substr( $text, 0, 50 ) . '…' : $text );
			default:
				return '';
		}
	}

	/**
	 * Obtain allowed arrays representation keys standard formats object bulk items operations filter mappings defaults default.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'delete' => __( 'Delete', 'logpilot' ),
		);
	}

	/**
	 * Route trigger matching target list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		if ( 'delete' === $this->current_action() && ! empty( $_POST['log_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ids = array_map( 'intval', (array) $_POST['log_ids'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->database->delete_logs( $ids );
		}
	}

	/**
	 * Load arrays from external source filter schema structure default lists mapping targets items query build object parameters table items list logic process mapping parsing schema targets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;

		$this->process_bulk_action();

		$table        = $wpdb->prefix . esc_sql( $this->database->table_name );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $this->per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe (esc_sql); live log counts must not be cached.
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Table name uses esc_sql(); live paginated log data must not be served from cache.
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY last_occurred DESC LIMIT %d OFFSET %d",
				$this->per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			)
		);
	}
}
