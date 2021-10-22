<?php
/**
 * WC_Dintero_Helper_Test class.
 *
 * @group helpers
 * 
 * Testing Dintero Helper functions
 */
class Dintero_HP_Helper_Test extends WP_UnitTestCase {

	public function test_coupons()
	{
		WC()->session->set('pw-gift-card-data', array(
			'gift_cards' => array(
				'foo' => 150
			)
		));
		$items = Dintero_HP_Helper::instance()->get_coupon_lines();
		echo (json_encode($items));
		$this->assertEquals(array(
			array(
				'id' => 'pw_gift_cards_foo',
				'line_id' => 'pw_gift_cards_foo',
				'description' => 'Gift card foo',
				'quantity' => 1,
				'amount' => -15000,
				'vat' => 0,
				'vat_amount' => 0,
			)
		), $items);
	}

}
