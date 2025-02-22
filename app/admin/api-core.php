<?php

/**
 * API Core
 *
 * @package Fez_Delivery
 * @since 1.0.0
 * @author Fez Team (https://www.fezdelivery.co)
 * @copyright (c) 2025, Fez Team (https://www.fezdelivery.co)
 */

namespace Fez_Delivery\Admin;

use Fez_Delivery\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

//check for security
if (!defined('ABSPATH')) {
	exit("You are not allowed to access this file.");
}

class API_Core extends Base
{
	/**
	 * Order statuses
	 *
	 * @var array
	 */
	public $order_statuses = array(
		"Accepted At Inventory Facility",
		"Accepted At Last Mile Hub",
		"Assigned To A Rider",
		"Delivered",
		"Dispatched",
		"Enroute To Last Mile Hub",
		"Exported",
		"In Return To Customer",
		"In Return To First Mile Hub",
		"In Return To Last Mile Hub",
		"Pending Payment",
		"Pending Pick-Up",
		"Pending Recipient Pick-Up",
		"Picked-Up",
		"Rejected At Inventory Facility",
		"Rejected At Last Mile Hub",
		"Returned",
		"Returned To First Mile Hub",
		"Returned To Last Mile Hub"
	);

	/**
	 * namespace
	 *
	 */
	private $namespace = 'fez-delivery';

	/**
	 * API Version
	 *
	 * @var string
	 */
	private $api_version = 'v1';

	/**
	 * Init
	 *
	 * @return void
	 */
	public function init()
	{
		//rest api init
		add_action('rest_api_init', [$this, 'initApi']);
	}

