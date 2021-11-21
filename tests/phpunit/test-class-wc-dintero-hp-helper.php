<?php
/**
 * Dintero_HP_Helper class.
 *
 * Testing helper functions
 */
class Dintero_HP_Helper_Test extends WP_UnitTestCase {

	/**
	 * @group helper
	 */
	public function test_convert_metadata_null() {
		$helper = Dintero_HP_Helper::instance();

		$dintero_meta = $helper->convert_to_dintero_metadata(null);
		$this->assertEquals(null, $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_convert_metadata_empty() {
		$helper = Dintero_HP_Helper::instance();

		$dintero_meta = $helper->convert_to_dintero_metadata(array());
		$this->assertEquals(null, $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_convert_metadata_straight_array() {
		$helper = Dintero_HP_Helper::instance();

		$dintero_meta = $helper->convert_to_dintero_metadata(array('a', 'b'));
		$this->assertEquals(null, $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_convert_metadata_object() {
		$helper = Dintero_HP_Helper::instance();

		$dintero_meta = $helper->convert_to_dintero_metadata(array(
			'a' => 'b',
			'number' => 1,
		));
		$this->assertEquals(array('a' => '"b"', 'number' => '1'), $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_convert_metadata_nested_object() {
		$helper = Dintero_HP_Helper::instance();

		$dintero_meta = $helper->convert_to_dintero_metadata(array(
			'a' => array(
				'foo' => 'bar'
			)
		));
		$this->assertEquals(array('a' => '{"foo":"bar"}'), $dintero_meta);
	}

	/**
	 * @group helper
	 */
	public function test_convert_discount_object() {
		$helper = Dintero_HP_Helper::instance();

		$discount_codes = $helper->convert_to_dintero_discounts(array(
			'2' => 'b'
		));
		$this->assertEquals(array('b'), $discount_codes);
	}

	/**
	 * @group helper
	 */
	public function test_convert_discount_null() {
		$helper = Dintero_HP_Helper::instance();

		$discount_codes = $helper->convert_to_dintero_discounts(null);
		$this->assertEquals(array(), $discount_codes);
	}

	/**
	 * @group helper
	 */
	public function test_convert_discount_normal_array() {
		$helper = Dintero_HP_Helper::instance();

		$discount_codes = $helper->convert_to_dintero_discounts(array(
			'a', 'b'
		));
		$this->assertEquals(array('a', 'b'), $discount_codes);
	}

	/**
	 * @group helper
	 */
	public function test_store_url_https() {
		$helper = Dintero_HP_Helper::instance();

		$store_id = $helper->url_to_store_id('https://example.com');
		$this->assertEquals('example.com', $store_id);
	}

	/**
	 * @group helper
	 */
	public function test_store_url_http_www_trailing_slash() {
		$helper = Dintero_HP_Helper::instance();

		$store_id = $helper->url_to_store_id('http://www.example.com/');
		$this->assertEquals('example.com', $store_id);
	}

	/**
	 * @group helper
	 */
	public function test_dintero_amount() {
		$helper = Dintero_HP_Helper::instance();

		$dintero_amount = $helper->to_dintero_amount(572.8);
		$this->assertEquals(57280, $dintero_amount);

		$dintero_amount = $helper->to_dintero_amount(5730);
		$this->assertEquals(573000, $dintero_amount);
	}

	/**
	 * @group gift_cards
	 */
	public function test_pw_gift_cards()
	{
		WC()->session->set('pw-gift-card-data', array(
			'gift_cards' => array(
				'foo' => 150
			)
		));
		$items = Dintero_HP_Helper::instance()->get_coupon_lines(40000);
		$this->assertEquals(array(
			array(
				'id' => 'pw_gift_cards_foo',
				'line_id' => 'pw_gift_cards_foo',
				'description' => 'Gift card foo',
				'quantity' => 1,
				'amount' => -15000,
				'vat' => 0,
				'vat_amount' => 0,
				'groups' => array(
					array(
						'id' => 'gift_card_usage_added_by_dintero',
						'name' => 'Gift card usage added by Dintero'
					)
				)
			)
		), $items);
	}

	/**
	 * @group gift_cards
	 */
	public function test_pw_gift_cards_more_than_total()
	{
		WC()->session->set('pw-gift-card-data', array(
			'gift_cards' => array(
				'foo' => 150
			)
		));
		$items = Dintero_HP_Helper::instance()->get_coupon_lines(10000);
		$this->assertEquals(array(
			array(
				'id' => 'pw_gift_cards_foo',
				'line_id' => 'pw_gift_cards_foo',
				'description' => 'Gift card foo',
				'quantity' => 1,
				'amount' => -10000,
				'vat' => 0,
				'vat_amount' => 0,
				'groups' => array(
					array(
						'id' => 'gift_card_usage_added_by_dintero',
						'name' => 'Gift card usage added by Dintero'
					)
				)
			)
		), $items);
	}
}
