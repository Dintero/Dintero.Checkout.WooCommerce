<?php

class Dintero_HP_Request
{
	/**
	 * @var array
	 */
	protected $_headers = array();

	/**
	 * @var string $payload
	 */
	protected $payload;

	/**
	 * @var string $url
	 */
	protected $url;

	/**
	 * @var string $auth_user
	 */
	protected $auth_user;

	/**
	 * @var string $auth_pass
	 */
	protected $auth_pass;

	/**
	 * @param array $headers
	 * @return $this
	 */
	public function set_headers($headers)
	{
		$this->_headers = (array)$headers;
		return $this;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return $this
	 */
	public function add_header($name, $value)
	{
		$this->_headers[$name] = $value;
		return $this;
	}

	/**
	 * @param string $data
	 * @return $this
	 */
	public function set_body($data)
	{
		$this->payload = (string) $data;
		return $this;
	}

	/**
	 * @param string $user
	 * @param string $pass
	 * @return $this
	 */
	public function set_auth_params($user, $pass)
	{
		$this->add_header('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
		return $this;
	}

	/**
	 * @return array
	 */
	public function get_headers()
	{
		return $this->_headers;
	}

	/**
	 * @return string
	 */
	public function get_auth_user()
	{
		return $this->auth_user;
	}

	/**
	 * @return string
	 */
	public function get_auth_pass()
	{
		return $this->auth_pass;
	}

	/**
	 * @return string
	 */
	public function get_body()
	{
		return $this->payload;
	}

	/**
	 * @return string
	 */
	public function get_url()
	{
		return $this->url;
	}
}
