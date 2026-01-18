<?php
/**
 * Template: Email Notification.
 *
 * @package Infynion\LogPilot\Templates
 * @var string $level Log Level.
 * @var string $site_name Site Name.
 * @var string $log_url URL to view the log.
 * @var string $message Optional custom message (for weekly).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php printf( esc_html__( 'System Alert: %s', 'logpilot' ), esc_html( $level ) ); ?></title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333;">
	<div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
		<h2 style="color: #d63638;"><?php esc_html_e( 'System Alert', 'logpilot' ); ?>: <?php echo esc_html( strtoupper( $level ) ); ?></h2>
		
		<p><?php printf( esc_html__( 'Hello Admin, a new log entry has been recorded on %s.', 'logpilot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ); ?></p>
		
		<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #d63638; margin: 20px 0;">
			<p style="margin: 0;"><strong><?php esc_html_e( 'Log Level', 'logpilot' ); ?>:</strong> <?php echo esc_html( strtoupper( $level ) ); ?></p>
		</div>

		<p>
			<a href="<?php echo esc_url( $log_url ); ?>" style="background-color: #0073aa; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;">
				<?php esc_html_e( 'View Log Details', 'logpilot' ); ?>
			</a>
		</p>
		
		<hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
		
		<p style="font-size: 12px; color: #777;">
			<?php printf( esc_html__( 'Sent by %s Logpilot', 'logpilot' ), esc_html( $site_name ) ); ?>
		</p>
	</div>
</body>
</html>
