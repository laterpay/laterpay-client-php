<?php

class LaterPay_Http_Client {
	/**
	 * POST method
	 *
	 * @var string
	 */
	const POST = 'POST';

	/**
	 * PUT method
	 *
	 * @var string
	 */
	const PUT = 'PUT';

	/**
	 * GET method
	 *
	 * @var string
	 */
	const GET = 'GET';

	/**
	 * HEAD method
	 *
	 * @var string
	 */
	const HEAD = 'HEAD';

	/**
	 * DELETE method
	 *
	 * @var string
	 */
	const DELETE = 'DELETE';

	/**
	 * PATCH method
	 *
	 * @link http://tools.ietf.org/html/rfc5789
	 * @var string
	 */
	const PATCH = 'PATCH';

	/**
	 * Registered transport classes
	 *
	 * @var array
	 */
	protected static $transports = array(
        'LaterPay_Http_Transport_Wp',
        'LaterPay_Http_Transport_Curl',
        'LaterPay_Http_Transport_Native',
    );

	/**
	 * Selected transport name
	 *
	 * Use {@see get_transport()} instead
	 *
	 * @var string|null
	 */
	public static $transport = null;

	/**
	 * This is a static class, do not instantiate it
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct() {}

	/**
	 * Register a transport
	 *
	 * @param string  $transport Transport class to add, must support the Requests_Transport interface
	 */
	public static function add_transport( $transport ) {
		if ( empty( self::$transports ) ) {
			self::$transports = array(
				'LaterPay_Http_Transport_Native',
			);
		}

		self::$transports = array_merge( self::$transports, array( $transport ) );
	}

	/**
	 * Get a working transport
	 *
	 * @throws Requests_Exception If no valid transport is found (`notransport`)
	 * @return Requests_Transport
	 */
	protected static function get_transport() {
		// Caching code, don't bother testing coverage
		// @codeCoverageIgnoreStart
		if ( self::$transport !== null ) {
			return new self::$transport();
		}
		// @codeCoverageIgnoreEnd

		if ( empty( self::$transports ) ) {
			self::$transports = array(
				'HttpClient_Transport_Native',
			);
		}

		// Find us a working transport
		foreach ( self::$transports as $class ) {
			if ( !class_exists( $class ) )
				continue;

			$result = call_user_func( array( $class, 'test' ) );
			if ( $result ) {
				self::$transport = $class;
				break;
			}
		}
		if ( self::$transport === null ) {
			throw new Exception( 'No working transports found', 'notransport' );
		}

		return new self::$transport();
	}

	/**#@+
	 * @see request()
	 * @param string $url
	 * @param array $headers
	 * @param array $options
	 * @return Requests_Response
	 */
	/**
	 * Send a GET request
	 */
	public static function get( $url, $headers = array(), $options = array() ) {
		return self::request( $url, $headers, null, self::GET, $options );
	}

	/**
	 * Send a HEAD request
	 */
	public static function head( $url, $headers = array(), $options = array() ) {
		return self::request( $url, $headers, null, self::HEAD, $options );
	}

	/**
	 * Send a DELETE request
	 */
	public static function delete( $url, $headers = array(), $options = array() ) {
		return self::request( $url, $headers, null, self::DELETE, $options );
	}
	/**#@-*/

	/**#@+
	 * @see request()
	 * @param string $url
	 * @param array $headers
	 * @param array $data
	 * @param array $options
	 * @return Requests_Response
	 */
	/**
	 * Send a POST request
	 */
	public static function post( $url, $headers = array(), $data = array(), $options = array() ) {
		return self::request( $url, $headers, $data, self::POST, $options );
	}
	/**
	 * Send a PUT request
	 */
	public static function put( $url, $headers = array(), $data = array(), $options = array() ) {
		return self::request( $url, $headers, $data, self::PUT, $options );
	}

	/**
	 * Send a PATCH request
	 *
	 * Note: Unlike {@see post} and {@see put}, `$headers` is required, as the
	 * specification recommends that should send an ETag
	 *
	 * @link http://tools.ietf.org/html/rfc5789
	 */
	public static function patch( $url, $headers, $data = array(), $options = array() ) {
		return self::request( $url, $headers, $data, self::PATCH, $options );
	}
	/**#@-*/

	/**
	 * Main interface for HTTP requests
	 *
	 *
	 * @param string  $url     URL to request
	 * @param array   $headers Extra headers to send with the request
	 * @param array   $data    Data to send either as a query string for GET/HEAD requests, or in the body for POST requests
	 * @param string  $type    HTTP request type (use Requests constants)
	 * @param array   $options Options for the request (see description for more information)
	 * @return Requests_Response
	 */
	public static function request( $url, $headers = array(), $data = array(), $type = self::GET, $options = array() ) {
		if ( empty( $options['type'] ) ) {
			$options['type'] = $type;
		}

		if ( !empty( $options['transport'] ) ) {
			$transport = $options['transport'];

			if ( is_string( $options['transport'] ) ) {
				$transport = new $transport();
			}
		}
		else {
			$transport = self::get_transport();
		}
        
		$response = $transport->request( $url, $headers, $data, $options );
		return $response;
	}

	/**
	 * Convert a key => value array to a 'key: value' array for headers
	 *
	 * @param array   $array Dictionary of header values
	 * @return array List of headers
	 */
	public static function flatten( $array ) {
		$return = array();
		foreach ( $array as $key => $value ) {
			$return[] = "$key: $value";
		}
		return $return;
	}
}
