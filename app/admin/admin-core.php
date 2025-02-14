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

			//validate username and password
			if (empty($fez_username) || empty($fez_password)) {
				throw new \Exception('Username or password is empty');
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
					'enabled' => $enabled ? 'yes' : 'no'
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
