<?php
/**
 * Admin UI handler file.
 *
 * @package Infynion\Logpilot
 * @since   1.0.0
 */

namespace Infynion\Logpilot\Admin;

use Infynion\Logpilot\Database;
use Infynion\Logpilot\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the Admin UI for Logpilot settings and log viewing.
 *
 * @since 1.0.0
 */
class UI {

	/**
	 * Database handler.
	 *
	 * @var Database
	 * @since 1.0.0
	 */
	private Database $database;

	/**
	 * Logger provider.
	 *
	 * @var Logger
	 * @since 1.0.0
	 */
	private Logger $logger;

	/**
	 * Constructor to inject dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database DB integration handler.
	 * @param Logger   $logger   Main logic controller.
	 */
	public function __construct( Database $database, Logger $logger ) {
		$this->database = $database;
		$this->logger   = $logger;
	}

	/**
	 * Register UI rendering hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add administration tools page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_management_page(
			__( 'Logpilot', 'logpilot' ),
			__( 'Logpilot', 'logpilot' ),
			'manage_options',
			'logpilot',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Assign configurable option nodes against WP settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'logpilot',
			'logpilot_enable',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			)
		);
		register_setting(
			'logpilot',
			'logpilot_expire',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 7,
			)
		);
		register_setting(
			'logpilot',
			'logpilot_notify',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'logpilot',
			'logpilot_notify_emails',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $val ) {
					$emails = array_map( 'sanitize_email', array_map( 'trim', explode( ',', $val ) ) );
					return implode( ',', array_filter( $emails ) );
				},
				'default'           => '',
			)
		);
	}

	/**
	 * Execute primary UI templates for log view navigation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'logpilot' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'logs'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log_id     = isset( $_GET['log_id'] ) ? absint( wp_unslash( $_GET['log_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Logpilot', 'logpilot' ) . '</h1>';

		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="?page=logpilot&tab=logs" class="nav-tab ' . ( 'logs' === $active_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Logs', 'logpilot' ) . '</a>';
		echo '<a href="?page=logpilot&tab=config" class="nav-tab ' . ( 'config' === $active_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Configuration', 'logpilot' ) . '</a>';
		echo '</h2>';

		if ( 'config' === $active_tab ) {
			$this->render_config();
		} elseif ( $log_id ) {
			$this->render_single_log( $log_id );
		} else {
			$this->render_log_list();
		}

		echo '</div>';
	}

	/**
	 * Config markup block mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_config(): void {
		$admin_email = get_option( 'admin_email' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'logpilot' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Enable Logging', 'logpilot' ); ?></th>
					<td>
						<input type="checkbox" id="enable_error_log" name="logpilot_enable" value="1" <?php checked( get_option( 'logpilot_enable', 1 ), 1 ); ?> />
						<p class="description"><?php esc_html_e( 'Toggle to enable error interception and logging globally.', 'logpilot' ); ?></p>
					</td>
				</tr>
				<tr valign="top" class="error-log-settings">
					<th scope="row"><?php esc_html_e( 'Error Log Expire Date (days)', 'logpilot' ); ?></th>
					<td>
						<input type="number" name="logpilot_expire" min="0" value="<?php echo esc_attr( get_option( 'logpilot_expire', 7 ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Enter 0 for no expiration. Default is 7 days.', 'logpilot' ); ?></p>
					</td>
				</tr>
				<tr valign="top" class="error-log-settings">
					<th scope="row"><?php esc_html_e( 'Notify by Email', 'logpilot' ); ?></th>
					<td>
						<input type="checkbox" id="error_log_notify" name="logpilot_notify" value="1" <?php checked( get_option( 'logpilot_notify', 0 ), 1 ); ?> />
						<p class="description"><?php esc_html_e( 'Send email notification when a new error log is recorded.', 'logpilot' ); ?></p>
					</td>
				</tr>
				<tr valign="top" class="error-log-email">
					<th scope="row"><?php esc_html_e( 'Notification Emails', 'logpilot' ); ?></th>
					<td>
						<input type="text" name="logpilot_notify_emails" value="<?php echo esc_attr( get_option( 'logpilot_notify_emails', '' ) ); ?>" placeholder="comma-separated emails; default: <?php echo esc_attr( sanitize_email( (string) $admin_email ) ); ?>" style="width:100%;" />
						<p class="description"><?php esc_html_e( 'Emails to notify when an error log is created.', 'logpilot' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'logpilot' ) ); ?>
		</form>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				function toggleFields() {
					const enableError = document.getElementById('enable_error_log').checked;
					document.querySelectorAll('.error-log-settings').forEach(el => el.style.display = enableError ? 'table-row' : 'none');
					const notifyError = document.getElementById('error_log_notify').checked;
					document.querySelectorAll('.error-log-email').forEach(el => el.style.display = (enableError && notifyError) ? 'table-row' : 'none');
				}
				toggleFields();
				document.getElementById('enable_error_log').addEventListener('change', toggleFields);
				document.getElementById('error_log_notify').addEventListener('change', toggleFields);
			});
		</script>
		<?php
	}

	/**
	 * Invoke WP_List_Table controller.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_log_list(): void {
		$table = new LogListTable( $this->database );
		$table->prepare_items();
		echo '<form method="post">';
		$table->display();
		echo '</form>';
	}

	/**
	 * Outputs single entity log viewer screen UI.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Database entry identifier.
	 * @return void
	 */
	private function render_single_log( int $id ): void {
		$log = $this->database->get_log( $id );
		if ( ! $log ) {
			echo '<p>' . esc_html__( 'Log not found.', 'logpilot' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html__( 'Log Details', 'logpilot' ) . ' (' . esc_html( $log['type'] ) . ')</h3>';
		echo '<p><a href="?page=logpilot" class="button">&laquo; ' . esc_html__( 'Back to Logs', 'logpilot' ) . '</a></p>';

		echo '<table class="widefat striped">';
		echo '<tr><th>' . esc_html__( 'Occurrences', 'logpilot' ) . '</th><td>' . esc_html( $log['occurrences'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Last Occurred', 'logpilot' ) . '</th><td>' . esc_html( $log['last_occurred'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'File', 'logpilot' ) . '</th><td>' . esc_html( $log['file'] ) . ':' . esc_html( $log['line'] ) . '</td></tr>';

		$decoded = base64_decode( $log['message'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$json    = json_decode( $decoded, true );

		echo '<tr><th>' . esc_html__( 'Raw Payload', 'logpilot' ) . '</th><td><pre>' . esc_html( $json ? wp_json_encode( $json, JSON_PRETTY_PRINT ) : $decoded ) . '</pre></td></tr>';
		echo '</table>';
	}
}
