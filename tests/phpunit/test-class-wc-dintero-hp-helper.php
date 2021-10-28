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


}
