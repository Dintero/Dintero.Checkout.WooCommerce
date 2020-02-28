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
			'dhp_update_ship'
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
		echo( 'Hello Test' );
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

	public static function express_pay() {
		//check_ajax_referer( 'express-checkout', 'security' );

		$test = WCDHP()->checkout()->pay_action(true);
	}

	/**
	 * Update order status post back
	 */
	public static function dhp_update_ord() {
		if ( ! empty( $_GET['transaction_id'] ) ) {
			$transaction_id = sanitize_text_field( wp_unslash( $_GET['transaction_id'] ) );
			//echo("<br />transaction_id: ".$transaction_id);

			$transaction = WCDHP()->checkout()->get_transaction( $transaction_id );
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

					WC()->session->set( 'order_awaiting_payment', null );
					
					if ( 'AUTHORIZED' === $transaction['status'] ) {

						$hold_reason = __( 'Transaction authorized via Dintero. Change order status to the manual capture status or the additional status that are selected in the settings page to capture the funds. Transaction ID: ' ) . $transaction_id;
						self::process_authorization( $order, $transaction_id, $hold_reason );
					} elseif ( 'CAPTURED' === $transaction['status'] ) {

						$note = __( 'Payment auto captured via Dintero. Transaction ID: ' ) . $transaction_id;
						self::payment_complete( $order, $transaction_id, $note );
					}
				}
			}

			if (true || ! $return_page ) {
				exit;
			}
		}
	}

	/**
	 * Update order shipping address post back
	 */
	public static function dhp_update_ship() {
		$str11 = 'ph';
		$str12 = 'p:';
		$str2 = '/';
		$str3 = 'input';
		$posted_data = file_get_contents( $str11 . $str12 . $str2 . $str2 . $str3 );

		$posted_data = trim(stripslashes($posted_data));
		$posted_arr = json_decode($posted_data, true);

		if (is_array($posted_arr) && isset($posted_arr['order']) && isset($posted_arr['order']['shipping_address'])) {
			$o = $posted_arr['order'];
			$a = $posted_arr['order']['shipping_address'];

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

			$order_amt = isset($o['amount']) ? $o['amount'] : 0;
			$order_id = isset($o['merchant_reference']) ? $o['merchant_reference'] : 0;

			$valid = true;

			if ($order_amt<=0) {
				$valid = false;
				$msg = 'Invalid order amount';
			}

			if ($valid) {
				$order = wc_get_order( $order_id );
				if ( ! empty( $order ) && $order instanceof WC_Order ) {
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

					$order->calculate_taxes();
					$order->calculate_totals();

					$items = array();

					$line_id = 0;
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
							
					if ( count( $order->get_shipping_methods() ) > 0 ) {
						$counter ++;
						$line_id                = strval( $counter );
					}

					$item_total_amount      = absint( strval( floatval( $order->get_shipping_total() ) * 100 ) );
					$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );

					$item_line_total_amount = $item_total_amount + $item_tax_amount;
					$item_tax_amount        = absint( strval( floatval( $order->get_shipping_tax() ) * 100 ) );
					$item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
						2 ) * 100 ) : 0;

					$shipping_options = array(
									0=>array(
											'id'=>'shipping_express',
											'line_id'=>$line_id,
											//"countries"=>array($country),
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
													"starts_at"=>"",
													"ends_at"=>""
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
													"phone_number"=>$order->get_billing_phone(),
													"email"=>$order->get_billing_email(),
													"latitude"=>0,
													"longitude"=>0,
													"comment"=>""
													//"distance"=>0
												)*/
										)
								);

					$shipping_arr = array('shipping_options'=>$shipping_options);

					wp_send_json($shipping_arr);
				} else {
					echo( 'Invalid order reference' );
				}
			} else {
				wp_kses_post( $msg );
			}
		} else {
			echo( 'Invalid post back format' );
		}
		exit();
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
		$order->payment_complete( $transaction_id );
		wc_reduce_stock_levels( $order->get_id() );
		WCDHP()->checkout()->create_receipt( $order );
	}

	/**
	 * Hold order and add note.
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
}

WC_AJAX_HP::init();
