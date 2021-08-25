<?php
/**
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
		WC()->shipping->packages = array(
			array(
				'rates'=>array(new WC_Shipping_Rate('r1', 'rate_1', 256.03, array(64.01), 'rate_1', 1))
			)
		);
		WCDHP()->setting()->update_option('shipping_method_in_iframe', 'yes');
		$adapter_stub = $this->createMock(Dintero_HP_Adapter::class);
		$adapter_stub->method('get_session')->willReturn($session);
		$ajax::$_adapter = $adapter_stub;
		MockPhpStream::register();
		file_put_contents("php://input", json_encode($session));
		WC_AJAX_HP::dhp_shipping_options();
		$this->expectOutputString('32004');
		MockPhpStream::unregister();
	}

}
