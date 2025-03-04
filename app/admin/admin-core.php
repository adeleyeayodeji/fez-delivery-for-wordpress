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
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_fez_delivery_order_meta'), PHP_INT_MAX);
		//woocommerce_checkout_order_created
		add_action('woocommerce_checkout_order_created', array($this, 'save_fez_delivery_order_meta'), PHP_INT_MAX);
		//filter woocommerce_' . $this->order_type . '_list_table_columns
		add_filter('woocommerce_shop_order_list_table_columns', array($this, 'fez_delivery_order_admin_list_column'), 10);
		//woocommerce_' . $this->order_type . '_list_table_custom_column
		add_action('woocommerce_shop_order_list_table_custom_column', array($this, 'fez_delivery_order_admin_list_column_content'), 10, 2);
		//order edit page actions
		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'add_order_meta_box'), PHP_INT_MAX);
		//add action to get fez delivery order details
		add_action('wp_ajax_get_fez_delivery_order_details', array($this, 'get_fez_delivery_order_details'));
		//listen for fez delivery label
		$this->listen_for_fez_delivery_label();
	}

	/**
	 * Listen for fez delivery label
	 *
	 * @return void
	 */
	public function listen_for_fez_delivery_label()
	{
		//check if fez delivery label is set
		if (isset($_GET['fez_delivery_label']) && !empty($_GET['fez_delivery_label'])) {
			//init fez shipping label
			$fez_shipping_label = new Fez_Shipping_Label();
			//generate shipping label
			$fez_shipping_label->generate_shipping_label($_GET['fez_delivery_label']);
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
			if (!wp_verify_nonce($_GET['nonce'], 'fez_delivery_admin_nonce')) {
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
?>


			<div class="fez-delivery-order-details">
				<h3>
					<img src="<?php echo FEZ_DELIVERY_ASSETS_URL; ?>img/fez_logo.svg" alt="Fez" class="fez-delivery-logo"> <span> Delivery Details</span>
				</h3>

				<p>Order No: <?php echo $fez_delivery_order_nos; ?></p>

				<p>Status: <span class="fez-delivery-order-status-wc-order" data-order-id="<?php echo $order->get_id(); ?>" data-order-nos="<?php echo $fez_delivery_order_nos; ?>">Getting details...</span></p>

				<p>Cost: <span class="fez-delivery-order-cost-wc-order" data-order-id="<?php echo $order->get_id(); ?>" data-order-nos="<?php echo $fez_delivery_order_nos; ?>">--</span></p>

				<div class="fez-delivery-order-buttons">
					<a href="#" class="fez-delivery-order-details-button" data-order-id="<?php echo $order->get_id(); ?>" data-order-nos="<?php echo $fez_delivery_order_nos; ?>">Manage on Fez</a>
					<a href="<?php echo add_query_arg('fez_delivery_label', $fez_delivery_order_nos, admin_url('admin.php?page=wc-orders&id=' . $order->get_id())); ?>" class="fez-delivery-order-label-button" data-order-id="<?php echo $order->get_id(); ?>" data-order-nos="<?php echo $fez_delivery_order_nos; ?>">Download Label</a>
				</div>
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
					//echo fez delivery order nos
					echo "<a href='" . add_query_arg('fez_delivery_label', $fez_delivery_order_nos, admin_url('admin.php?page=wc-orders&id=' . $order_id)) . "' class='fez-delivery-order-label-button'>Download Label</a>";
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

			//get fez session
			$fezsession = FezCoreSession::instance();

			//check if delivery state label is set
			if (!$fezsession->get('delivery_state_label')) {
				throw new \Exception('Delivery state label is not set');
			}

			//get pickup state
			$pickup_state = $fezsession->get('pickup_state_label');

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
					"pickUpState" => $pickup_state
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
			$fezsession->unset('delivery_state_label');
			$fezsession->unset('pickup_state_label');
			$fezsession->unset('total_weight');

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

			//get delivery state label
			$delivery_state_label = sanitize_text_field($_POST['delivery_state_label']);

			//get pickup state label
			$pickup_state_label = sanitize_text_field($_POST['pickup_state_label']);

			//get total weight
			$total_weight = sanitize_text_field($_POST['total_weight']);

			//set session
			$fezsession = FezCoreSession::instance();

			//clear previous data
			$fezsession->unset('delivery_cost');
			$fezsession->unset('delivery_state_label');
			$fezsession->unset('pickup_state_label');
			$fezsession->unset('total_weight');

			//set new data
			$fezsession->set('delivery_cost', $delivery_cost);
			$fezsession->set('delivery_state_label', $delivery_state_label);
			$fezsession->set('pickup_state_label', $pickup_state_label);
			$fezsession->set('total_weight', $total_weight);

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
					'cost' => $response['cost'],
					'delivery_state_label' => $delivery_state_label,
					'pickup_state_label' => $pickup_state_label,
					'total_weight' => $total_weight
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
				'message' => 'Error getting delivery cost: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile(),
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
}
