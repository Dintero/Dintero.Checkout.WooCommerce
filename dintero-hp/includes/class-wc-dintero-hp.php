<?php
/**
 * Core Dintero WooCommerce Extension
 *
 * @class   WC_Dintero_HP
 * @package Dintero/Classes
 */

final class WC_Dintero_HP {

	/**
	 * Version.
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * The single instance of the class.
	 *
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woocommerce' ), '2.1' );
	}

	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '2.1' );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Register all of the hooks related to plugin functionality.
	 */
	private function init_hooks() {
		// Override template if Klarna Checkout page.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );
		add_action( 'wp_footer', array( $this, 'init_footer') );

		$express_enable = $this->setting()->get('express_enable');
		$embed_enable = $this->setting()->get('embed_enable');

		if ('yes' == $express_enable) { //express
			add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'add_custom_style' ), 1, 1 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'init_script' ));
		add_action( 'dhp_after_checkout_form', array( $this, 'init_checkout' ), 50);
		add_action( 'woocommerce_pay_order_after_submit', array( $this, 'init_pay' ), 50);

		add_action( 'woocommerce_cancelled_order', array( $this, 'cancel_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'check_status' ), 10, 3 );

		add_action( 'woocommerce_applied_coupon', array( $this, 'applied_coupon' ), 10, 3 ); 
		add_action( 'woocommerce_removed_coupon', array( $this, 'removed_coupon' ), 10, 3 ); 

		add_action( 'template_redirect', array( $this, 'check_thankyou' ), 10, 3 ); 
		add_action( 'dhp_payment_tab', array( $this, 'create_checkout_nav' ));

		//if ( 'no' == $express_enable || ( 'yes' == $express_enable && 'no' == $embed_enable ) ) {
			add_action( 'dhp_checkout_billing', array( $this, 'checkout_form_billing' ) );
			add_action( 'dhp_checkout_shipping', array( $this, 'checkout_form_shipping' ) );
		//}

		if ( 'yes' == $express_enable && 'no' == $embed_enable ) {
			//make billing fields not required in checkout
			add_filter( 'woocommerce_billing_fields', array( $this, 'wc_npr_filter_billing_fields' ), 10, 1 );

			//make shipping fields not required in checkout
			add_filter( 'woocommerce_shipping_fields', array( $this, 'wc_npr_filter_shipping_fields' ), 10, 1 );
		}

		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'check_dintero_shipping' ));
		add_action( 'dhp_business_customer', array( $this, 'check_dintero_shipping' ));		
	}

	public function wc_npr_filter_billing_fields( $address_fields ) {
		$address_fields['billing_first_name']['required'] = false;
		$address_fields['billing_last_name']['required'] = false;
		$address_fields['billing_company']['required'] = false;
		$address_fields['billing_address_1']['required'] = false;
		$address_fields['billing_address_2']['required'] = false;
		$address_fields['billing_country']['required'] = false;
		$address_fields['billing_city']['required'] = false;
		$address_fields['billing_state']['required'] = false;
		$address_fields['billing_postcode']['required'] = false;
		$address_fields['billing_phone']['required'] = false;
		$address_fields['billing_email']['required'] = false;

		return $address_fields;
	}

	public function wc_npr_filter_shipping_fields( $address_fields ) {
		$address_fields['shipping_first_name']['required'] = false;
		$address_fields['shipping_last_name']['required'] = false;
		$address_fields['shipping_company']['required'] = false;
		$address_fields['shipping_address_1']['required'] = false;
		$address_fields['shipping_address_2']['required'] = false;
		$address_fields['shipping_city']['required'] = false;
		$address_fields['shipping_state']['required'] = false;
		$address_fields['shipping_postcode']['required'] = false;
		$address_fields['shipping_country']['required'] = false;

		return $address_fields;
	}

	/**
	 * Define variable
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Define constant variable
	 */
	public function define_constants() {
		$this->define( 'DHP_ABSPATH', dirname( DHP_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Include classes
	 */
	public function includes() {
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-setting.php';
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-ajax.php';
		include_once DHP_ABSPATH . 'includes/class-wc-dintero-hp-checkout.php';
		include_once DHP_ABSPATH . 'includes/admin/class-wc-dintero-hp-admin-menus.php';
		include_once DHP_ABSPATH . 'includes/admin/class-wc-dintero-hp-admin-settings.php';
	}

	/**
	 * Include script and style
	 */
	public function init_script() {
		wp_enqueue_style( 'style', plugin_dir_url(__DIR__) . 'assets/css/style.css', array(), '1.0.07', 'all' );

		$handle = 'dhp-hp';
		$src = plugin_dir_url(__DIR__) . 'assets/js/dintero_hp.js';
		$deps = array( 'jquery' );
		$version = false;

		// Register the script
		wp_register_script( $handle, $src, $deps, $version, true );
		wp_enqueue_script( $handle);
	}

	/**
	 * Render checkout page
	 */
	public function init_checkout() {
		WCDHP()->checkout()->init_checkout();
	}

	/**
	 * Render payment page
	 */
	public function init_pay() {
		WCDHP()->checkout()->init_pay();
	}

	/**
	 * Render footer line
	 */
	public function init_footer() {
		echo( '<div class="dhp_footer_logo">' . wp_kses_post( WCDHP()->checkout()->get_icon_footer() ) . '</div>' );
	}

	/**
	 * Print out inline style
	 */
	public function add_custom_style() {
		$custom_css = '<style type="text/css">
	                #customer_details { display: none; }
					</style>';

		wp_kses_post( $custom_css );
	}

	/**
	 * Cancel the order by order id
	 */
	public function cancel_order( $order_id) {
		WCDHP()->checkout()->cancel($order_id);
	}

	/**
	 * Check order status by order id
	 */
	public function check_status( $order_id, $previous_status, $current_status) {
		WCDHP()->checkout()->check_status( $order_id, $previous_status, $current_status );
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', DHP_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( DHP_PLUGIN_FILE ) );
	}

	/**
	 * Get Checkout Class.
	 *
	 * @return WC_Dintero_HP_Checkout
	 */
	public function checkout() {
		return WC_Dintero_HP_Checkout::instance();
	}

	/**
	 * Get Setting Class.
	 *
	 * @return WC_Dintero_HP_Setting
	 */
	public function setting() {
		return WC_Dintero_HP_Setting::instance();
	}

	/**
	 * Apply coupon from order compare to cart
	 */
	public function applied_coupon() {
		$order_id = WC()->session->get( 'order_awaiting_payment' );

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$used_coupons = $order->get_used_coupons();
		
			$coupons = WC()->cart->get_coupons();

			foreach ($coupons as $coupon_code=>$cdata) {
				if (!in_array($coupon_code, $used_coupons)) {
					$order->apply_coupon($coupon_code);
				}
			}
			$order->calculate_totals();
		}
	}

	/**
	 * Remove coupon from order compare to cart
	 */
	public function removed_coupon() {
		$order_id = WC()->session->get( 'order_awaiting_payment' );
		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$used_coupons = $order->get_used_coupons();

			$coupons = WC()->cart->get_coupons();

			foreach ($used_coupons as $coupon_code) {
				if (!isset($coupons[$coupon_code])) {
					//remove
					$order->remove_coupon($coupon_code);
				}
			}
			$order->calculate_totals();
		}		
	}

	/**
	 * Check the order before display thank you page
	 */
	public function check_thankyou( $order_id ) {
		if ( isset( $_SERVER['SERVER_NAME'] ) && isset( $_SERVER['REQUEST_URI'] ) && isset( $_REQUEST['key'] ) ) {
			$url = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$template_name = strpos( $url, '/order-received/' ) === false ? '/view-order/' : '/order-received/';
			if ( strpos( $url, $template_name ) !== false ) {
				$start = strpos( $url, $template_name );
				$first_part = substr( $url, $start + strlen( $template_name ) );
				$order_id = substr( $first_part, 0, strpos( $first_part, '/' ) );

				$order = wc_get_order( $order_id );

				if ( ! empty( $order ) && $order instanceof WC_Order ) {
					$order_key = get_post_meta( $order_id, '_order_key', true );

					if ( sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) == $order_key ) {
						if ( isset( $_REQUEST['error'] ) && 'cancelled' == $_REQUEST['error'] ) {						
							$order_status = $order->get_status();
							if ( 'pending' == $order_status ) {
								//$order->update_status( 'failed' );

								$url = home_url() . '/my-account/view-order/' . $order_id . '/';
								wp_redirect ( $url );
								exit;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Override checkout form template if Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {
			// Fallback Order Received, used when WooCommerce checkout form submission fails.
			if ( 'checkout/thankyou.php' === $template_name ) {
				if ( isset( $_GET['dhp_checkout_error'] ) && 'true' === $_GET['dhp_checkout_error'] ) {
					$template = DHP_ABSPATH . 'templates/dhp-checkout-order-received.php';
				}
			}

			// Don't display template if we have a cart that doesn't needs payment.
			/*
			if ( apply_filters( 'dhp_check_if_needs_payment', true ) ) {
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
			}*/

			// Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$embed_enable = WCDHP()->setting()->get('embed_enable');
				$express_enable = WCDHP()->setting()->get('express_enable');

				if ( 'yes' == $express_enable ) {
					if ( 'yes' == $embed_enable ) {
						return DHP_ABSPATH . 'templates/dhp-checkout-embed-express.php';
					} else {
						return DHP_ABSPATH . 'templates/dhp-checkout-noembed-express.php';
					}
				} else {				
					return DHP_ABSPATH . 'templates/dhp-checkout.php';
				}
			}

			// Pay.
			if ( 'checkout/form-pay.php' === $template_name ) {
				return DHP_ABSPATH . 'templates/dhp-pay.php';
			}			
		}

		// Order detail customer info
		if ( 'order/order-details-customer.php' === $template_name ) {
			return DHP_ABSPATH . 'templates/order/order-details-customer.php';
		}

		return $template;
	}

	/**
	 * Output the billing form.
	 */
	public function checkout_form_billing() {
		WC()->checkout()->checkout_form_billing();
	}

	/**
	 * Output the shipping form.
	 */
	public function checkout_form_shipping() {
		WC()->checkout()->checkout_form_shipping();
	}

	public function create_checkout_nav() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$enabled_gateways = array();

		if ( $gateways ) {
			foreach ( $gateways as $gateway ) {
				if ( 'yes' == $gateway->enabled ) {
					$enabled_gateways[] = $gateway;
				}
			}
		}

		if ( count( $enabled_gateways ) > 1) {
			$tab_w = 100 / count( $enabled_gateways );

			echo( '<div class="dhp-checkout-tab">' );
			foreach ( $enabled_gateways as $gateway ) {
				$title = $gateway->settings['title'] ? $gateway->settings['title'] : '';
				$id = $gateway->id ? $gateway->id : '';
				$rel = 'dintero-hp' == $id ? 'dhp-embed' : 'dhp-others';
				
				if ( 'dintero-hp' == $id ) {
					echo( '<div id="' . esc_attr( $id ) . '" rel="' . esc_attr ( $rel ) . '" style="width:' . esc_attr( $tab_w ) . '%;background-image: url(\'' . wp_kses_post( WCDHP()->checkout()->get_icon_tab() ) . '\');"></div>' );
				} else {
					echo( '<div id="' . esc_attr( $id ) . '" rel="' . esc_attr ( $rel ) . '" style="width:' . esc_attr( $tab_w ) . '%;">' . esc_html ( $title ) . '</div>' );
				}
			}
			echo( '</div>' );
		}
	}

	public function check_dintero_shipping( $order ) {
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$payment_method = $order->get_payment_method();

			if ( 'dintero-hp' == $payment_method ) { // && $order->get_transaction_id()
				$transaction_id = $order->get_transaction_id();
				if ( !$transaction_id && isset($_GET['transaction_id']) ) {
					$transaction_id = sanitize_text_field( $_GET['transaction_id'] );
				}

				$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );
				if ( isset ( $transaction['shipping_address'] ) ) {
					$shipping_addr = $transaction['shipping_address'];
					$organization_number = isset ( $shipping_addr['organization_number'] ) ? $shipping_addr['organization_number'] : '';
					$business_name = isset ( $shipping_addr['business_name'] ) ? $shipping_addr['business_name'] : '';
					$co_address = isset ( $shipping_addr['co_address'] ) ? $shipping_addr['co_address'] : '';
					$customer_reference = isset ( $shipping_addr['customer_reference'] ) ? $shipping_addr['customer_reference'] : '';
					$cost_center = isset ( $shipping_addr['cost_center'] ) ? $shipping_addr['cost_center'] : '';

					if ( $organization_number || $customer_reference || $cost_center ) {
						if ( $organization_number ) {
							echo ( '<p><strong>Organization Number:</strong><br />' . esc_attr( $organization_number ) . '</p>' );
						}
						if ( $organization_number ) {
							echo ( '<p><strong>Business Name:</strong><br />' . esc_attr( $business_name ) . '</p>' );
						}
						if ( $co_address ) {
							echo ( '<p><strong>C/O:</strong><br />' . esc_attr( $co_address ) . '</p>' );
						}
						if ( $customer_reference ) {
							echo ( '<p><strong>Reference:</strong><br />' . esc_attr( $customer_reference ) . '</p>' );
						}
						if ( $cost_center ) {
							echo ( '<p><strong>Cost Center:</strong><br />' . esc_attr( $cost_center ) . '</p>' );
						}
					}
				}
			}
		}
	}
}
