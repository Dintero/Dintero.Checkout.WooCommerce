<?php
/**
 * WC_Dintero_HP_Checkout class.
 *
 * Testing Checkout functions
 */
class WC_Dintero_HP_Checkout_Test extends WP_UnitTestCase
{

	public function test_shipping_amount()
	{
		WC()->initialize_cart();
		WC()->cart->set_shipping_total(256.03);
		WC()->cart->set_shipping_tax(64.01);
		$checkout = new WC_Dintero_HP_Checkout();
		$shipping_amount = $checkout->get_shipping_amount();
		$this->assertEquals(32004, $shipping_amount);
	}

	public function test_shipping_amount2()
	{
		WC()->initialize_cart();
		WC()->cart->set_shipping_total(124.96);
		WC()->cart->set_shipping_tax(31.24);
		$checkout = new WC_Dintero_HP_Checkout();
		$shipping_amount = $checkout->get_shipping_amount();
		$this->assertEquals(15620, $shipping_amount);
	}
	
	/**
	 * @group process_cart
	 */
	public function test_process_cart_with_discount()
	{
		$coupon_code = 'dintero20';
		$amount = '20';
		$discount_type = 'percent'; 

		$coupon = array(
			'post_title' => $coupon_code,
			'post_content' => '',
			'post_status' => 'publish',
			'post_author' => 1,
			'post_type'     => 'shop_coupon'
		);    

		$new_coupon_id = wp_insert_post( $coupon );
		update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
		update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
		update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
		update_post_meta( $new_coupon_id, 'individual_use', 'no' );
		update_post_meta( $new_coupon_id, 'product_ids', '' );
		update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );
		update_post_meta( $new_coupon_id, 'usage_limit', '1' );
		update_post_meta( $new_coupon_id, 'expiry_date', '' );
		update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
		update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

		update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

		WC()->initialize_cart();
		$product = WC_Helper_Product::create_simple_product(true, array(
			'price' => 179.00,
			'regular_price' => 179.00,
		));


		$cart_item_id = WC()->cart->add_to_cart($product->get_id(), 4);

		$cart_item = WC()->cart->get_cart_item($cart_item_id);
		WC()->cart->add_discount( sanitize_text_field( $coupon_code ));
		$cart_item['line_tax'] = 114.56;

		$checkout = new WC_Dintero_HP_Checkout();
		$checkout->process_cart();
		$this->assertEquals(57280, reset($checkout->order_lines)['amount']);
	}
}
