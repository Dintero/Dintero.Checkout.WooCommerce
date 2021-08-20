<?php
/**
 * WC_Ajax_Shipping_Options_Test class.
 *
 * Testing HTTP functions
 */

class Ajax_Shipping_Options_Test extends WP_UnitTestCase {

	public function test_shipping_callback() {
		$ajax = new WC_AJAX_HP();

		$session_id = 'P12345678.55wd3zjzVCkXXoVFv518q7';
		// Mock query parameters
		$_GET['id'] = $session_id;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// create dummy order and insert into database

		$customer_id = WC()->session->get_customer_id();
		// mock transaction returned from Dintero /v1/transactions/{transaction_id}
		$session = array(
			'id' => $session_id,
			'order' => array(
				'shipping_address' => array(
				)
			),
			'metadata' => array(
				'woo_customer_id' => $customer_id
			)
		);
		WC_Helper_Shipping::create_simple_flat_rate();

		$request_helpers_stub = $this->createMock(RequestHelpers::class);
		$request_helpers_stub->method('get_input')->willReturn(json_encode($session));
		$request_helpers_stub->expects($this->once())
			->method('send_json')
			->with($this->identicalTo(array(
				'shipping_options'=>array(
					array(
						'foo'=>'foo'
					)
				)
			)));

		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_session')->willReturn($session);
		$ajax::$_adapter = $adapter_stub;
		$ajax::$_request_helpers = $request_helpers_stub;

		WC_AJAX_HP::dhp_shipping_options();


		// check that the order has been updated with the new status
	}

}
