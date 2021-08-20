<?php

/**
 * Class RequestHelpers
 *
 * Class to help with request functionality like getting json from POST-request
 * and sending json
 */
class RequestHelpers
{
	public function get_input(): string
	{
		return file_get_contents('php://input');
	}

	public function send_json($response, $status_code = null, $options = 0)
	{
		wp_send_json($response, $status_code, $options);
	}
}
