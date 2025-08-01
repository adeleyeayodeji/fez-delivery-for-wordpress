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
use WC_Order;
use WC_Order_Item_Shipping;

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
		//add action to apply fez delivery cost
		add_action('wp_ajax_nopriv_apply_fez_delivery_cost', array($this, 'apply_fez_delivery_cost'));
		//add action to refresh shipping methods realtime
		add_action('woocommerce_checkout_update_order_review', array($this, 'checkout_update_refresh_shipping_methods'), PHP_INT_MAX, 1);
		//fez_reset_cost_data
		add_action('wp_ajax_fez_reset_cost_data', array($this, 'fez_reset_cost_data'));
		// Add shipping icon to the shipping label
		add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_shipping_icon'), PHP_INT_MAX, 2);
		//woocommerce_checkout_update_order_meta
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_fez_locker_order_meta'), PHP_INT_MAX);
		$this->fezCreateOrderCondition();
		//filter woocommerce_' . $this->order_type . '_list_table_columns
		add_filter('woocommerce_shop_order_list_table_columns', array($this, 'fez_delivery_order_admin_list_column'), 10);
		//woocommerce_' . $this->order_type . '_list_table_custom_column
		add_action('woocommerce_shop_order_list_table_custom_column', array($this, 'fez_delivery_order_admin_list_column_content'), 10, 2);
		//bulk_actions-woocommerce_page_wc-orders
		add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_fez_delivery_order_bulk_action'), 10, 1);
		//handle bulk action
		add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handle_fez_delivery_order_bulk_action'), 10, 3);
		//admin_notices
		add_action('admin_notices', array($this, 'fez_bulk_admin_notices'));
		//order edit page actions
		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'add_order_meta_box'), PHP_INT_MAX);
		//add action to get fez delivery order details
		add_action('wp_ajax_get_fez_delivery_order_details', array($this, 'get_fez_delivery_order_details'));
		//add ajax action to get safe locker content
		add_action('wp_ajax_get_safe_locker_content', array($this, 'get_safe_locker_content'));
		//add ajax action to get safe locker content
		add_action('wp_ajax_nopriv_get_safe_locker_content', array($this, 'get_safe_locker_content'));
		//listen for fez delivery label
		$this->listen_for_fez_delivery_label();
		//add ajax action to sync fez delivery order manually
		add_action('wp_ajax_sync_fez_delivery_order_manual', array($this, 'sync_fez_delivery_order_manual'));
	}

	/**
	 * Sync fez delivery order manually
	 *
	 * @return void
	 */
	public function sync_fez_delivery_order_manual()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fez_delivery_admin_nonce')) {
				wp_send_json_error('Invalid nonce, please refresh the page and try again.');
			}

			//get order id
			$order_id = sanitize_text_field(wp_unslash($_POST['order_id']));

			//get order
			$order = wc_get_order($order_id);

			//send order to fez server
			$response = $this->send_order_to_fez_server($order);

			//check if response is successful
			if ($response['success']) {
				//return success
				wp_send_json_success([
					'success' => true,
					'message' => $response['message']
				]);
			} else {
				//return error
				wp_send_json_error([
					'success' => false,
					'message' => $response['message']
				]);
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Order Sync Error: " . $e->getMessage());
			//return error
			wp_send_json_error([
				'success' => false,
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Save fez locker order meta
	 *
	 * @param mixed $order
	 * @return void
	 */
	public function save_fez_locker_order_meta($order)
	{
		try {
			//check if order is an instance of WC_Order
			if ($order && $order instanceof WC_Order) {
				//get order id
				$order_id = $order->get_id();
			} else {
				//get order id
				$order_id = $order;
			}

			//get order
			$order = wc_get_order($order_id);
			//get fez session
			$fez_session = FezCoreSession::instance();
			//get safe locker id
			$safe_locker_id = $fez_session->get('safe_locker_id');
			//check if safe locker id is not empty
			if (!empty($safe_locker_id)) {
				//save safe locker id to order meta
				$order->update_meta_data('fez_safe_locker_id', $safe_locker_id);
				//safe another for post meta
				update_post_meta($order->get_id(), 'fez_safe_locker_id', $safe_locker_id);
				//add order message
				$order->add_order_note('Fez Safe Locker ID: ' . $safe_locker_id);
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Save Locker Order Meta Error: " . $e->getMessage());
		}
	}

	/**
	 * Get safe locker content
	 *
	 * @return void
	 */
	public function get_safe_locker_content()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce, please refresh the page and try again.');
			}

			//get fez core
			$fez_core = Fez_Core::instance();

			//get billing state
			$billing_state = sanitize_text_field($_GET['billing_state']);

			//get woocommerce states
			$woocommerce_states = WC()->countries->get_states("NG");

			//get state from state code
			$state_name = $woocommerce_states[$billing_state];

			//check if state name is abuja
			if (strpos(strtolower($state_name), 'abuja') !== false) {
				$state_name = 'FCT';
			}

			//get safe locker content
			$response = $fez_core->getSafeLockerContent($state_name);

			//check if response is successful
			if ($response['success']) {
				//send success response
				wp_send_json_success([
					'success' => true,
					'message' => $response['message'],
					'data' => $response['data']
				]);
			} else {
				//send error response
				wp_send_json_error([
					'success' => false,
					'message' => $response['message'],
					'data' => null
				]);
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Safe Locker Content Error: " . $e->getMessage());
			//return error
			wp_send_json_error([
				'success' => false,
				'message' => $e->getMessage(),
				'data' => null
			]);
		}
	}

	/**
	 * fezCreateOrderCondition
	 *
	 * @return void
	 */
	public function fezCreateOrderCondition()
	{
		try {
			//get create_fez_order_condition
			$fez_core = Fez_Core::instance();
			//get create_fez_order_condition
			$create_fez_order_condition = $fez_core->create_fez_order_condition;

			//switch on create_fez_order_condition
			switch ($create_fez_order_condition) {
				case 'processing':
					//woocommerce_checkout_order_created
					add_action('woocommerce_checkout_order_created', array($this, 'send_order_to_fez_server'), PHP_INT_MAX);
					break;
				case 'pending':
					//on order pending
					add_action('woocommerce_order_status_pending', array($this, 'send_order_to_fez_server'), PHP_INT_MAX);
					break;
				case 'completed':
					//on order completed
					add_action('woocommerce_order_status_completed', array($this, 'send_order_to_fez_server'), PHP_INT_MAX);
					break;
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Order Condition Error: " . $e->getMessage());
		}
	}

	/**
	 * fez_bulk_admin_notices
	 *
	 */
	public function fez_bulk_admin_notices()
	{
		//check if fez delivery sync order is set
		if (isset($_GET['fez_delivery_sync_order']) && !empty($_GET['processed_count'])) {

			//get processed count
			$count = intval($_REQUEST['processed_count']);

			printf('<div class="notice notice-success fade is-dismissible"><p>' .
				_n(
					'Fez Delivery synced for %s Order.',
					'Fez Delivery synced for %s Orders.',
					$count,
					'fez-delivery'
				) . '</p></div>', $count);
		}
	}

	/**
	 * Handle fez delivery order bulk action
	 *
	 * @param string $redirect_url
	 * @param string $action
	 * @param array $order_ids
	 * @return string
	 */
	public function handle_fez_delivery_order_bulk_action($redirect_url, $action, $order_ids)
	{
		//check if action is fez_delivery_sync_order
		if ($action === 'fez_delivery_sync_order') {
			$processed_ids = array(); // Initializing

			//loop through order ids
			foreach ($order_ids as $order_id) {
				//get order
				$order = wc_get_order($order_id);

				//get fez delivery order nos
				$fez_delivery_order_nos = $order->get_meta('fez_delivery_order_nos');

				//check if fez delivery order nos is not empty
				if (!empty($fez_delivery_order_nos)) {
					//skip order
					continue;
				}

				//send order to fez server
				$this->send_order_to_fez_server($order);

				//add order id to processed ids
				$processed_ids[] = $order_id;
			}

			// Adding the right query vars to the returned URL
			$redirect_url = add_query_arg(array(
				'fez_delivery_sync_order' => '1',
				'processed_count' => count($processed_ids),
				'processed_ids' => implode(',', $processed_ids),
			), $redirect_url);
		}
		return $redirect_url;
	}

	/**
	 * Add fez delivery order bulk action
	 *
	 * @param array $bulk_actions
	 * @return array
	 */
	public function add_fez_delivery_order_bulk_action($bulk_actions)
	{
		$bulk_actions['fez_delivery_sync_order'] = 'Sync with Fez';
		//sort to first position
		$bulk_actions = array_slice($bulk_actions, 0, 1, true) +
			['fez_delivery_sync_order' => $bulk_actions['fez_delivery_sync_order']] +
			array_slice($bulk_actions, 1, count($bulk_actions) - 1, true);

		return $bulk_actions;
	}

	/**
	 * Listen for fez delivery label
	 *
	 * @return void
	 */
	public function listen_for_fez_delivery_label()
	{
		//check if fez delivery label is set
		if (isset($_GET['fez_delivery_label']) && !empty($_GET['fez_delivery_label']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'download_label_' . $_GET['id'])) {
			//init fez shipping label
			$fez_shipping_label = new Fez_Shipping_Label();
			//sanitize fez delivery label
			$fez_delivery_label = sanitize_text_field($_GET['fez_delivery_label']);
			//generate shipping label
			$fez_shipping_label->generate_shipping_label($fez_delivery_label);
			//exit
			wp_die();
		}
	}

	/**
	 * Get fez delivery order details
	 *
	 * @return void
	 */
	public function get_fez_delivery_order_details()
	{
		try {
			//validate nonce
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'fez_delivery_admin_nonce')) {
				throw new \Exception('Invalid nonce');
			}

			//get order id
			$order_id = sanitize_text_field($_GET['order_id']);

			//get order nos
			$order_nos = sanitize_text_field($_GET['order_nos']);

			//get fez core
			$fez_core = Fez_Core::instance();

			//get fez delivery order details
			$response = $fez_core->getFezDeliveryOrderDetails($order_id, $order_nos);

			//check if response is successful
			if ($response['success']) {
				//send success response
				wp_send_json_success($response['data']);
			} else {
				//send error response
				wp_send_json_error($response['message']);
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Order Details Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Add order meta box
	 *
	 * @param mixed $order
	 * @return void
	 */
	public function add_order_meta_box($order)
	{
		//get fez delivery order nos
		$fez_delivery_order_nos = $order->get_meta('fez_delivery_order_nos');
		//check if fez delivery order nos is not empty
		if (!empty($fez_delivery_order_nos)) {
			//get fez core
			$fez_core = Fez_Core::instance();
			//get fez mode
			$fez_mode = $fez_core->fez_mode;
			//check if fez mode is sandbox
			if ($fez_mode == 'sandbox') {
				$fez_delivery_url = FEZ_DELIVERY_SANDBOX_TRACKING_URL;
			} else {
				$fez_delivery_url = FEZ_DELIVERY_PRODUCTION_TRACKING_URL;
			}



			// Debugging output for URL and nonce
			$constructed_url = add_query_arg(['fez_delivery_label' => $fez_delivery_order_nos, 'nonce' => wp_create_nonce('download_label_' . $order->get_id())], admin_url('admin.php?page=wc-orders&id=' . $order->get_id()));
?>


			<div class="fez-delivery-order-details">
				<div class="fez-delivery-order-details-header">
					<h3>
						<img src="<?php echo esc_url(FEZ_DELIVERY_ASSETS_URL); ?>img/fez_logo.svg" alt="Fez" class="fez-delivery-logo"> <span> Delivery Details</span>
					</h3>
					<a href="javascript:void(0)" onclick="window.location.reload()" class="fez-delivery-order-refresh-button">Refresh</a>
				</div>

				<p>Order No: <?php echo esc_html($fez_delivery_order_nos); ?></p>

				<p>Status: <span class="fez-delivery-order-status-wc-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-order-nos="<?php echo esc_attr($fez_delivery_order_nos); ?>">Getting details...</span></p>

				<p>Cost: <span class="fez-delivery-order-cost-wc-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-order-nos="<?php echo esc_attr($fez_delivery_order_nos); ?>">--</span></p>

				<div class="fez-delivery-order-buttons">
					<a href="<?php echo esc_url($fez_delivery_url . $fez_delivery_order_nos); ?>" class="fez-delivery-order-details-button" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-order-nos="<?php echo esc_attr($fez_delivery_order_nos); ?>" target="_blank">Track Delivery</a>
					<a href="<?php echo esc_url($constructed_url); ?>" class="fez-delivery-order-label-button" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-order-nos="<?php echo esc_attr($fez_delivery_order_nos); ?>">Download Label</a>
				</div>
			</div>
		<?php
		} else {
		?>
			<div class="fez-delivery-order-sync-button-container" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
				<img src="<?php echo esc_url(FEZ_DELIVERY_ASSETS_URL); ?>img/fez_logo.svg" alt="Fez" class="fez-delivery-logo">
				<a href="javascript:void(0)" class="fez-delivery-order-sync-button">Sync with Fez</a>
			</div>
		<?php
		}
	}

	/**
	 * Fez delivery order admin list column
	 *
	 * @param array $columns
	 * @return array
	 */
	public function fez_delivery_order_admin_list_column($columns)
	{
		//add new column
		$columns['fez_delivery_order_nos'] = 'Fez Delivery';
		//shipping label download
		$columns['fez_delivery_shipping_label'] = 'Fez Shipping Label';
		//move to second position
		$columns = array_slice($columns, 0, 2, true) +
			['fez_delivery_order_nos' => $columns['fez_delivery_order_nos']] +
			['fez_delivery_shipping_label' => $columns['fez_delivery_shipping_label']] +
			array_slice($columns, 2, count($columns) - 2, true);
		//return columns
		return $columns;
	}

	/**
	 * Fez delivery order admin list column content
	 *
	 * @param string $column
	 * @param mixed $order_id
	 * @return void
	 */
	public function fez_delivery_order_admin_list_column_content($column, $order_id)
	{
		//check if column is fez_delivery_order_nos
		switch ($column) {
			case 'fez_delivery_order_nos':
				//get order
				$order = wc_get_order($order_id);
				//get fez delivery order no
				$fez_delivery_order_nos = $order->get_meta('fez_delivery_order_nos');
				//check if fez delivery order nos is not empty
				if (!empty($fez_delivery_order_nos)) {
					//echo fez delivery order nos
					echo "<span class='fez-delivery-order-nos active'>✅ Synced</span>";
				} else {
					echo "<span class='fez-delivery-order-nos inactive'>❌ Not synced</span>";
				}
				break;
			case 'fez_delivery_shipping_label':
				//get order
				$order = wc_get_order($order_id);
				//get fez delivery order no
				$fez_delivery_order_nos = $order->get_meta('fez_delivery_order_nos');
				//check if fez delivery order nos is not empty
				if (!empty($fez_delivery_order_nos)) {
					// Debugging output for URL and nonce
					$constructed_url = add_query_arg(['fez_delivery_label' => $fez_delivery_order_nos, 'nonce' => wp_create_nonce('download_label_' . $order->get_id())], admin_url('admin.php?page=wc-orders&id=' . $order->get_id()));

					// Use the constructed URL
					echo "<a href='" . esc_url($constructed_url) . "' class='fez-delivery-order-label-button'>Download Label</a>";
				} else {
					echo "<span class='fez-delivery-order-label-button inactive'>N/A</span>";
				}
				break;
		}
	}

	/**
	 * Save fez delivery order meta
	 *
	 * @param mixed $order
	 * @return void
	 */
	public function save_fez_delivery_order_meta($order)
	{
		try {
			//check if order is an instance of WC_Order
			if ($order && $order instanceof WC_Order) {
				//get order id
				$order_id = $order->get_id();
			} else {
				//get order id
				$order_id = $order;
			}

			//get order
			$order = wc_get_order($order_id);

			//check if fez delivery order nos is not empty
			$fez_delivery_order_nos = $order->get_meta('fez_delivery_order_nos');
			if (!empty($fez_delivery_order_nos)) {
				//return
				return false;
			}

			//get fez session
			$fezsession = FezCoreSession::instance();

			//check if delivery state label is set
			if (!$fezsession->get('delivery_state_label')) {
				throw new \Exception('Delivery state label is not set');
			}

			//get pickup state
			$pickup_state = $fezsession->get('pickup_state_label');

			//check if pickup state is not set
			if (empty($pickup_state)) {
				throw new \Exception('Pickup state label is not set');
			}

			//get total weight
			$total_weight = $fezsession->get('total_weight');

			//get delivery state
			$delivery_state = $fezsession->get('delivery_state_label');

			//get billing address
			$billing_address = $order->get_address();

			//customer name
			$customer_name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();

			//customer phone
			$customer_phone = $order->get_billing_phone();

			//$order_items
			$order_items = $order->get_items();

			$data_items_message = "";

			//loop through cart items
			foreach ($order_items as $product_id => $item) {
				//append to data items message
				$data_items_message .= "{$item->get_quantity()} of {$item->get_name()} at {$item->get_total()}, ";
				//add new line
				$data_items_message .= "\n";
			}

			//check if delivery state is matched abuja
			if (strpos(strtolower($delivery_state), 'abuja') !== false) {
				$delivery_state = 'FCT';
			}

			//check if pickup state is matched abuja
			if (strpos(strtolower($pickup_state), 'abuja') !== false) {
				$pickup_state = 'FCT';
			}

			$dataRequest = [
				[
					"recipientAddress" => $billing_address['address_1'],
					"recipientState" => $delivery_state,
					"recipientName" => $customer_name,
					"recipientPhone" => $customer_phone,
					"uniqueID" => "woocommerce_" . $order_id,
					"BatchID" => "woocommerce_batch_" . $order_id,
					"valueOfItem" => $order->get_total(),
					"weight" => $total_weight,
					"pickUpState" => $pickup_state,
					"itemDescription" => "Order #" . $order_id . " with items: " . $data_items_message,
					"orderRequestSource" => "Wordpress Plugin"
				]
			];

			//get fez core
			$fez_core = Fez_Core::instance();

			//get delivery cost
			$response = $fez_core->createOrder($dataRequest);

			//check if response is successful
			if ($response['success']) {
				//update order meta
				$order->update_meta_data('fez_delivery_order_nos', $response['data']->{'woocommerce_' . $order_id});
				//add order note
				$order->add_order_note('Fez Delivery Order Initiated: ' . $response['data']->{'woocommerce_' . $order_id});
				//add message note
				$order->add_order_note('Fez Delivery Order Note: ' . $response['message']);
				//add meta
				update_post_meta($order_id, 'fez_delivery_order_nos', $response['data']->{'woocommerce_' . $order_id});
				//save order
				$order->save();
			} else {
				error_log("Fez Delivery Order Error: " . $response['message']);
				//add wc order note
				$order->add_order_note('Fez Delivery Order Error: ' . $response['message']);
				//save order
				$order->save();
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Order Meta Error: " . $e->getMessage());
		}
	}

	/**
	 * Send order to fez server for already created orders
	 *
	 * @param mixed $order
	 * @return void
	 */
	public function send_order_to_fez_server($order)
	{
		//check if order is an instance of WC_Order
		if ($order && $order instanceof WC_Order) {
			//get order id
			$order_id = $order->get_id();
		} else {
			//get order
			$order = wc_get_order($order);
			//get order id
			$order_id = $order->get_id();
		}

		try {

			//get customer note
			$customer_note = $order->get_customer_note();

			//check if order is already synced
			if (!empty($order->get_meta('fez_delivery_order_nos'))) {
				//return
				return false;
			}

			//get customer billing state
			$customer_billing_state = $order->get_billing_state();
			//init fez core
			$fez_core = Fez_Core::instance();

			//get pickup state
			$pickup_state = $fez_core->pickup_state;

			//order_id
			$order_id = $order->get_id();

			$data_items_message = "";

			$local_payment_method = ['bacs', 'cod'];

			//get total weight
			$total_weight = 0;
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();

				$total_weight += (float)get_post_meta($product_id, '_weight', true) ?: 0;

				//append to data items message
				$data_items_message .= "{$item->get_quantity()} of {$item->get_name()} at {$item->get_total()}, ";
				//add new line
				$data_items_message .= "\n";
			}

			//get billing address
			$billing_address = $order->get_address();

			//customer name
			$customer_name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();

			//customer phone
			$customer_phone = $order->get_billing_phone();

			//igonre if order country is not NG
			if ($order->get_billing_country() !== 'NG') {

				//get order country
				$order_country = $order->get_billing_country();

				//get customers email
				$customer_email = $order->get_billing_email();

				//get weight for export locations
				$export_result = $this->get_export_locations_and_exports_weights($order_country);

				//check if export result is not empty
				if (empty($export_result)) {
					throw new \Exception('Export locations and exports weights is not successful');
				}

				//get weight for export locations
				$weight_result = $this->extract_weight_id($total_weight, $export_result->int_price);

				//get Export Delivery Cost
				$response = $fez_core->getExportDeliveryCost($weight_result['weight_id'], $weight_result['location_id']);

				//check if response is successful
				if (!$response['success']) {
					throw new \Exception('Export delivery cost is not successful: ' . $response['message']);
				}

				//get shipping amount
				$shipping_amount = $response['data']->data->price;

				$dataRequest = [
					[
						"recipientAddress" => $billing_address['address_1'],
						"recipientName" => $customer_name,
						"recipientPhone" => $customer_phone,
						"recipientEmail" => $customer_email,
						"uniqueID" => "woocommerce_" . $order_id,
						"BatchID" => "woocommerce_batch_" . $order_id,
						"valueOfItem" => $order->get_total(),
						"exportLocationId" => $weight_result['location_id'],
						"weight" => $weight_result['weight_size'],
						"itemDescription" => "Order #" . $order_id . " with items: " . $data_items_message,
						"orderRequestSource" => "WooCommerce Plugin",
						"additionalDetails" => $customer_note
					]
				];

				//check if payment method is bank transfer, cash on delivery
				if (in_array($order->get_payment_method(), $local_payment_method)) {
					//add isItemCod and cashOnDeliveryAmount
					$dataRequest[0]['isItemCod'] = true;
					$dataRequest[0]['cashOnDeliveryAmount'] = $order->get_total();
				}

				//get delivery cost
				$response = $fez_core->createOrder($dataRequest, true);

				//add note: order type
				$order->add_order_note('Fez Delivery Order Type: Export');
			} else {
				//do normal delivery cost

				//get woocommerce states
				$woocommerce_states = WC()->countries->get_states("NG");

				//get customers email
				$customer_email = $order->get_billing_email();

				//get state from state code
				$delivery_state_label = $woocommerce_states[$customer_billing_state];

				//get state from state code
				$pickup_state_label = $woocommerce_states[$pickup_state];

				//get fez safe locker id from order
				$fez_safe_locker_id = get_post_meta($order_id, 'fez_safe_locker_id', true) ?: "none";

				//get the shiiping amount from fez
				$shipping_amount = $fez_core->getDeliveryCost($delivery_state_label, $pickup_state_label, $total_weight, $fez_safe_locker_id);

				//check if shipping amount is successful
				if (!$shipping_amount['success']) {
					throw new \Exception('Failed to get delivery cost: ' . $shipping_amount['message']);
				}

				//check if delivery state is matched abuja
				if (strpos(strtolower($delivery_state_label), 'abuja') !== false) {
					$delivery_state_label = 'FCT';
				}

				//check if pickup state is matched abuja
				if (strpos(strtolower($pickup_state_label), 'abuja') !== false) {
					$pickup_state_label = 'FCT';
				}

				//get shipping amount
				$shipping_amount = $shipping_amount['cost']->cost;

				$dataRequest = [
					[
						"recipientAddress" => $billing_address['address_1'],
						"recipientState" => $delivery_state_label,
						"recipientName" => $customer_name,
						"recipientPhone" => $customer_phone,
						"recipientEmail" => $customer_email,
						"uniqueID" => "woocommerce_" . $order_id,
						"BatchID" => "woocommerce_batch_" . $order_id,
						"valueOfItem" => $order->get_total(),
						"weight" => $total_weight,
						"pickUpState" => $pickup_state_label,
						"itemDescription" => "Order #" . $order_id . " with items: " . $data_items_message,
						"orderRequestSource" => "WooCommerce Plugin",
						"additionalDetails" => $customer_note
					]
				];

				//check if fez safe locker id is not empty
				if (!empty($fez_safe_locker_id) && $fez_safe_locker_id != "none") {
					//add safe locker id to data request
					$dataRequest[0]['lockerID'] = $fez_safe_locker_id;
				}

				//check if payment method is bank transfer, cash on delivery
				if (in_array($order->get_payment_method(), $local_payment_method)) {
					//add isItemCod and cashOnDeliveryAmount
					$dataRequest[0]['isItemCod'] = true;
					$dataRequest[0]['cashOnDeliveryAmount'] = $order->get_total();
				}

				//get delivery cost
				$response = $fez_core->createOrder($dataRequest);

				//add note: order type
				$order->add_order_note('Fez Delivery Order Type: Local');
			}

			//check if response is successful
			if ($response['success']) {
				//update order meta
				$order->update_meta_data('fez_delivery_order_nos', $response['data']->{'woocommerce_' . $order_id});
				//add order note
				$order->add_order_note('Fez Delivery Order Initiated: ' . $response['data']->{'woocommerce_' . $order_id});
				//add message note
				$order->add_order_note('Fez Delivery Order Note: ' . $response['message']);

				//add note to order
				$order->add_order_note('Fez Delivery synced via Admin Panel');


				$items = (array) $order->get_items('shipping');

				if (!empty($items)) {
					// // Loop through shipping items
					foreach ($items as $item) {
						//get shipping method id
						$shipping_method_id = $item->get_method_id();
						//if shipping method id is fez_delivery
						if ($shipping_method_id == "fez_delivery") {
							$item->set_method_title(apply_filters('fez_delivery_shipping_method_label', "Fez Delivery"));
							$item->set_total($shipping_amount);
							$item->save();
						}
					}
				} else {
					// Get a new instance of the WC_Order_Item_Shipping Object
					$item = new WC_Order_Item_Shipping();
					//title
					$item->set_method_title(apply_filters('fez_delivery_shipping_method_label', "Fez Delivery"));
					//method id
					$item->set_method_id('fez_delivery');
					//total
					$item->set_total($shipping_amount);
					//add item to order
					$order->add_item($item);
				}

				//recalculate order totals
				$order->calculate_totals();

				//save order
				$order->save();

				return [
					'success' => true,
					'message' => $response['message']
				];
			} else {
				//add wc order note
				$order->add_order_note('Fez Delivery Order Error: ' . $response['message']);
				//add note to order
				$order->add_order_note('Fez Delivery sync failed via Admin Panel');
				//save order
				$order->save();

				return [
					'success' => false,
					'message' => $response['message']
				];
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Order Send Error: " . $e->getMessage());
			//add wc order note
			$order->add_order_note('Fez Delivery Order Send Error: ' . $e->getMessage());
			//add note to order
			$order->add_order_note('Fez Delivery sync failed via Admin Panel');
			//save order
			$order->save();

			return [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
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
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce');
			}
			//unset delivery cost
			$fezsession = FezCoreSession::instance();
			$fezsession->unset('delivery_cost');
			$fezsession->unset('delivery_state_label');
			$fezsession->unset('pickup_state_label');
			$fezsession->unset('total_weight');
			//unset mode
			$fezsession->unset('mode');

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
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce');
			}

			//get delivery cost
			$delivery_cost = sanitize_text_field($_POST['delivery_cost']);

			//check if delivery cost is not empty
			// if (empty($delivery_cost)) {
			// 	throw new \Exception('Delivery cost is empty, please try again');
			// }

			//get delivery state label
			$delivery_state_label = sanitize_text_field($_POST['delivery_state_label']);

			//get pickup state label
			$pickup_state_label = sanitize_text_field($_POST['pickup_state_label']);

			//get total weight
			$total_weight = sanitize_text_field($_POST['total_weight']);

			//get country mode
			$country_mode = sanitize_text_field($_POST['country_mode']);

			//get location id
			$location_id = $country_mode ? sanitize_text_field($_POST['location_id']) : "";
			//get weight id
			$weight_id = $country_mode ? sanitize_text_field($_POST['weight_id']) : "";

			//set session
			$fezsession = FezCoreSession::instance();

			//clear previous data
			$fezsession->unset('delivery_cost');
			$fezsession->unset('delivery_state_label');
			$fezsession->unset('pickup_state_label');
			$fezsession->unset('total_weight');
			$fezsession->unset('location_id');
			$fezsession->unset('weight_id');
			$fezsession->unset('country_mode');

			//set new data
			$fezsession->set('delivery_cost', $delivery_cost);
			$fezsession->set('delivery_state_label', $delivery_state_label);
			//set mode
			$fezsession->set('mode', empty($delivery_cost) ? 'safe_locker' : 'local');
			$fezsession->set('pickup_state_label', $pickup_state_label);
			$fezsession->set('total_weight', $total_weight);
			$fezsession->set('location_id', $location_id);
			$fezsession->set('weight_id', $weight_id);
			$fezsession->set('country_mode', $country_mode);

			//return success
			wp_send_json_success(array('message' => 'Delivery cost applied successfully', 'mode' => empty($delivery_cost) ? 'safe_locker' : 'local'));
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Cost Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Extract weight id
	 *
	 * @param float $total_weight
	 * @param array $result
	 * @return mixed
	 */
	public function extract_weight_id($total_weight, $result)
	{
		usort($result, function ($a, $b) {
			return floatval($a->weight->name) <=> floatval($b->weight->name);
		});

		$default_weight = [
			'weight_id' => $result[0]->weights_id,
			'weight_size' => $result[0]->weight->name,
			'location_id' => $result[0]->intlocations_id,
		];

		//check if total weight is zero
		if (empty($total_weight)) {
			//return first weight id
			return $default_weight;
		}

		//get array of weight names
		$weight_names = array_map(
			function ($item) {
				return floatval($item->weight->name);
			},
			$result
		);

		//get weight index
		$weight_index = array_search($total_weight, $weight_names);

		//check if weight index is empty
		if (empty($weight_index)) {
			//approximate weight
			$total_weight = ceil($total_weight);
			//get weight index
			$weight_index = array_search($total_weight, $weight_names);
		}

		//get weight id
		$weight_result = $weight_index !== false ? [
			'weight_id' => $result[$weight_index]->weights_id,
			'weight_size' => $result[$weight_index]->weight->name,
			'location_id' => $result[$weight_index]->intlocations_id,
		] : $default_weight;

		//return weight id
		return $weight_result;
	}

	/**
	 * Get fez delivery cost
	 *
	 * @return void
	 */
	public function get_fez_delivery_cost()
	{
		try {
			//init fez core
			$fez_core = Fez_Core::instance();

			//validate nonce
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fez_delivery_frontend_nonce')) {
				throw new \Exception('Invalid nonce');
			}

			//get country
			$country = sanitize_text_field($_POST['country']);

			//get safe locker id
			$safe_locker_id = sanitize_text_field($_POST['safe_locker_id']);

			//session
			$fez_session = FezCoreSession::instance();

			//check if safe locker id is not none
			if ($safe_locker_id != "none") {
				//save safe locker id to session
				$fez_session->set('safe_locker_id', $safe_locker_id);
			} else {
				//unset safe locker id
				$fez_session->unset('safe_locker_id');
			}

			//get total weight
			$cart_items = WC()->cart->get_cart();

			//check if cart items is not empty
			if (empty($cart_items)) {
				throw new \Exception('Cart items are empty');
			}

			//get total weight
			$total_weight = 0;
			foreach ($cart_items as $item) {
				$total_weight += !empty($item['data']->get_weight()) ? (float)$item['data']->get_weight() : 0;
			}

			//check if country is not NG
			if ($country != 'NG') {
				//get weight for export locations
				$result = $this->get_export_locations_and_exports_weights($country);

				//get weight for export locations
				$weight_result = $this->extract_weight_id($total_weight, $result->int_price);

				//get Export Delivery Cost
				$response = $fez_core->getExportDeliveryCost($weight_result['weight_id'], $weight_result['location_id']);

				//check if response is successful
				if ($response['success']) {
					//return success
					wp_send_json_success(array(
						'message' => $response['message'],
						'cost' => (object)[
							"cost" => $response['data']->data->price
						],
						'total_weight' => $total_weight,
						'pickup_state_label' => "Nigeria",
						'delivery_state_label' => $result->name,
						'location_id' => $weight_result['location_id'],
						'weight_id' => $weight_result['weight_id'],
						'country_mode' => true
					));
				} else {
					//return error
					wp_send_json_error(array(
						'message' => $response['message'],
						'cost' => 0,
						'country_mode' => true
					));
				}
			}

			//get delivery state
			$delivery_state = sanitize_text_field($_POST['deliveryState']);

			//check if not empty
			if (empty($delivery_state)) {
				throw new \Exception('Delivery state is empty');
			}

			//get pickup state
			$pickup_state = $fez_core->pickup_state;

			//get woocommerce states
			$woocommerce_states = WC()->countries->get_states("NG");

			//get state from state code
			$delivery_state_label = $woocommerce_states[$delivery_state];

			//get state from state code
			$pickup_state_label = $woocommerce_states[$pickup_state];

			//get delivery cost
			$response = $fez_core->getDeliveryCost($delivery_state_label, $pickup_state_label, $total_weight, $safe_locker_id);

			//check if response is successful
			if ($response['success']) {
				//return success
				wp_send_json_success(array(
					'message' => $response['message'],
					'cost' => $response['cost'],
					'delivery_state_label' => $delivery_state_label,
					'pickup_state_label' => $pickup_state_label,
					'total_weight' => $total_weight,
					'country_mode' => false
				));
			} else {
				//return error
				wp_send_json_error(array(
					'message' => $response['message'],
					'cost' => 0,
					'country_mode' => false
				));
			}
		} catch (\Exception $e) {
			//log
			error_log("Fez Delivery Cost Error: " . $e->getMessage());
			//return error
			wp_send_json_error(array(
				'message' => 'Unable to get fez delivery cost: ' . $e->getMessage(),
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
		//get fez core
		$fez_core = Fez_Core::instance();

		//get enable fez safe locker
		$enable_fez_safe_locker = $fez_core->enable_fez_safe_locker;

		//enqueue frontend script
		wp_enqueue_script('fez-delivery-frontend-script', FEZ_DELIVERY_ASSETS_URL . 'js/fezdeliveryhome.min.js', array('jquery'), FEZ_DELIVERY_VERSION, true);
		//style
		wp_enqueue_style('fez-delivery-frontend-style', FEZ_DELIVERY_ASSETS_URL . 'css/fezdeliveryhome.min.css', array(), FEZ_DELIVERY_VERSION);
		//localize script
		wp_localize_script('fez-delivery-frontend-script', 'fez_delivery_frontend', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fez_delivery_frontend_nonce'),
			'export_locations_and_exports_weights' => is_checkout() || is_cart() ? $this->get_export_locations_and_exports_weights() : null,
			'enable_fez_safe_locker' => $enable_fez_safe_locker
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
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fez_delivery_admin_nonce')) {
				throw new \Exception('Invalid nonce');
			}
			//get woocommerce_fez_delivery_fez_username
			$fez_username = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_username']);
			//get woocommerce_fez_delivery_fez_password
			$fez_password = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_password']);
			//get woocommerce_fez_delivery_fez_mode
			$fez_mode = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_mode']);
			//get woocommerce_fez_delivery_enable_fez_safe_locker
			$enable_fez_safe_locker = absint($_POST['woocommerce_fez_delivery_enable_fez_safe_locker']);
			//get woocommerce_fez_delivery_enabled
			$enabled = absint($_POST['woocommerce_fez_delivery_enabled']);
			//get woocommerce_fez_delivery_fez_pickup_state
			$fez_pickup_state = sanitize_text_field($_POST['woocommerce_fez_delivery_fez_pickup_state']);
			//get woocommerce_fez_delivery_create_fez_order_condition
			$create_fez_order_condition = sanitize_text_field($_POST['woocommerce_fez_delivery_create_fez_order_condition']);

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
				$old_options = get_option('woocommerce_fez_delivery_settings', []);

				//save to options to woocommerce
				$woo_args = [
					'fez_mode' => $fez_mode,
					'fez_username' => $fez_username,
					'fez_password' => $fez_password,
					'enabled' => $enabled ? 'yes' : 'no',
					'fez_pickup_state' => $fez_pickup_state,
					'create_fez_order_condition' => $create_fez_order_condition,
					'enable_fez_safe_locker' => $enable_fez_safe_locker ? 'yes' : 'no'
				];

				//check if old options is not empty
				if (!empty($old_options)) {
					//update woocommerce options
					update_option('woocommerce_fez_delivery_settings', array_merge($old_options, $woo_args));
				} else {
					//update woocommerce options
					update_option('woocommerce_fez_delivery_settings', $woo_args);
				}

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
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fez_delivery_admin_nonce')) {
				throw new \Exception('Invalid nonce');
			}
			//delete auth token
			delete_transient('fez_delivery_auth_token_static');
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


	/**
	 * Check if current page is woocommerce checkout page
	 * @param string $country
	 *
	 * @return void|mixed
	 */
	public function get_export_locations_and_exports_weights($country = 'CA')
	{
		//fez session
		$fezsession = FezCoreSession::instance();

		//check if session exists
		if ($result = $fezsession->get('export_locations_and_exports_weights')) {
			//loop
			foreach ($result as $key => $value) {
				//check if country_code matches
				if ($value->country_code == $country) {
					//return data
					return $value;
				}
			}
			//return empty array
			return [];
		}

		//get export locations and exports weights
		$response = Fez_Core::instance()->getExportLocationsAndExportsWeights();

		//check if response is successful
		if ($response['success']) {
			//get data
			$data = $response['data']->data->exportLocations;

			//set export locations and exports weights
			$fezsession->set('export_locations_and_exports_weights', $data);

			//loop
			foreach ($data as $key => $value) {
				//check if country_code matches
				if ($value->country_code == $country) {
					//return data
					return $value;
				}
			}

			//return data
			return [];
		}
	}
}
