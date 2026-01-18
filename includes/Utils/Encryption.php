<?php
/**
 * Encryption Utility.
 *
 * @package    Infynion\LogPilot\Utils
 */

namespace Infynion\LogPilot\Utils;

/**
 * Class Encryption
 *
 * Handles encryption and decryption of sensitive data.
 *
 * @package Infynion\LogPilot\Utils
 */
class Encryption {

	/**
	 * Encrypts data using AES-256-CBC and returns a base64-encoded string.
	 *
	 * @param mixed $data Data to encrypt.
	 * @return string
	 */
	public static function encrypt( $data ) {
		// Define key constant if not defined, ideally in wp-config.php. 
		// Fallback to internal managed key logic if needed, but for now expect define.
		if ( ! defined( 'LOGPILOT_KEY' ) ) {
			// Fallback: use AUTH_KEY or generate a warning. 
			// Using AUTH_KEY is a reasonably safe default for WP specific encryption if no custom key exists.
			if ( defined( 'AUTH_KEY' ) ) {
				$key = AUTH_KEY;
			} else {
				return $data; // Fail open (return raw) or fail closed (return empty)? Safer to return raw if key missing? Or error?
			}
		} else {
			$key = LOGPILOT_KEY;
		}

		if ( is_array( $data ) || is_object( $data ) ) {
			$data = wp_json_encode( $data );
		}

		$iv        = substr( hash( 'sha256', $key ), 0, 16 );
		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypts base64-encoded encrypted data.
	 *
	 * @param string $data Encrypted data.
	 * @return mixed
	 */
	public static function decrypt( $data ) {
		if ( ! defined( 'LOGPILOT_KEY' ) ) {
			if ( defined( 'AUTH_KEY' ) ) {
				$key = AUTH_KEY;
			} else {
				return $data;
			}
		} else {
			$key = LOGPILOT_KEY;
		}

		$data_decoded = base64_decode( $data );
		$iv           = substr( $data_decoded, 0, 16 );
		$cipher       = substr( $data_decoded, 16 );

		$decrypted = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( $decrypted === false ) {
			return $data; // Failed to decrypt, return original (might be unencrypted old data)
		}

		$json = json_decode( $decrypted, true );

		return $json !== null ? $json : $decrypted;
	}
}
