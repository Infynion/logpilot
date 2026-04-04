<?php
/**
 * AI context builder.
 *
 * @package Infynion\Logpilot\Admin\AI
 * @since   1.1.0
 */

namespace Infynion\Logpilot\Admin\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles enriched context from a log row for use in an AI prompt.
 *
 * @since 1.1.0
 */
class ContextBuilder {

	/**
	 * Number of source lines to include around the error line.
	 *
	 * @var int
	 */
	private const EXCERPT_RADIUS = 20;

	/**
	 * Build a context array from a log row.
	 *
	 * @since 1.1.0
	 *
	 * @param array $log Log row as returned by Database::get_log().
	 * @return array Context data ready for prompt assembly.
	 */
	public function build( array $log ): array {
		$payload = $this->decode_payload( $log );

		$file = $log['file'] ?? '';
		$line = (int) ( $log['line'] ?? 0 );
		$type = $log['type'] ?? '';

		$abs_file    = $file ? $this->safe_realpath( $file ) : null;
		$is_core     = $abs_file ? $this->is_core_file( $abs_file ) : false;
		$is_plugin   = $abs_file ? str_starts_with( $abs_file, WP_PLUGIN_DIR ) : false;
		$is_theme    = $abs_file ? str_starts_with( $abs_file, get_theme_root() ) : false;
		$file_excerpt = ( $abs_file && $line > 0 ) ? $this->read_file_excerpt( $abs_file, $line ) : null;

		return array(
			'error_type'    => $type,
			'error_message' => $payload['message'] ?? ( $log['message'] ?? '' ),
			'file_path'     => $file,
			'line'          => $line,
			'stack_trace'   => $payload['trace'] ?? null,
			'file_excerpt'  => $file_excerpt,
			'php_version'   => PHP_VERSION,
			'wp_version'    => get_bloginfo( 'version' ),
			'is_plugin_file' => $is_plugin,
			'is_theme_file'  => $is_theme,
			'is_core_file'   => $is_core,
		);
	}

	/**
	 * Decode the Base64 + JSON message payload stored in the log row.
	 *
	 * @since 1.1.0
	 *
	 * @param array $log Log row.
	 * @return array Decoded payload array.
	 */
	private function decode_payload( array $log ): array {
		$raw = base64_decode( $log['message'] ?? '' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! $raw ) {
			return array();
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Resolve and validate a file path is inside the WordPress root.
	 *
	 * @since 1.1.0
	 *
	 * @param string $path File path to resolve.
	 * @return string|null Absolute path, or null if invalid / outside WP root.
	 */
	private function safe_realpath( string $path ): ?string {
		$resolved = realpath( $path );
		if ( false === $resolved ) {
			return null;
		}
		$wp_root = realpath( ABSPATH );
		if ( false === $wp_root || ! str_starts_with( $resolved, $wp_root ) ) {
			return null;
		}
		return $resolved;
	}

	/**
	 * Determine whether an absolute path points to a WordPress core file.
	 *
	 * @since 1.1.0
	 *
	 * @param string $abs_path Resolved absolute path.
	 * @return bool True if the file is a core file.
	 */
	private function is_core_file( string $abs_path ): bool {
		$protected = array(
			realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR,
			realpath( ABSPATH . 'wp-admin' ) . DIRECTORY_SEPARATOR,
			realpath( ABSPATH . 'wp-config.php' ),
			realpath( ABSPATH . 'wp-settings.php' ),
			realpath( ABSPATH . 'index.php' ),
		);
		foreach ( $protected as $guard ) {
			if ( $guard && ( $abs_path === $guard || str_starts_with( $abs_path, $guard ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Read a window of source lines around the error line.
	 *
	 * Lines are 1-indexed and the error line is flagged with ">>".
	 *
	 * @since 1.1.0
	 *
	 * @param string $abs_path Validated absolute file path.
	 * @param int    $line     1-based error line number.
	 * @param int    $radius   Number of lines before/after to include.
	 * @return string|null Formatted excerpt, or null on read failure.
	 */
	private function read_file_excerpt( string $abs_path, int $line, int $radius = self::EXCERPT_RADIUS ): ?string {
		if ( ! is_readable( $abs_path ) ) {
			return null;
		}

		$lines = file( $abs_path, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			return null;
		}

		$total = count( $lines );
		$start = max( 0, $line - $radius - 1 );
		$end   = min( $total - 1, $line + $radius - 1 );

		$output = array();
		for ( $i = $start; $i <= $end; $i++ ) {
			$num    = $i + 1;
			$marker = ( $num === $line ) ? '>>' : '  ';
			$output[] = sprintf( '%s %4d: %s', $marker, $num, $lines[ $i ] );
		}

		return implode( "\n", $output );
	}

	/**
	 * Assemble the full user-facing prompt string from context data.
	 *
	 * @since 1.1.0
	 *
	 * @param array $context Context array from build().
	 * @return string The prompt string to send to the AI provider.
	 */
	public function build_prompt( array $context ): string {
		$parts = array();

		$parts[] = 'PHP Error Report:';
		$parts[] = '- Type:    ' . $context['error_type'];
		$parts[] = '- Message: ' . $context['error_message'];
		$parts[] = '- File:    ' . $context['file_path'];
		$parts[] = '- Line:    ' . $context['line'];
		$parts[] = '- PHP:     ' . $context['php_version'];
		$parts[] = '- WP:      ' . $context['wp_version'];

		if ( $context['stack_trace'] ) {
			$parts[] = '';
			$parts[] = 'Stack Trace:';
			$parts[] = $context['stack_trace'];
		}

		if ( $context['file_excerpt'] ) {
			$start = max( 1, $context['line'] - self::EXCERPT_RADIUS );
			$end   = $context['line'] + self::EXCERPT_RADIUS;
			$parts[] = '';
			$parts[] = "File excerpt ({$context['file_path']}, lines {$start}–{$end}):";
			$parts[] = '```php';
			$parts[] = $context['file_excerpt'];
			$parts[] = '```';
		}

		return implode( "\n", $parts );
	}
}
