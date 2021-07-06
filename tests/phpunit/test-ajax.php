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

		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$transaction = array(
			'merchant_reference' => '',
			'merchant_reference_2' => $order_id,
			'status' => 'AUTHORIZED',
		);
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_transaction')->willReturn($transaction);
		$ajax::$_adapter = $adapter_stub;

		// perform callback to create order
		$ajax->dhp_create_order();

		// check that the order has been updated with the new status
		$updated_order = wc_get_order( $order_id );
		$this->assertEquals($updated_order->get_status(), 'processing');
	}
}
