<?php

use PHPUnit\Framework\TestCase;

/**
 * Dintero_HP_Helper class.
 *
 * Testing helper functions
 */
class Dintero_HP_Helper_Test extends TestCase {

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


}
