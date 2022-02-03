<?php

/**
 * Class Dintero_HP_Request_Builder
 */
class Dintero_HP_Request_Builder
{
	/**
	 * @var null|Dintero_HP_Request_Builder
	 */
	protected static $instance = null;

	/**
	 * Dintero_HP_Request_Builder constructor.
	 */
	private function __construct()
	{

	}

	/**
	 * Preventing from cloning object
	 */
	private function __clone()
	{

	}

	/**
	 * @return Dintero_HP_Request_Builder
	 */
	public static function instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @param Dintero_HP_Request $request
	 * @return mixed|void
	 */
	public function build(Dintero_HP_Request $request)
	{
		$args = array(
			'headers' => $request->get_headers(),
			'body' => $request->get_body(),
			'timeout' => 30,
		);
		return (array) apply_filters('dhp_request_build_before', $args);
	}
}
