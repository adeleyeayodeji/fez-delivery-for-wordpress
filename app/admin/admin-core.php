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
		//add action to save fez auth
		add_action('wp_ajax_save_fez_auth_woocommerce', array($this, 'save_fez_auth_woocommerce'));
		//add action to disconnect fez auth
		add_action('wp_ajax_disconnect_fez_auth', array($this, 'disconnect_fez_auth'));
		//add settings link
		add_filter('plugin_action_links_' . FEZ_DELIVERY_BASENAME, array($this, 'settings_link'));
	}

	/**
	 * Settings link
	 *
	 * @param array $links
	 * @return array
	 */
	public function settings_link($links)
	{
		//add settings link
		$links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=fez_delivery') . '">' . __('Settings', 'fez-delivery') . '</a>';
		//return links
		return $links;
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
		//enqueue admin script
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_script'));
		//enqueue frontend script
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_script'));
		//add action to get fez delivery cost
		add_action('wp_ajax_get_fez_delivery_cost', array($this, 'get_fez_delivery_cost'));
		//add action to get fez delivery cost
		add_action('wp_ajax_nopriv_get_fez_delivery_cost', array($this, 'get_fez_delivery_cost'));
		//add action to apply fez delivery cost
		add_action('wp_ajax_apply_fez_delivery_cost', array($this, 'apply_fez_delivery_cost'));
		//add action to refresh shipping methods realtime
		add_action('woocommerce_checkout_update_order_review', array($this, 'checkout_update_refresh_shipping_methods'), PHP_INT_MAX, 1);
		//add action to apply fez delivery cost
		add_action('wp_ajax_nopriv_apply_fez_delivery_cost', array($this, 'apply_fez_delivery_cost'));
		//fez_reset_cost_data
		add_action('wp_ajax_fez_reset_cost_data', array($this, 'fez_reset_cost_data'));
		// Add shipping icon to the shipping label
		add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_shipping_icon'), PHP_INT_MAX, 2);
	}

	/**
	 * Add shipping icon
	 *
	 * @param string $label
	 * @param object $method
	 * @return string
	 */
	public function add_shipping_icon($label, $method)
	{
		if ($method->method_id == 'fez_delivery') {
			$logo_title = 'Fez Delivery';
			$icon_url = FEZ_DELIVERY_ASSETS_URL . 'img/fez_logo.svg';
			$img = '<img class="fez-delivery-logo" align="left"' .
				' alt="' . $logo_title . '"' .
				' title="' . $logo_title . '"' .
				' src="' . $icon_url . '"' .
				'>';
			$label = $img . ' ' . $label;
		}

		return $label;
	}

	/**
	 * Checkout update refresh shipping methods realtime
	 *
	 * @param string $post_data
	 * @return void
	 */
	public function checkout_update_refresh_shipping_methods($post_data)
	{
		//update shipping pricing realtime
		$packages = WC()->cart->get_shipping_packages();
		foreach ($packages as $package_key => $package) {
			WC()->session->set('shipping_for_package_' . $package_key, false); // Or true
		}
	}

	/**
	 * fez_reset_cost_data
	 *
	 * @return void
	 */
	public function fez_reset_cost_data()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce($_POST['nonce'], 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce');
			}
			//unset delivery cost
			$fezsession = FezCoreSession::instance();
			$fezsession->unset('delivery_cost');

			//return success
			wp_send_json_success(array('message' => 'Delivery cost reset successfully'));
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Cost Clear Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Apply fez delivery cost
	 *
	 * @return void
	 */
	public function apply_fez_delivery_cost()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce($_POST['nonce'], 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce');
			}

			//get delivery cost
			$delivery_cost = sanitize_text_field($_POST['delivery_cost']);

			//check if delivery cost is not empty
			if (empty($delivery_cost)) {
				throw new \Exception('Delivery cost is empty, please try again');
			}

			$fezsession = FezCoreSession::instance();
			$fezsession->set('delivery_cost', $delivery_cost);

			//return success
			wp_send_json_success(array('message' => 'Delivery cost applied successfully'));
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Cost Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Get fez delivery cost
	 *
	 * @return void
	 */
	public function get_fez_delivery_cost()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce($_POST['nonce'], 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce');
			}

			//get delivery state
			$delivery_state = sanitize_text_field($_POST['deliveryState']);

			//check if not empty
			if (empty($delivery_state)) {
				throw new \Exception('Delivery state is empty');
			}

			//init fez core
			$fez_core = Fez_Core::instance();

			//get pickup state
			$pickup_state = $fez_core->pickup_state;

			//get woocommerce states
			$woocommerce_states = WC()->countries->get_states("NG");

			//get state from state code
			$delivery_state_label = $woocommerce_states[$delivery_state];

			//get state from state code
			$pickup_state_label = $woocommerce_states[$pickup_state];

			//get total weight
			$cart_items = WC()->cart->get_cart();

			//check if cart items is not empty
			if (empty($cart_items)) {
				throw new \Exception('Cart items are empty');
			}

			//get total weight
			$total_weight = 0;
			foreach ($cart_items as $item) {
				$total_weight += !empty($item['data']->get_weight()) ? (float)$item['data']->get_weight() : 3;
			}

			//get delivery cost
			$response = $fez_core->getDeliveryCost($delivery_state_label, $pickup_state_label, $total_weight);

			//check if response is successful
			if ($response['success']) {
				//return success
				wp_send_json_success(array(
					'message' => $response['message'],
					'cost' => $response['cost']
				));
			} else {
				//return error
				wp_send_json_error(array(
					'message' => $response['message'],
					'cost' => 0
				));
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Cost Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array(
				'message' => 'Error getting delivery cost: ' . $e->getMessage(),
				'cost' => 0
			));
		}
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

	/**
	 * Enqueue admin script
	 *
	 * @return void
	 */
	public function enqueue_admin_script()
	{
		//enqueue admin script
		wp_enqueue_script('fez-delivery-admin-script', FEZ_DELIVERY_ASSETS_URL . 'js/fezdelivery.min.js', array('jquery'), FEZ_DELIVERY_VERSION, true);
		//style
		wp_enqueue_style('fez-delivery-admin-style', FEZ_DELIVERY_ASSETS_URL . 'css/fezdelivery.min.css', array(), FEZ_DELIVERY_VERSION);
		//localize script
		wp_localize_script('fez-delivery-admin-script', 'fez_delivery_admin', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fez_delivery_admin_nonce'),
			'connection_status' => $this->connection_status_html()
		));
	}

	/**
	 * Enqueue frontend script
	 *
	 * @return void
	 */
	public function enqueue_frontend_script()
	{
		//enqueue frontend script
		wp_enqueue_script('fez-delivery-frontend-script', FEZ_DELIVERY_ASSETS_URL . 'js/fezdeliveryhome.min.js', array('jquery'), FEZ_DELIVERY_VERSION, true);
		//style
		wp_enqueue_style('fez-delivery-frontend-style', FEZ_DELIVERY_ASSETS_URL . 'css/fezdeliveryhome.min.css', array(), FEZ_DELIVERY_VERSION);
		//localize script
		wp_localize_script('fez-delivery-frontend-script', 'fez_delivery_frontend', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fez_delivery_frontend_nonce')
		));
	}

	/**
	 * Connection status html
	 *
	 * @return string
	 */
	public function connection_status_html()
	{
		//get fez_delivery_user
		$fez_delivery_user = get_option('fez_delivery_user');

		ob_start();
		//html
?>
		<div class='fez-connection-status-notice'>
			<?php if (!empty($fez_delivery_user)) : ?>
				<span class='fez-connection-status-connected'>
					✅ Connected to Fez Server
				</span>
			<?php else : ?>
				<span class='fez-connection-status-pending'>
					❌ Pending connection to Fez Server
				</span>
			<?php endif; ?>
		</div>
<?php
		//return html
		$html = ob_get_clean();
		return [
			'html' => $html,
			'connection_status' => !empty($fez_delivery_user) ? 'connected' : 'pending'
		];
	}

	/**
	 * Save fez auth woocommerce
	 *
	 * @return void
	 */
	public function save_fez_auth_woocommerce()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce($_POST['nonce'], 'fez_delivery_admin_nonce')) {
				throw new \Exception('Invalid nonce');
			}
			//get woocommerce_fez_delivery_fez_username
			$fez_username = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_username']);
			//get woocommerce_fez_delivery_fez_password
			$fez_password = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_password']);
			//get woocommerce_fez_delivery_fez_mode
			$fez_mode = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_mode']);
			//get woocommerce_fez_delivery_enabled
			$enabled = absint($_POST['woocommerce_fez_delivery_enabled']);
			//get woocommerce_fez_delivery_fez_pickup_state
			$fez_pickup_state = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_pickup_state']);

			//validate username and password
			if (empty($fez_username) || empty($fez_password)) {
				throw new \Exception('Username or password is empty');
			}

			//validate pickup state
			if (empty($fez_pickup_state)) {
				throw new \Exception('Pickup state is empty');
			}

			//validate user credentials
			$user_credentials = array(
				'user_id' => $fez_username,
				'password' => $fez_password
			);

			//authenticate user
			$fez_core = new Fez_Core();
			//set fez mode
			$fez_core->getFezMode($fez_mode);
			//authenticate user
			$response = $fez_core->authenticateUser($user_credentials);

			//check if response is successful
			if ($response['success']) {
				//get old options
				$old_options = get_option('woocommerce_fez_delivery_settings');

				//save to options to woocommerce
				$woo_args = [
					'fez_mode' => $fez_mode,
					'fez_username' => $fez_username,
					'fez_password' => $fez_password,
					'enabled' => $enabled ? 'yes' : 'no',
					'fez_pickup_state' => $fez_pickup_state
				];
				//update woocommerce options
				update_option('woocommerce_fez_delivery_settings', array_merge($old_options, $woo_args));

				//set fez_delivery_user
				update_option("fez_delivery_user", $response['data']);

				//return success
				wp_send_json_success(array(
					'message' => 'User authenticated successfully',
					'status' => 'success'
				));
			} else {
				//delete user option
				delete_option('fez_delivery_user');
				//return error
				wp_send_json_error(array(
					'message' => $response['message'],
					'status' => 'error'
				));
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Auth Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array(
				'message' => 'Error validating user credentials: ' . $e->getMessage(),
				'status' => 'error'
			));
		}
	}

	/**
	 * Disconnect fez auth
	 *
	 * @return void
	 */
	public function disconnect_fez_auth()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce($_POST['nonce'], 'fez_delivery_admin_nonce')) {
				throw new \Exception('Invalid nonce');
			}
			//delete user option
			delete_option('fez_delivery_user');
			//return success
			wp_send_json_success(array(
				'message' => 'User disconnected successfully',
			));
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Auth Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array(
				'message' => 'Error disconnecting user: ' . $e->getMessage(),
				'status' => 'error'
			));
		}
	}
}
