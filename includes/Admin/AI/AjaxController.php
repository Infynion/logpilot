<?php
/**
 * AJAX controller for AI analysis endpoints.
 *
 * @package Infynion\Logpilot\Admin\AI
 * @since   1.1.0
 */

namespace Infynion\Logpilot\Admin\AI;

use Infynion\Logpilot\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all wp_ajax_logpilot_* actions for AI analysis.
 *
 * @since 1.1.0
 */
class AjaxController {

	/**
	 * Database handler.
	 *
	 * @var Database
	 * @since 1.1.0
	 */
	private Database $database;

	/**
	 * AI analyser.
	 *
	 * @var AIAnalyser
	 * @since 1.1.0
	 */
	private AIAnalyser $analyser;

	/**
	 * Context builder.
	 *
	 * @var ContextBuilder
	 * @since 1.1.0
	 */
	private ContextBuilder $context_builder;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param Database       $database        Database handler.
	 * @param AIAnalyser     $analyser        AI analyser.
	 * @param ContextBuilder $context_builder Context builder.
	 */
	public function __construct(
		Database $database,
		AIAnalyser $analyser,
		ContextBuilder $context_builder
	) {
		$this->database        = $database;
		$this->analyser        = $analyser;
		$this->context_builder = $context_builder;
	}

	/**
	 * Register all AJAX hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_logpilot_get_suggestion', array( $this, 'handle_get_suggestion' ) );
		add_action( 'wp_ajax_logpilot_get_patch', array( $this, 'handle_get_patch' ) );
	}

	/**
	 * Handle request to fetch (or generate) an AI suggestion for a log entry.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_get_suggestion(): void {
		$this->verify_request();

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'logpilot' ) ), 400 );
		}

		$log = $this->database->get_log( $log_id );
		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log entry not found.', 'logpilot' ) ), 404 );
		}

		// Build context and prompt.
		$context = $this->context_builder->build( $log );
		$prompt  = $this->context_builder->build_prompt( $context );

		// Deduplicate — reuse a cached suggestion for the same prompt.
		$prompt_hash = hash( 'sha256', $prompt );
		$existing    = $this->database->get_suggestion_for_log( $log_id, $prompt_hash );
		if ( $existing ) {
			wp_send_json_success( $this->format_suggestion( $existing ) );
		}

		/**
		 * Fires before an AI request is sent.
		 *
		 * @since 1.1.0
		 *
		 * @param int   $log_id  Log entry ID.
		 * @param array $context Context array from ContextBuilder::build().
		 */
		do_action( 'logpilot_before_ai_request', $log_id, $context );

		$suggestion = $this->analyser->analyse( $context, $prompt );

		if ( is_wp_error( $suggestion ) ) {
			wp_send_json_error(
				array( 'message' => $suggestion->get_error_message() ),
				500
			);
		}

		/**
		 * Fires after an AI response is received (before storage).
		 *
		 * @since 1.1.0
		 *
		 * @param int   $log_id     Log entry ID.
		 * @param array $suggestion Parsed suggestion array.
		 */
		do_action( 'logpilot_after_ai_response', $log_id, $suggestion );

		// Persist the suggestion.
		$insert_id = $this->database->insert_suggestion(
			array(
				'log_id'         => $log_id,
				'prompt_hash'    => $prompt_hash,
				'severity'       => $suggestion['severity'],
				'explanation'    => $suggestion['explanation'],
				'suggested_code' => $suggestion['suggested_code'],
				'target_file'    => $suggestion['target_file'],
				'docblock'       => $suggestion['docblock'] ?? '',
				'is_core_file'   => $suggestion['is_core_file'] ? 1 : 0,
				'confidence'     => (int) ( $suggestion['confidence'] ?? 50 ),
				'status'         => 'ready',
			)
		);

		if ( ! $insert_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save suggestion.', 'logpilot' ) ), 500 );
		}

		$stored = $this->database->get_suggestion( $insert_id );
		wp_send_json_success( $this->format_suggestion( $stored ) );
	}

	/**
	 * Handle request to download a .patch file for a suggestion.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_get_patch(): void {
		$this->verify_request();

		$suggestion_id = isset( $_POST['suggestion_id'] ) ? absint( $_POST['suggestion_id'] ) : 0;
		if ( ! $suggestion_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid suggestion ID.', 'logpilot' ) ), 400 );
		}

		$suggestion = $this->database->get_suggestion( $suggestion_id );
		if ( ! $suggestion ) {
			wp_send_json_error( array( 'message' => __( 'Suggestion not found.', 'logpilot' ) ), 404 );
		}

		$patch = $this->generate_patch( $suggestion );

		wp_send_json_success( array( 'patch' => $patch ) );
	}

	/**
	 * Format a DB suggestion row for the JS front-end.
	 *
	 * @since 1.1.0
	 *
	 * @param array $suggestion DB row.
	 * @return array Formatted data.
	 */
	private function format_suggestion( array $suggestion ): array {
		return array(
			'id'             => (int) $suggestion['id'],
			'severity'       => $suggestion['severity'],
			'explanation'    => $suggestion['explanation'],
			'suggested_code' => $suggestion['suggested_code'],
			'docblock'       => $suggestion['docblock'] ?? '',
			'target_file'    => $suggestion['target_file'],
			'is_core_file'   => (bool) $suggestion['is_core_file'],
			'confidence'     => (int) ( $suggestion['confidence'] ?? 50 ),
			'status'         => $suggestion['status'],
			'provider_label' => $this->analyser->get_provider_label(),
		);
	}

	/**
	 * Generate a minimal unified diff patch from a suggestion.
	 *
	 * @since 1.1.0
	 *
	 * @param array $suggestion Suggestion DB row.
	 * @return string Patch content.
	 */
	private function generate_patch( array $suggestion ): string {
		$target   = $suggestion['target_file'];
		$new_code = $suggestion['suggested_code'];
		$docblock = $suggestion['docblock'] ?? '';

		$code_with_doc = $docblock ? $docblock . "\n" . $new_code : $new_code;

		// Build a simple single-file patch showing the suggested replacement.
		$lines = array(
			'--- a/' . $target,
			'+++ b/' . $target,
			'@@ -0,0 +1,' . count( explode( "\n", $code_with_doc ) ) . ' @@',
		);
		foreach ( explode( "\n", $code_with_doc ) as $line ) {
			$lines[] = '+' . $line;
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Verify the AJAX request has a valid nonce and the user has required capability.
	 *
	 * @since 1.1.0
	 *
	 * @return void Sends JSON error and exits on failure.
	 */
	private function verify_request(): void {
		check_ajax_referer( 'logpilot_ai', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'logpilot' ) ), 403 );
		}
	}
}
