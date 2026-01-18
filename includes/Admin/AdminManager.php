<?php
/**
 * Admin Manager.
 *
 * @package    Infynion\LogPilot\Admin
 */

namespace Infynion\LogPilot\Admin;

/**
 * Class AdminManager
 *
 * Handles the registration of admin menus, scripts, and styles.
 *
 * @package Infynion\LogPilot\Admin
 */
class AdminManager {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		
		// Initialize the settings page logic
		$settings = new SettingsPage();
		$settings->register();
	}

	/**
	 * Add admin menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Logpilot Logs', 'logpilot' ),
			__( 'Logpilot Logs', 'logpilot' ),
			'manage_options',
			'logpilot-logs',
			array( $this, 'render_page' ),
			'dashicons-list-view',
			80
		);
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render_page() {
		// Determine which tab to show: 'logs' or 'settings'
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'logs';

		// Load template
		require LOGPILOT_PATH . 'templates/admin-dashboard.php';
	}

	/**
	 * Render the WP List Table.
	 *
	 * @return void
	 */
	public function render_logs_table() {
		require_once LOGPILOT_PATH . 'includes/Admin/LogListTable.php';
		
		$list_table = new LogListTable();
		$list_table->prepare_items();
		
		?>
		<form method="post">
			<?php
			$list_table->search_box( __( 'Search Logs', 'logpilot' ), 'infynion-log-search' );
			$list_table->display();
			?>
		</form>
		<?php
	}

	/**
	 * Render Single Log Details.
	 *
	 * @param int $log_id Log ID.
	 * @return void
	 */
	public function render_log_details( $log_id ) {
		$model = new \Infynion\LogPilot\Models\LogModel();
		$log   = $model->get_by_id( $log_id );

		if ( ! $log ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Log not found.', 'logpilot' ) . '</p></div>';
			return;
		}

		require LOGPILOT_PATH . 'templates/admin-details.php';
	}
}
