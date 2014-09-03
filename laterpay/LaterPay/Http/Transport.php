<?php
/**
 * Base HTTP transport
 *
 * @package Requests
 * @subpackage Transport
 */
interface LaterPay_Http_Transport {
	/**
	 * Perform a request
	 *
	 * @param string  $url     URL to request
	 * @param array   $headers Associative array of request headers
	 * @param string|array $data    Data to send either as the POST body, or as parameters in the URL for a GET/HEAD
	 * @param array   $options Request options
	 * @return string Raw HTTP result
	 */
	public function request( $url, $headers = array(), $data = array(), $options = array() );

	/**
	 * Self-test whether the transport can be used
	 *
	 * @return bool
	 */
	public static function test();
}
