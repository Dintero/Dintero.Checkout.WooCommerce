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
	public function test_get_metadata_null() {
		$helper = Dintero_HP_Helper::instance();
		
		$dintero_meta = $helper->get_metadata(null);
		$this->assertEquals(null, $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_get_metadata_empty() {
		$helper = Dintero_HP_Helper::instance();
		
		$dintero_meta = $helper->get_metadata(array());
		$this->assertEquals(null, $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_get_metadata_straight_array() {
		$helper = Dintero_HP_Helper::instance();
		
		$dintero_meta = $helper->get_metadata(array('a', 'b'));
		$this->assertEquals(null, $dintero_meta);
	}
	/**
	 * @group helper
	 */
	public function test_get_metadata_object() {
		$helper = Dintero_HP_Helper::instance();
		
		$dintero_meta = $helper->get_metadata(array(
			'a' => 'b'
		));
		$this->assertEquals(array('a' => 'b'), $dintero_meta);
	}


}
