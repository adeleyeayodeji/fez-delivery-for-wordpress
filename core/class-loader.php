<?php

/**
 * Class to boot up plugin.
 *
 * @link    https://www.fezdelivery.co/
 * @since   1.0.0
 *
 * @author  Fez Team (https://www.fezdelivery.co)
 * @package Fez_Delivery
 *
 * @copyright (c) 2025, Fez Team (https://www.fezdelivery.co)
 */

namespace Fez_Delivery;

use Fez_Delivery\Admin\Admin_Core;
use Fez_Delivery\Admin\API_Core;
use Fez_Delivery\Admin\Fez_Core;
use Fez_Delivery\Base;

// If this file is called directly, abort.
defined('WPINC') || die;

final class Loader extends Base
{
	/**
	 * Settings helper class instance.
	 *
	 * @since 1.0.0
	 * @var object
	 *
	 */
	public $settings;

	/**
	 * Minimum supported php version.
	 *
	 * @since  1.0.0
	 * @var float
	 *
	 */
	public $php_version = '7.4';

	/**
	 * Minimum WordPress version.
	 *
	 * @since  1.0.0
	 * @var float
	 *
	 */
	public $wp_version = '5.0';

	/**
	 * Active plugins.
	 *
	 * @since  1.0.0
	 * @var array
	 *
	 */
	private static $active_plugins;

	/**
	 * Initialize functionality of the plugin.
	 *
	 * This is where we kick-start the plugin by defining
	 * everything required and register all hooks.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function __construct()
	{
		self::$active_plugins = (array) get_option('active_plugins', array());

		if (is_multisite()) {
			self::$active_plugins = array_merge(self::$active_plugins, get_site_option('active_sitewide_plugins', array()));
		}

		if (!$this->can_boot()) {
			//log error
			error_log('Fez Delivery: Plugin could not boot, PHP version is less than ' . $this->php_version . ' and WP version is less than ' . $this->wp_version);
			return;
		}

		if (!$this->wc_active_check()) {
			//add install wc notice
			add_action('admin_notices', array($this, 'install_wc_notice'));
			return;
		}

		$this->init();
	}

	/**
	 * WooCommerce Checks
	 *
	 */
	public function wc_active_check()
	{
		return in_array('woocommerce/woocommerce.php', self::$active_plugins) || array_key_exists('woocommerce/woocommerce.php', self::$active_plugins);
	}

	/**
	 * Install WC Notice
	 *
	 */
	public function install_wc_notice()
	{
		$class = 'notice notice-error';
		$message = __('Fez Delivery requires WooCommerce to be installed and activated.', 'fez-delivery');
		$link = admin_url('plugin-install.php?s=woocommerce&tab=search&type=term');

		printf('<div class="%1$s"><p>%2$s <a href="%3$s">Install</a></p></div>', esc_attr($class), esc_html($message), esc_url($link));
	}


	/**
	 * Main condition that checks if plugin parts should continue loading.
	 *
	 * @return bool
	 */
	private function can_boot()
	{
		/**
		 * Checks
		 *  - PHP version
		 *  - WP Version
		 * If not then return.
		 */
		global $wp_version;

		return (
			version_compare(PHP_VERSION, $this->php_version, '>') &&
			version_compare($wp_version, $this->wp_version, '>')
		);
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function init()
	{
		//init admin core
		Admin_Core::instance()->init();
		//init fez core
		Fez_Core::instance()->init();
		//init api core
		API_Core::instance()->init();
	}
}
