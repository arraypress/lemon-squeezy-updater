# Lemon Squeezy WordPress Plugin Updater and License Manager

This library seamlessly integrates with WordPress plugins, adding a simple yet powerful licensing field directly into
the WordPress plugins list. It enables effortless activation, deactivation, and management of plugin licenses
through AJAX for a streamlined user experience. With just one line of code, this functionality can be added to any
WordPress plugin, facilitating license validations against `product_id`, `store_id`, and `variation_id` to ensure
legitimacy
before activation. Additionally, it supports an optional renewal URL feature, guiding users to repurchase when licenses
expire.

![Alt text](/assets/preview.webp "Preview Video")

**Key Features:**

- **License Activation and Validation:** Simplifies the process of activating and validating license keys, ensuring that
  users are authorized to use the plugin. Validates against product_id, store_id, and variation_ids for enhanced
  security.
- **Seamless Updates:** Automates the checking for plugin updates and manages the update process, ensuring plugins
  remain up-to-date effortlessly.
- **Easy Integration:** Designed for high compatibility with various WordPress environments, it integrates smoothly into
  any plugin with minimal effort.
- **Secure API Communication:** Utilizes secure API calls for communication
  with [Lemon Squeezy](https://lemonsqueezy.com) for license management and update repositories, ensuring data
  protection.
- **Streamlined User Experience:** Leverages AJAX for real-time license operations, providing a seamless experience
  without page reloads.
- **Renewal URL Support:** Offers an optional renewal URL for expired licenses, directing users to repurchase, thus
  maintaining continuity and support.

Leverage the Lemon Squeezy WordPress Plugin Updater and License Manager to ensure your plugins are efficiently managed,
securely licensed, and always up to date.

## Minimum Requirements

- **PHP:** 7.4 or later
- **WordPress:** 6.4.3 or later

**Important: The [Lemon Squeezy - Better Endpoints](https://github.com/arraypress/lemon-squeezy-better-endpoints)
plugin must be installed and active on your website to use this library.**

## Installation

To integrate the library into your WordPress plugin, use Composer:

```bash
composer require arraypress/lemon-squeezy-updater
```

### Including the Library in Your Plugin

Include the Composer autoloader in your plugin to access the library functionalities:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

### Example Integration

```php
use ArrayPress\LemonSqueezy\Updater;

// Initialize the updater with your Lemon Squeezy store details
$updater = new Updater(
	__FILE__,
	'https://example.com/wp-json/lsq/v1',
	'1.0.0', // Optional. Leave empty to use the WordPress plugin version number
	'store_id', // Optional but recommended. Your Lemon Squeezy Store ID (12345)
	'product_id', // Optional but recommended. Your Lemon Squeezy Product ID (12345)
	'variation_id', // Optional. Your Lemon Squeezy Product Variation ID (12345)
	'https://example.com/my-plugin-page' // Optional. Expiration Renewal URL
);

if ( ! $updater->is_license_activated() ) {
 // Disable functionality in your plugin if not active
}
```

## Contributions

Contributions to improve the library are welcome. Please submit pull requests or create issues on GitHub for any bugs or
feature suggestions.

## License

This library is licensed under the GPL-2.0-or-later. It is free to use and modify according to the terms of the GNU
General Public License.