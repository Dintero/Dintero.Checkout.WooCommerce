<?php
/**
 * WC_Gateway_Dintero_HP class.
 *
 * Testing Checkout functions
 */
class WC_Gateway_Dintero_HP_Test extends WP_UnitTestCase {

	public function test_creating_order_for_redirect() {
		$checkout = new WC_Gateway_Dintero_HP();
		// These together should of course be 320.04, but were 320.03 previously
		$shipping_cost = 256.03;
		$shipping_tax = 64.01;

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( 'extra_fee' );
		$fee_item->set_total( 100 );

		$order = WC_Helper_Order::create_order(1, null, $shipping_cost, $shipping_tax, array($fee_item));

		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);

		$expected = array(
			'url' => array(
				'return_url' => 'http://example.org?order-received='.$order->get_id().'&key='.$order->get_order_key(),
				'callback_url' => 'http://example.org/?wc-api=wc_gateway_dintero_hp',
			),
			'customer' => array(
				'email' => 'admin@example.org',
				'phone_number' => '555-32123',
			),
			'order' => array(
				'amount' => 46004,
				'vat_amount' => 6401,
				'currency' => 'NOK',
				'merchant_reference' => ''.$order->get_id(),
				'shipping_address' => array(
					'first_name' => '',
					'last_name' => '',
					'address_line' => '',
					'postal_code' => '',
					'postal_place' => '',
					'country' => '',
				),
				'billing_address' => array(
					'first_name' => 'Jeroen',
					'last_name' => 'Sormani',
					'address_line' => 'WooAddress',
					'postal_code' => '12345',
					'postal_place' => 'WooCity',
					'country' => 'US',
				),
				'items' => array(
					array(
						'id' => 'item_1',
						'description' => 'Dummy Product',
						'quantity' => 4,
						'vat_amount' => 0,
						'vat' => 0.0,
						'amount' => 4000,
						'line_id' => '1',
					),
					array(
						'id' => 'shipping',
						'description' => 'Shipping: Flat rate shipping',
						'quantity' => 1,
						'vat_amount' => 6401,
						'vat' => 25.0,
						'amount' => 32004,
						'line_id' => '2',
					),
					array(
						'id' => 'fee_1',
						'description' => 'extra_fee',
						'quantity' => 1,
						'vat_amount' => 0,
						'vat' => 0.0,
						'amount' => 10000,
						'line_id' => 'fee_1',
					)
				)
			),
			'profile_id' => '',
			'metadata' => array(
				'woo_customer_id' => WC()->session->get_customer_id()
			)
		);
		$adapter_stub
			->expects($this->exactly(1))
			->method('init_session')
			->with($this->identicalTo($expected));
		$checkout::$_adapter = $adapter_stub;

		$checkout->process_payment($order->get_id());
	}

	public function test_capture() {
		$checkout = new WC_Gateway_Dintero_HP();
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);

		// These together should of course be 320.04, but were 320.03 previously
		$shipping_cost = 256.03;
		$shipping_tax = 64.01;

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( 'extra_fee' );
		$fee_item->set_total( 100 );
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
								'id' => ''.$product->get_id(),
								'description' => 'Dummy Product',
								'quantity' => 4,
								'vat_amount' => 0,
								'vat' => 0.0,
								'amount' => 4000,
								'line_id' => ''.$product->get_id()
							), array(
								'id' => 'flat_rate_shipping:',
								'description' => ', Shipping: Flat rate shipping',
								'quantity' => 1,
								'vat_amount' => 6401,
								'vat' => 25.0,
								'amount' => 32004,
								'line_id' => 'shipping_method',
							), array(
								'id' => 'fee_1',
								'description' => 'extra_fee',
								'quantity' => 1,
								'vat_amount' => 0,
								'vat' => 0.0,
								'amount' => 10000,
								'line_id' => 'fee_1',
							))
					)
				))
			->willReturn($captured_transaction);
		$checkout::$_adapter = $adapter_stub;

		$checkout->check_status($order->get_id(), '', 'completed');

		// check that the order has been updated with the new status
		$note = wc_get_order_notes(array(
				'order_id' => $order->get_id(),
				'limit'    => 10,
				'orderby'  => 'date_created_gmt',
			)
		);
		$this->assertEquals('Payment captured via Dintero. Transaction ID: P12345678.abcdefghijklmnop', end($note)->content);
	}

	public function test_capture_on_auto_captured() {
		$checkout = new WC_Gateway_Dintero_HP();
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);

		$shipping_cost = 256.03;
		$shipping_tax = 64.01;

		$product = WC_Helper_Product::create_simple_product();

		$order = WC_Helper_Order::create_order(1, $product, $shipping_cost, $shipping_tax);
		$order->set_transaction_id('P12345678.abcdefghijklmnop');
		$order->save();

		$captured_transaction = array(
			'amount' => 5000,
			'merchant_reference' => '',
			'merchant_reference_2' => $order->get_id(),
			'status' => 'CAPTURED',
		);

		$adapter_stub
			->expects($this->exactly(1))
			->method('get_transaction')
			->willReturn($captured_transaction);

		$adapter_stub
			->expects($this->exactly(0))
			->method('capture_transaction')
			->willReturn($captured_transaction);
		$checkout::$_adapter = $adapter_stub;

		$checkout->check_status($order->get_id(), '', 'completed');

		// check that the order has been updated with the new status
		$note = wc_get_order_notes(array(
				'order_id' => $order->get_id(),
				'limit'    => 10,
				'orderby'  => 'date_created_gmt',
			)
		);
		$this->assertEquals('Payment captured via Dintero, already captured from before. Transaction ID: P12345678.abcdefghijklmnop', end($note)->content);
	}


}
