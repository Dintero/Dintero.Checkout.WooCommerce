<?php
/**
 * WC_Ajax_Test class.
 *
 * Testing HTTP functions
 */
class Ajax_Test extends WP_UnitTestCase {

	public function test_updating_order_with_new_authorization() {
		$ajax = new WC_AJAX_HP();

		// Mock query parameters
		$_GET['transaction_id'] = 'P12345678.55wd4cLGiHcyNrfnCmzeqH';
		$_GET['session_id'] = 'P12345678.55wd3zjzVCkXXoVFv518q7';

		// create dummy order and insert into database
		$existing_order = WC_Helper_Order::create_order();
		$order_id = $existing_order->get_id();

		$customer_id = WC()->session->get_customer_id();
		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$transaction = array(
			'merchant_reference' => '',
			'merchant_reference_2' => $order_id,
			'status' => 'AUTHORIZED',
		);
		$session = array(
			'metadata' => array(
				'woo_customer_id' => $customer_id
			),
		);
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_transaction')->willReturn($transaction);
		$adapter_stub->method('get_session')->willReturn($session);
		$ajax::$_adapter = $adapter_stub;

		// perform callback to create order
		$ajax->dhp_create_order();

		// check that the order has been updated with the new status
		$updated_order = wc_get_order( $order_id );
		$this->assertEquals($updated_order->get_status(), 'processing');
	}

	public function test_updating_order_with_included_session() {
		$ajax = new WC_AJAX_HP();

		// Mock query parameters
		$_GET['transaction_id'] = 'P12345678.55wd4cLGiHcyNrfnCmzeqH';
		$_GET['session_id'] = 'P12345678.55wd3zjzVCkXXoVFv518q7';

		// create dummy order and insert into database
		$existing_order = WC_Helper_Order::create_order();
		$order_id = $existing_order->get_id();

		$customer_id = WC()->session->get_customer_id();
		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$transaction = array(
			'merchant_reference' => '',
			'merchant_reference_2' => $order_id,
			'status' => 'AUTHORIZED',
			'session' => array(
				'metadata' => array(
					'woo_customer_id' => $customer_id
				),
			)
		);
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_transaction')->willReturn($transaction);
		$adapter_stub->expects($this->exactly(0))->method('get_session');
		$ajax::$_adapter = $adapter_stub;

		// perform callback to create order
		$ajax->dhp_create_order();

		// check that the order has been updated with the new status
		$updated_order = wc_get_order( $order_id );
		$this->assertEquals($updated_order->get_status(), 'processing');
	}

	public function test_order_still_on_hold() {
		$ajax = new WC_AJAX_HP();

		// Mock query parameters
		$_GET['transaction_id'] = 'P87654321.55wd4cLGiHcyNrfnCmzeqH';
		$_GET['session_id'] = 'P87654321.55wd3zjzVCkXXoVFv518q7';

		// create dummy order and insert into database
		$existing_order = WC_Helper_Order::create_order();
		$order_id = $existing_order->get_id();

		$customer_id = WC()->session->get_customer_id();
		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$transaction = array(
			'merchant_reference' => '',
			'merchant_reference_2' => $order_id,
			'status' => 'ON_HOLD',
		);
		$session = array(
			'metadata' => array(
				'woo_customer_id' => $customer_id
			),
		);
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_transaction')->willReturn($transaction);
		$adapter_stub->method('get_session')->willReturn($session);
		$ajax::$_adapter = $adapter_stub;

		// perform callback to create order
		$ajax->dhp_create_order();

		// check that the order has been updated with the new status
		$updated_order = wc_get_order( $order_id );
		$this->assertEquals($updated_order->get_status(), 'on-hold');
	}

