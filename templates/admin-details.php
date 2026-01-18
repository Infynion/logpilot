<?php
/**
 * Template: Log Details View.
 *
 * @package Infynion\SystemLogger\Templates
 * @var array $log Log entry data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=logpilot-logs&tab=logs' );
?>
<div class="infynion-log-details">
	<p>
		<a href="<?php echo esc_url( $back_url ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Logs', 'logpilot' ); ?></a>
	</p>

	<div class="card" style="max-width: 100%; margin-top: 20px;">
		<h2><?php printf( esc_html__( 'Log #%d Details', 'logpilot' ), intval( $log['id'] ) ); ?></h2>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Type/Level', 'logpilot' ); ?></th>
					<td><span class="badge badge-<?php echo esc_attr( $log['type'] ); ?>"><?php echo esc_html( strtoupper( $log['type'] ) ); ?></span></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Date', 'logpilot' ); ?></th>
					<td><?php echo esc_html( $log['last_occurred'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'File', 'logpilot' ); ?></th>
					<td><code><?php echo esc_html( $log['file'] ); ?>:<?php echo intval( $log['line'] ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Occurrences', 'logpilot' ); ?></th>
					<td><?php echo intval( $log['occurrences'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Message', 'logpilot' ); ?></th>
					<td>
						<?php
						$decrypted = \Infynion\SystemLogger\Utils\Encryption::decrypt( $log['message'] );
						if ( is_array( $decrypted ) || is_object( $decrypted ) ) {
							echo '<pre>' . esc_html( wp_json_encode( $decrypted, JSON_PRETTY_PRINT ) ) . '</pre>';
						} else {
							echo '<pre>' . esc_html( $decrypted ) . '</pre>';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
