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
			'a' => 'b'
		));
		$this->assertEquals(array('a' => 'b'), $dintero_meta);
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


}
