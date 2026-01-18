<?php
/**
 * Logger Service.
 *
 * @package    Infynion\SystemLogger\Services
 */

namespace Infynion\SystemLogger\Services;

use Infynion\SystemLogger\Models\LogModel;
use Infynion\SystemLogger\Utils\Encryption;

/**
 * Class LoggerService
 *
 * Handles the logic of preparing log data, encryption, and interacting with the Model.
 *
 * @package Infynion\SystemLogger\Services
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

		// Calculate hash BEFORE encryption to ensure deduplication works.
		// We use md5 of raw data.
		$raw_message_str = is_scalar( $message ) ? (string) $message : wp_json_encode( $message );
		$error_hash      = hash( 'sha256', "{$level}|{$raw_message_str}|{$file}|{$line}" );

		// Encrypt message
		$encrypted_message = Encryption::encrypt( $message );

		$data = array(
			'error_hash' => $error_hash,
			'type'       => $level,
			'message'    => $encrypted_message,
			'file'       => $file,
			'line'       => $line,
		);

		$result = $this->log_model->insert_or_increment( $data );
		
		// Fire action for notification service
		if ( $send_email && $result['is_new'] ) {
			do_action( 'infynion_log_written_new', $result['log_id'], $level );
		}
	}
}
