<?php
/**
 * Dintero WooCommerce Extension Checkout Handlers.
 *
 * @class   WC_Dintero_HP_Checkout
 * @package Dintero/Classes
 */

defined( 'ABSPATH' ) || exit;

require_once WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-checkout.php';

class WC_Dintero_HP_Checkout extends WC_Checkout
{

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Dintero_HP_Checkout|null
	 */
	protected static $instance = null;

	private $id = 'dintero-hp';
	private $enabled;
	private $test_mode;
	private $payment_method = 'dintero-hp';
	private $account_id;
	private $client_id;
	private $client_secret;
	private $profile_id;

	private $api_endpoint = 'https://api.dintero.com/v1';
	private $checkout_endpoint = 'https://checkout.dintero.com/v1';
	private $oid;

	private $checkout_logo_width;
	private $default_order_status;
	private $manual_capture_status;
	private $additional_manual_capture_status;
	private $additional_cancel_status;
	private $additional_refund_status;

	public $separate_sales_tax = false;

	// Added By Ritesh
	public $order_lines = array();
	public $order_lines_item_id = array();
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->enabled                          = $this->get_option( 'enabled' );
		$this->test_mode                        = 'yes' === $this->get_option( 'test_mode' );
		$this->callback_verification            = 'yes' === $this->get_option( 'callback_verification' );
		$this->account_id                       = $this->get_option( 'account_id' ); //'11112468'
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
		//$this->api_endpoint                     = 'https://api.dintero.com/v1';
		//$this->checkout_endpoint                = 'https://checkout.dintero.com/v1';
		$environment_character                  = $this->test_mode ? 'T' : 'P';
		$this->oid                              = $environment_character . $this->account_id;

