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
class WC_Gateway_Dintero_HP extends WC_Payment_Gateway
{
	/** @var null | Dintero_HP_Adapter */
	static $_adapter = null;

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
			add_action( 'woocommerce_thankyou', array( $this, 'callback' ));
		}
		add_action( 'woocommerce_order_status_changed', array( $this, 'check_status' ), 10, 3 );
		add_action( 'woocommerce_cancelled_order', array( $this, 'cancel_order' ) );
	}

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
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_url  = 'https://checkout.dintero.com/v1/branding/profiles/' . $this->profile_id . '/variant/colors/color/cecece/width/' . $this->checkout_logo_width . '/dintero_left_frame.svg';
		//$icon_html = '<img src="' . esc_attr( $icon_url ) . '" alt="Dintero Logo" />';
		$icon_html = $this->get_icon_checkout();
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
			'express_product' => array(
				'title'       => __( 'Enable Express Button (Product Page):' ),
				'label'       => __( 'Enable Checkout Express (Product Page)' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Checkout Express on Product page' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'shipping_method_in_iframe' => array(
				'title'       => __( 'Shipping methods in iframe:' ),
				'label'       => __( 'Display Shipping methods in Dintero iframe.' ),
				'type'        => 'checkbox',
				'description' => __( 'Shipping methods will be pushed in the iframe, Recomended to use only when shipping method are of type flat i.e. Price not dependent on Shipping postcode' ),
				'default'     => 'no',

			),
			'express_button_type'                        => array(
				'title'       => __( 'Express Button Image Type:' ),
				'label'       => __( 'Express Button Image Type' ),
				'type'        => 'select',
				'description' => __( 'Choose type of button shown for express in Cart ' ),
				'options' => array(
				        '0' => 'Dark',
				        '1' => 'Light'
				        //etc
				 ),
				'desc_tip'    => true,
			),
			// 'express_rewards'                        => array(
			// 	'title'       => __( 'Enable Rewards:' ),
			// 	'label'       => __( 'Enable Checkout Express Rewards' ),
			// 	'type'        => 'checkbox',
			// 	'description' => __( 'Enable or disable Dintero Checkout Express Rewards' ),
			// 	'default'     => 'yes',
			// 	'desc_tip'    => true,
			// ),
			// 'express_shopping_price'                        => array(
			// 	'title'       => __( 'Show Shopping Price:' ),
			// 	'label'       => __( 'Show/Hide Shipping Price' ),
			// 	'type'        => 'checkbox',
			// 	'description' => __( 'Show or hide shopping price' ),
			// 	'default'     => 'yes',
			// 	'desc_tip'    => true,
			// ),
            'express_customer_types_title' => array(
                'title'       => __( 'Customer types:' ),
                'type'        => 'title',
                'description' => ''
            ),
            'express_customer_types'                        => array(
                'title'       => __( 'Express Customer Types:' ),
                'label'       => __( 'Express Customer Types' ),
                'type'        => 'select',
                'description' => __( 'Choose available customer types used in Checkout Express ' ),
                'options' => array(
                    'both' => 'Consumers and businesses',
                    'b2c' => 'Consumers only',
                    'b2b' => 'Businesses only'
                ),
                'default' => 'both',
                'desc_tip'    => true,
            ),
            'express_allow_no_shipping_title' => array(
                'title'       => __( 'Allow no shipping:' ),
                'type'        => 'title',
                'description' => ''
            ),
			'express_allow_no_shipping' => array(
				'title'       => __( 'Allow no shipping:' ),
				'label'       => __( 'Allow Checkout Express without shipping' ),
				'type'        => 'checkbox',
				'description' => __( 'Allow orders without shipping' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'branding_title'                => array(
				'title'       => __( 'Branding:' ),
				'type'        => 'title',
				'description' => ''
			),
			'branding_enable'                        => array(
				'title'       => __( 'Add branding image in footer:' ),
				'label'       => __( 'Check to add dintero image in footer' ),
				'type'        => 'checkbox',
				'description' => __( 'Check to add dintero image in foote' ),
				'default'     => 'yes',
				'desc_tip'    => true,
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
		if ( $this->separate_sales_tax ) {
			$shipping_tax_amount = 0;
		} else {
			$shiping_total_amount        = $this->get_shipping_amount();
			$shipping_total_exluding_tax = $shiping_total_amount / ( 1 + ( $this->get_shipping_tax_rate() / 10000 ) );
			$shipping_tax_amount         = $shiping_total_amount - $shipping_total_exluding_tax;
		}
		return round( $shipping_tax_amount );
	}

	private function callbackUrlForNonExpress() {
		$base_url = WC()->api_request_url(strtolower(get_class( $this )));
		$query = parse_url($base_url, PHP_URL_QUERY);

		if ($query) {
			return $base_url . '&delay_callback=10';
		}
		return $base_url . '?delay_callback=10';
	}

	/**
	 * Creating checkout session and requesting payment page URL
	 * Executed when the checkout button is pressed
	 *
	 * @param $order
	 * @param bool $isExpress
	 * @return mixed|string
	 */
	private function get_payment_page_url( $order , $isExpress = false)
	{
		if ( empty( $order ) || !($order instanceof WC_Order)) {
			return '';
		}

		$order_id     = $order->get_id();
		$embed_enable = WCDHP()->setting()->get('embed_enable');
		$express_customer_types = WCDHP()->setting()->get('express_customer_types');

		$return_url   = $this->get_return_url( $order );
		$callback_url = $isExpress
			? home_url() . '?dhp-ajax=dhp_update_ord' : $this->callbackUrlForNonExpress();

		$order_tax_amount   = absint( strval( floatval( $order->get_total_tax() ) * 100 ) );
		$items = array();
		$total_amount = $counter = 0;
		foreach ( $order->get_items() as $order_item ) {
			$counter++;
			$line_id                = strval( $counter );
			$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
			$item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
			$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
					true ) ) * 100 ) );
			$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
					2 ) * 100 ) : 0;
			$item = array(
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

		$express_option = array();

		$counter ++;
		$line_id                = strval( $counter );
		$item_total_amount      = absint( round($order->get_shipping_total() * 100, 2));
		$item_tax_amount        = absint( round( $order->get_shipping_tax() * 100, 2 ));
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
		$fee_counter = 0;
		foreach( $order->get_items('fee') as $item_id => $item_fee ){
			$fee_counter++;
			$line_id                = 'fee_'.$fee_counter;
			$item_total_amount      = absint( strval( floatval( $item_fee->get_total() ) * 100 ) );
			$item_tax_amount        = absint( strval( floatval( $item_fee->get_total_tax() ) * 100 ) );
			$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $item_fee,
					true ) ) * 100 ) );
			$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
					2 ) * 100 ) : 0;
			$item = array(
				'id'          => 'fee_'.$fee_counter,
				'description' => $item_fee->get_name(),
				'quantity'    => $item_fee->get_quantity(),
				'vat_amount'  => $item_tax_amount,
				'vat'         => $item_tax_percentage,
				'amount'      => $item_line_total_amount,
				'line_id'     => $line_id
			);
			array_push( $items, $item );
			$total_amount += $item_line_total_amount;
		}
		$order_total_amount = $total_amount;
		$hasShippingOptions = count($order->get_shipping_methods()) > 0;
		if ($isExpress) {
			// This is as far as I know the only place that creates sessions for express button-clicks,
			// and it doesn't create sessions for any other type of payment
			$ship_callback_url = home_url() . '?dhp-ajax=dhp_shipping_options&express_button=true';
			$customer_types = array();
			if ($express_customer_types == 'b2c') {
				array_push($customer_types, 'b2c');
			} else if ($express_customer_types == 'b2b') {
				array_push($customer_types, 'b2b');
			} else {
				array_push($customer_types, 'b2b', 'b2c');
			}
			$dintero_shipping_options = array();
			if ($hasShippingOptions) {
				$dintero_shipping_options = array(
					0 => array(
						'id' => 'shipping_express',
						'line_id' => $line_id,
						'amount' => intval($item_line_total_amount),
						'vat_amount' => intval($item_tax_amount),
						'vat' => $item_tax_percentage,
						'title' => 'Shipping: ' . $order->get_shipping_method(),
						'description' => '',
						'delivery_method' => 'delivery',
						'operator' => '',
						'operator_product_id' => '',
					)
				);
			}
			$express_option = array(
				// Temporarily disable shipping_address callback,
				// as order update after payment doesn't handle
				// that the session was actually updated with new shipping options
				// TODO: Add back when we properly support updating shipping during express checkout
//				'shipping_address_callback_url' => $ship_callback_url,
				'customer_types'=>$customer_types,
				'shipping_options' => $dintero_shipping_options,
			);
		}

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
				'billing_address'   => array(
					'first_name'   => $order->get_billing_first_name(),
					'last_name'    => $order->get_billing_last_name(),
					'address_line' => $order->get_billing_address_1(),
					'postal_code'  => $order->get_billing_postcode(),
					'postal_place' => $order->get_billing_city(),
					'country'      => $order->get_billing_country()
				),
				'items'              => $items,
				'store'				=> array(
					'id' => Dintero_HP_Helper::instance()->url_to_store_id(get_home_url()),
				)
			),
			'profile_id' => $this->profile_id,
			'metadata' => array(
				'woo_customer_id' => WC()->session->get_customer_id(),
			)
		);

		if ( $isExpress ) {
			$payload['express'] = $express_option;
		}

		if ($isExpress && !$hasShippingOptions) {
			$payload["order"]["shipping_option"]	= array(
				'id' => 'shipping_express',
				'line_id' => 'shipping_method',
				'amount' => 0,
				'title' => 'Shipping: none',
				'description' => '',
				'delivery_method' => 'none',
				'operator' => ''
			);
		}
		$response = self::_adapter()->init_session($payload);
		return isset($response['url']) ? $response['url'] : '';
	}

	/**
	 * We're processing the payment here.
	 */
	public function process_payment( $order_id, $isExpress = false ) {
		$embed_enable = WCDHP()->setting()->get('embed_enable');
		if ($embed_enable == 'yes' && !$isExpress  && !isset($_GET['pay_for_order'])) { // If its an Iframe
			$redirect_url = $this->process_payment_handler( $order_id , true);
			WC()->session->__unset('dintero_wc_order_id');
			WC()->session->__unset('dintero_shipping_line_id');

			if ( $redirect_url ) {
				wc_empty_cart();
				return array(
					'result'   => 'success',
					'redirect' =>  $redirect_url
				);
			}

			return array('result' => 'error');
		}

		$order = wc_get_order( $order_id );
		WC()->session->__unset('dintero_wc_order_id');
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$payment_page_url = $this->get_payment_page_url( $order, $isExpress );

			return array(
				'result'   => 'success',
				'redirect' => $payment_page_url
			);
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
		if ( ! array_key_exists('transaction_id', $dintero_order_session_detail)) {
			return false;
		}
		$dintero_order_transaction_id = $dintero_order_session_detail['transaction_id'];
		if ( $order_id && $dintero_order_transaction_id ) {

			// Set WC order transaction ID.
			update_post_meta( $order_id, '_wc_dintero_session_id', sanitize_key(WC()->session->get( 'dintero_wc_order_id' ) ) );

			update_post_meta( $order_id, '_transaction_id', sanitize_key( $dintero_order_transaction_id ) );

			// Update Shipping Line Id
			update_post_meta($order_id,'_wc_dintero_shipping_line_id',sanitize_key(sanitize_text_field( $dintero_order_session_detail['order']['shipping_option']['line_id']  )));

			// Update the Dintero order with new confirmation merchant reference.  TO DO
			$transaction = WCDHP()->checkout()->update_transaction($dintero_order_transaction_id, $order_id);
			if (is_wp_error($transaction)) {
				$updated_transaction = self::_adapter()->get_transaction($dintero_order_transaction_id);
				if (isset($updated_transaction['merchant_reference_2']) && !empty($updated_transaction['merchant_reference_2'])) {
					$fail_reason = __( 'Duplicate order of order ' ) . $updated_transaction['merchant_reference_2'] . '.';
					self::failed_order( $order, $dintero_order_transaction_id, $fail_reason );
				} else {
					$fail_reason = __( 'Failed updating transaction with order_id. This means that it will be harder to find the order in the settlements. ' ) . '.';
					$order->add_order_note( $fail_reason );

					$update_response_retry = WCDHP()->checkout()->update_transaction($dintero_order_transaction_id, $order_id);

					if (is_wp_error($update_response_retry)) {
						$fail_reason = __( 'Failed updating transaction with order_id. Will stop trying ' ) . '.';
						$order->add_order_note( $fail_reason );
					} else {
						$order->add_order_note( 'Order id was updated after retry');
					}
				}
			}

			$methodName = 'Dintero - '.$transaction['payment_product'];
			$order->set_payment_method_title($methodName);


			if($transaction){

				if ( $transaction['status']  == 'AUTHORIZED') {

					$hold_reason = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $dintero_order_transaction_id;
					self::process_authorization( $order, $dintero_order_transaction_id, $hold_reason );

				} elseif ( 'CAPTURED' === $transaction['status'] ) {

					$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $dintero_order_transaction_id;
					self::payment_complete( $order, $dintero_order_transaction_id, $note );
				}elseif('ON_HOLD' === $transaction['status'] ){
					$hold_reason = __( 'The payment is put on on-hold for manual review by payment provider. The review will usually be finished within 5 minutes, and the status will be updated. Transaction ID: ' ) . $dintero_order_transaction_id;
					self::on_hold_order( $order, $dintero_order_transaction_id, $hold_reason );
				}elseif('FAILED' === $transaction['status'] ){
					$hold_reason = __( 'The payment is not approved. Transaction ID: ' ) . $dintero_order_transaction_id;
					self::failed_order( $order, $dintero_order_transaction_id, $hold_reason );
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
		$sessionDetail = self::_adapter()->get_session($sessionId);
		return $sessionDetail;
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
	}

	/**
	 * Process order and add note.
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

	/**
	 * Check order status when it is changed and call the right action
	 *
	 * @param int $order_id Order ID.
	 */
	public function check_status( $order_id, $previous_status, $current_status ) {


		if ( $current_status === $this->manual_capture_status ||
			 $current_status === $this->additional_manual_capture_status ) {

			$this->capture( $order_id );
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
	 * Cancel the order by order id
	 */
	public function cancel_order( $order_id) {
		$this->cancel($order_id);
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
			$transaction = self::_adapter()->get_transaction($transaction_id);
			$merchant_reference = absint( strval(trim($transaction['merchant_reference'])));
			$merchant_reference_2 = absint( strval(trim($transaction['merchant_reference_2'])));

			if ( ($merchant_reference === $order_id  || $merchant_reference_2 === $order_id ) &&
				 array_key_exists( 'status', $transaction ) &&
				 'AUTHORIZED' === $transaction['status'] ) {

				$response_array = self::_adapter()->void_transaction($transaction_id);

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
			$transaction = self::_adapter()->get_transaction($transaction_id);

			if ( array_key_exists( 'status', $transaction ) &&
				 array_key_exists( 'amount', $transaction ) &&
				 ( 'CAPTURED' === $transaction['status'] ||
				 'PARTIALLY_CAPTURED' === $transaction['status'] ||
				 'PARTIALLY_REFUNDED' === $transaction['status'] ) ) {

				if ( empty( $amount ) ) {
					$amount = $transaction['amount'];
				} else {
					$amount = ( floatval( $amount ) * 100 );
				}

				$amount = absint( strval( $amount ) );

				$payload = array(
					'amount' => $amount,
					'reason' => $reason
				);

				$response_array = self::_adapter()->refund_transaction($transaction_id, $payload);

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
				} else {
					$note = __('Payment refund failed at Dintero. Transaction ID: ') . $transaction_id;
					$order->add_order_note($note);
				}

				return false;
			}
		}
	}

	/**
	 * Capture Payment.
	 */
	private function capture( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order ) &&
			 $order instanceof WC_Order &&
			 'dintero-hp' === $order->get_payment_method() ) {
			$transaction_id = $order->get_transaction_id();
			if (empty($transaction_id)) {
				$order->add_order_note('Payment capture failed at Dintero because the order lacks transaction_id. Contact integration@dintero.com with order information. Changing status to on-hold.');
				$order->set_status( 'on-hold' );
				$order->save();
				return false;
			}


			$transaction_id = $order->get_transaction_id();
			$transaction = self::_adapter()->get_transaction($transaction_id);

			if (isset($transaction['error'])) {
				$order->add_order_note(__('Could not capture transaction: ' . $transaction['message'] . '. Changing status to on-hold.'));
				$order->set_status( 'on-hold' );
				$order->save();
				$order->save_meta_data();
				return false;
			}

			$transaction_status = $this->extract('status', $transaction);

			if ($transaction_status === 'CAPTURED') {
				$order->add_order_note('Payment captured via Dintero, already captured from before.');
				$order->save_meta_data();
				return false;
			};

			if ($transaction_status === 'REFUNDED' ||
				$transaction_status === 'PARTIALLY_REFUNDED') {
				$order->add_order_note(__(
					sprintf(
						'Dintero transaction has already been captured, and now has status (%s).',
						$transaction_status
					)
				));
				$order->save_meta_data();
				return false;
			};

			if ($transaction_status !== 'AUTHORIZED') {
				$order->add_order_note(__(
					sprintf(
						'Could not capture transaction: Transaction status is wrong (%s). Changing status to on-hold.',
						$transaction_status
					)
				));
				$order->set_status( 'on-hold' );
				$order->save();
				$order->save_meta_data();
				return false;
			}

			$capture_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );

			if ($this->is_redirect_payment($transaction)) {
				$items = $this->get_items_to_capture_redirect($order);
			} else {
				$items = $this->get_items_to_capture_embed($order);
			}

			$transaction_amount = $transaction['amount'];

			// We experience inexplicable differences of 1 cent (or smallest unit of the currency)
			// To lessen the impact, we will allow difference of one cent (or smallest unit of the currency),
			// and will adjust the capture call accordingly
			if ($capture_amount === $transaction_amount + 1 ||
				$capture_amount === $transaction_amount - 1) {
				$capture_amount = $transaction_amount;
				$items = array();
			}

			$payload = array(
				'amount'            => $capture_amount,
				'capture_reference' => strval( $order_id ),
			);
			if (count($items) > 0) {
				$payload['items'] = $items;
			}

			$response_array = self::_adapter()->capture_transaction($transaction_id, $payload);

			if ( array_key_exists( 'status', $response_array ) &&
				('CAPTURED' === $response_array['status'] || 'PARTIALLY_CAPTURED' === $response_array['status'])) {

				$note = sprintf('Payment captured via Dintero. Transaction ID: %s. Dintero status: %s', $transaction_id, $response_array['status']);
				$this->payment_complete( $order, $transaction_id, $note );
			} else {
				$error_message = 'unknown';
				if (array_key_exists('error', $response_array) && isset($response_array['error']['message'])) {
					$error_message = $response_array['error']['message'];
				}
				$note = sprintf('Payment capture failed at Dintero. Transaction ID: %s. Error message: %s. Changing status to on-hold.', $transaction_id, $error_message);
				$order->add_order_note( $note );
				$order->set_status('on-hold');
				$order->save();
			}
		}
	}

	private function is_redirect_payment($transaction) {
		if (!isset($transaction['items'])) {
			return false;
		}
		foreach ( $transaction['items'] as $item ) {
			if ($this->str_starts_with($item['id'], 'item_')) {
				return true;
			}
		}
		return false;
	}

	private function str_starts_with ( $haystack, $needle ) {
		return strpos( $haystack , $needle ) === 0;
	}

	private function get_items_to_capture_embed($order) {
		$items = array();

		$counter = 0;
		foreach ( $order->get_items() as $order_item ) {
			$counter ++;
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
			array_push( $items, $item );
		}

		if ( count( $order->get_shipping_methods() ) > 0 ) {
			$counter ++;
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
			$shippingLineId = get_post_meta($order->get_id(),'_wc_dintero_shipping_line_id');

			if($shippingLineId && count($shippingLineId)>0){
				if($shippingLineId[0] != ''){
					$item['line_id'] = $shippingLineId[0];
				}

			}

			array_push( $items, $item );
		}

		$items = array_merge($items, $this->get_fee_items($order));
		return $items;
	}

	private function get_items_to_capture_redirect($order) {
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
		}

		if ( count( $order->get_shipping_methods() ) > 0 ) {
			$counter ++;
			$line_id                = strval( $counter );
			$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
			$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
			$item_line_total_amount = $item_total_amount + $item_tax_amount;
			$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
					2 ) * 100 ) : 0;

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
		}

		$items = array_merge($items, $this->get_fee_items($order));

		return $items;
	}

	private function get_fee_items($order) {
		$fee_items = array();
		$fee_counter = 0;
		foreach( $order->get_items('fee') as $item_id => $item_fee ){
			$fee_counter++;
			$line_id                = 'fee_'.$fee_counter;
			$item_total_amount      = absint( strval( floatval( $item_fee->get_total() ) * 100 ) );
			$item_tax_amount        = absint( strval( floatval( $item_fee->get_total_tax() ) * 100 ) );
			$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $item_fee,
					true ) ) * 100 ) );
			$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
					2 ) * 100 ) : 0;
			$item = array(
				'id'          => 'fee_'.$fee_counter,
				'description' => $item_fee->get_name(),
				'quantity'    => $item_fee->get_quantity(),
				'vat_amount'  => $item_tax_amount,
				'vat'         => $item_tax_percentage,
				'amount'      => $item_line_total_amount,
				'line_id'     => $line_id
			);
			array_push( $fee_items, $item );
		}
		return $fee_items;
	}

	/**
	 * Extracting value from an array
	 *
	 * @param string $key
	 * @param array $data
	 * @param null $default
	 * @return mixed|null
	 */
	private function extract($key, $data, $default = null)
	{
		return !empty($data[$key]) ? $data[$key] : $default;
	}

	/**
	 * Notification handler. To Process order
	 * Used only when its normal payment, callack recived from Dintero after successfull payment
	 */
	public function callback( $return_page = false ) {
		if ( ! empty( $_GET['transaction_id'] ) ) {
			$transaction_id = sanitize_text_field( wp_unslash( $_GET['transaction_id'] ));

			// TODO: Calls with invalid id might come from here
			$transaction = self::_adapter()->get_transaction($transaction_id);
			if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
				return false;
			}

			$transaction_order_id = $transaction['merchant_reference'];
			$order                = wc_get_order( $transaction_order_id );

			if ( ! empty( $order ) && $order instanceof WC_Order ) {
				$amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
				if ( array_key_exists( 'status', $transaction ) &&
					 array_key_exists( 'amount', $transaction )) {
					$transaction_status = $transaction['status'];
					$transaction_amount_too_low = $transaction['amount'] < $amount - 1;
					if ($transaction_amount_too_low) {
						$manual_description = 'Order was authorized with a different amount, so manual handling is required. When captured in Dintero Backoffice, the order can be marked as completed.';
						if ('CAPTURED' === $transaction_status) {
							$manual_description = 'Order was captured with a different amount. Figure out what the cause is and mark the order as completed when done.';
						}
						$hold_reason = sprintf(
							'<p>%s<p>Transaction amount: %s.</p><p>Order amount: %s.</p><p>Transaction ID: %s. </p>',
							$manual_description,
							$transaction['amount'],
							$amount,
							$transaction_id
						);
						$this->on_hold_order( $order, $transaction_id, $hold_reason );
					} else if ( 'AUTHORIZED' === $transaction_status ) {
						$hold_reason = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
						$this->process_authorization( $order, $transaction_id, $hold_reason );
					} elseif ( 'CAPTURED' === $transaction_status ) {
						$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
						$this->payment_complete( $order, $transaction_id, $note );
					} elseif ('ON_HOLD' === $transaction_status ){
						$hold_reason = __( 'The payment is put on on-hold for manual review by payment provider. The review will usually be finished within 5 minutes, and the status will be updated. Transaction ID: ' ) . $transaction_id;
						$this->on_hold_order( $order, $transaction_id, $hold_reason );
					} elseif ('FAILED' === $transaction_status ){
						$hold_reason = __( 'The payment is not approved. Transaction ID: ' ) . $transaction_id;
						$this->failed_order( $order, $transaction_id, $hold_reason );
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
					case 'collector.invoice':
					case 'collector.invoice_b2b':
						$title .= 'Collector Invoice';
						break;
					case 'collector.installment':
						$title .= 'Collector Installment';
						break;
					case 'vipps':
						$title .= 'Vipps';
						break;
					case 'payex.creditcard':
						$title .= 'Card';
						break;
					case 'payex.swish':
					case 'swish.swish':
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
