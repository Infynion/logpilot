<?php
/**
 * Template: Admin Dashboard Wrapper.
 *
 * @package Infynion\LogPilot\Templates
 * @var string $tab Current tab.
 * @var object $this AdminManager instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Logpilot', 'logpilot' ); ?></h1>
	<nav class="nav-tab-wrapper">
		<a href="?page=logpilot-logs&tab=logs" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Logs', 'logpilot' ); ?></a>
		<a href="?page=logpilot-logs&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'logpilot' ); ?></a>
	</nav>

	<div class="infynion-logs-content" style="margin-top: 20px;">
		<?php
		if ( $tab === 'settings' ) {
			$settings = new \Infynion\LogPilot\Admin\SettingsPage();
			// We could also template the settings form container if we wanted, 
			// but the render method calls WP functions that output directly.
			$settings->render();
		} else {
			// Check for 'view' action
			$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
			if ( 'view' === $action && isset( $_GET['log_id'] ) ) {
				$this->render_log_details( (int) $_GET['log_id'] );
			} else {
				$this->render_logs_table();
			}
		}
		?>
	</div>
</div>
