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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_ai_assets' ) );
	}

	/**
	 * Enqueue AI panel assets only on the LogPilot single-log detail page.
	 *
	 * @since 1.1.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_ai_assets( string $hook ): void {
		if ( 'tools_page_logpilot' !== $hook ) {
			return;
		}
		$log_id = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $log_id ) {
			return;
		}

		wp_enqueue_script(
			'logpilot-ai',
			LOGPILOT_URL . 'assets/js/logpilot-ai.js',
			array(),
			LOGPILOT_VERSION,
			true
		);

		wp_localize_script(
			'logpilot-ai',
			'logpilotAI',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'logpilot_ai' ),
				'log_id'      => $log_id,
				'site_env'    => get_option( 'logpilot_site_env', 'production' ),
				'has_api_key' => '' !== logpilot_decrypt_option( 'logpilot_ai_api_key' ),
			)
		);
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

		// AI Analysis settings.
		register_setting(
			'logpilot',
			'logpilot_site_env',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $val ) {
					return in_array( $val, array( 'production', 'staging', 'development' ), true ) ? $val : 'production';
				},
				'default'           => 'production',
			)
		);
		register_setting(
			'logpilot',
			'logpilot_ai_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $val ) {
					return in_array( $val, array( 'anthropic', 'openai', 'gemini' ), true ) ? $val : 'anthropic';
				},
				'default'           => 'anthropic',
			)
		);
		register_setting(
			'logpilot',
			'logpilot_ai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'claude-sonnet-4-6',
			)
		);
		register_setting(
			'logpilot',
			'logpilot_ai_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $val ) {
					$val = sanitize_text_field( $val );
					// If the submitted value is the masked placeholder, keep the existing stored value.
					if ( '' === $val || '••••••••••••••••' === $val ) {
						return get_option( 'logpilot_ai_api_key', '' );
					}
					return logpilot_encrypt_option( $val );
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

		<hr />
		<h2><?php esc_html_e( 'AI Analysis', 'logpilot' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure AI-powered error analysis. The AI will explain captured errors, assess severity, and suggest code fixes.', 'logpilot' ); ?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'logpilot' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Site Environment', 'logpilot' ); ?></th>
					<td>
						<?php
						$current_env = get_option( 'logpilot_site_env', 'production' );
						$envs        = array(
							'production'  => __( 'Production', 'logpilot' ),
							'staging'     => __( 'Staging', 'logpilot' ),
							'development' => __( 'Development', 'logpilot' ),
						);
						foreach ( $envs as $val => $label ) :
							?>
							<label style="margin-right:16px;">
								<input type="radio" name="logpilot_site_env" value="<?php echo esc_attr( $val ); ?>" <?php checked( $current_env, $val ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description" id="logpilot-env-note" style="margin-top:6px;"></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="logpilot_ai_provider"><?php esc_html_e( 'AI Provider', 'logpilot' ); ?></label></th>
					<td>
						<?php
						$current_provider = get_option( 'logpilot_ai_provider', 'anthropic' );
						$providers        = array(
							'anthropic' => __( 'Anthropic (Claude)', 'logpilot' ),
							'openai'    => __( 'OpenAI (GPT) — Pro', 'logpilot' ),
							'gemini'    => __( 'Google Gemini — Pro', 'logpilot' ),
						);
						?>
						<select name="logpilot_ai_provider" id="logpilot_ai_provider">
							<?php foreach ( $providers as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_provider, $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'OpenAI and Gemini providers are available in LogPilot Pro.', 'logpilot' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="logpilot_ai_model"><?php esc_html_e( 'AI Model', 'logpilot' ); ?></label></th>
					<td>
						<?php
						$current_model  = get_option( 'logpilot_ai_model', 'claude-sonnet-4-6' );
						$models_by_prov = array(
							'anthropic' => array(
								'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (Recommended)',
								'claude-opus-4-6'           => 'Claude Opus 4.6 (Most Capable)',
								'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Fastest)',
							),
							'openai'    => array(
								'gpt-4o'       => 'GPT-4o',
								'gpt-4-turbo'  => 'GPT-4 Turbo',
								'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
							),
							'gemini'    => array(
								'gemini-1.5-pro'   => 'Gemini 1.5 Pro',
								'gemini-1.5-flash' => 'Gemini 1.5 Flash',
							),
						);
						foreach ( $models_by_prov as $prov => $models ) :
							$display = ( $prov === $current_provider ) ? '' : 'display:none;';
							?>
							<select name="logpilot_ai_model" id="logpilot_ai_model_<?php echo esc_attr( $prov ); ?>" class="logpilot-model-select" data-provider="<?php echo esc_attr( $prov ); ?>" style="<?php echo esc_attr( $display ); ?>">
								<?php foreach ( $models as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_model, $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="logpilot_ai_api_key"><?php esc_html_e( 'API Key', 'logpilot' ); ?></label></th>
					<td>
						<?php $has_key = '' !== logpilot_decrypt_option( 'logpilot_ai_api_key' ); ?>
						<input type="password" name="logpilot_ai_api_key" id="logpilot_ai_api_key" value="<?php echo $has_key ? esc_attr( '••••••••••••••••' ) : ''; ?>" style="width:360px;" autocomplete="new-password" />
						<?php if ( $has_key ) : ?>
							<span style="color:#46b450;margin-left:8px;">&#10003; <?php esc_html_e( 'Key saved', 'logpilot' ); ?></span>
						<?php endif; ?>
						<p class="description">
							<?php
							$provider_key_links = array(
								'anthropic' => 'https://console.anthropic.com/keys',
								'openai'    => 'https://platform.openai.com/api-keys',
								'gemini'    => 'https://aistudio.google.com/app/apikey',
							);
							$key_link = $provider_key_links[ $current_provider ] ?? '';
							printf(
								/* translators: %s: URL to get API key */
								esc_html__( 'Enter your %s API key. Keys are encrypted before storage and never exposed in the browser.', 'logpilot' ),
								'<a href="' . esc_url( $key_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $providers[ $current_provider ] ?? $current_provider ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save AI Settings', 'logpilot' ) ); ?>
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

				// AI provider/model switcher.
				const providerSelect = document.getElementById('logpilot_ai_provider');
				const modelSelects   = document.querySelectorAll('.logpilot-model-select');
				function syncModelSelect() {
					const chosen = providerSelect.value;
					modelSelects.forEach(function(sel) {
						sel.style.display = (sel.dataset.provider === chosen) ? '' : 'none';
					});
				}
				providerSelect.addEventListener('change', syncModelSelect);

				// Environment description notes.
				const envNotes = {
					production:  'Production — AI suggestions are read-only. No files will ever be modified automatically.',
					staging:     'Staging — File apply is available (Pro) with automatic backup and auto-rollback watchdog.',
					development: 'Development — File apply is available (Pro) with lighter warnings and auto-rollback watchdog.',
				};
				const envNote = document.getElementById('logpilot-env-note');
				function updateEnvNote() {
					const val = document.querySelector('input[name="logpilot_site_env"]:checked');
					envNote.textContent = val ? (envNotes[val.value] || '') : '';
				}
				document.querySelectorAll('input[name="logpilot_site_env"]').forEach(function(r) {
					r.addEventListener('change', updateEnvNote);
				});
				updateEnvNote();
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

		$this->render_ai_panel( $id );
	}

	/**
	 * Render the AI analysis panel below the log detail table.
	 *
	 * @since 1.1.0
	 *
	 * @param int $log_id Log entry ID.
	 * @return void
	 */
	private function render_ai_panel( int $log_id ): void {
		$has_api_key = '' !== logpilot_decrypt_option( 'logpilot_ai_api_key' );
		$site_env    = get_option( 'logpilot_site_env', 'production' );
		?>
		<div id="logpilot-ai-panel" style="margin-top:24px;">
			<h2 style="border-bottom:1px solid #ccc;padding-bottom:8px;">
				<?php esc_html_e( 'AI Error Analysis', 'logpilot' ); ?>
			</h2>

			<?php if ( ! $has_api_key ) : ?>
				<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;border-radius:4px;">
					<strong><?php esc_html_e( 'API Key Required', 'logpilot' ); ?></strong><br />
					<?php
					printf(
						/* translators: %s: URL to the configuration tab */
						esc_html__( 'Configure your AI API key in the %s to enable AI-powered error analysis.', 'logpilot' ),
						'<a href="?page=logpilot&tab=config">' . esc_html__( 'Configuration tab', 'logpilot' ) . '</a>'
					);
					?>
				</div>
			<?php else : ?>
				<div id="logpilot-ai-actions">
					<button type="button" id="logpilot-get-ai-fix" class="button button-primary" style="font-size:14px;height:36px;line-height:36px;padding:0 20px;">
						&#10024; <?php esc_html_e( 'Get AI Fix', 'logpilot' ); ?>
					</button>
					<span id="logpilot-ai-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
				</div>

				<!-- Error Analysis Card -->
				<div id="logpilot-ai-diagnosis" style="display:none;margin-top:20px;">
					<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;">
						<h3 style="margin-top:0;"><?php esc_html_e( 'Error Analysis', 'logpilot' ); ?></h3>
						<div style="margin-bottom:12px;">
							<strong><?php esc_html_e( 'Severity:', 'logpilot' ); ?></strong>
							<span id="logpilot-severity-badge" style="display:inline-block;padding:3px 10px;border-radius:3px;font-weight:700;text-transform:uppercase;font-size:11px;margin-left:6px;"></span>
							<span id="logpilot-confidence-bar" style="display:inline-block;margin-left:16px;font-size:12px;color:#666;"></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Root Cause', 'logpilot' ); ?></strong>
							<p id="logpilot-explanation" style="margin:6px 0 0;color:#444;line-height:1.6;"></p>
						</div>
					</div>
				</div>

				<!-- Suggested Fix Card -->
				<div id="logpilot-ai-fix" style="display:none;margin-top:16px;">
					<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;">
						<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
							<h3 style="margin:0;"><?php esc_html_e( 'Suggested Fix', 'logpilot' ); ?></h3>
							<div>
								<button type="button" id="logpilot-copy-btn" class="button" title="<?php esc_attr_e( 'Copy to clipboard', 'logpilot' ); ?>">
									&#128203; <?php esc_html_e( 'Copy', 'logpilot' ); ?>
								</button>
								<a id="logpilot-download-patch" href="#" class="button" style="margin-left:6px;" title="<?php esc_attr_e( 'Download as .patch file', 'logpilot' ); ?>">
									&#11015; <?php esc_html_e( 'Download .patch', 'logpilot' ); ?>
								</a>
							</div>
						</div>
						<p style="margin:0 0 8px;font-size:12px;color:#666;">
							<strong><?php esc_html_e( 'Target file:', 'logpilot' ); ?></strong>
							<span id="logpilot-target-file" style="font-family:monospace;word-break:break-all;"></span>
						</p>
						<pre id="logpilot-fix-code" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:4px;overflow-x:auto;font-size:13px;line-height:1.5;white-space:pre-wrap;margin:0;"><code></code></pre>
					</div>
				</div>

				<!-- Environment action section -->
				<div id="logpilot-ai-env-section" style="display:none;margin-top:16px;">

					<!-- Production / core-file lock banner -->
					<div id="logpilot-production-banner" style="display:none;background:#f8f8f8;border:1px solid #ddd;border-left:4px solid #dc3232;border-radius:6px;padding:16px 20px;">
						<strong id="logpilot-prod-banner-title">
							&#128274; <?php esc_html_e( 'PRODUCTION SITE — Read Only', 'logpilot' ); ?>
						</strong>
						<p style="margin:8px 0 0;color:#555;font-size:13px;" id="logpilot-prod-banner-msg">
							<?php esc_html_e( 'This is a production environment. File changes are disabled to protect your live site. Copy the fix above and apply it via FTP, SSH, or your code editor.', 'logpilot' ); ?>
						</p>
					</div>

					<!-- Staging/Dev apply section -->
					<div id="logpilot-staging-section" style="display:none;background:#f8f8f8;border:1px solid #ddd;border-left:4px solid #ffb900;border-radius:6px;padding:16px 20px;">
						<strong>&#9888;&#65039; <?php esc_html_e( 'NON-PRODUCTION ENVIRONMENT', 'logpilot' ); ?></strong>
						<p style="margin:8px 0 12px;color:#555;font-size:13px;">
							<?php esc_html_e( 'Applying this fix will modify a server file directly. A full backup is created first. If the site breaks, use the recovery link emailed to you to restore the original file.', 'logpilot' ); ?>
						</p>
						<hr style="border-color:#ddd;" />
						<p style="text-align:center;color:#888;font-size:12px;margin:8px 0;">
							— <?php esc_html_e( 'File apply requires LogPilot Pro', 'logpilot' ); ?> —
						</p>
					</div>

					<!-- Post-apply confirmation timer -->
					<div id="logpilot-applied-section" style="display:none;background:#f0fff4;border:1px solid #46b450;border-radius:6px;padding:16px 20px;">
						<strong>&#9989; <?php esc_html_e( 'Fix applied — please verify your site is working.', 'logpilot' ); ?></strong>
						<p style="margin:8px 0 4px;font-size:13px;color:#555;">
							<?php esc_html_e( 'Auto-rollback in:', 'logpilot' ); ?>
							<strong id="logpilot-countdown" style="font-family:monospace;font-size:16px;margin-left:6px;">05:00</strong>
						</p>
						<div style="margin-top:12px;">
							<button type="button" id="logpilot-confirm-btn" class="button button-primary">
								&#9989; <?php esc_html_e( "Confirm it's working", 'logpilot' ); ?>
							</button>
							<button type="button" id="logpilot-rollback-btn" class="button" style="margin-left:8px;border-color:#dc3232;color:#dc3232;">
								&#8617; <?php esc_html_e( 'Rollback now', 'logpilot' ); ?>
							</button>
						</div>
					</div>

				</div><!-- /#logpilot-ai-env-section -->

				<div id="logpilot-ai-error" style="display:none;margin-top:16px;background:#fef7f7;border:1px solid #dc3232;border-radius:6px;padding:12px 16px;color:#dc3232;"></div>
			<?php endif; ?>
		</div><!-- /#logpilot-ai-panel -->
		<?php
	}
}
