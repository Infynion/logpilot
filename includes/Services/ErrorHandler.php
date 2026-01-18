<?php
/**
 * Error Handler Service.
 *
 * @package    Infynion\SystemLogger\Services
 */

namespace Infynion\SystemLogger\Services;

/**
 * Class ErrorHandler
 *
 * Registers global PHP error, exception, and shutdown handlers.
 *
 * @package Infynion\SystemLogger\Services
 */
class ErrorHandler {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'init_handlers' ) );
	}

	/**
	 * Initialize handlers.
	 *
	 * @return void
	 */
	public function init_handlers() {
		// Only run if logging enabled.
		if ( ! get_option( 'infynion_enable_error_log', 0 ) ) {
			return;
		}
		error_log('here-1111');

		set_error_handler( array( $this, 'handle_error' ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
		register_shutdown_function( array( $this, 'handle_shutdown' ) );
	}

	/**
	 * Handle PHP errors.
	 *
	 * @param int    $errno   Error number.
	 * @param string $errstr  Error message.
	 * @param string $errfile File path.
	 * @param int    $errline Line number.
	 * @return bool
	 */
	public function handle_error( $errno, $errstr, $errfile, $errline ) {
		$log_levels = array(
			E_ERROR             => 'error',
			E_WARNING           => 'warning',
			E_PARSE             => 'parse',
			E_NOTICE            => 'notice',
			E_CORE_ERROR        => 'core_error',
			E_CORE_WARNING      => 'core_warning',
			E_COMPILE_ERROR     => 'compile_error',
			E_COMPILE_WARNING   => 'compile_warning',
			E_USER_ERROR        => 'user_error',
			E_USER_WARNING      => 'user_warning',
			E_RECOVERABLE_ERROR => 'recoverable_error',
			E_USER_NOTICE       => 'user_notice',
			E_DEPRECATED        => 'deprecated',
			E_USER_DEPRECATED   => 'user_deprecated',
			E_STRICT            => 'improvement',
		);
		error_log($errno);

		$level = isset( $log_levels[ $errno ] ) ? $log_levels[ $errno ] : 'error';

		// Use the LoggerService via binding or singleton?
		// Since we are in the same 'system', we can instantiate or use a facade.
		// For detailed SOA, dependency injection is best, but here we instantiate newly or use global.
		// NOTE: ErrorHandler is called by PHP, context might be fragile.
		// Safest is to instantiate LoggerService freshly to avoid state pollution, 
		// but ensure no heavy loading. Service is lightweight.
		try {
			$logger = new LoggerService();
			$logger->write( $errstr, $level, $errfile, $errline );
		} catch ( \Throwable $e ) {
			// Do nothing to avoid loops.
		}

		return false; // Continue normal WP error handling.
	}

	/**
	 * Handle Exceptions.
	 *
	 * @param \Throwable $exception Exception object.
	 * @return void
	 */
	public function handle_exception( $exception ) {
		try {
			$logger = new LoggerService();
			$logger->write(
				$exception->getMessage(),
				'exception',
				$exception->getFile(),
				$exception->getLine()
			);
		} catch ( \Throwable $e ) {
			// Do nothing.
		}
	}

	/**
	 * Handle Shutdown (Fatal Errors).
	 *
	 * @return void
	 */
	public function handle_shutdown() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			try {
				$logger = new LoggerService();
				$logger->write(
					$error['message'],
					'fatal',
					$error['file'],
					$error['line']
				);
			} catch ( \Throwable $e ) {
				// Do nothing.
			}
		}
	}
}
