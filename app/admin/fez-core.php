<?php

/**
 * Fez Core
 *
 * @package Fez_Delivery
 * @since 1.0.0
 * @author Fez Team (https://www.fezdelivery.co)
 * @copyright (c) 2025, Fez Team (https://www.fezdelivery.co)
 */

namespace Fez_Delivery\Admin;

use Fez_Delivery\Base;
use WC_Fez_Delivery_Shipping_Method;
use WpOrg\Requests\Requests;


//check for security
if (!defined('ABSPATH')) {
	exit("You are not allowed to access this file.");
}

class Fez_Core extends Base
{
	/**
	 * Fez mode
	 * @var string
	 */
	public $fez_mode;

	/**
	 * API URL
	 * @var string
	 */
	public $api_url;

	/**
	 * User ID
	 * @var string
	 */
	public $user_id;

	/**
	 * Password
	 * @var string
	 */
	public $password;

	/**
	 * Pickup state
	 * @var string
	 */
	public $pickup_state;

	/**
	 * Fez delivery user
	 * @var array
	 */
	public $fez_delivery_user = [];

	/**
	 * Constructor
	 * @return void
	 */
	public function __construct()
	{
		//get fez mode
		$this->getFezMode();
	}

	/**
	 * Initialize the class
	 * @return void
	 */
	public function init()
	{
		//plugin loaded
		add_action('admin_init', array($this, 'fez_delivery_plugin_loaded'), PHP_INT_MAX);
	}

	/**
	 * fez_delivery_plugin_loaded
	 *
	 */
	public function fez_delivery_plugin_loaded()
	{
		$shipping = new WC_Fez_Delivery_Shipping_Method();
		//check if shipping method is enabled
		if ($shipping->enabled == "no") {
			add_action('admin_notices', array($this, 'fez_delivery_disabled_notice'));
		}
	}

