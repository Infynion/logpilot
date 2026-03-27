<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
// Define missing constants
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	abstract class WP_List_Table {
		public function __construct( $args = [] ) {}
		public function display() {}
		public function prepare_items() {}
		protected function get_pagenum() { return 1; }
		protected function set_pagination_args( $args ) {}
		public function current_action() { return $_REQUEST['action'] ?? ''; }
		protected function get_sortable_columns() { return []; }
	}
}
