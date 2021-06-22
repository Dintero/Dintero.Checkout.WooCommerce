<?php

/**
 * Class Dintero_HP_Adapter
 */
class Dintero_HP_Adapter
{

	/**
	 * @var string
	 */
	private $api_base_url = 'https://api.dintero.com/v1';

	/**
	 * @var string
	 */
	private $checkout_base_url = 'https://checkout.dintero.com/v1/';

	/**
	 * @return array
	 */
	private function _default_headers()
	{
		return array(
			'Content-type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'User-Agent' => 'Dintero.Checkout.Woocomerce.1.2.1 (+https://github.com/Dintero/Dintero.Checkout.Woocomerce)',
			'Dintero-System-Name' => 'woocommerce',
			'Dintero-System-Version' =>  WC()->version,
			'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
			'Dintero-System-Plugin-Version' => DINTERO_HP_VERSION
		);
	}

	/**
	 * @return Dintero_HP_Request
	 */
	protected function _init_request($access_token = null)
	{
		$request = (new Dintero_HP_Request())
			->set_headers($this->_default_headers());

		if (!empty($access_token)) {
			$request->add_header('Authorization', 'Bearer ' . $access_token);
		}

		return $request;
	}

	/**
	 * @param string $endpoint
	 * @return string
	 */
	private function _endpoint($endpoint)
	{
		return $this->checkout_base_url . trim($endpoint, '/');
	}

	/**
	 * @return false|string
	 */
	public function get_access_token()
	{
		$is_sandbox = WCDHP()->setting()->get('test_mode') === 'yes';
		$client_id = WCDHP()->setting()->get($is_sandbox ? 'test_client_id' : 'production_client_id' );
		$client_secret = WCDHP()->setting()->get($is_sandbox ? 'test_client_secret' : 'production_client_secret');
		$account_id = ($is_sandbox ? 'T' : 'P') . WCDHP()->setting()->get('account_id');
		$request = $this->_init_request()
			->set_auth_params($client_id, $client_secret)
			->set_body(wp_json_encode(array(
				'grant_type' => 'client_credentials',
				'audience'   => sprintf('%s/accounts/%s', $this->api_base_url, $account_id),
			)));

		$response = json_decode(
			wp_remote_retrieve_body(
				_wp_http_get_object()->post(
					sprintf('%s/accounts/%s/auth/token', $this->api_base_url, $account_id),
					Dintero_HP_Request_Builder::instance()->build($request))
			),
			true
		);

		return isset($response['access_token']) ? $response['access_token'] : false;
	}

	/**
	 * @param $session_id
	 * @return array
	 */
	public function get_session($session_id)
	{
		$request = $this->_init_request($this->get_access_token());
		$response = _wp_http_get_object()->get(
			$this->_endpoint(sprintf('sessions/%s', $session_id)),
			Dintero_HP_Request_Builder::instance()->build($request)
		);

		return json_decode(wp_remote_retrieve_body($response), true);
	}

	/**
	 * @param string $transaction_id
	 * @return array
	 */
	public function get_transaction($transaction_id)
	{
		$request = $this->_init_request($this->get_access_token());
		$response = _wp_http_get_object()->get(
			$this->_endpoint(sprintf('/transactions/%s', $transaction_id)),
			Dintero_HP_Request_Builder::instance()->build($request)
		);

		return json_decode(wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Initializing session
	 *
	 * @param array $payload
	 * @return mixed
	 */
	public function init_session($payload)
	{
		$request = $this->_init_request($this->get_access_token());
		$request->set_body(wp_json_encode($payload));

		$response = _wp_http_get_object()->post(
			$this->_endpoint('/sessions-profile'),
			Dintero_HP_Request_Builder::instance()->build($request)
		);

		return json_decode(wp_remote_retrieve_body($response), true);
	}
}
