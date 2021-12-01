<?php
/**
 * Dintero Checkout AJAX Event Handlers.
 *
 * @class   WC_AJAX_HP
 * @package Dintero/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Ajax class.
 */
class WC_AJAX_HP {

	/**
	 * @var null
	 */
	static $_adapter = null;

	public $separate_sales_tax = false;
	public static $isOnGoingPushOperation = false;

	/**
	 * @return Dintero_HP_Adapter|null
	 */
	protected static function _adapter()
	{
		if (!self::$_adapter) {
			self::$_adapter = new Dintero_HP_Adapter();
		}
		return self::$_adapter;
	}

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'define_ajax' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'do_wc_ajax' ), 0 );
		self::add_ajax_events();
	}

	/**
	 * Get WC Ajax Endpoint.
	 *
	 * @param string $request Optional.
	 *
	 * @return string
	 */
	public static function get_endpoint( $request = '' ) {
		return esc_url_raw( apply_filters( 'woocommerce_ajax_get_endpoint', add_query_arg( 'dhp-ajax', $request, remove_query_arg( array( 'remove_item', 'add-to-cart', 'added-to-cart', 'order_again', '_wpnonce' ), home_url( '/', 'relative' ) ) ), $request ) );
	}

	/**
	 * Set WC AJAX constant and headers.
	 */
	public static function define_ajax() {
		// phpcs:disable
		if ( ! empty( $_GET['dhp-ajax'] ) ) {
			wc_maybe_define_constant( 'DOING_AJAX', true );
			wc_maybe_define_constant( 'WC_DOING_AJAX', true );
			//if ( ! WP_DEBUG || ( WP_DEBUG && ! WP_DEBUG_DISPLAY ) ) {
				//@ini_set( 'display_errors', 0 ); // Turn off display_errors during AJAX events to prevent malformed JSON.
			//}
			$GLOBALS['wpdb']->hide_errors();
		}
		// phpcs:enable
	}

	/**
	 * Send headers for WC Ajax Requests.
	 *
	 * @since 2.5.0
	 */
	private static function wc_ajax_headers() {
		if ( ! headers_sent() ) {
			send_origin_headers();
			send_nosniff_header();
			wc_nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			header( 'X-Robots-Tag: noindex' );
			status_header( 200 );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			headers_sent( $file, $line );
			trigger_error( 'wc_ajax_headers cannot set headers - headers already sent by ' . esc_attr( $file ) . ' on line ' . esc_attr( $line ), E_USER_NOTICE ); // @codingStandardsIgnoreLine
		}
	}

	/**
	 * Check for WC Ajax request and fire action.
	 */
	public static function do_wc_ajax() {
		global $wp_query;

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		if ( ! empty( $_GET['dhp-ajax'] ) ) {
			$wp_query->set( 'dhp-ajax', sanitize_text_field( wp_unslash( $_GET['dhp-ajax'] ) ) );
		}

		$action = $wp_query->get( 'dhp-ajax' );

		if ( $action ) {
			self::wc_ajax_headers();
			$action = sanitize_text_field( $action );
			do_action( 'dhp_ajax_' . $action );
			wp_die();
		}
		// phpcs:enable
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events_nopriv = array(
			'test',
			'embed_checkout',
			'express_checkout',
			'embed_pay',
			'express_pay',
			'dhp_update_ord',
			'dhp_update_ord_emded',
			'dhp_update_ship', // For backwards compatibility
			'dhp_shipping_options',
			'create_order',
			'dhp_create_order',
			'check_order_status',
			'update_session',
			'destroy_session',
			'update_shipping_postcode',
			'check_transaction',
			'test',
			'update_shipping_line_id',
		);

		foreach ( $ajax_events_nopriv as $ajax_event ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			// WC AJAX can be used for frontend ajax requests.
			add_action( 'dhp_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	/**
	 * Testing function
	 */
	public static function test() {
		$orders = wc_get_orders( array(
						    'transaction_id' => 'T12000001.4XrEWRnfPBEsp34LEmBzdP'
						)
		 			);
		echo '<pre>';
		print_r($orders);
		exit;
	}

	public static function embed_checkout() {
		//check_ajax_referer( 'embed-checkout', 'security' );

		$test = WCDHP()->checkout()->process_checkout();
	}

	public static function express_checkout() {
		//check_ajax_referer( 'express-checkout', 'security' );

		$test = WCDHP()->checkout()->process_checkout(true);
	}

	public static function embed_pay() {
		//check_ajax_referer( 'embed-checkout', 'security' );

		$test = WCDHP()->checkout()->pay_action();
	}

	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

	public function check_transaction(){
		$transaction_id = sanitize_text_field($_POST['transaction_id']);

		$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );

		$transaction_order_id = trim($transaction['merchant_reference']);
		if($transaction_order_id == '' && isset($transaction['merchant_reference_2'])){
			$transaction_order_id = trim($transaction['merchant_reference_2']);
		}


		$order                = wc_get_order( $transaction_order_id );

		if(!$order){
			wp_send_json_success(
				array(
					'msg'  => 'Order Doesnot exist'

				)
			);
		}else{
			$url = apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order );
			$redirectUrl = $url.'&transaction_id='.$transaction_id;
			wp_send_json_error(
				array(
					'redirect_url'  => $redirectUrl

				)
			);

		}

	}

	public function destroy_session(){
		//WC()->session->reload_checkout = true;
		WC()->session->__unset('dintero_wc_order_id');
		wp_send_json_success(
			array(
				'success'  => true

			)
		);
	}

	public function update_shipping_postcode(){
		$formData = sanitize_text_field($_POST['post_code']);
		$posted_data['postcode'] = sanitize_text_field($_POST['post_code']);
		$customer_data = array();
		$customer_data['billing_postcode']  = sanitize_text_field($_POST['post_code']);
		$customer_data['shipping_postcode'] = sanitize_text_field($_POST['post_code']);
		$country = WC()->countries->get_base_country();
		$customer_data['billing_country']  = $country;
			$customer_data['shipping_country'] = $country;
		WC()->customer->set_props( $customer_data );
		WC()->customer->save();

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
		WC()->session->reload_checkout = true;
		wp_send_json_success(
			array(
				'success'  => true

			)
		);
		wp_die();
	}
	public function check_order_status(){
		if(!isset($_POST['transaction_id'])){
			return false;
		}
		$transaction_id = sanitize_text_field($_POST['transaction_id']);

		//$transaction_id = 'T12000001.4XmAg8326rD1rdYFepUBe4';
		$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );

		$transaction_order_id = trim($transaction['merchant_reference']);
		if($transaction_order_id == '' && isset($transaction['merchant_reference_2'])){
			$transaction_order_id = trim($transaction['merchant_reference_2']);
		}

		$order                = wc_get_order( $transaction_order_id );

		if($order){
            $location = $order->get_checkout_order_received_url();
            $location = $location.'&merchant_reference='.$transaction_order_id.'&transaction_id='.$transaction_id;

            $return = array(
                'message' => __( 'order created', 'textdomain' ),
                'redirect_url' => $location,

            );
            wp_send_json_success($return);

        }
	}

	public function update_session(){

		if ( apply_filters( 'dhp_check_if_needs_payment', true ) ) {
	        if ( ! WC()->cart->needs_payment() ) {
	            $redirectUrl = wc_get_checkout_url();
	            $result = array(
					'success' => false,
					'redirect_url' => $redirectUrl
				);
	            wp_send_json($result);
	        }
        }
		$sessionId = WC()->session->get( 'dintero_wc_order_id' );
		$session = WCDHP()->checkout()->update_session($sessionId);
		$result = array(
			'success' => true
		);
		wp_send_json($result);

	}


	public function update_shipping_line_id(){
		$shippingLineId = sanitize_text_field($_POST['line_id']);
		if($shippingLineId){
			WC()->session->__unset('dintero_shipping_line_id');
			WC()->session->set( 'dintero_shipping_line_id', $shippingLineId );
			$result = array(
				'success' => true
			);
			echo sanitize_key(WC()->session->get( 'dintero_shipping_line_id'));
			exit;
			wp_send_json($result);
		}


	}

	/*
	* The function can create order in woocommerce
	* This function is used for backup callback if order does not get created in first instance
	*
	*/
	public function dhp_create_order() {

		$transaction_id = !empty($_GET['transaction_id']) ? $_GET['transaction_id'] : null;
		$session_id = !empty($_GET['session_id']) ? $_GET['session_id'] : null;

		if (empty($transaction_id) || empty($session_id)) {
			wp_send_json_error(array('message' => __('Missing required params')), 400);
		}

		$transaction_id = sanitize_text_field( wp_unslash( $transaction_id ) );
		$session_id = sanitize_text_field( wp_unslash( $session_id ) );

		// extracting transaction and session from dintero
		$transaction = self::_adapter()->get_transaction($transaction_id);

		if (isset($transaction['error'])) {
			wp_send_json_error(__('Invalid response'), 400);
		}

		// TODO: Can be removed when all callbacks with ?include_session contain session.metadata.woo_customer_id
		if (array_key_exists('session', $transaction) &&
			array_key_exists('metadata', $transaction['session']) &&
			array_key_exists('woo_customer_id', $transaction['session']['metadata'])
		) {
			$session = $transaction['session'];
		} else {
			$session = self::_adapter()->get_session($session_id);
		}
		$session_data = WC()->session->get_session($session['metadata']['woo_customer_id']);

		$transaction_order_id = trim($transaction['merchant_reference']);
		if ($transaction_order_id == '' && isset($transaction['merchant_reference_2'])) {
			$transaction_order_id = trim($transaction['merchant_reference_2']);
		}

		$order = wc_get_order( $transaction_order_id );

		if(!$order && $transaction['merchant_reference_2'] == '') {
			$coupon_codes = isset($session_data['applied_coupons']) ? maybe_unserialize($session_data['applied_coupons']) : array();
			if (count($coupon_codes) == 0 && isset($session['order']['discount_codes']) && count($session['order']['discount_codes']) > 0) {
				$coupon_codes = $session['order']['discount_codes'];
			}
			$items = $transaction['items'];
			$order = wc_create_order( array( 'status' => 'pending' ) );
			$order->set_transaction_id( $transaction_id );
			$order->set_prices_include_tax(true);
			$order->set_total($transaction['amount'] / 100);

			$data = array(
				'term' => 1,
				'createaccount' => 0,
				'shipping_method' => array(
					'0' => $transaction['shipping_option']['id']
				),
				'ship_to_different_address' => '',
				'woocommerce_checkout_update_totals',
				'payment_method' => 'dintero-hp',

				// billing details
				'billing_first_name' => sanitize_text_field($transaction['shipping_address']['first_name']),
				'billing_last_name' => sanitize_text_field($transaction['shipping_address']['last_name']),
				'billing_country' => sanitize_text_field($transaction['shipping_address']['country']),
				'billing_address_1' => sanitize_text_field($transaction['shipping_address']['address_line']),
				'billing_city' => sanitize_text_field($transaction['shipping_address']['postal_place']),
				'billing_postcode' => sanitize_text_field($transaction['shipping_address']['postal_code']),
				'billing_phone' => sanitize_text_field($transaction['shipping_address']['phone_number']),
				'billing_email' => sanitize_text_field($transaction['shipping_address']['email']),

				// shipping details
				'shipping_first_name' => sanitize_text_field($transaction['shipping_address']['first_name']),
				'shipping_last_name' => sanitize_text_field($transaction['shipping_address']['last_name']),
				'shipping_country' => sanitize_text_field($transaction['shipping_address']['country']),
				'shipping_address_1' => sanitize_text_field($transaction['shipping_address']['address_line']),
				'shipping_city' => sanitize_text_field($transaction['shipping_address']['postal_place']),
				'shipping_postcode' => sanitize_text_field($transaction['shipping_address']['postal_code']),
			);

			$order->set_props($data);

			// Get a new instance of the WC_Order_Item_Shipping Object
			$shipping_item_id = $transaction['shipping_option']['id'];
			$total_vat = $session['order']['vat_amount'] / 100;
			$real_shipping_tax = 0;

			/** @var WC_Order_Item $item */
			foreach ($items as $key => $item) {
				$vat_amount = $item['vat_amount'] / 100;
				$amount = $item['amount'] / 100;

				// processing shipping item
				if ($item['id'] == $shipping_item_id) {
					$real_shipping_tax = $vat_amount;
					$order_item = new WC_Order_Item_Shipping();
					$order_item->set_method_id(substr($item['id'], 0, strpos($item['id'], ':')));
					$order_item->set_method_title(!empty($item['description']) ? $item['description'] : __('Shipping'));
					$order_item->set_total($amount - $vat_amount);
					$order_item->set_instance_id($transaction['shipping_option']['operator_product_id'] ); // set an existing Shipping method instance ID
					$order_item->set_taxes(array(
						'total' => array($vat_amount)
					));

					if (isset($transaction['shipping_option']['metadata'])) {
						foreach ($transaction['shipping_option']['metadata'] as $meta_key => $meta_item) {
							$meta_value = self::isJson($meta_item) ? json_decode($meta_item) : $meta_item;
							$order_item->add_meta_data($meta_key, $meta_value);
						}
					}
					$order_item->save();

					/**
					 * Action hook to adjust item before save.
					 *
					 * @since 3.0.0
					 */
					do_action('woocommerce_checkout_create_order_shipping_item', $order_item, $key, $item, $order);

					$order->add_item($order_item);
					$order->set_shipping_total($amount - $vat_amount);
					$order->set_shipping_tax($vat_amount);
					$total_vat += $vat_amount;
					continue;
				}

				// skipping if no product found
				if (!$product = wc_get_product( $item['id'] )) {
					continue;
				}

				$item_id = $order->add_product($product, $item['quantity']);

				$order_item = $order->get_item($item_id);

				/**
				 * Action hook to adjust item before save.
				 *
				 * @since 3.0.0
				 */
				do_action(
					'woocommerce_checkout_create_order_line_item',
					$order_item,
					$order_item->get_id(),
					$order_item->get_data(),
					$order
				);
				$order_item->save_meta_data();
			}

			if(isset($transaction['shipping_address']['business_name']) && $transaction['shipping_address']['business_name']){ // if Business Checkout
				update_post_meta( $order->get_id(), '_shipping_vat_number', sanitize_text_field($transaction['shipping_address']['organization_number'] ) );
				update_post_meta( $order->get_id(), '_shipping_company', sanitize_text_field($transaction['shipping_address']['business_name'] ) );
				update_post_meta( $order->get_id(), '_billing_company', sanitize_text_field($transaction['shipping_address']['business_name'] ) );
				$data['billing_vat'] = sanitize_text_field($transaction['shipping_address']['organization_number'] );
				$data['billing_company'] = sanitize_text_field($transaction['shipping_address']['business_name'] );
				$coName = $transaction['shipping_address']['co_address'];
				$tempName = explode(" ",$coName);
				$data['shipping_first_name'] = $data['billing_first_name'] = sanitize_text_field($tempName[0]);
				$data['shipping_last_name'] = $data['billing_last_name'] = sanitize_text_field(
					str_replace($tempName[0],'', $coName)
				);
			}

			update_post_meta( $order->get_id(), '_shipping_phone', sanitize_text_field($transaction['shipping_address']['phone_number'] ) );
			update_post_meta( $order->get_id(), '_shipping_email', sanitize_text_field( $transaction['shipping_address']['email']  ) );

			// Update Shipping Line Id
			update_post_meta($order->get_id(),'_wc_dintero_shipping_line_id',sanitize_text_field( $transaction['shipping_option']['line_id']  ) );

			// Set the payment product used for transaction
			$order->set_payment_method_title('Dintero - ' . $transaction['payment_product']);

			if(count($coupon_codes) > 0){
				foreach ($coupon_codes as $coupon_code) {
					$order->apply_coupon($coupon_code);
				}
			}

			$order->calculate_shipping();
			$order->recalculate_coupons();
			$order->calculate_totals();

			// fixing shipping tax amount if precision is set to 0
			/** @var WC_Order_Item_Shipping $shipping_item */
			$shipping_item = current($order->get_items('shipping'));

			$shipping_item->add_meta_data(__('Items', 'woocommerce'), implode(', ', array_map(function($item) {
		 		return $item->get_name() . ' &times; ' . $item->get_quantity();
			}, $order->get_items())), true);

			/** @var WC_Order_Item_Tax $tax_item */
			$tax_item = current($order->get_items('tax'));
			if ($shipping_item && $shipping_item->get_total_tax() != $real_shipping_tax) {
				$tax_item->set_shipping_tax_total($real_shipping_tax);
				$order->set_total($transaction['amount'] / 100);
			}

			/**
			 * Action hook to adjust order before save.
			 *
			 *
			 */
			do_action( 'woocommerce_checkout_create_order', $order, $data );

			// Save the order.
			$order_id = $order->save();
			// Hook to update order meta
			do_action( 'woocommerce_checkout_update_order_meta', $order_id, $data );

			// The text for the note
			$orderNote = __('Order Created Via CallBack');

			// Add the note
			$order->add_order_note( $orderNote );

			if ( ! empty( $order ) && $order instanceof WC_Order ) {
				if (array_key_exists( 'status', $transaction )
					&& array_key_exists( 'amount', $transaction )
				) {

					WC()->session->set( 'order_awaiting_payment', null );
					if ( 'AUTHORIZED' === $transaction['status'] ) {

						$note = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
						self::process_authorization( $order, $transaction_id, $note );
					} elseif ( 'CAPTURED' === $transaction['status'] ) {

						$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
						self::payment_complete( $order, $transaction_id, $note );
					} elseif('ON_HOLD' === $transaction['status'] ) {
						$hold_reason = __( 'The payment is put on on-hold for manual review by payment provider. The review will usually be finished within 5 minutes, and the status will be updated. Transaction ID: ' ) . $transaction_id;
						self::on_hold_order( $order, $transaction_id, $hold_reason );
					} elseif('FAILED' === $transaction['status'] ) {
						$fail_reason = __( 'The payment is not approved. Transaction ID: ' ) . $transaction_id;
						self::failed_order( $order, $transaction_id, $fail_reason );
					}
					WC()->session->delete_session($session['metadata']['woo_customer_id']);
				}
			}
			$update_payload = array(
				'merchant_reference_2' => (string) $order->get_id()
			);
			$update_response = self::_adapter()->update_transaction(
				$transaction_id, $update_payload
			);
			if (is_wp_error($update_response)) {
				$updated_transaction = self::_adapter()->get_transaction($transaction_id);
				if (isset($updated_transaction['merchant_reference_2']) && !empty($updated_transaction['merchant_reference_2'])) {
					$fail_reason = __( 'Duplicate order of order ' ) . $updated_transaction['merchant_reference_2'] . '.';
					self::failed_order( $order, $transaction_id, $fail_reason );
				} else {
					$fail_reason = __( 'Failed updating transaction with order_id. This means that it will be harder to find the order in the settlements. ' ) . '.';
					$order->add_order_note( $fail_reason );

					$update_response_retry = self::_adapter()->update_transaction(
						$transaction_id, $update_payload
					);

					if (is_wp_error($update_response_retry)) {
						$fail_reason = __( 'Failed updating transaction with order_id. Will stop trying ' ) . '.';
						$order->add_order_note( $fail_reason );
					} else {
						$order->add_order_note( 'Order id was updated after retry');
					}
				}
			}
			self::$isOnGoingPushOperation = false;
		} elseif($order && ($order->get_status() == 'on-hold' || $order->get_status() == 'pending') ){
			if ( 'AUTHORIZED' === $transaction['status'] ) {

				$note = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
				self::process_authorization( $order, $transaction_id, $note );
			} elseif ( 'CAPTURED' === $transaction['status'] ) {

				$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
				self::payment_complete( $order, $transaction_id, $note );
			}  elseif ( 'ON_HOLD' === $transaction['status'] ) {
				$hold_reason = __( 'The payment is put on on-hold for manual review by payment provider. The review will usually be finished within 5 minutes, and the status will be updated. Transaction ID: ' ) . $transaction_id;
				self::on_hold_order( $order, $transaction_id, $hold_reason );
			} elseif('FAILED' === $transaction['status'] ){
				$fail_reason = __( 'The payment is not approved. Transaction ID: ' ) . $transaction_id;
				self::failed_order( $order, $transaction_id, $fail_reason );
			}
		}
	}

	static function isJson($string) {
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	 }

	/*
	* The Create order function can create order in woocommcer
	* This funtion is  used in order creation for Checkout express
	*
	*/

	public static function create_order(){

		WC()->session->__unset('dintero_wc_order_id');

		try {
			$country = WC()->checkout()->get_value( 'shipping_country' );

			if(is_null($country)){ // Checks if there is No Shippingn Country in Customer Session
				 WC()->customer->set_shipping_country(WC()->countries->get_base_country());
				 WC()->cart->calculate_totals();
			}

			$order = wc_create_order( array( 'status' => 'pending' ) );

			$order->set_customer_id(WC()->cart->get_customer()->get_id());
			$order->set_billing_first_name( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_first_name' ) ));
			$order->set_billing_last_name( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_last_name' )  ) );
			$order->set_billing_country( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_country' ) ) );
			$order->set_billing_address_1( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_address_1' ) ) );
			$order->set_billing_city( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_city' ) ) );
			$order->set_billing_postcode( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_postcode' ) ) );
			$order->set_billing_phone( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_phone' )) );
			$order->set_billing_email( sanitize_text_field( (string) WC()->checkout()->get_value( 'billing_email' ) ) );

			$order->set_shipping_first_name( sanitize_text_field( (string) WC()->checkout()->get_value( 'shipping_first_name' )  ) );
			$order->set_shipping_last_name( sanitize_text_field( (string) WC()->checkout()->get_value( 'shipping_last_name' ) ) );
			$order->set_shipping_country( sanitize_text_field( (string) WC()->checkout()->get_value( 'shipping_country' ) ) );
			$order->set_shipping_address_1( sanitize_text_field( (string) WC()->checkout()->get_value( 'shipping_address_1' ) ) );

			$order->set_shipping_city( sanitize_text_field( (string) WC()->checkout()->get_value( 'shipping_city' ) ) );

			$order->set_shipping_postcode( sanitize_text_field( (string) WC()->checkout()->get_value( 'shipping_postcode' ) ) );
			update_post_meta( $order->get_id(), '_shipping_phone', sanitize_text_field( WC()->checkout()->get_value( 'shipping_phone' ) ) );
			update_post_meta( $order->get_id(), '_shipping_email', sanitize_text_field( WC()->checkout()->get_value( 'shipping_email' )  ) );


			$order->set_created_via( 'dintero_checkout' );
			$order->set_currency( get_woocommerce_currency());
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

			$available_gateways = WC()->payment_gateways->payment_gateways();
			$payment_method     = $available_gateways['dintero-hp'];
			$order->set_payment_method( $payment_method );

			WC()->cart->calculate_totals();
			$order->set_shipping_total( WC()->cart->get_shipping_total() );
			$order->set_discount_total( WC()->cart->get_discount_total() );
			$order->set_discount_tax( WC()->cart->get_discount_tax() );
			$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
			$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
			$order->set_total( WC()->cart->get_total( 'edit' ) );

			WC()->checkout()->create_order_line_items( $order, WC()->cart );
			WC()->checkout()->create_order_fee_lines( $order, WC()->cart );
			WC()->checkout()->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping()->get_packages() );
			WC()->checkout()->create_order_tax_lines( $order, WC()->cart );
			WC()->checkout()->create_order_coupon_lines( $order, WC()->cart );

			/**
			 * Added to simulate WCs own order creation.
			 *
			 * TODO: Add the order content into a $data variable and pass as second parameter to the hook.
			 */
			do_action( 'woocommerce_checkout_create_order', $order, array() );

			// Save the order.
			$order_id = $order->save();

			if ($order_id) {
				do_action( 'woocommerce_checkout_update_order_meta', $order_id, array() );
				$result = $payment_method->process_payment( $order_id ,true);
				wp_send_json($result);
			}


            //wp_send_json_success($return);

		} catch ( Exception $e ) {
			return new WP_Error( 'checkout-error', $e->getMessage() );
		}
	}

	public static function express_pay() {
		//check_ajax_referer( 'express-checkout', 'security' );

		$test = WCDHP()->checkout()->pay_action(true);
	}

	/**
	* TO DO , Create call back method to create order if something goes wrong
	*/


	public static function dhp_update_ord_emded(){

	}
	/**
	 * Update order status post back
	 */
	public static function dhp_update_ord() {
		if ( ! empty( $_GET['transaction_id'] ) ) {

			$transaction_id = sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) );

			$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );

			$transaction_order_id = $transaction['merchant_reference'];


			$order                = wc_get_order( $transaction_order_id );
			$isExpress = false;

			if($transaction['shipping_option']['id'] == 'shipping_express'){
				$isExpress = true;

				$shipping_address = $transaction['shipping_address'];
				if(array_key_exists('business_name', $shipping_address)) { // if Business Checkout
					$postMeta = get_post_meta( $order->get_id()) ;

					update_post_meta( $order->get_id(), '_shipping_vat_number', sanitize_text_field($shipping_address['organization_number'] ) );

					update_post_meta( $order->get_id(), '_shipping_company', sanitize_text_field($shipping_address['business_name'] ) );
					update_post_meta( $order->get_id(), '_billing_company', sanitize_text_field($shipping_address['business_name'] ) );


					$coName = $shipping_address['co_address'];
					$tempName = explode(" ",$coName);

					$firstName = $tempName[0];
					$lastName = str_replace($tempName[0],"",$coName);

					$order->set_billing_first_name( sanitize_text_field($firstName));
					$order->set_billing_last_name( sanitize_text_field( $lastName) );

					$order->set_shipping_first_name( sanitize_text_field( $firstName) );
					$order->set_shipping_last_name( sanitize_text_field( $lastName) );
				}else{
					$order->set_billing_first_name( sanitize_text_field($shipping_address['first_name']));
					$order->set_billing_last_name( sanitize_text_field( $shipping_address['last_name']  ) );
					$order->set_shipping_first_name( sanitize_text_field( $shipping_address['first_name']) );
					$order->set_shipping_last_name( sanitize_text_field( $shipping_address['last_name']) );

				}




				$order->set_billing_country( sanitize_text_field($shipping_address['country']) );
				$order->set_billing_address_1( sanitize_text_field( $shipping_address['address_line'] ) );
				$order->set_billing_city( sanitize_text_field( $shipping_address['postal_place'] ) );
				$order->set_billing_postcode( sanitize_text_field( $shipping_address['postal_code']  ) );
				$order->set_billing_phone( sanitize_text_field( $shipping_address['phone_number']) );
				$order->set_billing_email( sanitize_text_field( $shipping_address['email'] ) );




				$order->set_shipping_country( sanitize_text_field( $shipping_address['country'] ) );
				$order->set_shipping_address_1( sanitize_text_field( $shipping_address['address_line'] ) );

				$order->set_shipping_city( sanitize_text_field( $shipping_address['postal_place']) );

				$order->set_shipping_postcode( sanitize_text_field( $shipping_address['postal_code'] ) );
				update_post_meta( $order->get_id(), '_shipping_phone', sanitize_text_field($shipping_address['phone_number'] ) );
				update_post_meta( $order->get_id(), '_shipping_email', sanitize_text_field( $shipping_address['email']  ) );

				$order->save();
			}
			$methodName = 'Dintero - '.$transaction['payment_product'];
			$order->set_payment_method_title($methodName);

			if ( ! empty( $order ) && $order instanceof WC_Order ) {
				$amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
				if ( array_key_exists( 'status', $transaction ) &&
					 array_key_exists( 'amount', $transaction ) &&
					 $transaction['amount'] === $amount ) {

					WC()->session->set( 'order_awaiting_payment', null );

					if ( 'AUTHORIZED' === $transaction['status'] ) {

						$note = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
						self::process_authorization( $order, $transaction_id, $note );
					} elseif ( 'CAPTURED' === $transaction['status'] ) {

						$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
						self::payment_complete( $order, $transaction_id, $note );
					}
				} else {
					if (array_key_exists( 'amount', $transaction ) &&
						$transaction['amount'] !== $amount ) {
						$note = sprintf(
							'Failed to authorize order: Order and transaction amounts do not match. Transaction amount: %s. Order amount: %s. ',
							$transaction['amount'],
							$amount
						) . $transaction_id;
						$order->add_order_note($note);
					}
				}
			}

			if (true || ! $return_page ) {
				exit;
			}
		}
	}


	/**
	 * Get shipping method name.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return string $shipping_name Name for selected shipping method.
	 */
	public function get_shipping_name() {
		$shipping_packages = WC()->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_name = $rate_value->get_label();
					}
				}
			}
		}
		if ( ! isset( $shipping_name ) ) {
			$shipping_name = __( 'Shipping', 'dintero-checkout-for-woocommerce' );
		}

		return (string) $shipping_name;
	}

	/**
	 * Get shipping reference.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return string $shipping_reference Reference for selected shipping method.
	 */
	public function get_shipping_reference() {
		$shipping_packages = WC()->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_reference = $rate_value->id;
					}
				}
			}
		}
		if ( ! isset( $shipping_reference ) ) {
			$shipping_reference = __( 'Shipping', 'Dintero-checkout-for-woocommerce' );
		}

		return (string) $shipping_reference;
	}

	/**
	 * Get shipping method amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return integer $shipping_amount Amount for selected shipping method.
	 */
	public function get_shipping_amount() {
		if ( self::separate_sales_tax ) {
			$shipping_amount = (int) number_format( WC()->cart->shipping_total * 100, 0, '', '' );
		} else {
			// $shipping_amount = (int) number_format( ( WC()->cart->shipping_total + WC()->cart->shipping_tax_total ) * 100, 0, '', '' );
			$shipping_amount = number_format( WC()->cart->shipping_total + WC()->cart->shipping_tax_total, wc_get_price_decimals(), '.', '' ) * 100;
		}

		return $shipping_amount;
	}

	/**
	 * Get shipping method tax rate.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return integer $shipping_tax_rate Tax rate for selected shipping method.
	 */
	public function get_shipping_tax_rate() {
		if ( WC()->cart->shipping_tax_total > 0 && ! self::separate_sales_tax ) {
			$shipping_rates = WC_Tax::get_shipping_tax_rates();
			$vat            = array_shift( $shipping_rates );
			if ( isset( $vat['rate'] ) ) {
				$shipping_tax_rate = round( $vat['rate'] * 100 );
			} else {
				$shipping_tax_rate = 0;
			}
		} else {
			$shipping_tax_rate = 0;
		}

		return round( $shipping_tax_rate );
	}

	/**
	 * Get shipping method tax amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return integer $shipping_tax_amount Tax amount for selected shipping method.
	 */
	public function get_shipping_tax_amount() {

		if ( self::separate_sales_tax ) {
			$shipping_tax_amount = 0;
		} else {
			$shiping_total_amount        = self::get_shipping_amount();
			$shipping_total_exluding_tax = $shiping_total_amount / ( 1 + ( self::get_shipping_tax_rate() / 10000 ) );
			$shipping_tax_amount         = $shiping_total_amount - $shipping_total_exluding_tax;
		}
		return round( $shipping_tax_amount );
	}

	/**
	 * @param $key
	 * @param $value
	 * @throws ReflectionException
	 */
	private static function update_session_prop($key, $value)
	{
		$reflection = new ReflectionProperty(WC()->session, $key);
		$reflection->setAccessible(true);
		$reflection->setValue(WC()->session, $value);
	}


	/**
	 * For backwards compatibility
	 */
	public static function dhp_update_ship()
	{
		self::dhp_shipping_options();
	}

	/**
	 * Update order shipping address post back
	 */
	public static function dhp_shipping_options()
	{
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			wp_send_json_error(array('message' => __('Method not allowed')), 403);
		}
		$php_input = file_get_contents('php://input');
		$post_data = json_decode($php_input, true);
		if (!is_array($post_data) || !isset($post_data['order']) || !isset($post_data['order']['shipping_address'])) {
			wp_send_json_error(array('message' => __('Bad request')), 400);
		}
		$dintero_session = self::_adapter()->get_session($post_data['id']);

		$shipping_address = $post_data['order']['shipping_address'];

		$fields_mapping = array(
			'billing_email' => 'email',
			'billing_postcode' => 'postal_code',
			'billing_first_name' => 'first_name',
			'billing_last_name' => 'last_name',
			'billing_country' => 'country',

			'shipping_postcode' => 'postal_code',
			'shipping_first_name' => 'first_name',
			'shipping_last_name' => 'last_name',
			'shipping_city' => 'postal_place',
			'shipping_country' => 'country',
		);
		$customer_data = array();
		foreach ($fields_mapping as $field => $name) {
			$customer_data[$field] = isset($shipping_address[$name]) ? $shipping_address[$name] : null;
		}
		$woo_customer_id = $dintero_session['metadata']['woo_customer_id'];
		$session_data = WC()->session->get_session($woo_customer_id);
		self::update_session_prop('_data', $session_data);
		self::update_session_prop('_customer_id', $woo_customer_id);
		WC()->cart->get_cart_from_session();
		// below code does not reflect changes in Checkout Onject as it cannot be access in callback
		WC()->customer->set_props( $customer_data );

		WC()->cart->calculate_totals();

		$isShippingInIframe = 'yes' == WCDHP()->setting()->get('shipping_method_in_iframe');
		if(!$isShippingInIframe){
			$isShippingInIframe = 0;
		}
		$express_button_query_param = sanitize_text_field($_GET['express_button']);
		$isExpressButton = 'true' == $express_button_query_param;
		if(!$isExpressButton) {
			$isExpressButton = 0;
		}
		$shipping_options = array();
		if ($isShippingInIframe || $isExpressButton) {
			foreach ( WC()->shipping()->get_packages() as $package ) {

				if ( empty($package['rates']) ) {
					continue;
				}

				foreach ( $package['rates'] as $method ) {
					$method_id = $method->id;
					$method_name = $method->label;
					$tax_display = get_option('woocommerce_tax_display_cart');
					$method_price = Dintero_HP_Helper::instance()->to_dintero_amount($method->cost, 2);
					if ( array_sum($method->taxes) > 0 && ('excl' !== $tax_display) ) {
						$method_tax_amount = Dintero_HP_Helper::instance()->to_dintero_amount(array_sum($method->taxes), wc_get_rounding_precision());
						$method_tax_rate = intval(round((array_sum($method->taxes) / $method->cost) * 100, 2));
					} else {
						$method_tax_amount = Dintero_HP_Helper::instance()->to_dintero_amount(array_sum($method->taxes), wc_get_price_decimals());
						$method_tax_rate = Dintero_HP_Helper::instance()->get_shipping_tax_rate();
					}

					$shipping_option = array(
						'id' => $method_id,
						'line_id' => 'shipping_method_' . $j,
						'title' => $method_name,
						'amount' => (int)($method_price + $method_tax_amount),
						'vat_amount' => (int)$method_tax_amount,
						'vat' => $method_tax_rate,
						'description' => '',
						'delivery_method' => 'unspecified',
						'operator' => '',
						'operator_product_id' => (string)$method->instance_id,
					);
					$metadata = Dintero_HP_Helper::instance()->convert_to_dintero_metadata($method->meta_data);

					if (!is_null($metadata)) {
						$shipping_option['metadata'] = $metadata;
					}

					$shipping_options[] = $shipping_option;

					if ( $j == 0 ) {
						WC()->session->set('dintero_shipping_line_id', 'shipping_method_' . $j);
					}

					$j++;
				}
			}
		}
		wp_send_json(array(
			'shipping_options' => $shipping_options,
		));
	}

	/**
	 * Complete order, add transaction ID and note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $note Payment note.
	 */
	public static function payment_complete( $order, $transaction_id = '', $note = '' ) {
		$order->add_order_note( $note );
		$order->set_transaction_id( $transaction_id );
		$order->payment_complete( $transaction_id );
		wc_reduce_stock_levels( $order->get_id() );
	}

	/**
	 * Pricess order and add note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $reason Reason why the payment is on hold.
	 */
	private static function process_authorization( $order, $transaction_id = '', $reason = '' ) {
		$order->set_transaction_id( $transaction_id );

		$default_order_status = WC_Dintero_HP_Admin_Settings::get_option('default_order_status');
		if (!$default_order_status) {
			$default_order_status = 'wc-processing';
		}

		$order->update_status( $default_order_status, $reason );

	}

	/**
	 * Hold order and add note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $reason Reason why the payment is on hold.
	 */
	private static function on_hold_order( $order, $transaction_id = '', $reason = '' ) {
		$order->set_transaction_id( $transaction_id );


		$default_order_status = 'wc-on-hold';


		$order->update_status( $default_order_status, $reason );

	}
	/**
	 * Failed order and add note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $reason Reason why the payment is on hold.
	 */
	private static function failed_order( $order, $transaction_id = '', $reason = '' ) {
		$order->set_transaction_id( $transaction_id );


		$default_order_status = 'wc-failed';


		$order->update_status( $default_order_status, $reason );

	}
}

WC_AJAX_HP::init();