	/**
	 * Init API
	 *
	 * @return void
	 */
	public function initApi()
	{
		/**
		 * Get WooCommerce Orders
		 * @example http://localhost/wordpress/wp-json/fez-delivery/v1/orders
		 * @return void
		 */
		register_rest_route($this->namespace . '/' . $this->api_version, '/orders', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'getWooCommerceOrders'],
			'permission_callback' => [$this, 'checkPermission'],
			'args' => [
				'order_id' => [
					'type' => 'string',
					'required' => false,
				],
				'page' => [
					'type' => 'integer',
					'required' => false,
				],
				'limit' => [
					'type' => 'integer',
					'required' => false,
				],
				'date_from' => [
					'type' => 'string',
					'required' => false,
				],
				'date_to' => [
					'type' => 'string',
					'required' => false,
				],
				'customer_email' => [
					'type' => 'string',
					'required' => false,
				],
				'orderby' => [
					'type' => 'string',
					'required' => false,
				],
				'ordermode' => [
					'type' => 'string',
					'required' => false,
				],
			],
		]);
	}

	/**
	 * Check permission
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function checkPermission($request)
	{
		//get user settings
		$user_settings = get_option('fez_delivery_user', []);
		//check if user settings is set
		if (empty($user_settings)) {
			//return false
			return false;
		}
		//get request header
		$authorization = $request->get_header('authorization');
		//check if user settings is set
		if (isset($authorization) && !empty($authorization)) {
			//get token from bearer
			$token = str_replace('Bearer ', '', $authorization);
			//check if token is set
			if (empty($token)) {
				//return false
				return false;
			}
			//check if token is set
			if ($token !== $user_settings['data']->orgDetails->{'secret-key'}) {
				//return false
				return false;
			}
			//return true
			return true;
		}

		//return true
		return false;
	}

	/**
	 * Get all wc orders
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getWooCommerceOrders(WP_REST_Request $request)
	{
		try {
			//order id
			$order_id = $request->get_param('order_id');
			//sanitize order id
			$order_id = sanitize_text_field($order_id);
			//check if the order id is set
			if ($order_id) {
				//load single order
				return $this->loadOrder($order_id);
			}
			//page
			$page = $request->get_param('page');
			//sanitize page id
			$page = sanitize_text_field($page);
			//limit
			$limit = $request->get_param('limit');
			//sanitize limit
			$limit = sanitize_text_field($limit);
			//date_from
			$date_from = $request->get_param('date_from');
			//sanitize date from
			$date_from = sanitize_text_field($date_from);
			//date_to
			$date_to = $request->get_param('date_to');
			//sanitize date to
			$date_to = sanitize_text_field($date_to);
			//customer email
			$customer_email = $request->get_param('customer_email');
			//sanitize customer email
			$customer_email = sanitize_text_field($customer_email);
			//orderby
			$orderby = $request->get_param('orderby');
			//sanitize order by
			$orderby = sanitize_text_field($orderby);
			//order
			$ordermode = $request->get_param('ordermode');
			//sanitize
			$ordermode = sanitize_text_field($ordermode);
			//get all orders
			$orders = wc_get_orders([
				'limit' => $limit ?: 10,
				'page' => $page ?: 1,
				'date_before' => $date_to ?: '',
				'date_after' => $date_from ?: '',
				'status' => array_merge(['processing', 'completed', 'on-hold', 'pending']),
				'billing_email' => $customer_email,
				//order by date
				'orderby' => $orderby ?: 'date',
				//order type
				'order' => $ordermode ?: 'ASC',
				//where order id
				'include' => [$order_id]
			]);
			//get total orders
			$total_orders = wc_get_orders([
				'limit' => -1,
				'page' => 1,
				'date_before' => $date_to ?: '',
				'date_after' => $date_from ?: '',
				'status' => array_merge(['processing', 'completed', 'on-hold', 'pending']),
				'billing_email' => $customer_email,
				//where order id
				'include' => [$order_id]
			]);
			//orders list
			$orders_list = [];
			//loop through the orders
			foreach ($orders as $order) {
				//get the order id
				$order_id = $order->get_id();
				//get the order data
				$order_data = $order->get_data();
				//get the order date
				$order_date = $order->get_date_created();
				//get the order date
				$order_date = $order_date->date('Y-m-d H:i:s');
				//get the products
				$items = $order->get_items();
				//products
				$products = [];
				//loop through the products
				foreach ($items as $product_id => $item) {
					//convert to int $product_id
					$product_id = intval($product_id);
					$products[] = [
						"name" => $item->get_name(),
						"quantity" => intval($item->get_quantity()) ?: 1,
						"value" => $item->get_total(),
						"description" => "{$item->get_quantity()} of {$item->get_name()} at {$item->get_total()} each for a total of {$item->get_total()}",
						"type" => "parcel",
						"currency" => get_woocommerce_currency(),
						"weight" => (float)get_post_meta($product_id, '_weight', true) ?: 0.1
					];
				}
				//check if order has fez_order_id
				$fez_order_id = get_post_meta(
					$order_id,
					'fez_order_id',
					true
				);
				//orders list
				$orders_list[] = [
					"id" => $order_id,
					"fez_order_id" => $fez_order_id ?: null,
					"products" => $products,
					'order_meta' => [
						//meta goes here
					],
					"extra" => $order_data,
				];
			}
			//response
			$response = [
				"status" => 200,
				"message" => "Orders fetched successfully",
				"meta" => [
					"page" => $page ?: 1,
					"limit" => $limit ?: 10,
					"total" => count($total_orders)
				],
				"data" => $orders_list,
			];
			//return
			return new WP_REST_Response($response, 200);
		} catch (\Exception $e) {
			//response
			$response = [
				"status" => 500,
				"message" => "Error loading orders",
				"data" => $e->getMessage(),
			];
			//return
			return new WP_REST_Response($response, 500);
		}
	}


	/**
	 * Load single order
	 * @param $order_id
	 */
	public function loadOrder($order_id)
	{
		try {
			//get the order
			$order = wc_get_order($order_id);
			//check if the order is set
			if (!$order) {
				//response
				$response = [
					"status" => 404,
					"message" => "Order not found",
					"data" => [],
				];
				//return
				return new WP_REST_Response($response, 404);
			}
			//get the order data
			$order_data = $order->get_data();
			//get the order date
			$order_date = $order->get_date_created();
			//get the order date
			$order_date = $order_date->date('Y-m-d H:i:s');
			//get the products
			$items = $order->get_items();
			//products
			$products = [];
			//loop through the products
			foreach ($items as $product_id => $item) {
				//convert to int $product_id
				$product_id = intval($product_id);
				$products[] = [
					"name" => $item->get_name(),
					"quantity" => intval($item->get_quantity()) ?: 1,
					"value" => $item->get_total(),
					"description" => "{$item->get_quantity()} of {$item->get_name()} at {$item->get_total()} each for a total of {$item->get_total()}",
					"type" => "parcel",
					"currency" => get_woocommerce_currency(),
					"weight" => (float)get_post_meta($product_id, '_weight', true) ?: 0.1
				];
			}
			//check if order has fez_order_id
			$fez_order_id = get_post_meta($order_id, 'fez_order_id', true);
			//orders list
			$orders_list[] = [
				"id" => $order_id,
				"fez_order_id" => $fez_order_id ?: "none",
				"products" => $products,
				"extra" => $order_data,
				'order_meta' => [
					//meta goes here
				],
			];
			//response
			$response = [
				"status" => 200,
				"message" => "Single order fetched successfully",
				"data" => $orders_list,
			];
			//return
			return new WP_REST_Response($response, 200);
		} catch (\Exception $e) {
			//response
			$response = [
				"status" => 500,
				"message" => "Error loading order",
				"data" => $e->getMessage(),
			];
			//return
			return new WP_REST_Response($response, 500);
		}
	}
}