		if ( $this->callback_verification ) {
			//Enable callback server-to-server verification
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'callback' ) );
		} else {
			//Use thank you page to check for transactions, only if callbacks are unavailable
			add_action( 'woocommerce_thankyou', array( $this, 'callback' ), 1, 1 );
		}
	}

	/**
	 * Gets the main WC_Dintero_HP_Checkout Instance.
	 *
	 * @since 2.1
	 * @static
	 * @return WC_Dintero_HP_Checkout Main instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woocommerce' ), '2.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '2.1' );
	}

	/**
	 * Retrieving the option
	 */
	private function get_option( $key ) {
		return WCDHP()->setting()->get( $key );
	}

	public function callback(){

	}
	/**
	 * Render the checkout
	 */
	public function init_checkout() {
		WC()->session->set( 'order_awaiting_payment', null );

		//check if parameters are ready
		if ($this->account_id && $this->client_id && $this->client_secret) {
			$embed_enable = WCDHP()->setting()->get('embed_enable');
			$express_enable = WCDHP()->setting()->get('express_enable');


			if ( 'yes' == $embed_enable ) {
				$this->start_embed( false, true );
			} else {
				$this->insertPaymentTypeFlag(false);

				$this->writeContainerScript();
			}


			$handle = 'dhp-checkout';
			$src = plugin_dir_url(__DIR__) . 'assets/js/checkout.js';
			$deps = array( 'jquery' );
			$version = false;

			// Register the script
			wp_register_script( $handle, $src, $deps, $version, true );

			$params = array(
						'embed_checkout_nonce' => wp_create_nonce( 'embed-checkout' ),
						'express_checkout_nonce' => wp_create_nonce( 'express-checkout' ),
					);

			$name = str_replace( '-', '_', $handle ) . '_params';

			wp_localize_script( $handle, $name, $params);

			wp_enqueue_script( $handle);
		}

		$this->check_payments();
	}

	public function init_pay() {
		WC()->session->set( 'order_awaiting_payment', null );

		//check if parameters are ready
		if ($this->account_id && $this->client_id && $this->client_secret) {
			$embed_enable = WCDHP()->setting()->get('embed_enable');
			$express_enable = WCDHP()->setting()->get('express_enable');


			if ( 'yes' == $embed_enable) {
				$this->start_embed( false, true );
			} else {
				$this->insertPaymentTypeFlag(false);

				$this->writeContainerScript();
			}


			$handle = 'dhp-checkout';
			$src = plugin_dir_url(__DIR__) . 'assets/js/pay.js';
			$deps = array( 'jquery' );
			$version = false;

			// Register the script
			wp_register_script( $handle, $src, $deps, $version, true );

			$params = array(
						'embed_pay_nonce' => wp_create_nonce( 'embed-pay' ),
						'express_pay_nonce' => wp_create_nonce( 'express-pay' ),
					);

			$name = str_replace( '-', '_', $handle ) . '_params';

			wp_localize_script( $handle, $name, $params);

			wp_enqueue_script( $handle);
		}

		$this->check_payments( true );
	}

	private function check_payments( $pay = false ) {
		//check available payment gateways
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$enabled_gateways = array();

		if ( $gateways ) {
			foreach ($gateways as $gateway) {
				if ( 'yes' == $gateway->enabled ) {
					$enabled_gateways[] = $gateway;
				}
			}
		}

		if ( count( $enabled_gateways ) <= 0 ) {
			if ( false === $pay ) {
				echo( "<script type=\"text/javascript\">
				jQuery('#dhp-order-review').addClass('no-payments');
				</script>" );

			} else {
				echo( "<script type=\"text/javascript\">
				jQuery('#order_review').addClass('no-payments');
				</script>" );
			}
		}
	}

	private function start_embed( $express = false, $pay_for_order = false ) {

		if ( true == $pay_for_order || ( false == $pay_for_order && !WC()->cart->is_empty() ) ) {
			$order_id = false;
			$errors      = new WP_Error();
			$posted_data = $this->get_data();

			$posted_data['payment_method'] = $this->payment_method;



			$user_id = get_current_user_id();

			// $posted_data['billing_country'] = WC()->customer->get_billing_country();
			// $posted_data['shipping_country'] = WC()->customer->get_shipping_country();
			// $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			// $posted_data['shipping_method'] = $chosen_shipping_methods;

			// // Update session for customer and totals.
			// $this->update_session( $posted_data );
			$embed_enable = WCDHP()->setting()->get('embed_enable');
			if ( !$express && $embed_enable != 'yes') {
				// Validate posted data and cart items before proceeding.
				if ( true == $pay_for_order ) {
					$this->validate_pay_hp( $posted_data, $errors );
				} else {
					$this->validate_checkout_hp( $posted_data, $errors );
				}
			} else {
				if ( ! empty( $_REQUEST['terms-field'] ) && empty( $_REQUEST['terms'] ) ) {
					$errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ) );
				}
			}

			foreach ( $errors->get_error_messages() as $message ) {
				wc_add_notice( $message, 'error' );
			}

			if ( 0 === wc_notice_count( 'error' ) ) {
				if ( true == $pay_for_order ) {
					$order_id = 0;

					if ( isset ( $_REQUEST['key'] ) ) {
						// Pay for existing order.
						$order_key = sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

						$host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field ( $_SERVER['HTTP_HOST'] ) : '';
						$uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field ( $_SERVER['REQUEST_URI'] ) : '';

						$url = 'http://' . $host . $uri;

						$referer = sanitize_text_field( wp_unslash( $url ) );

						$a1 = strpos($referer, 'order-pay/');
						if (false !== $a1) {
							$start_pos = $a1+10;
							$a2 = strpos($referer, '?', $start_pos);
							if (false !== $a1) {
								$order_id  = absint( substr($referer, $start_pos, $a2-$start_pos) );
							}
						}
					}
				} else {
					if ( empty( $posted_data['woocommerce_checkout_update_totals'] ) ) {
						$this->process_customer( $posted_data );
						if ( !$express ) {
							$order_id = $this->create_order_hp( $posted_data );
						}
					}
				}

				if ( $order_id && !$express) {
					$order    = wc_get_order( $order_id );

					if ( is_wp_error( $order_id ) ) {
						throw new Exception( $order_id->get_error_message() );
					}

					if ( ! $order ) {
						throw new Exception( __( 'Unable to create order.', 'woocommerce' ) );
					}

					do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );

					WC()->session->set( 'order_awaiting_payment', $order_id );

					$this->process_payment_embed( $order_id, $express, $pay_for_order );
				}
				if(WCDHP()->setting()->get('embed_enable') == 'yes'){

					$this->process_payment_embed_express($express, $pay_for_order );
				}
			}
		}
	}

	private function insertPaymentTypeFlag( $express) {
		$express = $express ? 1 : 0;
		echo( '<input type="hidden" id="dhp-exp-ele" value="' . esc_attr( $express ) . '" />' );
	}

	/**
	 * Get gateway icon.
	 * https://checkout.dintero.com/v1/branding/profiles/' . $this->profile_id . '/variant/colors/color/cecece/width/' . $this->checkout_logo_width . '/dintero_left_frame.svg
	 *
	 * @return string
	 */
	public function get_icon( $type = '', $width = '', $img = true ) {
		if ($this->profile_id) {
			$logo_w = WCDHP()->setting()->get('checkout_logo_width');

			if ( !$width ) {
				$width = WCDHP()->setting()->get('checkout_logo_width');
				if ( !$width ) {
					$width = WCDHP()->setting()->getDefault('checkout_logo_width');
				}
			}

			$w_str = $width && is_numeric($width) ? $width : $logo_w;

			$template = 'dintero_left_frame'; //or dintero_top_frame

			if ( 'checkout' == $type ) {
				$logos = 'visa_mastercard_vipps_swish_instabank';
				$variant = 'colors'; //or mono
				$color = 'ffffff';
				//$icon_url = 'https://checkout.dintero.com/v1/branding/logos/' . $logos . '/variant/' . $variant . '/color/' . $color . '/width/' . $w_str . '/' . $template . '.svg';
				$template = 'dintero_left_frame';

				$icon_url = 'https://checkout.dintero.com/v1/branding/profiles/' . $this->profile_id . '/variant/' . $variant . '/color/' . $color . '/width/' . $w_str . '/' . $template . '.svg';
			} else {
				$template = 'dintero_top_frame';
				$icon_url = 'https://backoffice.dintero.com/api/checkout/v1/branding/profiles/' . $this->profile_id . '/type/colors/width/' . $w_str . '/' . $template . '.svg';
			}

			if ( true == $img ) {
				$icon_html = '<img src="' . esc_attr( $icon_url ) . '" alt="Dintero Logo" />';

				return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
			} else {
				return $icon_url;
			}
		}
	}

	/**
	 * Get icon to show on the footer
	 *
	 * @return string url
	 */
	public function get_icon_footer( $width = '' ) {
		$icon_url = trim(WCDHP()->setting()->get('branding_footer_url'));
		if ('' == $icon_url) {
			$icon_url = $this->get_icon( $width );
		} else {
			if ( !$width ) {
				$width = WCDHP()->setting()->get('checkout_logo_width');
				if ( !$width ) {
					$width = WCDHP()->setting()->getDefault('checkout_logo_width');
				}
			}

			$w_str = $width && is_numeric($width) ? ' width="' . $width . '"' : '';
			$icon_url = '<img src="' . esc_attr( $icon_url ) . '" alt="Dintero Logo"' . $w_str . ' />';
		}

		return $icon_url;
	}

	/**
	 * Get icon to show on the checkout page
	 *
	 * @return string url
	 */
	public function get_icon_checkout( $width = '' ) {
		$icon_url = trim(WCDHP()->setting()->get('branding_checkout_url'));
		if ('' == $icon_url) {
			$icon_url = $this->get_icon( 'checkout', $width );
		} else {
			if ( !$width ) {
				$width = WCDHP()->setting()->get('checkout_logo_width');
				if ( !$width ) {
					$width = WCDHP()->setting()->getDefault('checkout_logo_width');
				}
			}

			$w_str = $width && is_numeric($width) ? ' width="' . $width . '"' : '';
			$icon_url = '<img src="' . esc_attr( $icon_url ) . '" alt="Dintero Logo" />';
		}

		return $icon_url;
	}

	/**
	 * Get icon to show on the checkout page
	 *
	 * @return string url
	 */
	public function get_icon_tab( $width = '' ) {
		//$logos = 'visa_mastercard_vipps_swish_instabank';
		//$variant = 'colors'; //or mono
		//$color = 'ffffff';
		//$template = 'dintero_top_frame';

		//$icon_url = 'https://checkout.dintero.com/v1/branding/logos/' . $logos . '/variant/' . $variant . '/color/' . $color . '/width/' . $w_str . '/' . $template . '.svg';

		//$icon_url = 'https://checkout.dintero.com/v1/branding/profiles/' . $this->profile_id . '/variant/' . $variant . '/color/' . $color . '/width/' . $w_str . '/' . $template . '.svg';

		$icon_url = trim(WCDHP()->setting()->get('branding_checkout_url'));
		if ('' == $icon_url) {
			$icon_url = $this->get_icon( 'checkout', $width, false );
		}

		return $icon_url;
	}

	/**
	 * Process the checkout after the confirm order button is pressed.
	 *
	 * @throws Exception When validation fails.
	 */
	public function process_checkout( $express = false) {
		try {
			if ( isset( $_REQUEST['woocommerce-process-checkout-nonce'] ) || isset( $_REQUEST['_wpnonce'] ) ) {

				$c = sanitize_text_field( wp_unslash( $_REQUEST['woocommerce-process-checkout-nonce'] ) );
				$n = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

				$nonce_value = wc_get_var( $c, wc_get_var( $n, '' ) ); // @codingStandardsIgnoreLine.

				if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
					WC()->session->set( 'refresh_totals', true );
					throw new Exception( __( 'We were unable to process your order, please try again.', 'woocommerce' ) );
				}

				wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
				wc_set_time_limit( 0 );

				do_action( 'woocommerce_before_checkout_process' );

				if ( WC()->cart->is_empty() ) {
					/* translators: %s: shop cart url */
					throw new Exception( sprintf( __( 'Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'woocommerce' ), esc_url( wc_get_page_permalink( 'shop' ) ) ) );
				}

				do_action( 'woocommerce_checkout_process' );

				$errors      = new WP_Error();
				$posted_data = $this->get_data();

				$posted_data['payment_method'] = $this->payment_method;

				// Update session for customer and totals.
				$this->update_session( $posted_data );

				if (!$express) {
					// Validate posted data and cart items before proceeding.
					$this->validate_checkout_hp( $posted_data, $errors );
				} else {
					if ( ! empty( $_REQUEST['terms-field'] ) && empty( $_REQUEST['terms'] ) ) {
						$errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ) );
					}

					$base_country = WC()->countries->get_base_country();
					$posted_data['shipping_country'] = $base_country;

					WC()->customer->set_shipping_country($base_country);
				}

				WC()->cart->calculate_shipping();
				WC()->cart->calculate_totals();

				foreach ( $errors->get_error_messages() as $message ) {
					wc_add_notice( $message, 'error' );
				}

				if ( empty( $posted_data['woocommerce_checkout_update_totals'] ) && 0 === wc_notice_count( 'error' ) ) {
					$this->process_customer( $posted_data );
					$order_id = $this->create_order_hp( $posted_data );
					$order    = wc_get_order( $order_id );

					if ( is_wp_error( $order_id ) ) {
						throw new Exception( $order_id->get_error_message() );
					}

					if ( ! $order ) {
						throw new Exception( __( 'Unable to create order.', 'woocommerce' ) );
					}

					do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );

					$this->process_payment( $order_id, $express );
				}
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
		$this->send_ajax_failure_response();
	}

	/**
	 * Process the pay form.
	 *
	 * @throws Exception On payment error.
	 */
	public function pay_action( $express = false ) {
		global $wp;

		if ( isset( $_REQUEST['woocommerce_pay'], $_REQUEST['key'] ) && ( isset( $_REQUEST['woocommerce-pay-nonce'] ) || isset( $_REQUEST['_wpnonce'] ) ) ) {
			wc_nocache_headers();

			$p = sanitize_text_field( wp_unslash( $_REQUEST['woocommerce-pay-nonce'] ) );
			$n = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

			$nonce_value = wc_get_var( $p, wc_get_var( $n, '' ) ); // @codingStandardsIgnoreLine.

			if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-pay' ) ) {
				wc_add_notice( __( 'We were unable to process your order, please try again.', 'woocommerce' ), 'error' );
				return;
			}

			$errors      = new WP_Error();
			$posted_data = $this->get_data();

			$posted_data['payment_method'] = $this->payment_method;

			// Update session for customer and totals.
			//$this->update_session( $posted_data );
			/*
			if (!$express) {
				// Validate posted data and cart items before proceeding.
				$this->validate_pay_hp( $posted_data, $errors );
			} else {

			}
			*/
			if ( ! empty( $_REQUEST['terms-field'] ) && empty( $_REQUEST['terms'] ) ) {
				$errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ) );
			}

			foreach ( $errors->get_error_messages() as $message ) {
				wc_add_notice( $message, 'error' );
			}

			if ( 0 === wc_notice_count( 'error' ) ) {
				ob_start();

				// Pay for existing order.
				$order_key = sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				$referer = isset( $_REQUEST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wp_http_referer'] ) ) : '';
				$order_id = 0;
				$a1 = strpos($referer, 'order-pay/');
				if (false !== $a1) {
					$start_pos = $a1+10;
					$a2 = strpos($referer, '?', $start_pos);
					if (false !== $a1) {
						$order_id  = absint( substr($referer, $start_pos, $a2-$start_pos) );
					}
				}

				if ($order_id) {
					$order     = wc_get_order( $order_id );

					if ( $order_id === $order->get_id() && hash_equals( $order->get_order_key(), $order_key ) && $order->needs_payment() ) {

						do_action( 'woocommerce_before_pay_action', $order );

						try {
							// Update payment method.
							$payment_method     = $this->payment_method;

							if ( ! $payment_method ) {
								throw new Exception( __( 'Invalid payment method.', 'woocommerce' ) );
							}

							$order->set_payment_method( $payment_method );
							$order->save();
							/*
							if ( !$express ) {
								$a = $posted_data;

								$first_name = isset($a['first_name']) ? $a['first_name'] : '';
								$last_name = isset($a['last_name']) ? $a['last_name'] : '';
								$company = isset($a['company']) ? $a['company'] : '';
								$addr1 = isset($a['address_line']) ? $a['address_line'] : '';
								$addr2 = isset($a['address_line_2']) ? $a['address_line_2'] : '';
								$city = isset($a['city']) ? $a['city'] : '';
								$state = isset($a['postal_place']) ? $a['postal_place'] : '';
								$postal = isset($a['postal_code']) ? $a['postal_code'] : '';
								$country = isset($a['country']) ? $a['country'] : '';
								$email = isset($a['email']) ? $a['email'] : '';
								$phone_number = isset($a['phone_number']) ? $a['phone_number'] : '';

								update_post_meta( $order_id, '_shipping_first_name', $first_name );
								update_post_meta( $order_id, '_shipping_last_name', $last_name );
								update_post_meta( $order_id, '_shipping_company', $company );
								update_post_meta( $order_id, '_shipping_address_1', $addr1 );
								update_post_meta( $order_id, '_shipping_address_2', $addr2 );
								update_post_meta( $order_id, '_shipping_city', $city );
								update_post_meta( $order_id, '_shipping_state', $state );
								update_post_meta( $order_id, '_shipping_postcode', $postal );
								update_post_meta( $order_id, '_shipping_country', $country );
								//update_post_meta( $order_id, '_shipping_email', $email );
								//update_post_meta( $order_id, '_shipping_phone', $phone_number );

								update_post_meta( $order_id, '_billing_first_name', $first_name );
								update_post_meta( $order_id, '_billing_last_name', $last_name );
								update_post_meta( $order_id, '_billing_company', $company );
								update_post_meta( $order_id, '_billing_address_1', $addr1 );
								update_post_meta( $order_id, '_billing_address_2', $addr2 );
								update_post_meta( $order_id, '_billing_city', $city );
								update_post_meta( $order_id, '_billing_state', $state );
								update_post_meta( $order_id, '_billing_postcode', $postal );
								update_post_meta( $order_id, '_billing_country', $country );
								update_post_meta( $order_id, '_billing_email', $email );
								update_post_meta( $order_id, '_billing_phone', $phone_number );
							}*/

							$this->process_payment( $order_id, $express, true );
						} catch ( Exception $e ) {
							wc_add_notice( $e->getMessage(), 'error' );
						}

						do_action( 'woocommerce_after_pay_action', $order );

					}
				} else {
					//echo( 'Invalid order id' );
					wc_add_notice( 'Invalid order id', 'error' );
				}
			}
		} else {
			wc_add_notice( __( 'We were unable to process your order, please try again.', 'woocommerce' ), 'error' );
		}

		$this->send_ajax_failure_response();
	}

	/**
	 * Create new order
	 *
	 * @return order id or exception when failed
	 */
	private function create_order_hp( $data ) {
		// Give plugins the opportunity to create an order themselves.
		$order_id = apply_filters( 'woocommerce_create_order', null, $this );
		if ( $order_id ) {
			return $order_id;
		}

		try {
			$order_id           = absint( WC()->session->get( 'order_awaiting_payment' ) );
			$cart_hash          = WC()->cart->get_cart_hash();
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$order              = $order_id ? wc_get_order( $order_id ) : null;

			/**
			 * If there is an order pending payment, we can resume it here so
			 * long as it has not changed. If the order has changed, i.e.
			 * different items or cost, create a new order. We use a hash to
			 * detect changes which is based on cart items + order total.
			 */
			if ( $order && $order->has_cart_hash( $cart_hash ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
				// Action for 3rd parties.
				do_action( 'woocommerce_resume_order', $order_id );

				// Remove all items - we will re-add them later.
				$order->remove_order_items();
			} else {
				$order = wc_create_order( array( 'status' => 'pending' ) );
				//$order = new WC_Order();
			}

			$fields_prefix = array(
				'shipping' => true,
				'billing'  => true,
			);

			$shipping_fields = array(
				'shipping_method' => true,
				'shipping_total'  => true,
				'shipping_tax'    => true,
			);
			foreach ( $data as $key => $value ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
					// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
				} elseif ( isset( $fields_prefix[ current( explode( '_', $key ) ) ] ) ) {
					if ( ! isset( $shipping_fields[ $key ] ) ) {
						$order->update_meta_data( '_' . $key, $value );
					}
				}
			}

			$order->set_created_via( 'checkout' );
			$order->set_cart_hash( $cart_hash );
			$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
			$order_vat_exempt = WC()->cart->get_customer()->get_is_vat_exempt() ? 'yes' : 'no';
			$order->add_meta_data( 'is_vat_exempt', $order_vat_exempt );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
			$order->set_customer_user_agent( wc_get_user_agent() );
			$order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
			//$order->set_payment_method( isset( $available_gateways[ $data['payment_method'] ] ) ? $available_gateways[ $data['payment_method'] ] : $data['payment_method'] );
			$order->set_payment_method( $this->payment_method );
			$order->set_shipping_total( WC()->cart->get_shipping_total() );
			$order->set_discount_total( WC()->cart->get_discount_total() );
			$order->set_discount_tax( WC()->cart->get_discount_tax() );
			$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
			$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
			$order->set_total( WC()->cart->get_total( 'edit' ) );
			$this->create_order_line_items( $order, WC()->cart );
			$this->create_order_fee_lines( $order, WC()->cart );

			$shipping_method = WC()->session->get( 'chosen_shipping_methods' );
			if (!is_array($shipping_method)) {
				if (isset($data['shipping_method']) && is_array($data['shipping_method'])) {
					$shipping_method = $data['shipping_method'];
				}
			}

			$this->create_order_shipping_lines( $order, $shipping_method, WC()->shipping()->get_packages() );
			$this->create_order_tax_lines( $order, WC()->cart );
			$this->create_order_coupon_lines( $order, WC()->cart );

			/**
			 * Action hook to adjust order before save.
			 *
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order', $order, $data );

			// Save the order.
			$order_id = $order->save();

			do_action( 'woocommerce_checkout_update_order_meta', $order_id, $data );

			return $order_id;
		} catch ( Exception $e ) {
			return new WP_Error( 'checkout-error', $e->getMessage() );
		}
	}

	/**
	 * Validate checkout process
	 *
	 * Display error notice on invalid
	 */
	private function validate_checkout_hp( &$data, &$errors ) {
		try {
			$this->validate_posted_data( $data, $errors );
			$this->check_cart_items();

			if ( isset( $_REQUEST['woocommerce-process-checkout-nonce'] ) ) {
				$c = sanitize_text_field( wp_unslash( $_REQUEST['woocommerce-process-checkout-nonce'] ) );

				$nonce_value = wc_get_var( $c, '' );

				if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
					$errors->add( 'general', __( 'We were unable to process your order, please try again (2).', 'woocommerce' ) );
				} else {

					if ( empty( $data['woocommerce_checkout_update_totals'] ) && empty( $data['terms'] ) && ! empty( $_POST['terms-field'] ) ) { // WPCS: input var ok, CSRF ok.
						$errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ) );
					}

					if ( WC()->cart->needs_shipping() ) {
						$shipping_country = WC()->customer->get_shipping_country();

						if ( empty( $shipping_country ) ) {
							$errors->add( 'shipping', __( 'Please enter an address to continue.', 'woocommerce' ) );
						} elseif ( ! in_array( WC()->customer->get_shipping_country(), array_keys( WC()->countries->get_shipping_countries() ), true ) ) {
							/* translators: %s: shipping location */
							$errors->add( 'shipping', sprintf( __( 'Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce' ), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country() ) );
						} else {
							$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

							foreach ( WC()->shipping()->get_packages() as $i => $package ) {
								if ( ! isset( $chosen_shipping_methods[ $i ], $package['rates'][ $chosen_shipping_methods[ $i ] ] ) ) {
									$errors->add( 'shipping', __( 'No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce' ) );
								}
							}
						}
					}

					do_action( 'woocommerce_after_checkout_validation', $data, $errors );
				}
			} else {
				$errors->add( 'general', __( 'We were unable to process your order, please try again (1).', 'woocommerce' ) );
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Validate pay form data
	 *
	 * Display error notice on invalid
	 */
	private function validate_pay_hp( &$data, &$errors ) {
		try {
			$this->validate_posted_data( $data, $errors );
			$this->check_cart_items();

			if ( isset( $_REQUEST['woocommerce-pay-nonce'] ) ) {
				$c = sanitize_text_field( wp_unslash( $_REQUEST['woocommerce-pay-nonce'] ) );

				$nonce_value = wc_get_var( $c, '' );

				if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-pay' ) ) {
					$errors->add( 'general', __( 'We were unable to process your order, please try again (2).', 'woocommerce' ) );
				} else {

					if ( empty( $data['woocommerce_checkout_update_totals'] ) && empty( $data['terms'] ) && ! empty( $_POST['terms-field'] ) ) { // WPCS: input var ok, CSRF ok.
						$errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ) );
					}

					if ( WC()->cart->needs_shipping() ) {
						$shipping_country = WC()->customer->get_shipping_country();

						if ( empty( $shipping_country ) ) {
							$errors->add( 'shipping', __( 'Please enter an address to continue.', 'woocommerce' ) );
						} elseif ( ! in_array( WC()->customer->get_shipping_country(), array_keys( WC()->countries->get_shipping_countries() ), true ) ) {
							/* translators: %s: shipping location */
							$errors->add( 'shipping', sprintf( __( 'Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce' ), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country() ) );
						} else {
							$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

							foreach ( WC()->shipping()->get_packages() as $i => $package ) {
								if ( ! isset( $chosen_shipping_methods[ $i ], $package['rates'][ $chosen_shipping_methods[ $i ] ] ) ) {
									$errors->add( 'shipping', __( 'No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce' ) );
								}
							}
						}
					}

					do_action( 'woocommerce_after_checkout_validation', $data, $errors );
				}
			} else {
				$errors->add( 'general', __( 'We were unable to process your order, please try again (1).', 'woocommerce' ) );
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Get posted data from checkout form
	 *
	 * @return array of data
	 */
	private function get_data() {


		$skipped = array();
		$data    = array(
			'terms'                              => (int) isset( $_REQUEST['terms'] ), // WPCS: input var ok, CSRF ok.
			'createaccount'                      => (int) ! empty( $_REQUEST['createaccount'] ), // WPCS: input var ok, CSRF ok.
			'payment_method'                     => isset( $_REQUEST['payment_method'] ) ? wc_clean( wp_unslash( $_REQUEST['payment_method'] ) ) : '', // WPCS: input var ok, CSRF ok.
			'shipping_method'                    => isset( $_REQUEST['shipping_method'] ) ? wc_clean( wp_unslash( $_REQUEST['shipping_method'] ) ) : '', // WPCS: input var ok, CSRF ok.
			'ship_to_different_address'          => ! empty( $_REQUEST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only(), // WPCS: input var ok, CSRF ok.
			'woocommerce_checkout_update_totals' => isset( $_REQUEST['woocommerce_checkout_update_totals'] ), // WPCS: input var ok, CSRF ok.
		);
		foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
			if ( $this->maybe_skip_fieldset( $fieldset_key, $data ) ) {
				$skipped[] = $fieldset_key;
				continue;
			}

			foreach ( $fieldset as $key => $field ) {
				$type = sanitize_title( isset( $field['type'] ) ? $field['type'] : 'text' );

				switch ( $type ) {
					case 'checkbox':
						$value = isset( $_REQUEST[ $key ] ) ? 1 : ''; // WPCS: input var ok, CSRF ok.
						break;
					case 'multiselect':
						$value = isset( $_REQUEST[ $key ] ) ? implode( ', ', wc_clean( wp_unslash( $_REQUEST[ $key ] ) ) ) : ''; // WPCS: input var ok, CSRF ok.
						break;
					case 'textarea':
						$value = isset( $_REQUEST[ $key ] ) ? wc_sanitize_textarea( wp_unslash( $_REQUEST[ $key ] ) ) : ''; // WPCS: input var ok, CSRF ok.
						break;
					case 'password':
						$value = isset( $_REQUEST[ $key ] ) ? wp_unslash( $_REQUEST[ $key ] ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
						break;
					default:
						$value = isset( $_REQUEST[ $key ] ) ? wc_clean( wp_unslash( $_REQUEST[ $key ] ) ) : ''; // WPCS: input var ok, CSRF ok.
						break;
				}

				$data[ $key ] = apply_filters( 'woocommerce_process_checkout_' . $type . '_field', apply_filters( 'woocommerce_process_checkout_field_' . $key, $value ) );
			}
		}

		if ( in_array( 'shipping', $skipped, true ) && ( WC()->cart->needs_shipping_address() || wc_ship_to_billing_address_only() ) ) {
			foreach ( $this->get_checkout_fields( 'shipping' ) as $key => $field ) {
				$data[ $key ] = isset( $data[ 'billing_' . substr( $key, 9 ) ] ) ? $data[ 'billing_' . substr( $key, 9 ) ] : '';
			}
		}

		// BW compatibility.
		$this->legacy_posted_data = $data;

		return apply_filters( 'woocommerce_checkout_posted_data', $data );
	}

	/**
	 * Requesting access token
	 */
	private function get_access_token() {
		$api_endpoint = $this->api_endpoint . '/accounts';

		$headers = array(
			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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
	 * Gets Dintero order from WC_Session
	 * Added by Ritesh | MooGruppen
	 * @return string
	 */
	public function get_order_id_from_session() {
		return WC()->session->get( 'dintero_wc_order_id' );
	}

	/**
	 * Saves Dintero order ID to WooCommerce session.
	 * Added by Ritesh | MooGruppen
	 * @param string $order_id Dintero order ID.
	 */
	public function save_order_id_to_session( $order_id ) {

		WC()->session->set( 'dintero_wc_order_id', $order_id );
	}



	private function process_payment_embed_express ($express = true, $pay_for_order = false ){

		$shipping_methods = WC()->session->get('chosen_shipping_methods');
		$allow_no_shipping = 'yes' == WCDHP()->setting()->get('express_allow_no_shipping');
		if(!$allow_no_shipping && (!$shipping_methods || !$shipping_methods[0])){

			$postCode = WC()->checkout()->get_value( 'shipping_postcode' );
			if(!$postCode){

				$oninput="this.value = this.value.replace(/[^0-9.]/g, ''); this.value = this.value.replace(/(\..*)\./g, '$1');";

				$html = "<div class='dhp-noshipping'> <h2>Express Checkout</h2><label>Vennligst skriv inn postnummeret for å komme i gang: </label><div class='shipping-postcode-element'><div class='post-code-wrapper'><div class='inner-wrapper-input-postcode'><label class='post-code-lbl'>Postcode</label><div class='post-input'><input type='text' onkeypress='validate(event)' minlength='4' maxlength='4' id='shipping_post_code' name='shipping_postcode'></div></div></div><div class='btn-wrap'><button id='save_post_code' class='' type='button' onclick='savePostCode()'>Neste</button></div></div>";


				echo( "<script type=\"text/javascript\">

						document.addEventListener('DOMContentLoaded', function(event) { 
						  //run plugin code
							jQuery('.checkout-box-dintero ').html(\"".$html. "\");
						});
						

						function savePostCode(){


							var postCode = jQuery('#shipping_post_code').val();
							if(postCode.length!=4){
								return false;
							}

							jQuery('.loader').css('display','block');
		            	 	jQuery('.loader').css('opacity','1');
							var url = \"".home_url().'?dhp-ajax=update_shipping_postcode'."\";
							var data = {
								        action: 'update_shipping_postcode',
								        post_code: postCode,
								       
								    };			    
							jQuery.ajax({
								type:		'POST',
								url:		url,
								data:		data,
								
								success:	function( result ) {
												jQuery( 'body' ).trigger( 'update_checkout' ); 
											}
								});
						}
						function validate(evt) {
						  var theEvent = evt || window.event;

						  // Handle paste
						  if (theEvent.type === 'paste') {
						      key = event.clipboardData.getData('text/plain');
						  } else {
						  // Handle key press
						      var key = theEvent.keyCode || theEvent.which;
						      key = String.fromCharCode(key);
						  }
						  var regex = /[0-9]|\./;
						  if( !regex.test(key) ) {
						    theEvent.returnValue = false;
						    if(theEvent.preventDefault) theEvent.preventDefault();
						  }
						}
					</script>
					");
			}else{
				 $html = 'Skriv inn adressen din for å vise fraktalternativer. ';
				 echo( "<script type=\"text/javascript\">
				 		document.addEventListener('DOMContentLoaded', function(event) { 
						  //run plugin code
							jQuery('.checkout-box-dintero ').html(\"".$html. "\");
						});
						
						
					</script>
					");
			}

			return;

		}
		$sessionExpired = false;
		$order_id = $this->get_order_id_from_session();

		if ( $order_id ) {
			//$order_id = 'P11112230.4XD6XFszoMbkg3ZQY7KaZ8';
			$sessionDetails = $this->get_dintero_session($order_id);


			if(isset($sessionDetails['error'])){
				$sessionExpired = true;

			}else{
				$expires_at = $sessionDetails['expires_at'];

				$date = date('d/M/Y:H:i:s', time()); // CURRENT TIME

				$strTime = strtotime($expires_at);
				$sessionExpiresAt = date('d/M/Y:H:i:s', $strTime); // Session Expires At
				foreach($sessionDetails['events'] as $event){
					if($event['name'] == 'AUTH_CALLBACK_SENT' || $event['name'] == 'FAILED' || $event['name'] == 'COMPLETED' || $event['name'] == 'DECLINED' || $event['name'] == 'CANCELLED'){
						$sessionExpired = true;
					}
				}
				$current = new DateTime();
				$expireAt = new DateTime($expires_at);


				if($current >= $expireAt){ // Check If it expired
					$sessionExpired = true;
				}else{
					// Get Dintero order. create array and load Ongoing session
					$results  = array(
								'result' => 1,
								'id' => $order_id,
								'url' => $this->checkout_endpoint.'/view/'.$order_id
							);
				}
			}



		}

		if($sessionExpired || !$order_id){
		    // Create New session
		    $results = $this->get_iframe();
		    if(isset($results['id'])){
		        $this->save_order_id_to_session($results['id']);
		    }

		}

		$result = isset($results['result']) ? $results['result'] : 0;
		$msg = isset($results['msg']) ? $results['msg'] : '';
		$url = isset($results['url']) ? $results['url'] : '';
		$id = isset($results['id']) ? $results['id'] : '';
		$isShippingInIframe = 'yes' == WCDHP()->setting()->get('shipping_method_in_iframe');
		if(!$isShippingInIframe){
			$isShippingInIframe = 0;
		}

		if (1 == $result && $url) {
			echo ('<span class="spinner"></span>');
			echo( '<div id="dhp-embed">' );
			$this->insertPaymentTypeFlag(true);

			echo( '<div id="dintero-checkout-iframe"></div>' );
			echo( '</div>' );


			$container_id =  'dhp-wrapper';

			echo( "<script type=\"text/javascript\">
					var dintero_url = \"".home_url().'?dhp-ajax=update_session'."\";
					var emb = document.getElementById('dhp-embed');
					var order_review = document.getElementById('" . wp_kses_post ( $container_id ) . "');
					order_review.appendChild(emb);
					var checkoutSessionData;
					var checkoutSession;
					var isShippingInIframe = ".$isShippingInIframe.";
					var homeUrl = \"" .home_url(). "\";
					document.addEventListener('DOMContentLoaded', function(event) { 
					  	jQuery( document ).on( 'updated_checkout', function(){
					  		if(checkoutSession && isShippingInIframe == 0){
					  			// If shipping in Iframe then we dont need the lock session 
								checkoutSession.lockSession();
							}
						});

						jQuery('input:radio[name=name]:checked').change(function () {
				            dinteroEvent = 1;
				        });
						const container = document.getElementById(\"dintero-checkout-iframe\");
					    if(typeof(dintero) != \"undefined\"){
					    	dintero
					        .embed({
					            container,
					            sid: \"" . esc_attr( $id ) . "\",
					            onPaymentAuthorized : function(event,checkout){
					            	 
					            	 	jQuery('.loader').css('display','block');
					            	 	jQuery('.loader').css('opacity','1');
					            		

					            		var response = jQuery( 'form.checkout' ).submit();  

					            		
					            	},
					            onSessionCancel: (event, SessionCancel) => {
							        console.log('href', event.href);
							        checkout.destroy();
							    },
							    onSessionLocked: (event, checkout) => {
							        console.log('pay_lock_id', event.pay_lock_id);
							        console.log(checkout);
							        //checkout.refreshSession();
							        
							        var data = {
									        action: 'create_order',
									        post_data: checkoutSessionData,
									        iframe_src: checkout.iframe.src
									    };
				            		var url = \"".home_url().'?dhp-ajax=update_session'."\";
									    
									jQuery.ajax({
										type:		'POST',
										url:		url,
										data:		data,
										
										success:	function( result ) {
														console.log('Success Called');
														if(result.redirect_url){
															window.location.href = result.redirect_url ;
														}else{
															checkout.refreshSession();
														}
														
														
													}
									});
							    },
							    onPayment: function(event, checkout) {
					               
					                jQuery('.loader').css('display','block');
				            	 	jQuery('.loader').css('opacity','1');
				            		

				            		var response = jQuery( 'form.checkout' ).submit();  
					            },
							    onSessionLockFailed: (event, checkout) => {
							        console.log('session lock failed');
							    },
					            onSession: function(event, checkout) {
					                console.log(\"session\", event.session);
					                var ss = event.session;
					                checkoutSessionData =  event.session;
					                checkoutSession = checkout;
					                jQuery( '#billing_first_name , #shipping_first_name' ).val(ss.order.shipping_address.first_name );
									jQuery( '#billing_last_name , #shipping_last_name' ).val(ss.order.shipping_address.last_name);
									
									jQuery( '#billing_address_1 , #shipping_address_1' ).val( ss.order.shipping_address.address_line);
									
									jQuery( '#billing_city , #shipping_city' ).val( ss.order.shipping_address.postal_place );
									jQuery( '#billing_postcode , #shipping_postcode' ).val( ss.order.shipping_address.postal_code );
									jQuery( '#billing_phone' ).val( ss.order.shipping_address.phone_number);
									jQuery( '#billing_email' ).val( ss.order.shipping_address.email );
									jQuery( '#billing_country' ).val( ss.order.shipping_address.country.toUpperCase() );

									var fieldName = 'shipping_method[0]';

									

									var url = \"".home_url().'?dhp-ajax=update_shipping_line_id'."\";
									var data = {
									        action: 'update_shipping_line_id',
									        line_id: ss.order.shipping_option.line_id
									    };    
									jQuery.ajax({
										type:		'POST',
										url:		url,
										data:		data,
										
										success:	function( result ) {
														console.log('Success Called');
														
														
														
													}
									});

									jQuery('input:radio[name=\"'+fieldName+'\"][value=\"'+ss.order.shipping_option.id+'\"]').attr('checked',true);
									//jQuery('input:radio[name=\"'+fieldName+'\"][value=\"'+ss.order.shipping_option.id+'\"]').trigger('click');
									if(ss.order.shipping_address.business_name){
										jQuery( '#billing_company' ).val(ss.order.shipping_address.business_name);
										jQuery( '#billing_vat' ).val(ss.order.shipping_address.organization_number);
										jQuery( '#billing_first_name' ).val(ss.order.shipping_address.co_address );

										var co_name = ss.order.shipping_address.co_address
										var res = co_name.split(' ');
										jQuery( '#billing_first_name' ).val(jQuery.trim(res[0]))	;
										var lastName = co_name.replace(res[0],'');
										jQuery('#billing_last_name' ).val(jQuery.trim(lastName));



									}
									jQuery( '#terms' ).prop( 'checked', true);


					                //jQuery('body').trigger('update_checkout' );
					                console.log(ss.order.shipping_address.postal_code);
					                jQuery('#billing_postcode , #shipping_postcode').val(ss.order.shipping_address.postal_code);
					                if(typeof(ss.order) != 'undefined' && typeof(ss.order.shipping_address) != 'undefined' && typeof(ss.order.shipping_address.country) != 'undefined'){
										var a = jQuery('#ship-to-different-address-checkbox').is(':checked');
										var bc = document.getElementById(\"billing_country\");
										var sc = document.getElementById(\"shipping_country\");

										if(a){
											if(sc){
								        		sc.value = ss.order.shipping_address.country;
								        		sc.dispatchEvent(new Event(\"change\"));
												jQuery('body').trigger('update_checkout' );
											}	
										}else{
							        		if(bc){
								        		bc.value = ss.order.shipping_address.country;
								        		bc.dispatchEvent(new Event(\"change\"));
												jQuery('body').trigger('update_checkout' );
											}

							        	}
							        	if (event.type === 'SessionUpdated') {
							        		dinteroEvent = 1; // 1 for updated
							        	}
							        	

							        	// Dont need to user update_session as we are using default WooCommerce Checout form
					            		
							        	
					                }
					            }
					        })
					        ;
						}
					});
					
				</script>" );
		} else {
			wp_kses_post( 'Error! ' . $msg );
		}
	}


	/**
	 * Proceed embed payment
	 */
	private function process_payment_embed( $order_id, $express = false, $pay_for_order = false ) {
		$order = wc_get_order( $order_id );
		//WC()->session->__unset('dintero_wc_order_id');
		if ( ! empty( $order ) && $order instanceof WC_Order ) {


			$results = $this->get_payment_page_url( $order, $express );

			$result = isset($results['result']) ? $results['result'] : 0;
			$msg = isset($results['msg']) ? $results['msg'] : '';
			$url = isset($results['url']) ? $results['url'] : '';
			$id = isset($results['id']) ? $results['id'] : '';

			if (1 == $result && $url) {
				echo ('<span class="spinner"></span>');
				echo( '<div id="dhp-embed">' );
				$this->insertPaymentTypeFlag($express);
				//echo( '<div class="dhp-logo">' . wp_kses_post( WCDHP()->checkout()->get_icon_checkout() ) . '</div>' );
				echo( '<div id="dintero-checkout-iframe"></div>' );
				echo( '</div>' );

				$handle = 'dintero-checkout-web-sdk';
				$src = 'https://assets.dintero.com/js/checkout-web-sdk@0.0.11/dist/checkout-web-sdk.umd.js';
				$deps = array( 'jquery' );
				$version = false;

				// Register the script
				wp_register_script( $handle, $src, $deps, '0.0.11', true );
				wp_enqueue_script( $handle);

				$container_id = true === $pay_for_order ? 'order_review' : 'dhp-wrapper';

				echo( "<script type=\"text/javascript\">
						var emb = document.getElementById('dhp-embed');
						var order_review = document.getElementById('" . wp_kses_post ( $container_id ) . "');
						order_review.appendChild(emb);
						document.addEventListener('DOMContentLoaded', function(event) { 
						  //run plugin code
							const container = document.getElementById(\"dintero-checkout-iframe\");
						    if(typeof(dintero) != \"undefined\"){
						    	dintero
						        .embed({
						            container,
						            sid: \"" . esc_attr( $id ) . "\",
						           
						            onSession: function(event, checkout) {
						                console.log(\"session\", event.session);
						                var ss = event.session;

						                jQuery( '#billing_first_name' ).val(ss.order.shipping_address.first_name );
										jQuery( '#billing_last_name' ).val(ss.order.shipping_address.last_name);
										
										jQuery( '#billing_address_1' ).val( ss.order.shipping_address.address_line);
										
										jQuery( '#billing_city' ).val( ss.order.shipping_address.postal_place );
										jQuery( '#billing_postcode' ).val( ss.order.shipping_address.postal_code );
										jQuery( '#billing_phone' ).val( ss.order.shipping_address.phone_number);
										jQuery( '#billing_email' ).val( ss.order.shipping_address.email );
										jQuery( '#billing_country' ).val( ss.order.shipping_address.country.toUpperCase() );
										
										jQuery( '#terms' ).prop( 'checked', true);
										if(ss.order.shipping_address.business_name){
											jQuery( '#billing_company' ).val(ss.order.shipping_address.business_name);
											jQuery( '#billing_company' ).val(ss.order.shipping_address.business_name);
										}

						                #jQuery('body').trigger('update_checkout' );
						                console.log(ss.order.shipping_address.postal_code);
						                jQuery('#billing_postcode , #shipping_postcode').val(ss.order.shipping_address.postal_code);
						                if(typeof(ss.order) != 'undefined' && typeof(ss.order.shipping_address) != 'undefined' && typeof(ss.order.shipping_address.country) != 'undefined'){
											var a = jQuery('#ship-to-different-address-checkbox').is(':checked');
											var bc = document.getElementById(\"billing_country\");
											var sc = document.getElementById(\"shipping_country\");

											if(a){
												if(sc){
									        		sc.value = ss.order.shipping_address.country;
									        		sc.dispatchEvent(new Event(\"change\"));
													jQuery('body').trigger('update_checkout' );
												}
											}else{
								        		if(bc){
									        		bc.value = ss.order.shipping_address.country;
									        		bc.dispatchEvent(new Event(\"change\"));
													jQuery('body').trigger('update_checkout' );
												}

								        	}
						                }
						            }
						        });
							}
						});
						
					</script>" );
			} else {
				wp_kses_post( 'Error! ' . $msg );
			}
		} else {
			echo( 'Invalid order' );
		}
	}

	/**
	 * Send to payment gateway page
	 */
	private function process_payment( $order_id, $express = false, $pay_for_order = false ) {

		echo $express;
		exit;

		$order = wc_get_order( $order_id );
		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$results = $this->get_payment_page_url( $order, $express );

			$result = isset($results['result']) ? $results['result'] : 0;
			$msg = isset($results['msg']) ? $results['msg'] : '';
			$url = isset($results['url']) ? $results['url'] : '';

			if ( false && $pay_for_order ) {
				if (1 == $result && $url) {
					wp_redirect( $url );
					exit;
				} else {
					wp_kses_post( $msg );
					exit;
				}
			} else {
				//not use this mode now
				$result_txt = 1 == $result ? 'success' : 'failure';

				$this_result = array(
					'result'   => $result_txt,
					'messages' => $msg,
					'redirect' => $url
				);

				wp_send_json( $this_result );
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
		// if(!$shipping_packages){

		// 	$method_rate_id = WC()->session->get( 'chosen_shipping_methods' )[0];

		// 	$method_key_id = str_replace( ':', '_', $method_rate_id ); // Formating
	 //        $option_name = 'woocommerce_'.$method_key_id.'_settings'; // Get the complete option slug

	 //        $shipping_name = get_option( $option_name, true )['title'];

		// }
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

		$shipping_reference = array();
		$shipping_packages = WC()->shipping->get_packages();


		foreach ( $shipping_packages as $i => $package ) {


			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';

			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {

						$shipping_reference['id'] = $rate_value->id;
						$shipping_reference['instance_id'] = $rate_value->instance_id;
						$shipping_reference['label'] = $rate_value->label;
					}
				}
			}
		}
		if ( ! isset( $shipping_reference ) ) {
			$shipping_reference = __( 'Shipping', 'Dintero-checkout-for-woocommerce' );
		}

		return  $shipping_reference;
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
			return (int) number_format(
				WC()->cart->shipping_total * 100,
				0,
				'',
				''
			);
		}
		return intval(number_format(
			WC()->cart->shipping_total + WC()->cart->shipping_tax_total,
			wc_get_price_decimals(),
			'.',
			''
		)) * 100;
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
				$shipping_tax_rate = round( $vat['rate'] ,2 );
			} else {
				$shipping_tax_rate = 0;
			}
		} else {
			$shipping_tax_rate = 0;
		}

		return round( $shipping_tax_rate,2 );
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
			$shipping_total_exluding_tax = $shiping_total_amount / ( 1 + ( $this->get_shipping_tax_rate() / 100 ) );
			$shipping_tax_amount         = $shiping_total_amount - $shipping_total_exluding_tax;
		}
		return round( $shipping_tax_amount,2 );
	}




	// Helpers.
	/**
	 * Get cart item name.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return string $item_name Cart item name.
	 */
	public function get_item_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$item_name      = $cart_item_data->get_name();

		return strip_tags( $item_name );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_item_tax_amount( $cart_item, $product ) {
		if ( $this->separate_sales_tax ) {
			$item_tax_amount = 0;
		} else {
			// $item_tax_amount = $cart_item['line_tax'] * 100;
			$item_total_amount       =  $this->get_item_total_amount( $cart_item, $product );


			// $item_total_exluding_tax = $item_total_amount / ( 1 + ( $this->get_item_tax_rate( $cart_item, $product ) / 100 ) );


			// $item_tax_amount         = $item_total_amount - $item_total_exluding_tax;
			$item_tax_amount = ( $item_total_amount * ( $this->get_item_tax_rate( $cart_item, $product ) / 100 ) );
		}
		return round(round( $cart_item['line_tax'],2 ) * 100);
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array  $cart_item Cart item.
	 * @param  object $product   Product object.
	 *
	 * @return integer $item_tax_rate Item tax percentage formatted for Dinetro.
	 */
	public function get_item_tax_rate( $cart_item, $product ) {
		if ( $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate.
			if ( $this->separate_sales_tax ) {
				$item_tax_rate = 0;
			} else {
				$_tax      = new WC_Tax();
				$tmp_rates = $_tax->get_rates( $product->get_tax_class() );
				$vat       = array_shift( $tmp_rates );
				if ( isset( $vat['rate'] ) ) {
					$item_tax_rate = round( $vat['rate'],2 );
				} else {
					$item_tax_rate = 0;
				}
			}
		} else {
			$item_tax_rate = 0;
		}

		return round( $item_tax_rate,2 );
	}

	/**
	 * Get cart item price.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_price Cart item price.
	 */
	public function get_item_price( $cart_item ) {
		if ( $this->separate_sales_tax ) {
			// $item_subtotal = $cart_item['line_subtotal'];
			// $item_subtotal = $cart_item['line_subtotal'] / $cart_item['quantity'];
			$item_subtotal = wc_get_price_excluding_tax( $cart_item['data'] );
		} else {
			// $item_subtotal = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
			$item_subtotal = wc_get_price_including_tax( $cart_item['data'] );
		}
		// $item_price = $item_subtotal * 100 / $cart_item['quantity'];
		$item_price = number_format( $item_subtotal, wc_get_price_decimals(), '.', '' ) * 100;
		return round( $item_price,2 );
	}

	/**
	 * Get cart item quantity.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_quantity Cart item quantity.
	 */
	public function get_item_quantity( $cart_item ) {
		return round( $cart_item['quantity'] );
	}

	/**
	 * Get cart item reference.
	 *
	 * Returns SKU or product ID.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  object $product Product object.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	public function get_item_reference( $product ) {
		$item_reference = $product->get_id();

		return substr( (string) $item_reference, 0, 64 );
	}

	/**
	 * Get cart item discount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	public function get_item_discount_amount( $key) {
		if (!isset($this->item_discounts[$key])) {
			return 0;
		}

		return wc_round_discount(array_sum($this->item_discounts[$key]), 2);

		/*
		if ( $cart_item['line_subtotal'] > $cart_item['line_total'] ) {
			if ( $this->separate_sales_tax ) {
				$item_discount_amount = $cart_item['line_subtotal'] - $cart_item['line_total'];
			} else {
				$item_discount_amount = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] - $cart_item['line_total'] - $cart_item['line_tax'];
			}
			$item_discount_amount = $item_discount_amount * 100;
		} else {
			$item_discount_amount = 0;
		}
		*/

		$order_line_max_amount = ( number_format( wc_get_price_including_tax( $cart_item['data'] ), wc_get_price_decimals(), '.', '' ) * $cart_item['quantity'] ) * 100;
		$order_line_amount     = number_format( ( $cart_item['line_total'] ) * ( 1 + ( $this->get_item_tax_rate( $cart_item, $product ) / 10000 ) ), wc_get_price_decimals(), '.', '' ) * 100;
		if ( $this->separate_sales_tax ) {
			$item_discount_amount = number_format( $cart_item['line_subtotal'] - $cart_item['line_total'], wc_get_price_decimals(), '.', '' ) * 100;
		} else {
			if ( $order_line_amount < $order_line_max_amount ) {
				$item_discount_amount = $order_line_max_amount - $order_line_amount;
			} else {
				$item_discount_amount = 0;
			}
		}

		return round( $item_discount_amount ,2);
	}



	/**
	 * Get cart item discount rate.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_discount_rate Cart item discount rate.
	 */
	public function get_item_discount_rate( $cart_item ) {
		$item_discount_rate = ( 1 - ( $cart_item['line_total'] / $cart_item['line_subtotal'] ) ) * 100 * 100;

		return round( $item_discount_rate,2 );
	}

	/**
	 * Get cart item total amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param WC_Product $product
	 * @param  array $cart_item Cart item.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 */
	public function get_item_total_amount( $cart_item, $product ) {
		/*
		if ( $this->separate_sales_tax ) {
			$item_total_amount = ( $cart_item['line_total'] * 100 );
		} else {
			$item_total_amount = ( ( $cart_item['line_total'] + $cart_item['line_tax'] ) * 100 );
		}
		*/

		if ( $this->separate_sales_tax ) {
			$item_total_amount     = number_format( ( $cart_item['line_total'] ) * ( 1 + ( $this->get_item_tax_rate( $cart_item, $product ) / 10000 ) ), wc_get_price_decimals(), '.', '' ) * 100;
			$max_order_line_amount = ( number_format( wc_get_price_including_tax( $cart_item['data'] ), wc_get_price_decimals(), '.', '' ) * $cart_item['quantity'] ) * 100;
		} else {
			$item_total_amount     = number_format( ( $cart_item['line_total'] ) * ( 1 + ( $this->get_item_tax_rate( $cart_item, $product ) / 10000 ) ), wc_get_price_decimals(), '.', '' ) * 100;
			$max_order_line_amount = ( number_format( wc_get_price_including_tax( $cart_item['data'] ), wc_get_price_decimals(), '.', '' ) * $cart_item['quantity'] ) * 100;
		}
		// Check so the line_total isn't greater than product price x quantity.
		// This can happen when having price display set to 0 decimals.
		if ( $item_total_amount > $max_order_line_amount ) {
			$item_total_amount = $max_order_line_amount;
		}
		$perItemCost = round($cart_item['line_total'] +  $cart_item['line_tax'] , 2);
		$item_total_amount = ( $perItemCost ) * 100;
		return round( $item_total_amount,2 );
	}


	/**
	 * Process WooCommerce cart to Dintero Payments order lines.
	 */
	public function process_cart() {
		$discounts = new WC_Discounts(WC()->cart);
		foreach (WC()->cart->get_applied_coupons() as $coupon_code) {
			$discounts->apply_coupon(new WC_Coupon($coupon_code));
		}
		foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
			if ( $cart_item['quantity'] ) {
				if ( $cart_item['variation_id'] ) {
					$product = wc_get_product( $cart_item['variation_id'] );
				} else {
					$product = wc_get_product( $cart_item['product_id'] );
				}

				if (in_array($this->get_item_reference( $product ), $this->order_lines_item_id)) {
					$key = array_search($this->get_item_reference( $product ),$this->order_lines_item_id,true);
					$this->order_lines[$key]['quantity'] = $this->order_lines[$key]['quantity'] + $this->get_item_quantity( $cart_item );
					$this->order_lines[$key]['vat_amount'] = $this->order_lines[$key]['vat_amount'] + $this->get_item_tax_amount( $cart_item, $product );
					$this->order_lines[$key]['vat'] = $this->order_lines[$key]['vat'] + $this->get_item_tax_rate( $cart_item, $product );
					$this->order_lines[$key]['amount'] = $this->order_lines[$key]['amount'] + $this->get_item_total_amount( $cart_item, $product );
					continue;
				}

				$round_precision = min(array(wc_get_price_decimals(), 2));
				$amount = $cart_item['line_tax'] + $cart_item['line_total'];
				$tax = $cart_item['line_tax'];

				$dintero_item = array(
					'id'          => $this->get_item_reference( $product ),
					'description' => $this->get_item_name( $cart_item ),
					'quantity'    => $this->get_item_quantity( $cart_item ),
					'vat_amount'  => intval(round($tax, $round_precision) * 100),
					'vat'         => $this->get_item_tax_rate( $cart_item, $product ),
					'amount'      => intval(round($amount, $round_precision) * 100),
					'line_id'     => $this->get_item_reference( $product ),
				);

				$this->order_lines_item_id[] = $this->get_item_reference( $product );
				$this->order_lines[] = $dintero_item;
			}
		}
	}

	public function get_order_lines_total_amount( ) {
		$total_amount = 0;

		foreach ( $this->order_lines as $order_line ) {
			$total_amount += $order_line['amount'] ;
		}

		return round( $total_amount,2 );
	}
	/*
	 * Update Session .
	 */
	public function update_session( $session_id ) {
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/sessions/'.$session_id;


		$headers = array(

			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token,

			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
		);
		$load = array('session_id' => $session_id) ;

		$cart = WC()->cart;

		$totals = $cart->get_totals();
		$order_tax_amount   = absint( strval( floatval( $totals['total_tax'] ) * 100 ) );
		WC()->cart->calculate_shipping();
		$this->process_cart();
		$total_amount = $this->get_order_lines_total_amount();
		$billingAddress = WC()->customer->get_billing();

		$order_total_amount = $total_amount;
		$selectedShippingReference = $this->get_shipping_reference();
		$currency = get_woocommerce_currency();
		$payload = array(
			'order' =>  array(
				'amount'             => $order_total_amount + $this->get_shipping_amount() ,
				'vat_amount'         => $order_tax_amount ,
				'currency'           => $currency,
				'merchant_reference' => '',

				'items'              => $this->order_lines,
			)

		);

		if (WC()->shipping->get_packages() && WC()->session->get( 'chosen_shipping_methods' )[0]) {
			$payload["order"]['shipping_option'] = array(
				'id'=> (string)$selectedShippingReference['id'],
				'line_id'=>'shipping_method',
				'amount'=> (int) $this->get_shipping_amount(),
				'vat_amount'=> (int)$this->get_shipping_tax_amount(),
				'vat'=> $this->get_shipping_tax_rate(),
				'title'=> $this->get_shipping_name(),
				'description'=>'',
				'delivery_method'=>'delivery',
				'operator'=>'',
				'operator_product_id'=> (string)$selectedShippingReference['instance_id'],
			);

		} else {
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


		$args = array(
		    'headers' => $headers,
		    'body'      => json_encode($payload),
		    'method'    => 'PUT'
		);



		$response = wp_remote_request( $api_endpoint, array(
			'method'    => 'PUT',
			'headers'   => $headers,
			'body'      => json_encode( $payload )
		) );
		$response_body  = wp_remote_retrieve_body( $response );
		$response_array = json_decode( $response_body, true );
		return $response_array;
	}

	/**
	 * Initializing iframe
	 *
	 * @return array
	 */
	private function get_iframe()
	{
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/sessions-profile';

		$return_url   = $this->get_return_url( );
        $express_customer_types = WCDHP()->setting()->get('express_customer_types');

		$callback_url = home_url() . '?dhp-ajax=dhp_create_order&delay_callback=180&include=session';
		$cart = WC()->cart;

		$totals = $cart->get_totals();
		$order_tax_amount   = absint( strval( floatval( $totals['total_tax'] ) * 100 ) );

		$this->process_cart();
		$total_amount = $this->get_order_lines_total_amount();

		$shipping_option = array();
		$order_total_amount = $total_amount;

		$ship_callback_url = home_url() . '?dhp-ajax=dhp_update_ship';
		$selectedShippingReference = $this->get_shipping_reference();
		$customer_types = array();
		if ($express_customer_types == 'b2c') {
			array_push($customer_types, 'b2c');
		} else if ($express_customer_types == 'b2b') {
			array_push($customer_types, 'b2b');
		} else {
			array_push($customer_types, 'b2b', 'b2c');
		}

		if (WC()->shipping->get_packages() && WC()->session->get( 'chosen_shipping_methods' )[0]) {
			$dintero_shipping_options = array();
			$isShippingInIframe = 'yes' == WCDHP()->setting()->get('shipping_method_in_iframe');

			if($isShippingInIframe){
				$packages         = WC()->shipping->get_packages();
				$tax_display      = get_option( 'woocommerce_tax_display_cart' );
				$j = 0;
				foreach ( $packages as $i => $package ) {
					foreach ( $package['rates'] as $method ) {
						$method_id   = $method->id;
						$method_name = $method->label;

						$method_price = intval(round( $method->cost, 2 ) * 100 );

						if ( array_sum( $method->taxes ) > 0 && ( ! $separate_sales_tax && 'excl' !== $tax_display ) ) {
							$method_tax_amount = intval( round( array_sum( $method->taxes ), wc_get_rounding_precision() ) * 100 );
							$method_tax_rate   = intval( round( ( array_sum( $method->taxes ) / $method->cost ) * 100, 2 ) * 100 );
						} else {
							$method_tax_amount = intval(round(array_sum($method->taxes), wc_get_price_decimals()) * 100);
							$method_tax_rate   = $this->get_shipping_tax_rate();
						}

						$dintero_shipping_options[] = array(
							'id'          => $method_id,
							'line_id' 	  => 'shipping_method_'.$j,
							'title'       =>  $method_name,
							'amount'      =>  (int) ($method_price + $method_tax_amount),
							'vat_amount'  =>(int) $method_tax_amount,
							'vat'    => $method_tax_rate,
							'description' => '',
							'delivery_method' => 'delivery',
							'operator' => '',
							'operator_product_id' => (string)$method->instance_id,
							
						);

						if ($j == 0) {
							WC()->session->set( 'dintero_shipping_line_id', 'shipping_method_'.$j );
						}
						$j++;
					}
					
				}
			} else {
				// If shipping is not In Iframe
				$dintero_shipping_options = array(
					array(
						'id' => (string)$selectedShippingReference['id'],
						'line_id' => 'shipping_method',
						'amount' => (int) $this->get_shipping_amount() + $this->get_shipping_tax_amount(),
						'vat_amount' => (int) $this->get_shipping_tax_amount(),
						'vat' => $this->get_shipping_tax_rate(),
						'title' => $this->get_shipping_name(),
						'description' => '',
						'delivery_method' => 'delivery',
						'operator' => '',
						'operator_product_id' => (string)$selectedShippingReference['instance_id'],
					)
				);
			}

			$express_option = array(
				'shipping_address_callback_url' => $ship_callback_url,
				'customer_types' => $customer_types,
				'shipping_options'=> $dintero_shipping_options
			);
		} else {
			$express_option = array(
				'customer_types' => $customer_types,
				'shipping_options'=> array(),
			);
		}

		$headers = array(
			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token,
			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
		);

		$payload_url = array(
			'return_url'   => $return_url,
			'callback_url' => $callback_url
		);

		$terms_page_id   = wc_terms_and_conditions_page_id();
		$terms_link      = esc_url( get_permalink( $terms_page_id ) );

		$payload_url[ 'merchant_terms_url' ] = $terms_link;

		$currency = get_woocommerce_currency();
		$country = WC()->countries->get_base_country();

		$billingPhone =  WC()->checkout()->get_value( 'billing_phone' );
		if($billingPhone != ''){
			$billingPhone = (string) WC()->checkout()->get_value( 'billing_phone' );

			$billingPhone = str_replace(' ', '', $billingPhone); // remove space from Phone number if any

			// Not necessary, but convenience so the phone number is properly added to the order
			if($country === 'NO' && strpos($billingPhone, '+') === false){
				$billingPhone = '+47'.$billingPhone;
			} else if($country === 'SE' && strpos($billingPhone, '+') === false){
				$billingPhone = '+46'.$billingPhone;
			}
		}

		$payload = array(
			'url'        => $payload_url,
			'customer'   => array(
				'email'        => (string) WC()->checkout()->get_value( 'billing_email' ),
				'phone_number' => $billingPhone ?: ''
			),
			'order'      => array(
				'amount'             => $order_total_amount ,
				'vat_amount'         => $order_tax_amount ,
				'currency'           => $currency,
				'discount_codes'	=> WC()->cart->get_applied_coupons(),
				'merchant_reference' => '',
				'shipping_address'   => array(
					'first_name'   => (string) WC()->checkout()->get_value( 'shipping_first_name' ),
					'last_name'    => (string) WC()->checkout()->get_value( 'shipping_last_name' ),
					'address_line' => (string) WC()->checkout()->get_value( 'shipping_address_1' ),
					'postal_code'  => (string) WC()->checkout()->get_value( 'shipping_postcode' ),
					'postal_place' => (string) WC()->checkout()->get_value( 'shipping_city' ),
					'country'      => $country,
					'email'        => (string) WC()->checkout()->get_value( 'billing_email' )
				),
				'items'              => $this->order_lines
			),
			'profile_id' => $this->profile_id,
			'metadata' => array(
				'woo_customer_id' => WC()->session->get_customer_id(),
			)
		);

		if ($billingPhone) {
			$payload['order']['shipping_address']['phone_number'] = $billingPhone;
		}

		if(count($shipping_option) > 0){
			$payload['order']['shipping_option'] = $shipping_option;
		} else {
			$payload['order']['shipping_option'] = array(
				'id' => 'shipping_express',
				'line_id' => 'shipping_method',
				'amount' => 0,
				'title' => 'Shipping: none',
				'description' => '',
				'delivery_method' => 'none',
				'operator' => ''
			);
		}

		//if ($express) {
		$payload['express'] = $express_option;

		//}

		$response = wp_remote_post( $api_endpoint, array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $payload ),
			'timeout'   => 90,
			'sslverify' => false
		) );

		// Retrieve the body's response if no errors found
		$response_body  = wp_remote_retrieve_body( $response );
		$response_trace_id = wp_remote_retrieve_header( $response, 'request-id');
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_array = json_decode( $response_body, true );
		
		if ( ! array_key_exists( 'url', $response_array ) ) {
			$msg = isset($response_array['error']) && isset($response_array['error']['message']) ? $response_array['error']['message'] : 'Unknown Error';
			echo '<p class="dintero-error-message">Problems creating payment, please contact Dintero with this message: ' . $response_trace_id . ', ';
			echo 'by sending an email to: <a href="mailto:integration@dintero.com&subject=WooCommerce%20session%20creation%20failed%20for%20' . $this->account_id  . '&body=Session%20creation%20failed%20with%20request_id%20' . $response_trace_id . '">integration@dintero.com</a>. ';
			echo( "<script type=\"text/javascript\">
				var dResponseCode = " . $response_code . "; 
				var dRequestId = " . json_encode($response_trace_id) . ";
				var dResponseBody = " . json_encode($response_body) . ";
				var now = new Date().toISOString();
				var errorObj = { statusCode: dResponseCode, request_id: dRequestId, body: dResponseBody, timestamp: now };
				console.log('dintero: error creating session, copy this and send to integration@dintero.com:', 'statusCode: ' + dResponseCode, 'request_id: ' + dRequestId , 'timestamp: ' + now);  
				console.log('dintero: extended error information:', errorObj);  
			</script>" );
			return array('result'=>2, 'msg'=>$msg);
		}

		return array('result'=>1, 'msg'=>'', 'url'=>$response_array['url'], 'id'=>$response_array['id']);
	}

	/**
	 * Creating checkout session and requesting payment page URL
	 */
	private function get_payment_page_url( $order, $express = false ) {

		if ( ! empty( $order ) && $order instanceof WC_Order ) {
			$order_id     = $order->get_id();
			// init session
			$access_token = $this->get_access_token();
			$api_endpoint = $this->checkout_endpoint . '/sessions-profile';

			$return_url   = $this->get_return_url( $order );
			$callback_url = home_url() . '?dhp-ajax=dhp_update_ord';

            $express_customer_types = WCDHP()->setting()->get('express_customer_types');

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );
			$order_tax_amount   = absint( strval( floatval( $order->get_total_tax() ) * 100 ) );

			$items = array();

			$counter = 0;
			$total_amount = 0;

			$this->process_cart();
			$total_amount = $this->get_order_lines_total_amount();

			// foreach ( $order->get_items() as $order_item ) {
			// 	$counter ++;
			// 	$line_id                = strval( $counter );
			// 	$item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
			// 	$item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
			// 	$item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
			// 			true ) ) * 100 ) );
			// 	$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
			// 			2 ) * 100 ) : 0;
			// 	$item                   = array(
			// 		'id'          => 'item_' . $counter,
			// 		'description' => $order_item->get_name(),
			// 		'quantity'    => $order_item->get_quantity(),
			// 		'vat_amount'  => $item_tax_amount,
			// 		'vat'         => $item_tax_percentage,
			// 		'amount'      => $item_line_total_amount,
			// 		'line_id'     => $line_id
			// 	);
			// 	array_push( $items, $item );

			// 	$total_amount += $item_line_total_amount;
			// }

			$shipping_option = array();
			$express_option = array();

			$counter ++;
			$line_id                = strval( $counter );
			$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
			$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
			$item_line_total_amount = $item_total_amount + $item_tax_amount;
			$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
					2 ) * 100 ) : 0;


			if (!$express) {
				$item = array(
					'id'          => 'shipping',
					'description' =>  $order->get_shipping_method(),
					'quantity'    => 1,
					'amount'=> $this->get_shipping_amount(),
					'vat_amount'=> $this->get_shipping_tax_amount(),
					'vat'=> $this->get_shipping_tax_rate(),
					'title'=> $this->get_shipping_name(),

					'line_id'     => $line_id
				);
				array_push( $items, $item );

				$total_amount += $item_line_total_amount;
			}

			$order_total_amount = $total_amount;
			$hasShippingOptions = count($order->get_shipping_methods()) > 0;
			if ($express) {
				$ship_callback_url = home_url() . '?dhp-ajax=dhp_update_ship';
				$selectedShippingReference = $this->get_shipping_reference();
				$customer_types = array();
				if ($express_customer_types == 'b2c') {
					array_push($customer_types, 'b2c');
				} else if ($express_customer_types == 'b2b') {
					array_push($customer_types, 'b2b');
				} else {
					array_push($customer_types, 'b2b', 'b2c');
				}
				if ($hasShippingOptions) {
					$dintero_shipping_options = array(
						0=>array(
							'id'=> (string)$selectedShippingReference['id'],
							'line_id'=>$line_id,
							//"countries"=>array($order->get_shipping_country()),
							'country'=>$order->get_shipping_country(),
							'amount'=> $this->get_shipping_amount(),
							'vat_amount'=> $this->get_shipping_tax_amount(),
							'vat'=> $this->get_shipping_tax_rate(),
							'title'=> $this->get_shipping_name(),
							'description'=>'',
							'delivery_method'=>'delivery',
							'operator'=>'',
							'operator_product_id'=>(string)$selectedShippingReference['instance_id'],
						)
					);
					$express_option = array(
						'shipping_address_callback_url' => $ship_callback_url,
						'customer_types' => $customer_types,
						'shipping_options'=> $dintero_shipping_options
					);
				} else {
					$express_option = array(
						'customer_types' => $customer_types,
						'shipping_options'=> array(),
					);
				}
			}



			$headers = array(
				'Content-type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
				'Dintero-System-Name' => 'woocommerce',
				'Dintero-System-Version' =>  WC()->version,
				'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
				'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
			);


			$payload_url = array(
					'return_url'   => $return_url,
					'callback_url' => $callback_url
				);

			$terms_page_id   = wc_terms_and_conditions_page_id();
			$terms_link      = esc_url( get_permalink( $terms_page_id ) );

			$embed_enable = WCDHP()->setting()->get('embed_enable');

			if ( 'yes' == $embed_enable && $express ) {
				$payload_url[ 'merchant_terms_url' ] = $terms_link;
			}


			$payload = array(
				'url'        => $payload_url,
				'customer'   => array(
					'email'        => (string) WC()->checkout()->get_value( 'billing_email' ),
					'phone_number' => (string) WC()->checkout()->get_value( 'billing_phone' )
				),
				'order'      => array(
					'amount'             => $order_total_amount,
					'vat_amount'         => $order_tax_amount,
					'currency'           => $order->get_currency(),
					'merchant_reference' => strval( $order_id ),
					'shipping_address'   => array(
						'first_name'   => (string) WC()->checkout()->get_value( 'shipping_first_name' ),
						'last_name'    => (string) WC()->checkout()->get_value( 'shipping_last_name' ),
						'address_line' => (string) WC()->checkout()->get_value( 'shipping_address_1' ),
						'postal_code'  => (string) WC()->checkout()->get_value( 'shipping_postcode' ),
						'postal_place' => (string) WC()->checkout()->get_value( 'shipping_city' ),
						'country'      => (string) WC()->checkout()->get_value( 'shipping_country' )
					),
					'billing_address'    => array(
						'first_name'   => (string) WC()->checkout()->get_value( 'billing_first_name' ),
						'last_name'    => (string) WC()->checkout()->get_value( 'billing_last_name' ),
						'address_line' => (string) WC()->checkout()->get_value( 'billing_address_1' ),
						'postal_code'  => (string) WC()->checkout()->get_value( 'billing_postcode' ),
						'postal_place' => (string) WC()->checkout()->get_value( 'billing_city' ),
						'country'      => (string) WC()->checkout()->get_value( 'billing_country' )
					),
					'items'              => $this->order_lines
				),
				'profile_id' => $this->profile_id,
				'metadata' => array(
					'woo_customer_id' => WC()->session->get_customer_id(),
				)
			);
			if ($express) {
				$payload['express'] = $express_option;
			}

			if ($express && !$hasShippingOptions) {
				$payload['order']['shipping_option']	= array(
					'id' => 'shipping_express',
					'line_id' => 'shipping_method',
					'amount' => 0,
					'title' => 'Shipping: none',
					'description' => '',
					'delivery_method' => 'none',
					'operator' => ''
				);
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
				$msg = isset($response_array['error']) && isset($response_array['error']['message']) ? $response_array['error']['message'] : 'Unknown Error';
				return array('result'=>2, 'msg'=>$msg);
			} else {
				return array('result'=>1, 'msg'=>'', 'url'=>$response_array['url'], 'id'=>$response_array['id']);
			}
		}
	}

	/**
	 * Get the return url, back from payment gateway
	 *
	 * @return string url
	 */
	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

	/**
	 * Get transaction by ID.
	 */
	public function get_transaction( $transaction_id ) {


		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/transactions';

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token,
			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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
	public function update_transaction( $transaction_id , $order_id ) {

		if($order_id == ''){
			return false;
		}
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/transactions';


		$headers = array(

			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token,
			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
		);
		$payload = array(
			'merchant_reference_2' => (string)$order_id
		);
		$url = $api_endpoint . '/' . $transaction_id;

		$args = array(
		    'headers' => $headers,
		    'body'      => json_encode($payload),
		    'method'    => 'PUT'
		);



		$response = wp_remote_request( $url, array(
			'method'    => 'PUT',
			'headers'   => $headers,
			'body'      => json_encode( $payload )
		) );
		$response_body  = wp_remote_retrieve_body( $response );
		$transaction = json_decode( $response_body, true );
		return $transaction;

	}

	/**
	 * Get Session detail By Session ID.
	 */
	public function get_dintero_session($sessionId){
		$access_token = $this->get_access_token();
		$api_endpoint = $this->checkout_endpoint . '/sessions';

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $access_token,
			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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
	 * Check order status when it is changed and call the right action
	 *
	 * @param int $order_id Order ID.
	 */
	public function check_status( $order_id, $previous_status, $current_status ) {
		if ( $current_status === $this->manual_capture_status ) { // OR $current_status === $this->additional_manual_capture_status
			$this->check_capture( $order_id );
		} else {
			if ( 'cancelled' === $current_status ) { // OR $current_status === $this->additional_cancel_status
				$this->cancel( $order_id );
			}

			if ( 'refunded' === $current_status ) { // OR $current_status === $this->additional_refund_status
				$this->process_refund( $order_id );
			}
		}
	}

	/**
	 * Cancel Order
	 *
	 * @param int $order_id Order ID.
	 */
	public function cancel( $order_id ) {
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
			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );

			if ( $transaction_order_id === $order_id &&
				 array_key_exists( 'status', $transaction ) &&
				 'AUTHORIZED' === $transaction['status'] ) {

				$access_token = $this->get_access_token();
				$api_endpoint = $this->checkout_endpoint . '/transactions';

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
					'Dintero-System-Name' => 'woocommerce',
					'Dintero-System-Version' =>  WC()->version,
					'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
					'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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
			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );

			if ( $transaction_order_id === $order_id &&
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
					'User-Agent' => 'Dintero.Checkout.Woocomerce.1.2.1 (+https://github.com/Dintero/Dintero.Checkout.Woocomerce)',
					'Authorization' => 'Bearer ' . $access_token,
					'Dintero-System-Name' => 'woocommerce',
					'Dintero-System-Version' =>  WC()->version,
					'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
					'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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
				} else {
					$note = __( 'Payment refund failed at Dintero. Transaction ID: ' ) . $transaction_id;
					$order->add_order_note( $note );
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
			$transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );
			if ( $transaction_order_id === $order_id ) {
				$this->capture( $order, $transaction );
			}
		}
	}

	/**
	 * Capture Payment.
	 */
	public function capture( $order, $transaction = null ) {
		if ( ! empty( $order ) &&
			 $order instanceof WC_Order &&
			 $order->get_transaction_id() ) {

			$order_id = $order->get_id();

			$transaction_id = $order->get_transaction_id();
			if ( empty( $transaction ) ) {
				$transaction = $this->get_transaction( $transaction_id );
			}

			$order_total_amount = absint( strval( floatval( $order->get_total() ) * 100 ) );

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

				$headers = array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
					'Dintero-System-Name' => 'woocommerce',
					'Dintero-System-Version' =>  WC()->version,
					'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
					'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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
					('CAPTURED' === $response_array['status'] || 'PARTIALLY_CAPTURED' === $response_array['status'])) {

					$note = __( 'Payment captured via Dintero. Transaction ID: ' ) . $transaction_id;
					WC_AJAX_HP::payment_complete( $order, $transaction_id, $note );
				} else {
					$note = __('Payment capture failed at Dintero. Transaction ID: ') . $transaction_id;
					$order->add_order_note($note);
				}
			}
		}
	}

	/**
	 * Creating order receipt.
	 */
	public function create_receipt( $order ) {
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
				'Authorization' => 'Bearer ' . $access_token,
				'Dintero-System-Name' => 'woocommerce',
				'Dintero-System-Version' =>  WC()->version,
				'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
				'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
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

	private function writeContainerScript() {
		echo( "<script type=\"text/javascript\">
	        	var dhpc = document.getElementById('dhp_container');
				var order_review = document.getElementById('dhp-order-review');
				order_review.appendChild(dhpc);
				</script>" );
	}
}
