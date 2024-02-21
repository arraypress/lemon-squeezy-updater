<?php
/**
 * Manages plugin updates, license activations, deactivations, validations, and communications with a remote API
 * server.
 *
 * This Updater class facilitates various operations essential for maintaining the plugin's license and ensuring it is
 * up-to-date. Key features include:
 * - Activation and deactivation of license keys to comply with licensing terms.
 * - Validation of license keys to check their status and ensure they are active.
 * - Automatic checks for plugin updates and retrieval of update information from a remote server.
 * - Enqueueing of scripts and styles for the plugin's admin interface and adding relevant action hooks for AJAX
 * operations.
 * - Scheduled tasks for daily license validation to maintain license activation status.
 * - Management of plugin options related to license keys and update caching, including error handling for license
 * operations.
 * - Utilization of WordPress HTTP API for secure and efficient communication with the API server, enhancing the
 * plugin's functionality and user experience.
 *
 * It is designed to work seamlessly within the WordPress environment, leveraging WordPress core functions and
 * standards for handling plugin settings, AJAX requests, and scheduled events. This class is an integral part of
 * ensuring that the plugin remains compliant with licensing terms, functions correctly, and stays updated with the
 * latest versions.
 *
 * @package         arraypress/lemon-squeezy-updater
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\LemonSqueezy;

use stdClass;
use function add_filter;
use function delete_transient;
use function get_option;
use function get_plugin_data;
use function get_transient;
use function is_wp_error;
use function plugin_basename;
use function sanitize_text_field;
use function set_transient;
use function update_option;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

/**
 * Check if the class `Updater` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Updater' ) ) :

	/**
	 * The name of this class should be unique to your plugin to
	 * avoid conflicts with other plugins using an updater class.
	 */
	class Updater {

		/**
		 * The base name of the plugin file.
		 *
		 * @var string
		 */
		public string $plugin_id;

		/**
		 * The slug derived from the plugin base name.
		 *
		 * @var string
		 */
		public string $plugin_slug;

		/**
		 * The API URL for the update server.
		 *
		 * @var string
		 */
		public string $api_url;

		/**
		 * The current version of the plugin.
		 *
		 * @var string
		 */
		public string $version;

		/**
		 * The store ID associated with the plugin.
		 *
		 * @var int
		 */
		public int $store_id;

		/**
		 * The product ID for the plugin.
		 *
		 * @var int
		 */
		public int $product_id;

		/**
		 * The variant ID of the plugin.
		 *
		 * @var int
		 */
		public int $variant_id;

		/**
		 * The URL for the renewal.
		 *
		 * @var string
		 */
		public string $renewal_url;

		/**
		 * The key used for caching updater-related data.
		 *
		 * @var string
		 */
		public string $cache_key;

		/**
		 * Whether caching is allowed for updater-related data.
		 *
		 * @var boolean
		 */
		public bool $cache_allowed;

		/**
		 * The key used to store the license key in the database.
		 *
		 * @var string
		 */
		public string $license_name_key;

		/**
		 * The key used to store the license data in the database.
		 *
		 * @var string
		 */
		public string $license_data_key;

		/**
		 * The instance of the API_Request class handling communication with the API.
		 *
		 * @var object
		 */
		public object $api_request;

		/**
		 * Initializes the plugin updater class with specified settings.
		 *
		 * Sets up the necessary properties for the plugin updater, including the API URL,
		 * plugin version, and product details such as store ID, product ID, and variant ID.
		 * Also initializes API request handling and schedules hooks for license management
		 * and update processes.
		 *
		 * @param string      $file        Required. The main plugin file path.
		 * @param string      $api_url     Required. The API URL to the update server.
		 * @param string|null $version     Optional. The current version of the plugin, with default value null.
		 * @param int         $store_id    Optional. The store ID associated with the plugin, with default value 0.
		 * @param int         $product_id  Optional. The product ID for the plugin, with default value 0.
		 * @param int         $variant_id  Optional. The variant ID of the plugin, with default value 0.
		 * @param string      $renewal_url Optional. The URL to the renewal/product page.
		 */
		public function __construct( string $file, string $api_url, ?string $version = null, int $store_id = 0, int $product_id = 0, int $variant_id = 0, string $renewal_url = '' ) {
			if ( empty( $file ) || empty( $api_url ) ) {
				return false;
			}

			$this->plugin_id   = plugin_basename( $file );
			$this->plugin_slug = dirname( $this->plugin_id );
			$this->api_url     = trim( $api_url );

			$this->version = ! empty( $version ) ? trim( $version ) : plugin_version( $file );

			$this->store_id   = absint( $store_id );
			$this->product_id = absint( $product_id );
			$this->variant_id = absint( $variant_id );

			$this->renewal_url = trim( $renewal_url );

			$this->license_name_key = suffix_str( $this->plugin_slug, 'license_key' );
			$this->license_data_key = suffix_str( $this->plugin_slug, 'license_data' );

			$this->api_request = new API_Request( $this->api_url, $this->license_data_key );

			$this->cache_key     = suffix_str( $this->plugin_slug, 'updater' );
			$this->cache_allowed = true; // Only disable this for debugging

			$this->hooks();
		}

		/** Hooks *********************************************************************/

		/**
		 * Sets up the necessary WordPress hooks for the plugin updater.
		 *
		 * This method enqueues scripts and styles for the admin interface, sets up AJAX actions for
		 * license activation and deactivation, schedules daily license checks, and hooks into the
		 * update process to provide plugin updates.
		 */
		private function hooks() {

			// Adds a hook for enqueueing admin scripts and styles.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Hooks into the plugin row action to display the license row in the plugins list.
			add_action( 'after_plugin_row_' . $this->plugin_id, [ $this, 'display_license_row' ], 10, 2 );

			// Sets up AJAX action for activating the license.
			add_action( 'wp_ajax_activate_lsq_license', [ $this, 'ajax_activate_license' ] );

			// Sets up AJAX action for deactivating the license.
			add_action( 'wp_ajax_deactivate_lsq_license', [ $this, 'ajax_deactivate_license' ] );

			// If not already scheduled, schedules a daily event to check the license status.
			if ( ! wp_next_scheduled( 'lsq_daily_license_check_event' ) ) {
				wp_schedule_event( time(), 'daily', 'lsq_daily_license_check_event' );
			}

			// Hooks into the scheduled event to perform a daily license check.
			add_action( 'lsq_daily_license_check_event', [ $this, 'daily_license_check' ] );

			// Adds a filter to inject plugin information into the plugins API response.
			add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );

			// Adds a filter to modify the transient data for available plugin updates.
			add_filter( 'site_transient_update_plugins', [ $this, 'update' ] );

			// Hooks into the process of completing an upgrade to clear cached update data.
			add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );

		}

		/** License Management ********************************************************/

		/**
		 * Enqueues JavaScript and CSS files for the library.
		 *
		 * This method is hooked into WordPress' admin_enqueue_scripts action and is responsible
		 * for enqueuing the plugin's JavaScript and CSS files when viewing the plugins page. It also
		 * localizes the JavaScript file, providing it with data such as the AJAX URL and a security nonce
		 * for AJAX requests.
		 *
		 * @param string $hook The current page hook suffix. Used to determine if scripts and styles
		 *                     should be enqueued on the current admin page.
		 */
		public function enqueue_scripts( string $hook ) {
			if ( 'plugins.php' !== $hook ) {
				return;
			}

			// Enqueue the JavaScript file
			wp_enqueue_script(
				'lemonsqueezy-updater-js',
				plugins_url( 'assets/js/updater.js', __FILE__ ),
				[ 'jquery' ],
				filemtime( __DIR__ . '/assets/js/updater.js' ),
				true
			);

			// Enqueue the CSS file
			wp_enqueue_style(
				'lemonsqueezy-updater-css',
				plugins_url( 'assets/css/updater.css', __FILE__ ),
				[],
				filemtime( __DIR__ . '/assets/css/updater.css' )
			);

			// Localize the script for security purposes
			wp_localize_script( 'lemonsqueezy-updater-js', 'lsq_updater_script_vars', [
				'nonce' => wp_create_nonce( 'wp_lsq_updater_nonce' ),
			] );
		}

		/**
		 * Renders the appropriate license row view for the plugin in the plugins page.
		 *
		 * This method checks if the license is activated and chooses the relevant view file to include.
		 * It only renders the row for the specified plugin based on the provided plugin file path.
		 * If the corresponding view file does not exist, the method will not render anything.
		 *
		 * @param string $plugin_file The file path of the plugin to display the license information for.
		 * @param array  $plugin_data An array of plugin data.
		 *
		 * @return bool False if the license row is not displayed, otherwise void.
		 */
		public function display_license_row( string $plugin_file, array $plugin_data ) {
			if ( $plugin_file !== $this->plugin_id ) {
				return false;
			}

			// Determine which view to use
			$view = $this->is_license_activated()
				? __DIR__ . '/views/license_valid.php'
				: __DIR__ . '/views/license_key.php';

			if ( file_exists( $view ) ) {
				include $view;
			}
		}

		/**
		 * Retrieves a translatable message about the current license status, including activation usage and expiration date.
		 *
		 * This function checks if the license is currently activated and provides details about the
		 * activation usage and limit, as well as the expiration date if applicable. If the license is
		 * not activated, it returns a message indicating that the license is not active or displays
		 * any specific error messages related to license activation. All messages are made translatable
		 * for localization support.
		 *
		 * @return string The translatable message regarding the license status.
		 */
		public function get_license_message(): string {
			$license_data = $this->get_license_data();

			// Handle expired license first to simplify logic
			if ( isset( $license_data->data->license_key->status ) && 'expired' === $license_data->data->license_key->status ) {
				if ( ! empty( $this->renewal_url ) && filter_var( $this->renewal_url, FILTER_VALIDATE_URL ) ) {
					return sprintf(
						__( 'Your license has expired. <a href="%s">Renew it?</a>', 'arraypress' ),
						esc_url( $this->renewal_url )
					);
				}

				return ! empty( $license_data->error )
					? $license_data->error
					: __( 'Your license key has expired. Please renew to reactivate.', 'arraypress' );
			}

			// Handle active license
			if ( $this->is_license_activated() ) {
				$limit   = ! empty( $license_data->data->license_key->activation_limit )
					? $license_data->data->license_key->activation_limit
					: __( 'unlimited', 'arraypress' );
				$usage   = $license_data->data->license_key->activation_usage ?? 0;
				$message = sprintf( __( 'You have %1$s of %2$s instances activated.', 'arraypress' ), $usage, $limit );

				// Check if an expiration date is set and add it to the message
				if ( ! empty( $license_data->data->license_key->expires_at ) ) {
					$expiration_date = date_i18n( get_option( 'date_format' ), strtotime( $license_data->data->license_key->expires_at ) );
					$message         .= ' ' . sprintf( __( 'Your license expires on %s.', 'arraypress' ), $expiration_date );
				}

				return $message;
			}

			// Default message for non-expired, non-active licenses
			return ! empty( $license_data->error )
				? $license_data->error
				: __( 'Please enter your license key and hit return to activate.', 'arraypress' );
		}

		/**
		 * Retrieves a CSS class name based on the current license status.
		 *
		 * This function determines the license status by first checking if the license is activated.
		 * If the license is activated, it returns 'active'. Otherwise, it retrieves the current license
		 * status. If the retrieved status is 'active', it treats it as a special case and returns 'inactive'
		 * instead. This allows for a distinction between a truly active license and one that may be considered
		 * active but requires further validation or action. The returned status is converted to lowercase
		 * to ensure consistency, especially for use as a CSS class.
		 *
		 * @return string The CSS class name corresponding to the license status, in lowercase.
		 */
		public function get_license_status_class(): string {
			if ( $this->is_license_activated() ) {
				$status = 'active';
			} else {
				$status = $this->get_license_status();
				if ( 'active' === $status ) {
					$status = 'inactive';
				}
			}

			return strtolower( $status );
		}

		/** Updater *******************************************************************/

		/**
		 * Override the WordPress request to return the correct plugin info.
		 *
		 * @see https://developer.wordpress.org/reference/hooks/plugins_api/
		 *
		 * @param false|object|array $result
		 * @param string             $action
		 * @param object             $args
		 *
		 * @return object|bool
		 */
		public function info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action ) {
				return $result;
			}

			if ( $this->plugin_slug !== $args->slug ) {
				return $result;
			}

			$remote = $this->request();
			if ( ! $remote || ! $remote->success || empty( $remote->update ) ) {
				return $result;
			}

			$plugin_data = get_plugin_data( __FILE__ );

			$result           = $remote->update;
			$result->name     = $plugin_data['Name'];
			$result->slug     = $this->plugin_slug;
			$result->sections = (array) $result->sections;

			return $result;
		}

		/**
		 * Fetch the update info from the remote server running the Lemon Squeezy plugin.
		 *
		 * @return object|bool
		 */
		public function request() {
			$license_key = $this->get_license_key();
			if ( ! $license_key ) {
				return false;
			}

			$is_activated = $this->is_license_activated();
			if ( ! $is_activated ) {
				return false;
			}

			$remote = get_transient( $this->cache_key );
			if ( false !== $remote && $this->cache_allowed ) {
				if ( 'error' === $remote ) {
					return false;
				}

				return json_decode( $remote );
			}

			$remote = wp_remote_get(
				$this->api_url . "/update?license_key={$license_key}",
				array(
					'timeout' => 10,
				)
			);

			if (
				is_wp_error( $remote )
				|| 200 !== wp_remote_retrieve_response_code( $remote )
				|| empty( wp_remote_retrieve_body( $remote ) )
			) {
				set_transient( $this->cache_key, 'error', MINUTE_IN_SECONDS * 10 );

				return false;
			}

			$payload = wp_remote_retrieve_body( $remote );

			set_transient( $this->cache_key, $payload, DAY_IN_SECONDS );

			return json_decode( $payload );
		}

		/**
		 * Override the WordPress request to check if an update is available.
		 *
		 * @see https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
		 *
		 * @param object $transient
		 *
		 * @return object
		 */
		public function update( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			$res = (object) array(
				'id'            => $this->plugin_id,
				'slug'          => $this->plugin_slug,
				'plugin'        => $this->plugin_id,
				'new_version'   => $this->version,
				'url'           => '',
				'package'       => '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new stdClass(),
			);

			$remote = $this->request();

			if (
				$remote && $remote->success && ! empty( $remote->update )
				&& version_compare( $this->version, $remote->update->version, '<' )
			) {
				$res->new_version = $remote->update->version;
				$res->package     = $remote->update->download_link;

				$transient->response[ $res->plugin ] = $res;
			} else {
				$transient->no_update[ $res->plugin ] = $res;
			}

			return $transient;
		}

		/**
		 * When the update is complete, purge the cache.
		 *
		 * @see https://developer.wordpress.org/reference/hooks/upgrader_process_complete/
		 *
		 * @param WP_Upgrader $upgrader
		 * @param array       $options
		 *
		 * @return void
		 */
		public function purge( $upgrader, $options ) {
			if (
				$this->cache_allowed
				&& 'update' === $options['action']
				&& 'plugin' === $options['type']
				&& ! empty( $options['plugins'] )
			) {
				foreach ( $options['plugins'] as $plugin ) {
					if ( $plugin === $this->plugin_id ) {
						delete_transient( $this->cache_key );
					}
				}
			}
		}

		/** AJAX **********************************************************************/

		/**
		 * Attempts to activate a license key and updates the license status accordingly.
		 *
		 * This method checks for the necessary permissions, validates the provided plugin
		 * and license key, and attempts to activate the license key while ensuring that
		 * only the most relevant error message is displayed to the user.
		 *
		 * @return void
		 */
		public function ajax_activate_license() {
			// Verify the AJAX nonce for security
			check_ajax_referer( 'wp_lsq_updater_nonce', 'nonce' );

			// Check if the current user has the required capability to manage options
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this feature.', 'arraypress' ) );
			}

			// Sanitize the input to prevent security issues
			$plugin        = sanitize_input( $_POST['plugin'] );
			$license_key   = sanitize_input( $_POST['license'] );
			$error_message = '';

			// Return early if the plugin ID does not match, indicating this AJAX call is not intended for this plugin
			if ( $this->plugin_id !== $plugin ) {
				return;
			}

			// Return early if the license key is empty, as there's nothing to activate
			if ( empty( $license_key ) ) {
				return;
			}

			// Proceed to set the license key for further validation and activation
			$this->set_license_key( $license_key );

			// Send the remote response to validate the license key
			$response = $this->api_request->validate_key( $license_key );

			// Validate the license key and set an error message if validation fails
			if ( $response === false ) {
				$error_message = __( 'There was a problem validating your license key. Please try again.', 'arraypress' );
			} elseif ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			} elseif ( ! isset( $response->data->valid ) || ! $response->data->valid ) {
				$error_message = __( 'Your license key appears to be invalid.', 'arraypress' );
			} elseif ( ! empty( $this->store_id ) && isset( $response->data->meta->store_id ) && $response->data->meta->store_id !== $this->store_id ) {
				$error_message = __( 'This license key is not valid for the store associated with this product.', 'arraypress' );
			} elseif ( ! empty( $this->product_id ) && isset( $response->data->meta->product_id ) && $response->data->meta->product_id !== $this->product_id ) {
				$error_message = __( 'This license key does not match the product you are trying to activate.', 'arraypress' );
			} elseif ( ! empty( $this->variant_id ) && isset( $response->data->meta->variant_id ) && $response->data->meta->variant_id !== $this->variant_id ) {
				$error_message = __( 'This license key is not compatible with the product variant you have.', 'arraypress' );
			} else {
				$license_data = $response->data->license_key;
				if ( isset( $license_data->activation_limit ) && $license_data->activation_limit !== null && $license_data->activation_usage >= $license_data->activation_limit ) {
					$error_message = __( 'Your license key has reached its activation limit.', 'arraypress' );
				}
			}

			// If no errors occurred during validation, attempt to activate the license key
			if ( empty( $error_message ) ) {
				$response = $this->api_request->activate_key( $license_key );

				// Set an error if the activation fails
				if ( $response === false ) {
					$error_message = __( 'There was a problem activating your license key. Please try again later.', 'arraypress' );
				} elseif ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
				}
			}

			// If an error has been set, update the option with the error message
			if ( ! empty( $error_message ) ) {
				$this->set_option_error( $error_message, 'activation_error' );
			}

			// Always send a JSON response back to the client with the updated license information or error message
			$this->send_json_response();
		}

		/**
		 * Handles the AJAX request for deactivating a license key.
		 *
		 * This function ensures that the request is secure and authorized by verifying the AJAX nonce and checking user capabilities.
		 * It sanitizes the received input to avoid security vulnerabilities. The method then checks whether the request is intended
		 * for the current plugin based on the plugin ID. If the request is valid, it proceeds to send a deactivation request to the
		 * remote server using the API_Request object.
		 *
		 * The function carefully handles the response from the server. If the deactivation is successful, it updates the plugin's
		 * license status. In case of failure, it captures and logs the error message. This method is designed to facilitate a
		 * seamless and secure way for users to deactivate their license keys directly from the WordPress admin dashboard, ensuring
		 * that the plugin remains compliant with licensing terms and conditions.
		 *
		 * @return void
		 */
		public function ajax_deactivate_license() {
			// Verify the AJAX nonce for security
			check_ajax_referer( 'wp_lsq_updater_nonce', 'nonce' );

			// Check if the current user has the required capability to manage options
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Unauthorized' );
			}

			// Sanitize the input to prevent security issues
			$plugin        = sanitize_input( $_POST['plugin'] );
			$error_message = '';

			// Return early if the plugin ID does not match, indicating this AJAX call is not intended for this plugin
			if ( $this->plugin_id !== $plugin ) {
				return;
			}

			$response = $this->api_request->deactivate_key( $this->get_license_key(), $this->get_instance_id() );

			// Set an error if the deactivation fails
			if ( $response === false ) {
				$error_message = __( 'There was a problem deactivating your license key. Please try again later.', 'arraypress' );
			} elseif ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			}

			// If an error has been set, update the option with the error message
			if ( ! empty( $error_message ) ) {
				$this->set_option_error( $error_message, 'deactivation_error' );
			}

			// Always send a JSON response back to the client with the updated license information or error message
			$this->send_json_response();
		}

		/**
		 * Sends a JSON response containing the updated license row or an error message.
		 */
		private function send_json_response() {
			ob_start();
			// Generate the HTML for the license row to be returned in the response
			$this->display_license_row( $this->plugin_id, array() );
			$html = ob_get_clean();

			wp_send_json( array(
				'html' => $html,
			) );
		}

		/** License Helpers ***********************************************************/

		/**
		 * Retrieves the currently stored license key from the database.
		 *
		 * This method is typically used in the context of a plugin settings page,
		 * where the license key is requested from the user and stored for future use.
		 * It fetches the license key using the WordPress option API.
		 *
		 * @return string|null The stored license key if available, otherwise null.
		 */
		protected function get_license_key(): ?string {
			$license_key = get_option( $this->license_name_key );

			return $license_key ?: null;
		}

		/**
		 * Stores the provided license key into the database if it's new and not empty.
		 *
		 * This method trims and sanitizes the provided license key using WordPress sanitization
		 * functions. It then checks if the key is not empty and different from the currently stored key
		 * before updating the corresponding option in the database. This prevents unnecessary updates
		 * if the key hasn't changed.
		 *
		 * @param string $license The license key to store.
		 *
		 * @return bool True if the license key was successfully stored or no change was needed, false if the key is empty or update failed.
		 */
		public function set_license_key( string $license ): bool {
			// Trim and sanitize the license key
			$license = trim( sanitize_text_field( $license ) );

			// Check if the license key is not empty
			if ( empty( $license ) ) {
				return false; // License key is empty, so we don't proceed with setting it
			}

			// Fetch the current license key from the database
			$current_license = get_option( $this->license_name_key );

			// If the new license key is the same as the current one, no update is needed
			if ( $license === $current_license ) {
				return true; // No change needed, return true as if successful
			}

			// Update the license key in the database since it's new and different
			return update_option( $this->license_name_key, $license );
		}

		/**
		 * Fetches the stored license data from the database.
		 *
		 * This method retrieves license data, which includes the license key and other
		 * metadata, from the WordPress options table. It decodes the JSON-encoded license
		 * data into an object for easy access to its properties.
		 *
		 * @return object|null Decoded license data object if available, otherwise null.
		 */
		protected function get_license_data(): ?object {
			$license_data = get_option( $this->license_data_key );

			return $license_data ? json_decode( $license_data ) : null;
		}

		/**
		 * Retrieves the current status of the license key.
		 *
		 * This method accesses the stored license data to determine the status of the
		 * license key, which indicates whether it is active, inactive, or in another state.
		 *
		 * @return string The current status of the license key, defaults to 'inactive' if not set.
		 */
		public function get_license_status(): string {
			$license_data = $this->get_license_data(); // Ensure we're using the latest data.

			return $license_data->data->license_key->status ?? 'inactive';
		}

		/**
		 * Retrieves the instance ID associated with the license.
		 *
		 * The instance ID is a unique identifier for the instance of the plugin that the
		 * license is activated on. This method fetches the instance ID from the stored
		 * license data.
		 *
		 * @return string|null The instance ID if available, otherwise null.
		 */
		public function get_instance_id(): ?string {
			$license_data = $this->get_license_data(); // Ensure we're using the latest data.

			return $license_data->data->instance->id ?? null;
		}

		/**
		 * Checks whether the license key is currently activated.
		 *
		 * This method determines if the license key is activated by examining the stored
		 * license data for activation indicators.
		 *
		 * @return bool True if the license key is activated, false otherwise.
		 */
		public function is_license_activated(): bool {
			$license_data = $this->get_license_data(); // Ensure we're using the latest data.

			return ! empty( $license_data->data->activated ) || ! empty( $license_data->data->valid );
		}

		/** Cron **********************************************************************/

		/**
		 * Performs a daily check on the license key's validity.
		 *
		 * This scheduled task triggers a validation check against the remote server to
		 * ensure the license key remains valid and active. It uses the API request object
		 * to perform the validation.
		 */
		public function daily_license_check() {
			$this->api_request->validate_key( $this->get_license_key(), $this->get_instance_id(), true );
		}

		/** Option Helpers ************************************************************/

		/**
		 * Sets an error option with the provided error message and code.
		 *
		 * This function updates a specific option in the database to store an error
		 * message and error code. It ensures the error message is not empty before
		 * proceeding. If the error message is empty, the function will return false
		 * and not update the option. It uses a default error code of 'generic_error'
		 * if a specific error code is not provided or is empty.
		 *
		 * @param string $error      A non-empty error message to store. If empty, the function returns false.
		 * @param string $error_code An error code to associate with the message. Uses 'generic_error' as the default if not provided or empty.
		 *
		 * @return bool Returns true if the option was successfully updated, false otherwise.
		 */
		private function set_option_error( string $error, string $error_code = 'unknown_error' ): bool {
			if ( empty( $error ) ) {
				return false;
			}

			$payload = json_encode( [
				'success'    => false,
				'error'      => $error,
				'error_code' => $error_code ?? 'unknown_error'
			] );

			return update_option( $this->license_data_key, $payload );
		}

	}

endif;
