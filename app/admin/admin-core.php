<?php

/**
 * Admin Core
 *
 * @package Fez_Delivery
 * @since 1.0.0
 * @author Fez Team (https://www.fezdelivery.co)
 * @copyright (c) 2025, Fez Team (https://www.fezdelivery.co)
 */

namespace Fez_Delivery\Admin;

use Fez_Delivery\Base;
use WC_Fez_Delivery_Shipping_Method;

//check for security
if (!defined('ABSPATH')) {
	exit("You are not allowed to access this file.");
}

/**
 * Class Admin_Core
 *
 * @package Fez_Delivery\Admin
 */
class Admin_Core extends Base
{


	/**
	 * Init
	 *
	 * @return void
	 */
	public function init()
	{
		//on woocommerce loaded
		add_action('woocommerce_loaded', array($this, 'init_admin'));
	}

	/**
	 * Init admin
	 *
	 * @return void
	 */
	public function init_admin()
	{
		//load shipping method
		add_action('woocommerce_shipping_init', array($this, 'load_shipping_method'), PHP_INT_MAX);
		//add shipping method to woocommerce
		add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'), PHP_INT_MAX);
		//enable city shipping calculator
		add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
	}

	/**
	 * Load shipping method
	 *
	 * @return void
	 */
	public function load_shipping_method()
	{
		//instantiate shipping method
		new WC_Fez_Delivery_Shipping_Method();
	}

	/**
	 * Add shipping method to woocommerce
	 *
	 * @param array $methods
	 * @return array
	 */
	public function add_shipping_method($methods)
	{
		//add fez delivery shipping method
		$methods['fez_delivery'] = 'WC_Fez_Delivery_Shipping_Method';
		//return methods
		return $methods;
	}
}
