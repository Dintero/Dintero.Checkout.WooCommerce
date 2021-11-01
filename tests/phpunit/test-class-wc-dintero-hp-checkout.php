<?php
/**
 * WC_Dintero_HP_Checkout class.
 *
 * Testing Checkout functions
 */
class WC_Dintero_HP_Checkout_Test extends WP_UnitTestCase {

	public function test_shipping_amount() {
		WC()->initialize_cart();
		WC()->cart->set_shipping_total(256.03);
		WC()->cart->set_shipping_tax(64.01);
		$checkout = new WC_Dintero_HP_Checkout();
		$shipping_amount = $checkout->get_shipping_amount();
		$this->assertEquals(32004, $shipping_amount);
	}

	public function test_shipping_amount2() {
		WC()->initialize_cart();
		WC()->cart->set_shipping_total(124.96);
		WC()->cart->set_shipping_tax(31.24);
		$checkout = new WC_Dintero_HP_Checkout();
		$shipping_amount = $checkout->get_shipping_amount();
		$this->assertEquals(15620, $shipping_amount);
	}
}
