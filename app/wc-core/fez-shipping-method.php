<?php

use Fez_Delivery\Admin\FezCoreSession;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Check if WooCommerce shipping method is active
if (!class_exists('WC_Shipping_Method')) {
	return;
}

/**
 * Fez Delivery Shipping Method Class
 *
 * Provides real-time shipping rates from Fez Delivery and handle order requests
 *
 * @since 1.0
 *
 * @extends \WC_Shipping_Method
 */
class WC_Fez_Delivery_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct($instance_id = 0)
	{
		$this->id                 = 'fez_delivery';
		$this->instance_id           = absint($instance_id);
		$this->method_title       = __('Fez Delivery', 'fez-delivery');
		$this->method_description = __('Enjoy reliable and efficient shipping solutions for local and global delivery needs.', 'fez-delivery');

		$this->supports  = array(
			'settings',
			'shipping-zones',
			// 'instance-settings',
			// 'instance-settings-modal',
		);

		$this->init();

		$this->title = 'Fez Delivery';

		$this->enabled = $this->get_option('enabled');
	}

	/**
	 * Init.
	 *
	 * Initialize Fez delivery shipping method.
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		$this->init_form_fields();
		$this->init_settings();

		// Save settings in admin if you have any defined
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Init fields.
	 *
	 * Add fields to the Fez delivery settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		//get fez_delivery_user
		$fez_delivery_user = get_option('fez_delivery_user');
		//get woocommerce states
		$woocommerce_states = WC()->countries->get_states("NG");
		//init form
		$this->form_fields = array(
			'enabled' => array(
				'title'     => __('Enable/Disable', 'fez-delivery'),
				'type'         => 'checkbox',
				'label'     => __('Enable this shipping method', 'fez-delivery'),
				'default'     => 'no',
				'disabled' => !empty($fez_delivery_user) ? true : false,
			),
			//fez mode
			"fez_mode" => array(
				"title" => __("Fez Mode", 'fez-delivery'),
				"type" => "select",
				"description" => __("Select production or sandbox mode, production mode will use for live orders", 'fez-delivery'),
				"placeholder" => "sandbox",
				"default" => "sandbox",
				"disabled" => !empty($fez_delivery_user) ? true : false,
				"options" => array(
					"sandbox" => __("Sandbox", 'fez-delivery'),
					"production" => __("Production", 'fez-delivery'),
				),
			),
			//fez username/email
			"fez_username" => array(
				"title" => __("Fez Username/Email", 'fez-delivery'),
				"type" => "text",
				"description" => __("Enter your Fez username or email", 'fez-delivery'),
				"placeholder" => "username@example.com",
				"default" => "",
				"disabled" => !empty($fez_delivery_user) ? true : false,
			),
			'enable_fez_safe_locker' => array(
				'title'     => __('Enable/Disable Safe Locker', 'fez-delivery'),
				'type'         => 'checkbox',
				'label'     => __('Enable Fez Safe Locker', 'fez-delivery'),
				'default'     => 'no',
				'disabled' => !empty($fez_delivery_user) ? true : false,
			),
			//fez password
			"fez_password" => array(
				"title" => __("Fez Password", 'fez-delivery'),
				"type" => !empty($fez_delivery_user) ? "hidden" : "password",
				"description" => __("Enter your Fez password", 'fez-delivery'),
				"placeholder" => "********",
				"default" => "",
				"class" => !empty($fez_delivery_user) ? "fez-password-hidden" : "fez-password",
				"disabled" => !empty($fez_delivery_user) ? true : false,
			),
			//fez pickup state
			"fez_pickup_state" => array(
				"title" => __("Fez Pickup State", 'fez-delivery'),
				"type" => "select",
				"description" => __("Select your Fez pickup state", 'fez-delivery'),
				"placeholder" => "LA",
				"default" => "LA",
				"options" => $woocommerce_states,
				"disabled" => !empty($fez_delivery_user) ? true : false,
			),
			//add connection status
			"connection_status" => array(
				"title" => __("Connection Status", 'fez-delivery'),
				"type" => "readonly",
				"description" => "",
				"default" => "",
				"class" => "fez-connection-status",
			),
			//select for after order status
			"create_fez_order_condition" => array(
				"title" => __("Create Fez Order Condition", 'fez-delivery'),
				"type" => "select",
				"description" => __("Select the woocommerce order status to create a Fez order", 'fez-delivery'),
				"default" => "processing",
				"options" => array(
					"processing" => __("New Order", 'fez-delivery'),
					"completed" => __("Completed", 'fez-delivery'),
					"pending" => __("Pending", 'fez-delivery')
				),
				"disabled" => !empty($fez_delivery_user) ? true : false,
			)
		);
	}

	function is_available($package)
	{
		if ($this->enabled === "no")
			return false;
		return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true);
	}


	/**
	 * Calculate shipping by sending destination/items to Fez Delivery
	 *
	 * @since 1.0
	 * @param array $package
	 */
	public function calculate_shipping($package = array())
	{

		if ($this->get_option('enabled') == 'no') {
			return;
		}

		//get delivery cost
		$fezsession = FezCoreSession::instance();
		$delivery_cost = $fezsession->get('delivery_cost');

		//check if delivery cost is set
		if (!empty($delivery_cost)) {
			//apply rate
			$this->add_rate(array(
				'id'        => $this->id . $this->instance_id,
				'label'     => apply_filters('fez_delivery_shipping_method_label', "Fez Delivery"),
				'cost'      => apply_filters('fez_delivery_shipping_method_cost', $delivery_cost),
			));
			//return
			return;
		}

		//apply rate
		$this->add_rate(array(
			'id'        => $this->id . $this->instance_id,
			'label'     => apply_filters('fez_delivery_shipping_method_label', "Fez Delivery"),
			'cost'      => apply_filters('fez_delivery_shipping_method_cost', 0),
			'meta_data' => [],
		));
	}
}
