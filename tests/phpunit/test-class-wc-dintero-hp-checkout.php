<?php
/**
 * WC_Dintero_HP_Checkout class.
 *
 * Testing Checkout functions
 */
class WC_Dintero_HP_Checkout_Test extends WP_UnitTestCase
{

	/**
	 * @return void
	 */
	public function tearDown()
	{
		WC()->cart = null;
		if ($product = wc_get_product(wc_get_product_id_by_sku('DUMMY SKU'))) {
			$product->delete();
		}
	}

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
	 * @dataProvider process_cart_with_discount_dataprovider
	 */
	public function test_process_cart_with_discount(
		$products, $coupon_code , $discount_amount, $discount_type, $tax_amount, $result
	) {
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
		update_post_meta( $new_coupon_id, 'coupon_amount', $discount_amount );
		update_post_meta( $new_coupon_id, 'individual_use', 'no' );
		update_post_meta( $new_coupon_id, 'product_ids', '' );
		update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );
		update_post_meta( $new_coupon_id, 'usage_limit', '1' );
		update_post_meta( $new_coupon_id, 'expiry_date', '' );
		update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
		update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

		update_post_meta( $new_coupon_id, 'free_shipping', 'no' );

		WC()->initialize_cart();

		foreach ( $products as $item ) {
			$product = WC_Helper_Product::create_simple_product(true, array(
				'price' => $item['price'],
				'regular_price' => $item['regular_price'],
			));
			$cart_item_id = WC()->cart->add_to_cart($product->get_id(), $item['qty']);
			$cart_item = WC()->cart->get_cart_item($cart_item_id);
			$cart_item['line_tax'] = $tax_amount;
		}

		WC()->cart->add_discount( sanitize_text_field( $coupon_code ));
		$checkout = new WC_Dintero_HP_Checkout();
		$checkout->process_cart();
		foreach ($checkout->order_lines as $item) {
			$this->assertEquals($result, $item['amount']);
		}
	}

	/**
	 * @return array[]
	 */
	public function process_cart_with_discount_dataprovider()
	{
		return array(
			array(
				'products' => array(
					array(
						'sku' => 'dummy1',
						'price' => 179.00,
						'regular_price' => 179.00,
						'qty' => 4,
					)
				),
				'coupon_code' => 'dintero20',
				'discount_amount' => '20',
				'discount_type' => 'percent',
				'tax_amount' => 114.56,
				'result' => 57280
			),
			array(
				'products' => array(
					array(
						'sku' => 'dummy2',
						'price' => 295.00,
						'regular_price' => 295.00,
						'qty' => 2,
					),
					array(
						'sku' => 'dummy3',
						'price' => 295.00,
						'regular_price' => 295.00,
						'qty' => 2,
					)
				),
				'coupon_code' => 'dintero40',
				'discount_amount' => '40',
				'discount_type' => 'percent',
				'tax_amount' => 92.34,
				'result' => 35400
			),
		);
	}
}
