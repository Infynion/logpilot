<?php
/**
 * Settings Page.
 *
 * @package    Infynion\LogPilot\Admin
 */

namespace Infynion\LogPilot\Admin;

/**
 * Class SettingsPage
 *
 * Handles registration and rendering of the settings options.
 *
 * @package Infynion\LogPilot\Admin
 */
class SettingsPage {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'infynion_system_logger_group', 'infynion_enable_error_log' );
		register_setting( 'infynion_system_logger_group', 'infynion_error_log_notify' );
		register_setting( 'infynion_system_logger_group', 'infynion_error_log_notify_emails', array(
			'sanitize_callback' => array( $this, 'sanitize_emails' ),
		) );
		register_setting( 'infynion_system_logger_group', 'infynion_error_log_expire' );

		// Section
		add_settings_section(
			'infynion_logger_main_section',
			__( 'Configuration', 'logpilot' ),
			null,
			'logpilot-logs-settings'
		);

		// Fields
		add_settings_field(
			'infynion_enable_error_log',
			__( 'Enable Logging', 'logpilot' ),
			array( $this, 'render_checkbox' ),
			'logpilot-logs-settings',
			'infynion_logger_main_section',
			array( 'label_for' => 'infynion_enable_error_log' )
		);

		add_settings_field(
			'infynion_error_log_notify',
			__( 'Enable Email Notifications', 'logpilot' ),
			array( $this, 'render_checkbox' ),
			'logpilot-logs-settings',
			'infynion_logger_main_section',
			array( 'label_for' => 'infynion_error_log_notify' )
		);

		add_settings_field(
			'infynion_error_log_notify_emails',
			__( 'Notification Emails', 'logpilot' ),
			array( $this, 'render_input' ),
			'logpilot-logs-settings',
			'infynion_logger_main_section',
			array( 'label_for' => 'infynion_error_log_notify_emails', 'description' => 'Comma separated.' )
		);

		add_settings_field(
			'infynion_error_log_expire',
			__( 'Auto-Delete Logs Older Than (Days)', 'logpilot' ),
			array( $this, 'render_number' ),
			'logpilot-logs-settings',
			'infynion_logger_main_section',
			array( 'label_for' => 'infynion_error_log_expire' )
		);
	}

	/**
	 * Sanitize emails.
	 * 
	 * @param string $input Comma separated emails.
	 * @return string
	 */
	public function sanitize_emails( $input ) {
		$emails = explode( ',', $input );
		$valid  = array();
		foreach ( $emails as $email ) {
			$e = sanitize_email( trim( $email ) );
			if ( is_email( $e ) ) {
				$valid[] = $e;
			}
		}
		return implode( ',', $valid );
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_checkbox( $args ) {
		$option = get_option( $args['label_for'] );
		?>
		<input type="checkbox" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="1" <?php checked( 1, $option, true ); ?> />
		<?php
	}

	/**
	 * Render text input field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_input( $args ) {
		$option = get_option( $args['label_for'] );
		?>
		<input type="text" class="regular-text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number input field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_number( $args ) {
		$option = get_option( $args['label_for'], 30 );
		?>
		<input type="number" min="0" step="1" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" />
		<p class="description"><?php esc_html_e( 'Set to 0 to disable auto-cleanup.', 'logpilot' ); ?></p>
		<?php
	}

	/**
	 * Render the settings page HTML.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'infynion_system_logger_group' );
			do_settings_sections( 'logpilot-logs-settings' );
			submit_button();
			?>
		</form>
		<?php
	}
}