	public function test_conflict_when_updating() {
		$ajax = new WC_AJAX_HP();
		$shipping_product = WC_Helper_Product::create_simple_product();
		$another_product = WC_Helper_Product::create_simple_product();

		// Mock query parameters
		$_GET['transaction_id'] = 'P87654321.55wd4cLGiHcyNrfnCmzeqH';
		$_GET['session_id'] = 'P87654321.55wd3zjzVCkXXoVFv518q7';

		$customer_id = WC()->session->get_customer_id();
		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$transaction = array(
			'id' => 'P87654321.55wd4cLGiHcyNrfnCmzeqH',
			'payment_product' => 'payex',
			'merchant_reference' => '',
			'merchant_reference_2' => '',
			'status' => 'AUTHORIZED',
			'items' => array(
				array(
					'id' => $shipping_product->get_id(),
					'amount' => 500,
					'vat_amount' => 0,
					'description' => 'shipping',
				),
				array(
					'id' => $another_product->get_id(),
					'amount' => 500,
					'vat_amount' => 20,
					'description' => 'shipping',
					'quantity' => 1
				)
			),
			'amount' => 1000,
			'shipping_option' => array(
				'id' => $shipping_product->get_id(),
				'line_id' => $shipping_product->get_id(),
				'operator_product_id' => $shipping_product->get_id()
			),
			'shipping_address' => array(
				'first_name' => 'Mickey',
				'last_name' => 'Mouse',
				'country' => 'NO',
				'address_line' => 'Scrooge Street 1',
				'postal_place' => 'Andeby',
				'postal_code' => '1337',
				'phone_number' => '+4748059134',
				'email' => 'mickey@disney.com'
			)
		);
		$updated_transaction = array(
			'merchant_reference_2' => 'other_order'
		);
		$session = array(
			'order' => array(
				'vat_amount' => 40
			),
			'metadata' => array(
				'woo_customer_id' => $customer_id
			),
		);
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_transaction')
			->will( $this->onConsecutiveCalls($transaction, $updated_transaction));
		$adapter_stub->method('get_session')->willReturn($session);
		$adapter_stub->method('update_transaction')->willReturn(
			new WP_Error(400, 'body' . 'error' ));
		$ajax::$_adapter = $adapter_stub;

		// perform callback to create order
		$ajax->dhp_create_order();

		// check that the order has been updated with the new status
		$updated_order = wc_get_orders(array(
			'billing_first_name' => 'Mickey',
			'limit' => 1,
			'order' => 'DESC',
		))[0];
		$this->assertEquals($updated_order->get_status(), 'failed');
		$last_note = wc_get_order_notes(array(
			'order_id' => $updated_order->get_id(),
			'limit' => 1,
			'order' => 'DESC',
			'type' => 'internal',
		))[0];
		$this->assertContains('Duplicate order of order other_order.', $last_note->content);
	}

	public function test_create_order_from_transaction() {
		$ajax = new WC_AJAX_HP();
		$shipping_product = WC_Helper_Product::create_simple_product();
		$another_product = WC_Helper_Product::create_simple_product();

		// Mock query parameters
		$_GET['transaction_id'] = 'P87654321.55wd4cLGiHcyNrfnCmzeqH';
		$_GET['session_id'] = 'P87654321.55wd3zjzVCkXXoVFv518q7';

		$customer_id = WC()->session->get_customer_id();
		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$transaction = array(
			'id' => 'P87654321.55wd4cLGiHcyNrfnCmzeqH',
			'payment_product' => 'payex',
			'merchant_reference' => '',
			'merchant_reference_2' => '',
			'status' => 'AUTHORIZED',
			'items' => array(
				array(
					'id' => $shipping_product->get_id(),
					'amount' => 500,
					'vat_amount' => 0,
					'description' => 'shipping',
				),
				array(
					'id' => $another_product->get_id(),
					'amount' => 500,
					'vat_amount' => 20,
					'description' => 'shipping',
					'quantity' => 1
				)
			),
			'amount' => 1000,
			'shipping_option' => array(
				'id' => $shipping_product->get_id(),
				'line_id' => $shipping_product->get_id(),
				'operator_product_id' => $shipping_product->get_id(),
				'metadata' => array(
					'foo' => 'bar'
				)
			),
			'shipping_address' => array(
				'first_name' => 'Dolly',
				'last_name' => 'Duck',
				'country' => 'NO',
				'address_line' => 'Scrooge Street 1',
				'postal_place' => 'Andeby',
				'postal_code' => '1337',
				'phone_number' => '+4748059134',
				'email' => 'mickey@disney.com'
			)
		);
		$session = array(
			'order' => array(
				'vat_amount' => 40
			),
			'metadata' => array(
				'woo_customer_id' => $customer_id
			),
		);
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_transaction')->willReturn($transaction);
		$adapter_stub->method('update_transaction')->willReturn($transaction);
		$adapter_stub->method('get_session')->willReturn($session);
		$ajax::$_adapter = $adapter_stub;

		// perform callback to create order
		$ajax->dhp_create_order();

		// check that the order has been updated with the new status
		$updated_order = wc_get_orders(array(
			'billing_first_name' => 'Dolly',
			'limit' => 1,
			'order' => 'DESC',
		))[0];
		$this->assertEquals('processing', $updated_order->get_status());
		$shipping_methods = $updated_order->get_shipping_methods();
		$shipping_meta = reset($shipping_methods)->get_meta_data();;

		$this->assertEquals(2, count($shipping_meta));
	}

}
