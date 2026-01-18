<?php
/**
 * Logger Service.
 *
 * @package    Infynion\LogPilot\Services
 */

namespace Infynion\LogPilot\Services;

use Infynion\LogPilot\Models\LogModel;
use Infynion\LogPilot\Utils\Encryption;

/**
 * Class LoggerService
 *
 * Handles the logic of preparing log data, encryption, and interacting with the Model.
 *
 * @package Infynion\LogPilot\Services
 */
class LoggerService {

	/**
	 * Log Model instance.
	 *
	 * @var LogModel
	 */
	private $log_model;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_model = new LogModel();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Allows other plugins to write logs via action
		add_action( 'infynion_log', array( $this, 'write' ), 10, 4 );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $message    Log message.
	 * @param string $level      Log level (error, warning, etc.).
	 * @param string $file       File path.
	 * @param int    $line       Line number.
	 * @param bool   $send_email Whether to trigger email notification.
	 * @return void
	 */
	public function write( $message, $level = 'error', $file = null, $line = null, $send_email = true ) {
		if ( ! get_option( 'infynion_enable_error_log', 0 ) ) {
			return;
		}

		// SRP: Logic for hash generation belongs in the Service, not the Model.
		// We use md5 of raw data to ensure uniqueness.
		$raw_message_str = is_scalar( $message ) ? (string) $message : wp_json_encode( $message );
		$error_hash      = hash( 'sha256', "{$level}|{$raw_message_str}|{$file}|{$line}" );

		// Encrypt message
		$encrypted_message = Encryption::encrypt( $message );

		// Contextual Intelligence
		$context = $this->get_context();

		$data = array_merge( array(
			'error_hash' => $error_hash,
			'type'       => $level,
			'message'    => $encrypted_message,
			'file'       => $file,
			'line'       => $line,
		), $context );

		// Delegate DB operation to Model
		$result = $this->log_model->upsert( $data );
		
		// SRP: Event triggering belongs in the Service layer, not the Data layer.
		if ( $result['log_id'] ) {
			do_action( 'infynion/log_saved', $result['log_id'], $result['is_new'], $data );
		}

		// Fire action for notification service
		if ( $send_email && $result['is_new'] ) {
			do_action( 'infynion_log_written_new', $result['log_id'], $level );
		}
	}

	/**
	 * Capture request and user context.
	 *
	 * @return array
	 */
	private function get_context() {
		$context = array(
			'user_id'        => get_current_user_id(),
			'request_uri'    => '',
			'request_method' => '',
			'user_agent'     => '',
			'client_ip'      => '',
		);

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$context['request_uri'] = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$context['request_method'] = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		}

		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// User agent can be long, cut to 255 if needed?? DB is TEXT so it's fine.
			$context['user_agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		// Simple IP check (behind proxies often uses X-Forwarded-For)
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		}
		$context['client_ip'] = sanitize_text_field( wp_unslash( $ip ) );

		return $context;
	}
}
