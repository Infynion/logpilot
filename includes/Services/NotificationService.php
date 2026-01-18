<?php
/**
 * Notification Service.
 *
 * @package    Infynion\LogPilot\Services
 */

namespace Infynion\LogPilot\Services;

use Infynion\LogPilot\Models\LogModel;

/**
 * Class NotificationService
 *
 * Handles sending email notifications for system logs.
 *
 * @package Infynion\LogPilot\Services
 */
class NotificationService {

	/**
	 * Log Model.
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
		// Event triggered by LoggerService when a new log is written.
		// NOTE: In WP, hooks are global.
		add_action( 'infynion_log_written_new', array( $this, 'maybe_notify' ), 10, 2 );

		// Weekly reminder cron.
		add_action( 'infynion_weekly_log_reminder', array( $this, 'send_weekly_summary' ) );
	}

	/**
	 * Maybe send notification for a new log.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $level  Log Level.
	 * @return void
	 */
	public function maybe_notify( $log_id, $level ) {
		if ( ! get_option( 'infynion_error_log_notify', 0 ) ) {
			return;
		}

		$immediate_levels = array( 'error', 'parse', 'core_error', 'compile_error', 'user_error', 'exception', 'fatal' );

		if ( in_array( $level, $immediate_levels, true ) ) {
			// Send instantly.
			$this->send_notification( $log_id, $level );
		}
	}

	/**
	 * Send immediate notification.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $level  Log Level.
	 * @return void
	 */
	private function send_notification( $log_id, $level ) {
		$to = $this->get_recipients();
		if ( empty( $to ) ) {
			return;
		}

		$subject   = sprintf( '🚨 System Alert: [%s] on %s', strtoupper( $level ), get_bloginfo( 'name' ) );
		$log_url   = admin_url( 'admin.php?page=logpilot-logs&action=view&log_id=' . $log_id );
		$site_name = get_bloginfo( 'name' );

		ob_start();
		require LOGPILOT_PATH . 'templates/email-notification.php';
		$message = ob_get_clean();

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Send weekly summary.
	 *
	 * @return void
	 */
	public function send_weekly_summary() {
		// Logic to count logs from the last week.
		// For brevity, we just nudge admin.
		$to = $this->get_recipients();
		if ( empty( $to ) ) {
			return;
		}

		$subject = sprintf( 'System Log Weekly Reminder - %s', get_bloginfo( 'name' ) );
		$url     = admin_url( 'admin.php?page=logpilot-logs' );

		$message  = '<p>Hello Admin,</p>';
		$message .= '<p>This is your weekly reminder to check the Logpilot Logs.</p>';
		$message .= sprintf( '<p><a href="%s">View All Logs</a></p>', esc_url( $url ) );

		$this->send_email( $to, $subject, $message );
	}

	/**
	 * Get recipients list.
	 *
	 * @return array
	 */
	private function get_recipients() {
		$admin_email = get_option( 'admin_email' );
		$emails_str  = get_option( 'infynion_error_log_notify_emails', '' );
		$emails      = explode( ',', $emails_str );
		$valid       = array();

		foreach ( $emails as $email ) {
			$email = sanitize_email( trim( $email ) );
			if ( is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		// Fallback to admin email if list is empty
		if ( empty( $valid ) ) {
			$valid[] = $admin_email;
		}

		return array_unique( $valid );
	}

	/**
	 * Wrapper for wp_mail.
	 *
	 * @param array|string $to      Recipient(s).
	 * @param string       $subject Subject.
	 * @param string       $message Message body.
	 * @return bool
	 */
	private function send_email( $to, $subject, $message ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		return wp_mail( $to, $subject, $message, $headers );
	}
}
