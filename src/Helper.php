<?php
/**
 * Facilitates the management of plugin updates, license activations, and interactions with a remote API server within
 * a WordPress environment.
 *
 * This file provides a helper function for initializing the Updater class, which is essential for:
 * - Managing plugin updates by checking for new versions and processing updates.
 * - Handling license key activations and deactivations to ensure compliance with licensing terms.
 * - Performing license key validations to verify active status and entitlement to updates.
 * - Communicating with a designated remote API server for retrieving update details and performing license operations.
 * - Optionally integrating error handling through a callback mechanism to manage and log initialization failures.
 *
 * By encapsulating these operations within a helper function, the plugin facilitates a cleaner, more maintainable,
 * and secure integration with the WordPress ecosystem, ensuring that plugin updates and license management are handled
 * efficiently and in accordance with best practices.
 *
 * @package         arraypress/lemon-squeezy-updater
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace {

	use ArrayPress\LemonSqueezy\Updater;

	if ( ! function_exists( 'register_lemon_squeezy_updater' ) ) {
		/**
		 * Function to instantiate the Updater class for managing plugin updates and license activations, with error handling.
		 *
		 * @param string        $file          The main plugin file path.
		 * @param string        $api_url       The API URL to the update server.
		 * @param string|null   $version       Optional. The current version of the plugin. Default null.
		 * @param int           $store_id      Optional. The store ID associated with the plugin. Default 0.
		 * @param int           $product_id    Optional. The product ID for the plugin. Default 0.
		 * @param int           $variant_id    Optional. The variant ID of the plugin. Default 0.
		 * @param string        $renewal_url   Optional. The URL for the renewal/product page. Default ''.
		 * @param callable|null $errorCallback Optional. A callback function for error handling. Default null.
		 *
		 * @return Updater|null The initialized Updater instance or null on failure.
		 */
		function register_lemon_squeezy_updater( string $file, string $api_url, ?string $version = null, int $store_id = 0, int $product_id = 0, int $variant_id = 0, string $renewal_url = '', ?callable $errorCallback = null ): ?Updater {
			try {
				// Instantiate the Updater class with the provided parameters
				return new Updater( $file, $api_url, $version, $store_id, $product_id, $variant_id, $renewal_url );
			} catch ( Exception $e ) {
				if ( $errorCallback && is_callable( $errorCallback ) ) {
					call_user_func( $errorCallback, $e );
				}

				// Return null on failure if error callback is provided.
				return null;
			}
		}
	}

}