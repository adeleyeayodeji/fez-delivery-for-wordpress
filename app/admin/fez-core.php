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
use WpOrg\Requests\Requests;

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
	public function init() {}

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
	 * @return mixed
	 */
	public function authenticateUser($user_credentials = [])
	{
		try {
			//create hash for the user credentials
			$hash = md5(json_encode($user_credentials));

			//get the auth token
			$auth_token = get_transient('fez_delivery_auth_token_' . $hash);

			//check if valid
			if ($auth_token) {
				//return existing auth token
				return [
					'success' => true,
					'message' => 'User authenticated successfully',
					'data' => $auth_token
				];
			}

			//check if user credentials are set
			if (empty($user_credentials)) {
				$request_args = [
					'user_id' => $this->user_id,
					'password' => $this->password
				];
			} else {
				$request_args = $user_credentials;
			}

			//authenticate user
			$response = Requests::post($this->api_url . 'v1/user/authenticate', [
				'Content-Type' => 'application/json'
			], json_encode($request_args));

			//get the body
			$body = json_decode($response->body);

			//check if response is successful
			if (!$response->success) {
				//throw error
				throw new \Exception($body->description);
			}

			//return response
			$response_data = [
				'success' => true,
				'message' => 'User authenticated successfully',
				'data' => $body,
				'authToken' => $body->authDetails->authToken,
				'expireToken' => $body->authDetails->expireToken
			];

			//save to transients for the expiry time (2025-02-06 11:04:24)
			set_transient('fez_delivery_auth_token_' . $hash, $response_data, strtotime($response_data['expireToken']));

			//return response
			return [
				'success' => true,
				'message' => 'User authenticated successfully',
				'data' => $response_data
			];
		} catch (\Exception $e) {
			error_log("Fez Authentication Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
			//return error response
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
			$secret_key = $this->fez_delivery_user["data"]->orgDetails->{'secret-key'};

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

			$response = Requests::request($url, $headers, json_encode($data), 'PUT');

			//log all sent data
			error_log("Fez Delivery Cost Data: " . print_r($data, true));
			error_log("Fez Delivery Cost Headers: " . print_r($headers, true));
			error_log("Fez Delivery Cost Response: " . print_r($response, true));

			if (!$response->success) {
				throw new \Exception($response->body);
			}

			$response_body = json_decode($response->body);

			//log the response
			error_log("Fez Delivery Cost Response: " . print_r($response_body, true));

			// Process the response as needed
			return [
				'success' => true,
				'message' => 'Delivery cost retrieved successfully',
				'data' => $response_body
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
}
