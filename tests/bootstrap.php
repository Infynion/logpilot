<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
// Define missing constants
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

// esc_sql is called from within namespaced production code.
// Brain\Monkey cannot stub it early enough because it is
// resolved during class loading; define it as a plain global.
if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( $data ) {
		return $data;
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	abstract class WP_List_Table {
		public $_column_headers = [];
		public $items           = [];

		public function __construct( $args = [] ) {}
		public function display() {}
		public function prepare_items() {}
		protected function get_pagenum() { return 1; }
		protected function set_pagination_args( $args ) {}
		public function current_action() { return $_REQUEST['action'] ?? ''; }
		protected function get_sortable_columns() { return []; }
	}
}
