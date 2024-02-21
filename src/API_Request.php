<?php
/**
 * The API_Request class manages communication with a remote API server for license key operations including
 * activation, deactivation, and validation.
 *
 * This class handles:
 *
 * - Sending activation requests to a remote server to activate a license key for a plugin.
 * - Sending deactivation requests to a remote server for a previously activated license key.
 * - Validating the status of a license key with the remote server to ensure it is valid and active.
 *
 * It ensures secure and efficient communication with the API server, leveraging WordPress HTTP API for requests and
 * providing an interface for managing license keys within plugin settings.
 *
 * @package         arraypress/lemon-squeezy-updater
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 * @description     Manages API communications for license key operations in a WordPress environment.
 */

declare( strict_types=1 );

namespace ArrayPress\LemonSqueezy;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use function add_query_arg;
use function delete_option;
use function home_url;
use function is_wp_error;
use function json_decode;
use function update_option;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

/**
 * Checks if the class `API_Request` is already defined within the namespace, and if not, defines it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\API_Request' ) ) :

	/**
	 * API_Request
	 *
	 * Manages API requests for activating, deactivating, and validating license keys for plugins. It provides a streamlined
	 * process for license management by interfacing with a specific API URL, handling the intricacies of request
	 * formation, response processing, and error handling.
	 */
	class API_Request {

		/**
		 * The API endpoint URL for sending requests related to license keys.
		 *
		 * @var string
		 */
		public string $api_url;

		/**
		 * The option key used to store license data in the WordPress database.
		 *
		 * @var string
		 */
		public string $license_data_key;

		/**
		 * Constructs a new API_Request instance with the specified API URL and license data storage key.
		 *
		 * @param string $api_url          The URL to the license management API.
		 * @param string $license_data_key The database option key for storing license data.
		 */
		public function __construct( string $api_url, string $license_data_key ) {
			$this->api_url          = trim( $api_url );
			$this->license_data_key = trim( $license_data_key );
		}

		/**
		 * Activates a license key by sending a request to the API URL.
		 *
		 * @param string $license_key The license key to activate.
		 * @param bool   $update      Whether to update the stored license data on success.
		 *
		 * @return mixed The response body from the API on success, or false on failure.
		 */
		public function activate_key( string $license_key, bool $update = true ) {
			$license_key = trim( $license_key );
			if ( ! $license_key || ! $this->api_url ) {
				return false;
			}

			$activation_url = add_query_arg(
				[
					'license_key'   => $license_key,
					'instance_name' => home_url(),
				],
				$this->api_url . '/activate'
			);

			$response = wp_remote_get( $activation_url, [
				'sslverify' => false,
				'timeout'   => 10,
			] );

			if (
				is_wp_error( $response )
				|| ( 200 !== wp_remote_retrieve_response_code( $response ) && 400 !== wp_remote_retrieve_response_code( $response ) )
				|| empty( wp_remote_retrieve_body( $response ) )
			) {
				return false;
			}

			$payload = wp_remote_retrieve_body( $response );

			// Update the license data option with the successful payload
			if ( ! empty( $this->license_data_key ) && $update ) {
				update_option( $this->license_data_key, $payload );
			}

			return json_decode( $payload );
		}

		/**
		 * Deactivates a license key by sending a deactivation request to the API.
		 *
		 * @param string $license_key The license key to deactivate.
		 * @param string $instance_id The instance ID where the license is activated.
		 * @param bool   $update      Whether to update the stored license data on success.
		 *
		 * @return mixed The response from the API on success, or false on failure.
		 */
		public function deactivate_key( string $license_key, string $instance_id, bool $update = true ) {
			$license_key = trim( $license_key );
			$instance_id = trim( $instance_id );
			if ( ! $license_key || ! $instance_id || ! $this->api_url ) {
				return false;
			}

			$deactivation_url = add_query_arg(
				[
					'license_key' => $license_key,
					'instance_id' => $instance_id,
				],
				$this->api_url . '/deactivate'
			);

			$response = wp_remote_get( $deactivation_url, [
				'sslverify' => false,
				'timeout'   => 10,
			] );

			if (
				200 === wp_remote_retrieve_response_code( $response ) || 400 === wp_remote_retrieve_response_code( $response )
			) {
				if ( ! empty( $this->license_data_key ) && $update ) {
					delete_option( $this->license_data_key );
				}

				return json_decode( wp_remote_retrieve_body( $response ) );
			}

			return false;
		}

		/**
		 * Validates the current status of a license key with the API.
		 *
		 * @param string      $license_key The license key to validate.
		 * @param string|null $instance_id The instance ID, if applicable.
		 * @param bool        $update      Whether to update the stored license data on success.
		 *
		 * @return mixed True if the license is valid, \WP_Error or false on failure or if the license is invalid.
		 */
		public function validate_key( string $license_key, ?string $instance_id = null, bool $update = false ) {
			$license_key = trim( $license_key );
			if ( ! $license_key || ! $this->api_url ) {
				return false;
			}

			// Prepare the query arguments, making 'instance_id' conditional
			$query_args = [ 'license_key' => $license_key ];
			if ( ! empty( $instance_id ) ) {
				$query_args['instance_id'] = trim( $instance_id );
			}

			$validation_url = add_query_arg( $query_args, $this->api_url . '/validate_license_key' );

			$response = wp_remote_get( $validation_url, [
				'sslverify' => false,
				'timeout'   => 10,
			] );

			if (
				is_wp_error( $response )
				|| ( 200 !== wp_remote_retrieve_response_code( $response ) && 400 !== wp_remote_retrieve_response_code( $response ) )
				|| empty( wp_remote_retrieve_body( $response ) )
			) {
				return false;
			}

			$payload = wp_remote_retrieve_body( $response );

			// Optionally update the stored license data if needed
			if ( ! empty( $this->license_data_key ) && $update ) {
				update_option( $this->license_data_key, $payload );
			}

			return json_decode( $payload );
		}

	}

endif;