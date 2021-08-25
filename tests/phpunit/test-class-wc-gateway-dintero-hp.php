<?php
/**
 * WC_Gateway_Dintero_HP class.
 *
 * Testing Checkout functions
 */
class WC_Gateway_Dintero_HP_Test extends WP_UnitTestCase {

	public function creating_order_for_redirect() {
		$checkout = new WC_Gateway_Dintero_HP();
		// These together should of course be 320.04, but were 320.03 previously
		$shipping_cost = 256.03;
		$shipping_tax = 64.01;
		$order = WC_Helper_Order::create_order(1, null, $shipping_cost, $shipping_tax);

		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);

		$expected = array(
			'url' => array(
				'return_url' => 'http://example.org?order-received=11&key='.$order->get_order_key(),
				'callback_url' => 'http://example.org/?wc-api=wc_gateway_dintero_hp',
			),
			'customer' => array(
				'email' => 'admin@example.org',
				'phone_number' => '555-32123',
			),
			'order' => array(
				'amount' => 36004,
				'vat_amount' => 6401,
				'currency' => 'USD',
				'merchant_reference' => '11',
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


}
