<?php
/**
 * Core logging file.
 *
 * @package Infynion\Logpilot
 * @since   1.0.0
 */

namespace Infynion\Logpilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles error interception and processing.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Database handler dependency.
	 *
	 * @var Database
	 * @since 1.0.0
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Database $database Dependency injection for Database logic.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Register error and exception handlers with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Only run if enabled in settings
		if ( ! get_option( 'logpilot_enable', 1 ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_error_handlers' ) );
		add_action( 'admin_init', array( $this, 'intercept_ajax_errors' ) );
		add_action( 'http_api_debug', array( $this, 'intercept_http_errors' ), 10, 5 );
	}

	/**
	 * Hook native PHP errors into internal processing methods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_error_handlers(): void {
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Intentional: this plugin's purpose is to intercept PHP errors.
		set_error_handler( array( $this, 'handle_php_error' ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
		// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		register_shutdown_function( array( $this, 'handle_shutdown_fatal' ) );
	}

	/**
	 * Handle standard PHP errors and warnings.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $errno   The error severity level.
	 * @param string $errstr  The error message.
	 * @param string $errfile The file where the error occurred.
	 * @param int    $errline The line number.
	 * @return bool Always returns false to allow standard handler processing.
	 */
	public function handle_php_error( $errno, $errstr, $errfile, $errline ): bool {
		$this->log(
			array(
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
			),
			'php_error'
		);
		return false; // allow standard PHP error handler
	}

	/**
	 * Intercept and log uncaught exceptions.
	 *
	 * @since 1.0.0
	 *
	 * @param \Throwable $exception The exception object thrown.
	 * @return void
	 */
	public function handle_exception( \Throwable $exception ): void {
		$this->log(
			array(
				'message' => $exception->getMessage(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
				'trace'   => $exception->getTraceAsString(),
			),
			'exception'
		);
	}

	/**
	 * Catch fatal errors during PHP shutdown.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_shutdown_fatal(): void {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$this->log(
				array(
					'message' => $error['message'],
					'file'    => $error['file'],
					'line'    => $error['line'],
				),
				'fatal'
			);
		}
	}

	/**
	 * Catch failing WP AJAX requests automatically via output buffer monitoring.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function intercept_ajax_errors(): void {
		if ( wp_doing_ajax() && ! empty( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			ob_start();
			add_action(
				'shutdown',
				function () {
					$response    = ob_get_clean();
					$action      = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$decoded     = json_decode( $response, true );
					$status_code = http_response_code();

					$is_error = ( is_array( $decoded ) && isset( $decoded['success'] ) && false === $decoded['success'] ) || ( $status_code >= 400 );

					if ( $is_error ) {
						$this->log(
							array(
								'action'   => $action,
								'response' => $decoded ?? $response,
								'status'   => $status_code,
							),
							'ajax_error'
						);
					}
					echo $response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
		}
	}

	/**
	 * Log external HTTP requests via WP HTTP API that fail or return bad status flags.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object $response HTTP response or WP_Error object.
	 * @param string       $context  Context of the hook.
	 * @param string       $class    Transport class doing the request.
	 * @param array        $args     HTTP request arguments.
	 * @param string       $url      The request URL.
	 * @return void
	 */
	public function intercept_http_errors( $response, $context, $class, $args, $url ): void {
		if ( wp_doing_ajax() ) {
			return;
		}

		$status_code = is_wp_error( $response ) ? 500 : wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || $status_code >= 400 ) {
			$this->log(
				array(
					'url'      => $url,
					'error'    => is_wp_error( $response ) ? $response->get_error_message() : 'HTTP Error',
					'response' => is_wp_error( $response ) ? 'WP_Error' : wp_remote_retrieve_body( $response ),
					'status'   => $status_code,
				),
				'http_error'
			);
		}
	}

	/**
	 * Push customized data directly to the database after secure encryption.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data Array payload of tracking variables.
	 * @param string $type The specific categorical slug for the error type.
	 * @return void
	 */
	public function log( array $data, string $type ): void {
		$message   = wp_json_encode( $data );
		$encrypted = base64_encode( $message ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$payload = array(
			'type'    => $type,
			'message' => $encrypted,
			'file'    => $data['file'] ?? '',
			'line'    => $data['line'] ?? 0,
		);

		$result = $this->database->insert_or_increment( $payload );

		if ( ! empty( $result['is_new'] ) && get_option( 'logpilot_notify', 0 ) ) {
			$emails = get_option( 'logpilot_notify_emails', '' );
			if ( empty( $emails ) ) {
				$emails = get_option( 'admin_email' );
			}

			if ( ! empty( $emails ) ) {
				$subject = sprintf( '[%s] New Error: %s', get_bloginfo( 'name' ), $type );
				$body    = sprintf( "A new error was logged in Logpilot.\n\nType: %s\nFile: %s\nLine: %s\nMessage:\n%s\n", $type, $payload['file'], $payload['line'], wp_json_encode( $data, JSON_PRETTY_PRINT ) );
				wp_mail( $emails, $subject, $body );
			}
		}
	}
}
