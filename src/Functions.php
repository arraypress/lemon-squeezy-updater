<?php
/**
 * Provides helper functions for various operations within a WordPress plugin environment.
 *
 * This file includes essential utility functions used throughout the plugin for tasks such as:
 *
 * - Retrieving the current plugin version from the plugin metadata.
 * - Generating sanitized, WordPress-standard option names based on the plugin slug and optional suffixes.
 * - Sanitizing input data to ensure safety and adherence to WordPress coding standards, particularly useful for
 * processing form submissions or AJAX request data.
 *
 * These functions are designed to enhance code reusability, maintainability, and ensure secure handling of data within
 * the plugin. By abstracting common tasks into helper functions, the plugin code remains clean, efficient, and less
 * prone to errors or security vulnerabilities.
 *
 * @package         arraypress/lemon-squeezy-updater
 * @copyright       Copyright (c) 2023, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 * @description     Manages API communications for license key operations in a WordPress environment.
 */

declare( strict_types=1 );

namespace ArrayPress\LemonSqueezy;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use function get_plugin_data;
use function sanitize_key;
use function sanitize_text_field;
use function wp_unslash;

if ( ! function_exists( 'plugin_version' ) ) {
	/**
	 * Retrieves the version of a given plugin.
	 *
	 * This function checks if the get_plugin_data function is available and includes it if not.
	 * It then fetches the plugin data from the specified file path. If the version information
	 * is available, it returns the version; otherwise, it returns null.
	 *
	 * @param string $plugin_file The full path to the plugin file from which to get the version.
	 *
	 * @return string|null The version of the plugin if available, or null if not.
	 */
	function plugin_version( string $plugin_file ): ?string {

		// Check if the get_plugin_data function doesn't exist and include the plugin.php file if necessary
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Get the plugin data from the specified file
		$plugin_data = get_plugin_data( $plugin_file );

		// Check if the version information exists in the plugin data
		if ( ! empty( $plugin_data ) && isset( $plugin_data['Version'] ) ) {
			return trim( $plugin_data['Version'] );
		}

		return null;
	}
}

if ( ! function_exists( 'plugin_author_url' ) ) {
	/**
	 * Retrieves the author URL of a given plugin.
	 *
	 * This function checks if the get_plugin_data function is available and includes it if not.
	 * It then fetches the plugin data from the specified file path. If the author URL information
	 * is available, it returns the author URL; otherwise, it returns null.
	 *
	 * @param string $plugin_file The full path to the plugin file from which to get the author URL.
	 *
	 * @return string|null The author URL of the plugin if available, or null if not.
	 */
	function plugin_author_url( string $plugin_file ): ?string {

		// Check if the get_plugin_data function doesn't exist and include the plugin.php file if necessary
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Get the plugin data from the specified file
		$plugin_data = get_plugin_data( $plugin_file );

		// Check if the author URL information exists in the plugin data
		if ( ! empty( $plugin_data ) && isset( $plugin_data['AuthorURI'] ) ) {
			return trim( $plugin_data['AuthorURI'] );
		}

		return null;
	}
}

if ( ! function_exists( 'suffix_str' ) ) {
	/**
	 * Generates a sanitized option name for the plugin based on the plugin slug.
	 * If a non-empty suffix is provided, it appends this suffix to the slug, preceded by an underscore.
	 * This method ensures the option name is safe, conforms to WordPress standards, and is versatile for various uses.
	 *
	 * @param string $name   The slug of the plugin, typically the directory name.
	 * @param string $suffix Optional. The suffix to append to the slug, if provided.
	 *
	 * @return string The sanitized and safe option name for the plugin's option.
	 */
	function suffix_str( string $name, string $suffix = '' ): string {
		// Conditionally append the suffix with an underscore if it's not empty
		$name = str_replace( '-', '_', $name ) . ( ! empty( $suffix ) ? '_' . $suffix : '' );

		// Sanitize the option name to ensure it adheres to WordPress standards and is safe to use
		return sanitize_key( $name );
	}
}

if ( ! function_exists( 'sanitize_input' ) ) {
	/**
	 * Sanitizes a given input by removing slashes and using WordPress's sanitize_text_field, then trims it.
	 *
	 * This function first removes magic quotes (if added) using wp_unslash, then sanitizes
	 * the input to ensure it's safe for use in the context of the application. It's designed
	 * to prevent XSS attacks and other potential vulnerabilities by stripping tags and
	 * removing extra spaces. Finally, it trims the input to remove any leading or trailing
	 * spaces. This ensures the sanitized input is in its intended form, free of unnecessary
	 * characters.
	 *
	 * @param string $input The input string to be sanitized and trimmed.
	 *
	 * @return string The sanitized and trimmed string.
	 */
	function sanitize_input( string $input ): string {
		$input = wp_unslash( $input );
		$input = sanitize_text_field( $input );

		return trim( $input );
	}
}