	/**
	 * fez_delivery_disabled_notice
	 *
	 */
	public function fez_delivery_disabled_notice()
	{
		echo '<div class="error notice is-dismissible"><p>' . __('Fez Delivery is disabled. Please enable it in the WooCommerce settings. <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=fez_delivery') . '">Settings</a>', 'fez-delivery') . '</p></div>';
	}

	/**
	 * Get the Fez mode
	 * @param string $fez_mode
	 * @return string
	 */
	public function getFezMode($fez_mode = null)
	{
		$fez_options = get_option('woocommerce_fez_delivery_settings');
		//get fez_delivery_user
		$fez_delivery_user = get_option('fez_delivery_user');
		//check if fez_delivery_user is set
		if (isset($fez_delivery_user)) {
			$this->fez_delivery_user = $fez_delivery_user;
		}

		//check if data is set
		if (isset($fez_options['fez_mode'])) {
			$this->fez_mode = $fez_options['fez_mode'];
		} else {
			$this->fez_mode = 'sandbox';
		}

		//check if $fez_mode is set
		if ($fez_mode) {
			$this->fez_mode = $fez_mode;
		}

		//check if fez mode is production
		if ($this->fez_mode == 'production') {
			$this->api_url = FEZ_DELIVERY_PRODUCTION_API_URL;
		} else {
			$this->api_url = FEZ_DELIVERY_SANDBOX_API_URL;
		}

		//get the user id and password
		$this->user_id = $fez_options['fez_username'];
		$this->password = $fez_options['fez_password'];

		//get the pickup state
		$this->pickup_state = $fez_options['fez_pickup_state'];
	}

	/**
	 * Authenticate user
	 * @param array $user_credentials
	 * @return array
	 */
	public function authenticateUser($user_credentials = [])
	{
		try {

			// Get cached auth token
			$auth_token = get_transient('fez_delivery_auth_token_static');

			// Check if valid and not expired
			if ($auth_token && isset($auth_token['expireToken']) && strtotime($auth_token['expireToken']) > time()) {
				return [
					'success' => true,
					'message' => 'User authenticated successfully',
					'data' => $auth_token
				];
			}

			// Prepare credentials
			$request_args = !empty($user_credentials) ? $user_credentials : [
				'user_id' => $this->user_id,
				'password' => $this->password
			];

			// Authenticate user via API
			$response = Requests::post(
				$this->api_url . 'v1/user/authenticate',
				['Content-Type' => 'application/json'],
				json_encode($request_args)
			);

			// Decode response
			$body = json_decode($response->body);

			// Ensure response is successful
			if ($response->status_code !== 200 || !isset($body->authDetails->authToken)) {
				throw new \Exception($body->description ?? 'Authentication failed');
			}

			// Extract authentication details
			$expire_timestamp = strtotime($body->authDetails->expireToken);
			if (!$expire_timestamp) {
				throw new \Exception('Invalid expiration time received');
			}

			$response_data = [
				'success' => true,
				'message' => 'User authenticated successfully',
				'data' => $body,
				'authToken' => $body->authDetails->authToken,
				'expireToken' => $body->authDetails->expireToken
			];

			// Calculate expiration time difference
			$current_timestamp = time();
			$time_difference = max($expire_timestamp - $current_timestamp, 1); // Ensure at least 1 second

			// Store in transient with expiration
			set_transient('fez_delivery_auth_token_static', $response_data, $time_difference);

			return [
				'success' => true,
				'message' => 'User authenticated successfully',
				'data' => $response_data
			];
		} catch (\Exception $e) {
			error_log("Fez Authentication Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'data' => null
			];
		}
	}

	/**
	 * Get delivery cost
	 * @param string $delivery_state
	 * @param string $pickup_state
	 * @param float $total_weight
	 * @return array
	 */
	public function getDeliveryCost(string $delivery_state, string $pickup_state, float $total_weight)
	{
		try {
			//get the auth token
			$auth_token = $this->authenticateUser();

			//check if auth token is set
			if (!$auth_token['success']) {
				throw new \Exception($auth_token['message']);
			}

			//get secret key
			$secret_key = $auth_token["data"]['data']->orgDetails->{'secret-key'};

			$url = $this->api_url . 'v1/order/cost';

			$headers = [
				'Content-Type' => 'application/json',
				'secret-key'   => $secret_key,
				'Authorization' => 'Bearer ' . $auth_token['data']['authToken']
			];

			$data = [
				'state' => $delivery_state,
				'pickUpState' => $pickup_state,
				'weight' => $total_weight
			];

			$response = Requests::post($url, $headers, json_encode($data));

			//get the body
			$response_body = json_decode($response->body);

			//check if response is successful
			if (!$response->success) {
				throw new \Exception("Fez Server: " . $response_body->description);
			}

			//check if response status is Success
			if ($response_body->status == 'Success') {
				//return success
				return [
					'success' => true,
					'message' => $response_body->description,
					'cost' => $response_body->Cost
				];
			}

			//return error
			return [
				'success' => false,
				'message' => $response_body->description,
				'data' => null
			];
		} catch (\Exception $e) {
			error_log("Fez Delivery Cost Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'data' => null
			];
		}
	}

	/**
	 * Create order
	 * @param array $data
	 * @return array
	 */
	public function createOrder(array $data)
	{
		try {
			//get the auth token
			$auth_token = $this->authenticateUser();

			//check if auth token is set
			if (!$auth_token['success']) {
				throw new \Exception($auth_token['message']);
			}

			//get secret key
			$secret_key = $this->fez_delivery_user["data"]->orgDetails->{'secret-key'};

			$url = $this->api_url . 'v1/order';
			$headers = [
				'Content-Type' => 'application/json',
				'secret-key'   => $secret_key,
				'Authorization' => 'Bearer ' . $auth_token['data']['authToken']
			];

			//create order
			$response = Requests::post($url, $headers, json_encode($data));

			//get the body
			$response_body = json_decode($response->body);

			//check if response is successful
			if (!$response->success) {
				throw new \Exception($response->body);
			}

			//return success
			return [
				'success' => true,
				'message' => $response_body->description,
				'data' => $response_body->orderNos
			];
		} catch (\Exception $e) {
			error_log("Fez Delivery Cost Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'data' => null
			];
		}
	}

	/**
	 * Get fez delivery order details
	 * @param string $order_id
	 * @param string $order_nos
	 * @return array
	 */
	public function getFezDeliveryOrderDetails(string $order_id, string $order_nos)
	{
		try {
			//get the auth token
			$auth_token = $this->authenticateUser();

			//get secret key
			$secret_key = $this->fez_delivery_user["data"]->orgDetails->{'secret-key'};

			//https://apisandbox.fezdelivery.co/v1/orders/JHAZ27012319
			$url = $this->api_url . 'v1/orders/' . $order_nos;
			$headers = [
				'Content-Type' => 'application/json',
				'secret-key'   => $secret_key,
				'Authorization' => 'Bearer ' . $auth_token['data']['authToken']
			];

			$response = Requests::get($url, $headers);

			//get the body
			$response_body = json_decode($response->body);

			//check if response is successful
			if (!$response->success) {
				throw new \Exception($response_body->description);
			}

			//check if response status is Success
			if ($response_body->status == 'Success') {
				//get the first of orderDetails
				$order_detail = $response_body->orderDetails[0];

				//return success
				return [
					'success' => true,
					'message' => $response_body->description,
					'data' => [
						'order_status' => $order_detail->orderStatus,
						'cost' => wc_price($order_detail->cost),
						'order_detail' => $order_detail
					]
				];
			}

			//return error
			return [
				'success' => false,
				'message' => $response_body->description,
				'data' => null
			];
		} catch (\Exception $e) {
			error_log("Fez Delivery Order Details Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'data' => null
			];
		}
	}
}
