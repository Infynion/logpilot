<?php
/**
 * AI analyser orchestrator.
 *
 * @package Infynion\Logpilot\Admin\AI
 * @since   1.1.0
 */

namespace Infynion\Logpilot\Admin\AI;

use Infynion\Logpilot\Admin\AI\Providers\ProviderInterface;
use Infynion\Logpilot\Admin\AI\Providers\ClaudeProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin orchestrator: selects the correct AI provider and delegates the analysis.
 *
 * @since 1.1.0
 */
class AIAnalyser {

	/**
	 * The active provider instance.
	 *
	 * @var ProviderInterface
	 * @since 1.1.0
	 */
	private ProviderInterface $provider;

	/**
	 * Constructor — builds the provider from settings or accepts an injected one.
	 *
	 * @since 1.1.0
	 *
	 * @param ProviderInterface|null $provider Optional injected provider (used in tests).
	 */
	public function __construct( ?ProviderInterface $provider = null ) {
		if ( $provider instanceof ProviderInterface ) {
			$this->provider = $provider;
		} else {
			$this->provider = $this->resolve_provider();
		}
	}

	/**
	 * Analyse a context array and return an AI suggestion.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $context Context array from ContextBuilder::build().
	 * @param string $prompt  Ready-assembled prompt string.
	 * @return array|\WP_Error Suggestion data array or WP_Error.
	 */
	public function analyse( array $context, string $prompt ): array|\WP_Error {
		/**
		 * Filters the prompt before it is sent to the AI provider.
		 *
		 * @since 1.1.0
		 *
		 * @param string $prompt  The assembled prompt string.
		 * @param array  $context The full context array.
		 */
		$prompt = (string) apply_filters( 'logpilot_ai_prompt', $prompt, $context );

		$suggestion = $this->provider->analyse( $prompt );

		if ( is_wp_error( $suggestion ) ) {
			return $suggestion;
		}

		// Force is_core_file based on context if not already set.
		if ( $context['is_core_file'] ) {
			$suggestion['is_core_file'] = true;
		}

		/**
		 * Filters the AI suggestion before it is stored and returned.
		 *
		 * @since 1.1.0
		 *
		 * @param array $suggestion The parsed AI suggestion.
		 * @param array $context    The full context array.
		 */
		$suggestion = (array) apply_filters( 'logpilot_ai_suggestion', $suggestion, $context );

		return $suggestion;
	}

	/**
	 * Return the human-readable label of the active provider.
	 *
	 * @since 1.1.0
	 *
	 * @return string Provider label.
	 */
	public function get_provider_label(): string {
		return $this->provider->get_label();
	}

	/**
	 * Build the active provider from WordPress options.
	 *
	 * Third-party plugins may extend the provider list via the
	 * `logpilot_ai_providers` filter.
	 *
	 * @since 1.1.0
	 *
	 * @return ProviderInterface
	 */
	private function resolve_provider(): ProviderInterface {
		$api_key  = logpilot_decrypt_option( 'logpilot_ai_api_key' );
		$model    = (string) get_option( 'logpilot_ai_model', 'claude-sonnet-4-6' );
		$provider = (string) get_option( 'logpilot_ai_provider', 'anthropic' );

		/**
		 * Filters the map of available AI providers.
		 *
		 * Keys are provider slugs (e.g. 'anthropic'); values are callables that
		 * return a ProviderInterface instance when given ($api_key, $model).
		 *
		 * @since 1.1.0
		 *
		 * @param array $providers Map of slug => callable.
		 */
		$providers = (array) apply_filters(
			'logpilot_ai_providers',
			array(
				'anthropic' => static fn( $k, $m ) => new ClaudeProvider( $k, $m ),
			)
		);

		if ( isset( $providers[ $provider ] ) && is_callable( $providers[ $provider ] ) ) {
			return $providers[ $provider ]( $api_key, $model );
		}

		// Fallback to Claude.
		return new ClaudeProvider( $api_key, $model );
	}
}

// ---------------------------------------------------------------------------
// Encryption helpers (global scope — loaded once by autoloader).
// ---------------------------------------------------------------------------

if ( ! function_exists( 'logpilot_encrypt_option' ) ) {
	/**
	 * Encrypt a value for storage as a WordPress option.
	 *
	 * Uses AES-256-CBC with a key derived from wp_salt('secure_auth').
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Plain-text value to encrypt.
	 * @return string Base64-encoded cipher text, or empty string on failure.
	 */
	function logpilot_encrypt_option( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		$key    = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 32 );
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( false === $iv_len ) {
			return '';
		}
		$iv         = openssl_random_pseudo_bytes( $iv_len );
		$ciphertext = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return '';
		}
		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}

if ( ! function_exists( 'logpilot_decrypt_option' ) ) {
	/**
	 * Decrypt a WordPress option value that was encrypted with logpilot_encrypt_option().
	 *
	 * @since 1.1.0
	 *
	 * @param string $option_name The WordPress option name holding the encrypted value.
	 * @return string Decrypted plain-text value, or empty string on failure.
	 */
	function logpilot_decrypt_option( string $option_name ): string {
		$stored = (string) get_option( $option_name, '' );
		if ( '' === $stored ) {
			return '';
		}
		$data   = base64_decode( $stored ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$key    = substr( hash( 'sha256', wp_salt( 'secure_auth' ) ), 0, 32 );
		$iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( false === $iv_len || strlen( $data ) <= $iv_len ) {
			return '';
		}
		$iv         = substr( $data, 0, $iv_len );
		$ciphertext = substr( $data, $iv_len );
		$plain      = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return ( false === $plain ) ? '' : $plain;
	}
}
