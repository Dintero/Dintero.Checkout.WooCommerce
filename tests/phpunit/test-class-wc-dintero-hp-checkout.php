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

	public function test_capture() {
		// These together should of course be 320.04, but were 320.03 previously
		$shipping_cost = 256.03;
		$shipping_tax = 64.01;

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( 'extra_fee' );
		$fee_item->set_total( '100' );
		$fee_item->set_total_tax('10');
		$product = WC_Helper_Product::create_simple_product();
		$order = WC_Helper_Order::create_order(1, $product, $shipping_cost, $shipping_tax, array($fee_item));
		$order->set_transaction_id('P12345678.abcdefghijklmnop');
		$order->save();

		$transaction = array(
			'amount' => 5000,
			'merchant_reference' => '',
			'merchant_reference_2' => $order->get_id(),
			'status' => 'AUTHORIZED',
		);

		$captured_transaction = array(
			'amount' => 5000,
			'merchant_reference' => '',
			'merchant_reference_2' => $order->get_id(),
			'status' => 'CAPTURED',
		);

		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub
			->expects($this->exactly(1))
			->method('get_transaction')
			->willReturn($transaction);

		$adapter_stub
			->expects($this->exactly(1))
			->method('capture_transaction')
			->with(
				$this->equalTo('P12345678.abcdefghijklmnop'),
				$this->identicalTo(array(
						'amount' => 5000,
						'capture_reference' => ''.$order->get_id(),
						'items' => array(
							array(
								'id' => 'item_1',
								'description' => 'Dummy Product',
								'quantity' => 4,
								'vat_amount' => 0,
								'vat' => 0.0,
								'amount' => 4000,
								'line_id' => '1'
							), array(
								'id' => 'shipping',
								'description' => 'Shipping: Flat rate shipping',
								'quantity' => 1,
								'vat_amount' => 6401,
								'vat' => 25.0,
								'amount' => 32004,
								'line_id' => '2',
							), array(
								'id' => 'fee_1',
								'description' => 'extra_fee',
								'quantity' => 1,
								'vat_amount' => 1000,
								'vat' => 10.0,
								'amount' => 11000,
								'line_id' => 'fee_1',
							))
					)
				))
			->willReturn($captured_transaction);

		$checkout = new WC_Dintero_HP_Checkout();
		$checkout::$_adapter = $adapter_stub;
		$checkout->capture($order);
	}


}
