<?php

/**
 * Plugin Name:     Fez Delivery
 * Plugin URI:      https://www.fezdelivery.co/wp-plugins/fez-delivery/
 * Description:     Fez Delivery is a WordPress plugin that allows you to manage your delivery orders.
 * Author:          Fez Team
 * Author URI:      https://www.fezdelivery.co/
 * Text Domain:     fez-delivery
 * Version:         0.1.0
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 *
 * @package  Fez_Delivery
 * @category Plugin
 * @author Fez Team
 * @copyright 2025 Fez Team
 * @license GPLv2 or later
 * @link https://www.fezdelivery.co/
 */

//check for security
if (!defined('ABSPATH')) {
	exit("You can't access file directly");
}

//define constants
define('FEZ_DELIVERY_VERSION', time());
define('FEZ_DELIVERY_FILE', __FILE__);
define('FEZ_DELIVERY_DIR', __DIR__);
define('FEZ_DELIVERY_URL', plugin_dir_url(__FILE__));
define('FEZ_DELIVERY_DIR_PATH', plugin_dir_path(__FILE__));
define('FEZ_DELIVERY_BASENAME', plugin_basename(__FILE__));
//assets url
define('FEZ_DELIVERY_ASSETS_URL', FEZ_DELIVERY_URL . '/assets/');
//sandbox api url
define('FEZ_DELIVERY_SANDBOX_API_URL', 'https://apisandbox.fezdelivery.co/');
//production api url
define('FEZ_DELIVERY_PRODUCTION_API_URL', 'https://api.fezdelivery.co/');
//sandbox tracking url
define('FEZ_DELIVERY_SANDBOX_TRACKING_URL', 'https://d2pqv4mo6dthx7.cloudfront.net/track-delivery/');
//production tracking url
define('FEZ_DELIVERY_PRODUCTION_TRACKING_URL', 'https://web.fezdelivery.co/track-delivery/');

//load the plugin
require_once __DIR__ . '/vendor/autoload.php';

//init the plugin
Fez_Delivery\Loader::instance();
