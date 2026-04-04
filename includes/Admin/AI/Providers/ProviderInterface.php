<?php
/**
 * AI provider interface.
 *
 * @package Infynion\Logpilot\Admin\AI\Providers
 * @since   1.1.0
 */

namespace Infynion\Logpilot\Admin\AI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract that every AI provider must satisfy.
 *
 * @since 1.1.0
 */
interface ProviderInterface {

	/**
	 * Send a prompt to the AI model and return a parsed suggestion.
	 *
	 * @since 1.1.0
	 *
	 * @param string $prompt The full prompt string to send.
	 * @return array|\WP_Error Parsed array with keys:
	 *                         explanation, severity, suggested_code, target_file, docblock, confidence.
	 *                         Returns WP_Error on failure.
	 */
	public function analyse( string $prompt ): array|\WP_Error;

	/**
	 * Return a human-readable label for this provider + model.
	 *
	 * @since 1.1.0
	 *
	 * @return string E.g. "Claude Sonnet 4.6".
	 */
	public function get_label(): string;
}
