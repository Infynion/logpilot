<?php
/**
 * Anthropic Claude AI provider.
 *
 * @package Infynion\Logpilot\Admin\AI\Providers
 * @since   1.1.0
 */

namespace Infynion\Logpilot\Admin\AI\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends prompts to Anthropic's Messages API and parses the structured response.
 *
 * @since 1.1.0
 */
class ClaudeProvider implements ProviderInterface {

	/**
	 * Anthropic Messages API endpoint.
	 *
	 * @var string
	 */
	private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version header value.
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * Decrypted API key.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private string $api_key;

	/**
	 * Model identifier.
	 *
	 * @var string
	 * @since 1.1.0
	 */
	private string $model;

	/**
	 * Human-readable model labels.
	 *
	 * @var array<string,string>
	 */
	private const MODEL_LABELS = array(
		'claude-opus-4-6'           => 'Claude Opus 4.6',
		'claude-sonnet-4-6'         => 'Claude Sonnet 4.6',
		'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
	);

	/**
	 * System prompt that enforces the structured response format.
	 *
	 * @var string
	 */
	private const SYSTEM_PROMPT = 'You are a WordPress PHP debugging assistant. Analyze the PHP error and provide a minimal, safe code fix. Structure your response EXACTLY as follows with no additional text:

SEVERITY: critical|high|medium|low

EXPLANATION:
2-3 sentences describing the root cause and what functionality is affected.

TARGET_FILE:
The absolute file path that needs changing, exactly as provided in the context. If the error is in a WordPress core file, write: WordPress Core — cannot be auto-applied

DOCBLOCK:
/**
 * Fix for: <brief description>
 * Error:   <error type>
 * Line:    <original line number>
 * Change:  <what was changed and why>
 */

SUGGESTED_FIX:
```php
<replacement PHP code block — only the affected function or block, not the entire file>
```

CONFIDENCE: 0-100

Do not suggest changes to WordPress core files (wp-includes, wp-admin, wp-config.php).';

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param string $api_key Decrypted Anthropic API key.
	 * @param string $model   Model identifier.
	 */
	public function __construct( string $api_key, string $model = 'claude-sonnet-4-6' ) {
		$allowed = array_keys( self::MODEL_LABELS );
		$this->api_key = $api_key;
		$this->model   = in_array( $model, $allowed, true ) ? $model : 'claude-sonnet-4-6';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return self::MODEL_LABELS[ $this->model ] ?? $this->model;
	}

	/**
	 * {@inheritdoc}
	 */
	public function analyse( string $prompt ): array|\WP_Error {
		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->model,
						'max_tokens' => 2048,
						'system'     => self::SYSTEM_PROMPT,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = $body['error']['message'] ?? __( 'Unknown API error.', 'logpilot' );
			return new \WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		$text = $body['content'][0]['text'] ?? '';
		return $this->parse_response( $text );
	}

	/**
	 * Parse the structured text response from Claude into an array.
	 *
	 * @since 1.1.0
	 *
	 * @param string $text Raw response text.
	 * @return array|\WP_Error Parsed suggestion data or WP_Error on malformed response.
	 */
	private function parse_response( string $text ): array|\WP_Error {
		$result = array(
			'severity'       => 'medium',
			'explanation'    => '',
			'target_file'    => '',
			'docblock'       => '',
			'suggested_code' => '',
			'confidence'     => 50,
			'is_core_file'   => false,
		);

		// Severity.
		if ( preg_match( '/^SEVERITY:\s*(critical|high|medium|low)/im', $text, $m ) ) {
			$result['severity'] = strtolower( trim( $m[1] ) );
		}

		// Confidence.
		if ( preg_match( '/^CONFIDENCE:\s*(\d+)/im', $text, $m ) ) {
			$result['confidence'] = min( 100, max( 0, (int) $m[1] ) );
		}

		// Explanation.
		if ( preg_match( '/^EXPLANATION:\s*\n([\s\S]*?)(?=\n^TARGET_FILE:)/im', $text, $m ) ) {
			$result['explanation'] = trim( $m[1] );
		}

		// Target file.
		if ( preg_match( '/^TARGET_FILE:\s*\n(.+)/im', $text, $m ) ) {
			$target = trim( $m[1] );
			if ( str_contains( strtolower( $target ), 'wordpress core' ) ) {
				$result['is_core_file'] = true;
				$result['target_file']  = $target;
			} else {
				$result['target_file'] = $target;
			}
		}

		// Docblock.
		if ( preg_match( '/^DOCBLOCK:\s*\n(\/\*\*[\s\S]*?\*\/)/im', $text, $m ) ) {
			$result['docblock'] = trim( $m[1] );
		}

		// Suggested fix — extract code from fenced block.
		if ( preg_match( '/^SUGGESTED_FIX:\s*\n```(?:php)?\n([\s\S]*?)\n```/im', $text, $m ) ) {
			$result['suggested_code'] = trim( $m[1] );
		}

		if ( empty( $result['explanation'] ) && empty( $result['suggested_code'] ) ) {
			return new \WP_Error( 'malformed_ai_response', __( 'AI response did not match expected format.', 'logpilot' ) );
		}

		return $result;
	}
}
