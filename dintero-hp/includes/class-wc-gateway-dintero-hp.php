<?php
/**
 * The file that defines the custom gateway class.
 *
 * A class definition that includes core functions of the custom payment gateway.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */

/**
 * The custom gateway class.
 *
 * This is used to define the core functions of the custom payment gateway.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */
class WC_Gateway_Dintero_HP extends WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'dintero-hp'; // payment gateway plugin ID
		$this->has_fields         = false;
		$this->method_title       = __( 'Dintero' );
		$this->method_description = __( 'Redirect customers to Dintero hosted page.' ); // will be displayed on the options page
		$this->init_form_fields();
		$this->init_settings();
		$this->supports                         = array(
			'products',
			'refunds'
		);
		$this->title                            = $this->get_option( 'title' );
		$this->description                      = $this->get_option( 'description' );
		$this->enabled                          = $this->get_option( 'enabled' );
		$this->test_mode                        = 'yes' === $this->get_option( 'test_mode' );
		$this->callback_verification            = 'yes' === $this->get_option( 'callback_verification' );
		$this->account_id                       = $this->get_option( 'account_id' );
		$this->client_id                        = $this->test_mode ? $this->get_option( 'test_client_id' ) : $this->get_option( 'production_client_id' );
		$this->client_secret                    = $this->test_mode ? $this->get_option( 'test_client_secret' ) : $this->get_option( 'production_client_secret' );
		$this->profile_id                       = $this->test_mode ? $this->get_option( 'test_profile_id' ) : $this->get_option( 'production_profile_id' );
		$this->checkout_logo_width              = $this->get_option( 'checkout_logo_width' ) ? $this->get_option( 'checkout_logo_width' ) : 600;
		$this->default_order_status             = $this->get_option('default_order_status') ? $this->get_option('default_order_status') : 'wc-processing';
		$this->manual_capture_status            = str_replace( 'wc-', '',
			$this->get_option( 'manual_capture_status' ) );
		$this->additional_manual_capture_status = str_replace( 'wc-', '',
			$this->get_option( 'additional_manual_capture_status' ) );
		$this->additional_cancel_status         = str_replace( 'wc-', '',
			$this->get_option( 'additional_cancel_status' ) );
		$this->additional_refund_status         = str_replace( 'wc-', '',
			$this->get_option( 'additional_refund_status' ) );
		$this->api_endpoint                     = 'https://api.dintero.com/v1';
		$this->checkout_endpoint                = 'https://checkout.dintero.com/v1';
		$environment_character                  = $this->test_mode ? 'T' : 'P';
		$this->oid                              = $environment_character . $this->get_option( 'account_id' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		if ( $this->callback_verification ) {
			//Enable callback server-to-server verification
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'callback' ) );
		} else {
			//Use thank you page to check for transactions, only if callbacks are unavailable
			add_action( 'woocommerce_thankyou', array( $this, 'callback' ), 1, 1 );
		}

		add_action( 'woocommerce_order_status_changed', array( $this, 'check_status' ), 10, 3 );
		//$transaction_id = sanitize_text_field( wp_unslash( $_GET['transaction_id'] ));

		// //$transaction = $this->get_transaction( $transaction_id );
		// $query_args = array(
		// 	'fields'      => 'ids',
		// 	'post_type'   => wc_get_order_types(),
		// 	'post_status' => array_keys( wc_get_order_statuses() ),
		// 	'meta_key'    => '_wc_dintero_transaction_id',
		// 	'meta_value'  => $transaction_id,
		// );
		// $orders = get_posts( $query_args );
		// $order_id = $orders[0];
		// echo $order_id;
		// //$response = $this->update_transaction($transaction_id, $order_id);
		// $transaction = $this->get_transaction($transaction_id );
		// echo '<pre>';
		// print_r($transaction);
		// exit;
		// // print_r($transaction);
		// echo $this->get_access_token();
		//  exit;
		
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_url  = 'https://checkout.dintero.com/v1/branding/profiles/' . $this->profile_id . '/variant/colors/color/cecece/width/' . $this->checkout_logo_width . '/dintero_left_frame.svg';
		$icon_html = '<img src="' . esc_attr( $icon_url ) . '" alt="Dintero Logo" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Plugin options.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                          => array(
				'title'       => __( 'Enable/Disable' ),
				'label'       => __( 'Enable Dintero Hosted Page Gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'                            => array(
				'title'       => __( 'Title' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.' ),
				'default'     => __( 'Dintero' ),
				'desc_tip'    => true,
			),
			'description'                      => array(
				'title'       => __( 'Description' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.' ),
				'default'     => __( 'Pay through Dintero gateway.' ),
				'desc_tip'    => true,
			),
			'account_id'                       => array(
				'title'       => __( 'Account ID' ),
				'type'        => 'text',
				'description' => __( 'Found under (SETTINGS >> Account) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_test_credentials'          => array(
				'title'       => __( 'Client Test' ),
				'type'        => 'title',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' )
			),
			'test_client_id'                   => array(
				'title'       => __( 'Test Client ID' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_client_secret'               => array(
				'title'       => __( 'Test Client Secret' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_profile_id'                  => array(
				'title'       => __( 'Test Payment Profile ID' ),
				'type'        => 'text',
				'description' => __( 'Test payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_production_credentials'    => array(
				'title'       => __( 'Client Production' ),
				'type'        => 'title',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
			),
			'production_client_id'             => array(
				'title'       => __( 'Production Client ID' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'production_client_secret'         => array(
				'title'       => __( 'Production Client Secret' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'production_profile_id'            => array(
				'title'       => __( 'Production Payment Profile ID' ),
				'type'        => 'text',
				'description' => __( 'Production payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'checkout_settings'                => array(
				'title'       => __( 'Checkout' ),
				'type'        => 'title',
				'description' => __( 'Checkout settings.' )
			),
			'test_mode'                        => array(
				'title'       => __( 'Test mode' ),
				'label'       => __( 'Enable Test Mode' ),
				'type'        => 'checkbox',
				'description' => __( 'Put the payment gateway in test mode using client test credentials.' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'callback_verification'            => array(
				'title'       => __( 'Callback URL Verification' ),
				'label'       => __( 'Enable Callback URL Server-to-Server Verification' ),
				'type'        => 'checkbox',
				'description' => __( 'Enabling this will send callback URL to the API and verify the transaction when a callback request received. Disabling this will verify the transaction using parameters returned to the return page.' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'checkout_logo_width'              => array(
				'title'       => __( 'Dintero Checkout Logo Width (in pixels)' ),
				'type'        => 'number',
				'description' => __( 'The width of Dintero\'s logo on the checkout page in pixels.' ),
				'default'     => 600,
				'desc_tip'    => true,
			),
			'capture_settings'                 => array(
				'title'       => __( 'Payment Capture' ),
				'type'        => 'title',
				'description' => __( 'Payment Capture settings.' )
			),
			'default_order_status' => array(
				'title'       => __( 'Default Order Status' ),
				'type'        => 'select',
				'options'     => array(
					'wc-processing' => _x( 'Processing', 'Order status' ),
					'wc-on-hold'    => _x( 'On hold', 'Order status' ),
				),
				'default'     => 'wc-processing',
				'description' => __( 'When payment Authorized.' ),
				'desc_tip'    => true
			),
			'manual_capture_settings' => array(
				'title'       => __( 'Capture order when:' ),
				'type'        => 'title',
			),
			'manual_capture_status'            => array(
				'title'       => __( 'Order status is changed to: ' ),
				'type'        => 'select',
				'options'     => wc_get_order_statuses(),
				'default'     => 'wc-completed',
				'description' => __( 'Select a status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true
			),
			'additional_manual_capture_status' => array(
				'title'       => __( 'Order status is changed to (additional): ' ),
				'type'        => 'select',
				'options'     => ( array(
									   - 1 => '--- Disable Additional Manual Capture Order Status ---'
								   ) + wc_get_order_statuses() ),
				'default'     => -1,
				'description' => __( 'Select an additional status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true
			),
			'cancel_refund_settings'           => array(
				'title'       => __( 'Cancel or refund order when:' ),
				'type'        => 'title'
			),
			'additional_cancel_status'         => array(
				'title'       => __( 'Order status is changed to:' ),
				'type'        => 'select',
				'options'     => ( array(
									   - 1 => '--- Disable Additional Cancellation Order Status ---'
								   ) + wc_get_order_statuses() ),
				'default'     => - 1,
				'description' => __( 'Select an additional status that will be used to cancel the order. Status "Cancelled" will be always used to cancel the order.' ),
				'desc_tip'    => true
			),
			'additional_refund_status'         => array(
				'title'       => __( 'Order status is changed to (additional): ' ),
				'type'        => 'select',
				'options'     => ( array(
									   - 1 => '--- Disable Additional Refund Order Status ---'
								   ) + wc_get_order_statuses() ),
				'default'     => - 1,
				'description' => __( 'Select an additional status that will be used to refund the order payment. Status "Refunded" will be always used to refund the order payment.' ),
				'desc_tip'    => true
			),

			'embed_settings'                => array(
				'title'       => __( 'Embedding Dintero Checkout:' ),
				'type'        => 'title',
				'description' => ''
			),
			'embed_enable'                        => array(
				'title'       => __( 'Enable:' ),
				'label'       => __( 'Enable Embed Checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Embed Checkout on Checkout page' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'express_settings'                => array(
				'title'       => __( 'Checkout Express' ),
				'type'        => 'title',
				'description' => ''
			),
			'express_enable'                        => array(
				'title'       => __( 'Enable:' ),
				'label'       => __( 'Enable Checkout Express' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Checkout Express on Checkout page' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'express_rewards'                        => array(
				'title'       => __( 'Enable Rewards:' ),
				'label'       => __( 'Enable Checkout Express Rewards' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Checkout Express Rewards' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'express_shopping_price'                        => array(
				'title'       => __( 'Show Shopping Price:' ),
				'label'       => __( 'Show/Hide Shipping Price' ),
				'type'        => 'checkbox',
				'description' => __( 'Show or hide shopping price' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'branding_title'                => array(
				'title'       => __( 'Branding:' ),
				'type'        => 'title',
				'description' => ''
			),
			'branding_footer_url'            => array(
				'title'       => __( 'URL (Footer):' ),
				'type'        => 'text',
				'description' => __( 'You can change color & size in Dintero Backoffice. Paste the new URL here:<br />Preview:<div>' . $this->get_icon_footer() . '</div>'),
				'default'     => '',
				'desc_tip'    => false,
			),
			'branding_checkout_url'            => array(
				'title'       => __( 'URL (In Checkout):' ),
				'type'        => 'text',
				'description' => __( 'You can change color & size in Dintero Backoffice. Paste the new URL here:<br />Preview:<div>' . $this->get_icon_checkout() . '</div>' ),
				'default'     => '',
				'desc_tip'    => false,
			),
		);
	}

	/**
	 * Requesting access token
	 */
	private function get_access_token() {
		$api_endpoint = $this->api_endpoint . '/accounts';

		$headers = array(
			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret )
		);

		$payload = array(
			'grant_type' => 'client_credentials',
			'audience'   => $api_endpoint . '/' . $this->oid
		);

		$response = wp_remote_post( $api_endpoint . '/' . $this->oid . '/auth/token', array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $payload ),
			'timeout'   => 90,
			'sslverify' => false
		) );

		// Retrieve the body's response if no errors found
		$response_body  = wp_remote_retrieve_body( $response );
		$response_array = json_decode( $response_body, true );

		if ( ! array_key_exists( 'access_token', $response_array ) ) {
			return false;
		}
		$access_token = $response_array['access_token'];

		return $access_token;
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
		if ( $this->separate_sales_tax ) {
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
		/*
		if ( WC()->cart->shipping_tax_total > 0 && ! $this->separate_sales_tax ) {
			$shipping_tax_rate = round( ( WC()->cart->shipping_tax_total / WC()->cart->shipping_total ) * 100, 2 ) * 100;
		} else {
			$shipping_tax_rate = 0;
		}
		*/

		// error_log( 'tax rate ' . var_export( WC_Tax::get_shipping_tax_rates(), true ) );
		if ( WC()->cart->shipping_tax_total > 0 && ! $this->separate_sales_tax ) {
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
		/*
		if ( $this->separate_sales_tax ) {
			$shipping_tax_amount = 0;
		} else {
			$shipping_tax_amount = WC()->cart->shipping_tax_total * 100;
		}
		*/
		if ( $this->separate_sales_tax ) {
			$shipping_tax_amount = 0;
		} else {
			$shiping_total_amount        = $this->get_shipping_amount();
			$shipping_total_exluding_tax = $shiping_total_amount / ( 1 + ( $this->get_shipping_tax_rate() / 10000 ) );
			$shipping_tax_amount         = $shiping_total_amount - $shipping_total_exluding_tax;
		}
		return round( $shipping_tax_amount );
	}

	/**
	 * Creating checkout session and requesting payment page URL
	 */
	private function get_payment_page_url( $order , $isExpress = false) {
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$order_id     = $order->get_id();
			$access_token = $this->get_access_token();
			$api_endpoint = $this->checkout_endpoint . '/sessions-profile';

			$express_enable = WCDHP()->setting()->get('express_enable');
			$embed_enable = WCDHP()->setting()->get('embed_enable');

			if($isExpress == true){
				$express_enable = 'yes';
			}

		
			$return_url   = $this->get_return_url( $order );

			if ( $isExpress ) {
				$callback_url = home_url() . '?dhp-ajax=dhp_update_ord';
			} else {
				$callback_url = WC()->api_request_url( strtolower( get_class( $this ) ) );
			}			

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
			$order_tax_amount   = absint( strval( floatval( $order->get_total_tax() ) * 100 ) );

			$items = array();

			$counter = 0;
			$total_amount = 0;
			foreach ( $order->get_items() as $order_item ) {
				$counter ++;
				$line_id                = strval( $counter );
				$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
				$item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
				$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
						true ) ) * 100 ) );
				$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
						2 ) * 100 ) : 0;
				$item                   = array(
					'id'          => 'item_' . $counter,
					'description' => $order_item->get_name(),
					'quantity'    => $order_item->get_quantity(),
					'vat_amount'  => $item_tax_amount,
					'vat'         => $item_tax_percentage,
					'amount'      => $item_line_total_amount,
					'line_id'     => $line_id
				);
				array_push( $items, $item );

				$total_amount += $item_line_total_amount;
			}

			$shipping_option = array();
			$express_option = array();

			if ( count( $order->get_shipping_methods() ) > 0 ) {
				$counter ++;
				$line_id                = strval( $counter );
				$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
				$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
				$item_line_total_amount = $item_total_amount + $item_tax_amount;
				$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
						2 ) * 100 ) : 0;

				if (!$isExpress) {
					$item = array(
						'id'          => 'shipping',
						'description' => 'Shipping: ' . $order->get_shipping_method(),
						'quantity'    => 1,
						'vat_amount'  => $item_tax_amount,
						'vat'         => $item_tax_percentage,
						'amount'      => $item_line_total_amount,
						'line_id'     => $line_id
					);
					array_push( $items, $item );

					$total_amount += $item_line_total_amount;
				}

				$order_total_amount = $total_amount;

				$shipping_option = array(
						'id'				=> 'shipping',
						'line_id'			=> $line_id,
						//"countries"			=> array($order->get_shipping_country()),
						'country'			=> $order->get_shipping_country(),
						'amount'			=> $item_line_total_amount,
						'vat_amount'		=> $item_tax_amount,
						'vat'				=> $item_tax_percentage,
						'title'				=> 'Shipping: ' . $order->get_shipping_method(),
						'description'		=> '',
						'delivery_method'	=> 'delivery',						
						'operator'			=> '',
						'operator_product_id' => '',
						'eta'				=> array(),
						/*
						"time_slot"			=> array(),
						"pick_up_address"	=> array(
								"first_name"=>$order->get_shipping_first_name(),
								"last_name"=>$order->get_shipping_last_name(),
								"address_line"=>$order->get_shipping_address_1(),
								"address_line_2"=>$order->get_shipping_address_2(),
								"co_address"=>"",
								"business_name"=>"",
								"postal_code"=>$order->get_shipping_postcode(),
								"postal_place"=>$order->get_shipping_city(),
								"country"=>$order->get_shipping_country(),
								"phone_number"=>$order->get_billing_phone(),
								"email"=>$order->get_billing_email(),
								"latitude"=>0,
								"longitude"=>0,
								"comment"=>"",
								"distance"=>0
							)*/
					);

				if ($isExpress) {
					$ship_callback_url = home_url() . '?dhp-ajax=dhp_update_ship';

					$express_option = array(
						'shipping_address_callback_url'=>$ship_callback_url,
						'shipping_options'=>array(
								0=>array(
										'id'=>'shipping_express',
										'line_id'=>$line_id,
										//"countries"=>array($order->get_shipping_country()),
										'country'=>$order->get_shipping_country(),
										'amount'=>$item_line_total_amount,
										'vat_amount'=>$item_tax_amount,
										'vat'=>$item_tax_percentage,
										'title'=>'Shipping: ' . $order->get_shipping_method(),
										'description'=>'',
										'delivery_method'=>'delivery',
										'operator'=>'',
										'operator_product_id'=>'',
										'eta'=>array(
												'relative'=>array(
													'minutes_min'=>0,
													'minutes_max'=>0
												),
												'absolute'=>array(
													'starts_at'=>'',
													'ends_at'=>''
												)
											),
										/*
										"time_slot"=>array(
												"starts_at"=>"2020-10-14T19:00:00Z",
												"ends_at"=>"2020-10-14T20:00:00Z"
											),
										"pick_up_address"=>array(
												"first_name"=>$order->get_shipping_first_name(),
												"last_name"=>$order->get_shipping_last_name(),
												"address_line"=>$order->get_shipping_address_1(),
												"address_line_2"=>$order->get_shipping_address_2(),
												"co_address"=>"",
												"business_name"=>"",
												"postal_code"=>$order->get_shipping_postcode(),
												"postal_place"=>$order->get_shipping_city(),
												"country"=>$order->get_shipping_country(),
												"phone_number"=>"123456", //$order->get_billing_phone(),
												"email"=>$order->get_billing_email(),
												"latitude"=>0,
												"longitude"=>0,
												"comment"=>""
												//"distance"=>0
											)*/
									)
							)
					);
				}
			}

			$headers = array(
				'Content-type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $access_token
			);

			$payload_url = array(
					'return_url'   => $return_url,
					'callback_url' => $callback_url
				);

			$terms_page_id   = wc_terms_and_conditions_page_id();
			$terms_link      = esc_url( get_permalink( $terms_page_id ) );

			if ( 'yes' == $embed_enable || $isExpress) {
				$payload_url[ 'merchant_terms_url' ] = $terms_link;
			}

			$payload = array(
				'url'        => $payload_url,
				'customer'   => array(
					'email'        => $order->get_billing_email(),
					'phone_number' => $order->get_billing_phone()
				),
				'order'      => array(
					'amount'             => $order_total_amount,
					'vat_amount'         => $order_tax_amount,
					'currency'           => $order->get_currency(),
					'merchant_reference' => strval( $order_id ),
					'shipping_address'   => array(
						'first_name'   => $order->get_shipping_first_name(),
						'last_name'    => $order->get_shipping_last_name(),
						'address_line' => $order->get_shipping_address_1(),
						'postal_code'  => $order->get_shipping_postcode(),
						'postal_place' => $order->get_shipping_city(),
						'country'      => $order->get_shipping_country()
					),
					'billing_address'    => array(
						'first_name'   => $order->get_billing_first_name(),
						'last_name'    => $order->get_billing_last_name(),
						'address_line' => $order->get_billing_address_1(),
						'postal_code'  => $order->get_billing_postcode(),
						'postal_place' => $order->get_billing_city(),
						'country'      => $order->get_billing_country()
					),
					'items'              => $items
				),
				'profile_id' => $this->profile_id
			);
			
			if ( $isExpress ) {
				$payload['express'] = $express_option;
			}
			
			$response = wp_remote_post( $api_endpoint, array(
				'method'    => 'POST',
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 90,
				'sslverify' => false
			) );

			// Retrieve the body's response if no errors found
			$response_body  = wp_remote_retrieve_body( $response );
			$response_array = json_decode( $response_body, true );

			if ( ! array_key_exists( 'url', $response_array ) ) {
				return false;
			}
			$payment_page_url = $response_array['url'];

			return $payment_page_url;
		}
	}


	/**
	 * We're processing the payment here.
	 */
	public function process_payment( $order_id, $isExpress = false ) {

		$embed_enable = WCDHP()->setting()->get('embed_enable');
		

		if($embed_enable == 'yes' && !$isExpress){ // If its an Iframe
			$redirect_url = $this->process_payment_handler( $order_id , true);
			WC()->session->__unset('dintero_wc_order_id');
			if ( $redirect_url ) {
				
				wc_empty_cart();
				
				return array(
					'result'   => 'success',
					'redirect' =>  $redirect_url
				);
			} else {
				return array(
					'result' => 'error',
				);
			}
			

		}else{

			$order = wc_get_order( $order_id );
			WC()->session->__unset('dintero_wc_order_id');	
			if ( ! empty( $order ) && $order instanceof WC_Order ) {
				$express_enable = WCDHP()->setting()->get('express_enable');
				$payment_page_url = $this->get_payment_page_url( $order, $isExpress );
				
				return array(
					'result'   => 'success',
					'redirect' => $payment_page_url
				);
			}
		}

		
	}

	/**
	 * Process the payment with information from Dintero and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function process_payment_handler( $order_id ) {
		// Get the Dintero order ID.
		$order = wc_get_order( $order_id );
		
		$dintero_order_session_detail = $this->get_dintero_session( WC()->session->get( 'dintero_wc_order_id' ));
		
		$dintero_order_transaction_id = $dintero_order_session_detail['transaction_id'];
		
		if ( ! $dintero_order_transaction_id ) {
			return false;
		}
		
		if ( $order_id && $dintero_order_transaction_id ) {

			// Set WC order transaction ID.
			update_post_meta( $order_id, '_wc_dintero_session_id', sanitize_key(WC()->session->get( 'dintero_wc_order_id' ) ) );

			update_post_meta( $order_id, '_transaction_id', sanitize_key( $dintero_order_transaction_id ) );

			// Update the Dintero order with new confirmation merchant reference.  TO DO
			$transaction = WCDHP()->checkout()->update_transaction($dintero_order_transaction_id, $order_id);
			
			$methodName = 'Dintero - '.$transaction['payment_product'];
			$order->set_payment_method_title($methodName);


			if($transaction){
				
				if ( $transaction['status']  == 'AUTHORIZED') {

					$hold_reason = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $dintero_order_transaction_id;
					self::process_authorization( $order, $dintero_order_transaction_id, $hold_reason );
					
				} elseif ( 'CAPTURED' === $transaction['status'] ) {

					$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $dintero_order_transaction_id;
					self::payment_complete( $order, $transaction_id, $note );
				}
				
			}
			$order->set_transaction_id( $dintero_order_transaction_id );
			$order->save();
			

			// Check that the transaction id got set correctly.
			if ( get_post_meta( $order_id, '_transaction_id', true ) === $dintero_order_transaction_id ) {

				$url = apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order );
				$redirectUrl = $url.'&transaction_id='.$dintero_order_transaction_id;
				
				return $redirectUrl;
			}
		}
		// Return false if we get here. Something went wrong.
		return false;
	}

	/**
	 * Get Session detail By Session ID.
	 */
	public function get_dintero_session($sessionId){
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/sessions';

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token
		);

		$response = wp_remote_get( $api_endpoint . '/' . $sessionId, array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 90,
			'sslverify' => false
		) );

		// Retrieve the body's response if no errors found
		$response_body = wp_remote_retrieve_body( $response );
		$sessionDetail   = json_decode( $response_body, true );

		return $sessionDetail;
	}

	/**
	 * Get transaction by ID.
	 */
	private function get_transaction( $transaction_id ) {
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/transactions';

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token
		);

		$response = wp_remote_get( $api_endpoint . '/' . $transaction_id, array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 90,
			'sslverify' => false
		) );

		// Retrieve the body's response if no errors found
		$response_body = wp_remote_retrieve_body( $response );
		$transaction   = json_decode( $response_body, true );

		return $transaction;
	}

	/**
	 * Update transaction with woocommerce Order Number.
	 */
	private function update_transaction( $transaction_id , $order_id ) {

		
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/transactions';
		

		$headers = array(
			'content-type'        => 'application/json',
			'authorization' => 'Bearer ' . $access_token
		);

		$payload = array(
			'merchant_reference_2' => (string)$order_id
		);
		$url = $api_endpoint . '/' . $transaction_id;
		// echo "Access Token :".$access_token.'<br />';
		// echo $order_id;
		// $response = wp_remote_post( $url, array(
		// 	'method'    => 'PUT',
		// 	'headers'   => $headers,
		// 	'body'      => wp_json_encode($payload),
		// 	'timeout'   => 90,
		// 	'sslverify' => false
		// ) );
		$args = array(
		    'headers' => $headers,
		    'body'      => json_encode($payload),
		    'method'    => 'PUT'
		);
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "PUT",
		  CURLOPT_POSTFIELDS => json_encode($payload),
		  CURLOPT_HTTPHEADER => array(
			    "authorization: Bearer ".$access_token,
			   	"content-type: application/json"
			    
			  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  
		  $transaction   = json_decode( $response, true );

		  return $transaction;
		 
		}
		// $result =  wp_remote_request( $url, $args );
		// // Retrieve the body's response if no errors found
		// $response_body = wp_remote_retrieve_body( $result );
		
		// $transaction   = json_decode( $response_body, true );

		// return $transaction;
	}

	/**
	 * Creating order receipt.
	 */
	private function create_receipt( $order ) {
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$order_id     = $order->get_id();
			$access_token = $this->get_access_token();
			$api_endpoint = $this->api_endpoint . '/accounts';

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
			$order_tax_amount   = absint( strval( floatval( $order->get_total_tax() ) * 100 ) );
			$order_net_amount   = $order_total_amount - $order_tax_amount;
			$purchase_date      = strval( $order->get_date_paid() );
			$currency           = $order->get_currency();
			$transaction_id     = $order->get_transaction_id();

			$store_name  = get_bloginfo( 'name' );
			$store_email = get_bloginfo( 'admin_email' );

			$items = array();

			$counter = 0;
			foreach ( $order->get_items() as $order_item ) {
				$counter ++;
				$line_id                = $counter;
				$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
				$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
						true ) ) * 100 ) );

				$item = array(
					'id'           => 'item_' . $counter,
					'description'  => $order_item->get_name(),
					'quantity'     => $order_item->get_quantity(),
					'gross_amount' => $item_line_total_amount,
					'net_amount'   => $item_total_amount,
					'line_id'      => $line_id
				);
				array_push( $items, $item );
			}

			if ( count( $order->get_shipping_methods() ) > 0 ) {
				$counter ++;
				$line_id                = $counter;
				$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
				$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
				$item_line_total_amount = $item_total_amount + $item_tax_amount;

				$item = array(
					'id'           => 'shipping',
					'description'  => 'Shipping: ' . $order->get_shipping_method(),
					'quantity'     => 1,
					'gross_amount' => $item_line_total_amount,
					'net_amount'   => $item_total_amount,
					'line_id'      => $line_id
				);
				array_push( $items, $item );
			}

			$headers = array(
				'Content-type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $access_token
			);

			$payload = array(
				array(
					'store'          => array(
						'id'    => $store_name,
						'name'  => $store_name,
						'email' => $store_email,
					),
					'receipt_id'     => strval( $order_id ),
					'purchase_at'    => $purchase_date,
					'items'          => $items,
					'gross_amount'   => $order_total_amount,
					'net_amount'     => $order_net_amount,
					'currency'       => $currency,
					'order_number'   => strval( $order_id ),
					'transaction_id' => $transaction_id
				)
			);

			$response = wp_remote_post( $api_endpoint . '/' . $this->oid . '/receipts', array(
				'method'    => 'POST',
				'headers'   => $headers,
				'body'      => wp_json_encode( $payload ),
				'timeout'   => 90,
				'sslverify' => false
			) );

			// Retrieve the body's response if no errors found
			$response_body  = wp_remote_retrieve_body( $response );
			$response_array = json_decode( $response_body, true );

			if ( array_key_exists( 'receipts', $response_array ) &&
				 count( $response_array['receipts'] ) &&
				 array_key_exists( 'id', $response_array['receipts'][0] ) ) {

				$receipt_id = $response_array['receipts'][0]['id'];
				$order->update_meta_data( 'receipt_id', $receipt_id );
				$order->save();

				$note = 'Payment receipt created via Dintero. Receipt ID: ' . $receipt_id;
				$order->add_order_note( $note );

				return true;
			}

			return false;
		}
	}

	/**
	 * Complete order, add transaction ID and note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $note Payment note.
	 */
	private function payment_complete( $order, $transaction_id = '', $note = '' ) {
		$order->add_order_note( $note );
		$order->payment_complete( $transaction_id );
		wc_reduce_stock_levels( $order->get_id() );
		$this->create_receipt( $order );
	}

	/**
	 * Hold order and add note.
	 *
	 * @param WC_Order $order Order object.
	 * @param string $transaction_id Transaction ID.
	 * @param string $reason Reason why the payment is on hold.
	 */
	private function process_authorization( $order, $transaction_id = '', $reason = '' ) {
		$order->set_transaction_id( $transaction_id );
		$order->update_status( $this->get_option('default_order_status'), $reason );
	}

	/**
	 * Check order status when it is changed and call the right action
	 *
	 * @param int $order_id Order ID.
	 */
	public function check_status( $order_id, $previous_status, $current_status ) {

		
		if ( $current_status === $this->manual_capture_status ||
			 $current_status === $this->additional_manual_capture_status ) {

			$this->check_capture( $order_id );
		} else {
			if ( 'cancelled' === $current_status ||
				 $current_status === $this->additional_cancel_status ) {

				$this->cancel( $order_id );
			}

			if ( 'refunded' === $current_status ||
				 $current_status === $this->additional_refund_status ) {

				$this->process_refund( $order_id );
			}
		}
	}

	/**
	 * Cancel Order
	 *
	 * @param int $order_id Order ID.
	 */
	private function cancel( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order ) &&
			 $order instanceof WC_Order &&
			 $order->get_transaction_id() &&
			 'dintero-hp' === $order->get_payment_method() ) {


			$transaction_id = $order->get_transaction_id();
			$transaction    = $this->get_transaction( $transaction_id );
			
			$merchant_reference = absint( strval(trim($transaction['merchant_reference'])));
			$merchant_reference_2 = absint( strval(trim($transaction['merchant_reference_2'])));

			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );

			

			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}
			if ( ($merchant_reference === $order_id  || $merchant_reference_2 === $order_id ) &&
				 array_key_exists( 'status', $transaction ) &&
				 'AUTHORIZED' === $transaction['status'] ) {

				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token
				);

				$response = wp_remote_post( $api_endpoint . '/' . $transaction_id . '/void', array(
					'method'    => 'POST',
					'headers'   => $headers,
					'timeout'   => 90,
					'sslverify' => false
				) );

				// Retrieve the body's response if no errors found
				$response_body  = wp_remote_retrieve_body( $response );
				$response_array = json_decode( $response_body, true );

				if ( array_key_exists( 'status', $response_array ) &&
					 'AUTHORIZATION_VOIDED' === $response_array['status'] ) {

					$note = __( 'Transaction cancelled via Dintero. Transaction ID: ' ) . $transaction_id;
					$order->add_order_note( $note );
					wc_increase_stock_levels( $order_id );
				}
			}
		}
	}

	/**
	 * Process a refund if supported.
	 *
	 * @param int $order_id Order ID.
	 * @param float $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) &&
			 $order instanceof WC_Order &&
			 $order->get_transaction_id() &&
			 'dintero-hp' === $order->get_payment_method() ) {

			$transaction_id = $order->get_transaction_id();
			$transaction    = $this->get_transaction( $transaction_id );
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}

			$merchant_reference = absint( strval(trim($transaction['merchant_reference'])));
			$merchant_reference_2 = absint( strval(trim($transaction['merchant_reference_2'])));



			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );

			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}

			if (  ($merchant_reference === $order_id  || $merchant_reference_2 === $order_id ) &&
				 array_key_exists( 'status', $transaction ) &&
				 array_key_exists( 'amount', $transaction ) &&
				 ( 'CAPTURED' === $transaction['status'] || 'PARTIALLY_REFUNDED' === $transaction['status'] ) ) {

				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				if ( empty( $amount ) ) {
					$amount = $transaction['amount'];
				} else {
					$amount = ( floatval( $amount ) * 100 );
				}

				$amount = absint( strval( $amount ) );

				$items = array(
					array(
						'amount'  => $amount,
						'line_id' => '1'
					)
				);

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token
				);

				$payload = array(
					'amount' => $amount,
					'reason' => $reason,
					'items'  => $items
				);

				$response = wp_remote_post( $api_endpoint . '/' . $transaction_id . '/refund', array(
					'method'    => 'POST',
					'headers'   => $headers,
					'body'      => wp_json_encode( $payload ),
					'timeout'   => 90,
					'sslverify' => false
				) );

				// Retrieve the body's response if no errors found
				$response_body  = wp_remote_retrieve_body( $response );
				$response_array = json_decode( $response_body, true );

				if ( array_key_exists( 'status', $response_array ) ) {

					$note = '';
					if ( 'REFUNDED' === $response_array['status'] ) {
						$note = __( 'Payment refunded via Dintero. Transaction ID: ' ) . $transaction_id;
						wc_increase_stock_levels( $order_id );
					} elseif ( 'PARTIALLY_REFUNDED' === $response_array['status'] ) {
						$note = ( $amount / 100 ) . ' ' . __( $order->get_currency() . ' refunded via Dintero. Transaction ID: ' ) . $transaction_id;
					}

					$order->add_order_note( $note );

					return true;
				}

				return false;
			}
		}
	}

	/**
	 * Check if payment capture is possible when the order is changed from on-hold to complete or processing
	 *
	 * @param int $order_id Order ID.
	 */
	private function check_capture( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order ) &&
			 $order instanceof WC_Order &&
			 $order->get_transaction_id() &&
			 'dintero-hp' === $order->get_payment_method() ) {

			$transaction_id = $order->get_transaction_id();
			$transaction    = $this->get_transaction( $transaction_id );

			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}

			$merchant_reference = absint( strval(trim($transaction['merchant_reference'])));
			$merchant_reference_2 = absint( strval(trim($transaction['merchant_reference_2'])));


			if ( $merchant_reference === $order_id  || $merchant_reference_2 === $order_id ) {
				$this->capture( $order, $transaction );
			}
		}
	}

	/**
	 * Capture Payment.
	 */
	private function capture( $order, $transaction = null ) {
		if ( ! empty( $order ) &&
			 $order instanceof WC_Order &&
			 $order->get_transaction_id() ) {

			$order_id = $order->get_id();

			$transaction_id = $order->get_transaction_id();
			if ( empty( $transaction ) ) {
				$transaction = $this->get_transaction( $transaction_id );
			}

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
			$order_total_amount = 0;
			if ( array_key_exists( 'status', $transaction ) &&
				 array_key_exists( 'amount', $transaction ) &&
				 'AUTHORIZED' === $transaction['status'] &&
				 $transaction['amount'] >= $order_total_amount ) {
				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				$items = array();

				$counter = 0;
				foreach ( $order->get_items() as $order_item ) {
					$counter ++;
					$line_id                = strval( $counter );
					$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
					$item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
					$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
							true ) ) * 100 ) );
					$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
							2 ) * 100 ) : 0;



					if ( $order_item['variation_id'] ) {
						$product = wc_get_product( $order_item['variation_id'] );
					} else {
						$product = wc_get_product( $order_item['product_id'] );
					}
					if($product){
						$item_reference = $product->get_id();

						$productId =  substr( (string) $item_reference, 0, 64 );
						$item                   = array(
							'id'          => $productId,
							'description' => $order_item->get_name(),
							'quantity'    => $order_item->get_quantity(),
							'vat_amount'  => $item_tax_amount,
							'vat'         => $item_tax_percentage,
							'amount'      => $item_line_total_amount,
							'line_id'     => $productId
						);
						$order_total_amount+=$item_line_total_amount;
						array_push( $items, $item );
					}
					
				}

				if ( count( $order->get_shipping_methods() ) > 0 ) {
					$counter ++;
					$line_id                = strval( $counter );
					$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
					$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
					$item_line_total_amount = $item_total_amount + $item_tax_amount;
					$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
							2 ) * 100 ) : 0;
					
					
					$shipping_method = @array_shift($order->get_shipping_methods());
					$shipping_method_id = $shipping_method['method_id'].':'.$shipping_method['instance_id'];
					
					// exit;
					$item = array(
						'id'          => (string)$shipping_method_id,
						'description' => ', Shipping: ' . $order->get_shipping_method(),
						'quantity'    => 1,
						'vat_amount'  => $item_tax_amount,
						'vat'         => $item_tax_percentage,
						'amount'      => $item_line_total_amount,
						'line_id'     => 'shipping_method'
					);
					array_push( $items, $item );
					$order_total_amount+=$item_line_total_amount;
				}

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token
				);

				$payload = array(
					'amount'            => $order_total_amount,
					'capture_reference' => strval( $order_id ),
					'items'             => $items
				);

				$response = wp_remote_post( $api_endpoint . '/' . $transaction_id . '/capture', array(
					'method'    => 'POST',
					'headers'   => $headers,
					'body'      => wp_json_encode( $payload ),
					'timeout'   => 90,
					'sslverify' => false
				) );

				// Retrieve the body's response if no errors found
				$response_body  = wp_remote_retrieve_body( $response );
				$response_array = json_decode( $response_body, true );
				
				if ( array_key_exists( 'status', $response_array ) &&
					 'CAPTURED' === $response_array['status'] ) {

					$note = __( 'Payment captured via Dintero. Transaction ID: ' ) . $transaction_id;
					$this->payment_complete( $order, $transaction_id, $note );
				}
				
			}
		}
	}

	/**
	 * Notification handler. To Process order 
	 * Used only when its normal payment, callack recived from Dintero after successfull payment
	 */
	public function callback( $return_page = false ) {
		if ( ! empty( $_GET['transaction_id'] ) ) {
			$transaction_id = sanitize_text_field( wp_unslash( $_GET['transaction_id'] ));

			$transaction = $this->get_transaction( $transaction_id );
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}

			$transaction_order_id = $transaction['merchant_reference'];
			$order                = wc_get_order( $transaction_order_id );

			if ( ! empty( $order ) && $order instanceof WC_Order ) {
				$amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
				if ( array_key_exists( 'status', $transaction ) &&
					 array_key_exists( 'amount', $transaction ) &&
					 $transaction['amount'] === $amount ) {

					if ( 'AUTHORIZED' === $transaction['status'] ) {

						$hold_reason = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
						$this->process_authorization( $order, $transaction_id, $hold_reason );
					} elseif ( 'CAPTURED' === $transaction['status'] ) {

						$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
						$this->payment_complete( $order, $transaction_id, $note );
					}
				}
			}

			if ( ! $return_page ) {
				exit;
			}
		}
	}



	private function get_icon_footer() {
		return WCDHP()->checkout()->get_icon_footer();
	}

	private function get_icon_checkout() {
		return WCDHP()->checkout()->get_icon_checkout();
	}

	public function get_title() {
		global $theorder;

		$order = $theorder;

		$title = '';

		if ( ! empty( $order ) && $order instanceof WC_Order ) { // && $order->get_transaction_id()
			$transaction_id = $order->get_transaction_id();
			if ( $transaction_id ) {
				$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );

				$payment_product_type = isset( $transaction['payment_product_type'] ) ? $transaction['payment_product_type'] : '';
				switch ($payment_product_type) {
					case 'instabank.finance':
					case 'instabank.invoice':
						$title .= 'Instabank';
						break;
					case 'vipps':
						$title .= 'Vipps';
						break;
					case 'payex.creditcard':
						$title .= 'Card';
						break;
					case 'payex.swish':
						$title .= 'Swish';
						break;
					default:
						$title .= 'Other';
				}

				$title .= ' (via ' . $this->title . ')';
			} else {
				$title = $this->title;
			}			
		} else {
			$title = $this->title;
		}		

		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}
}
