<?php

namespace LaterPayClient\Http;

/**
 * HTTP transport interface
 *
 * @package LaterPayClient\Http
 */
interface TransportInterface
{

    /**
     * TransportInterface constructor.
     */
    public function __construct();

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @param string $url
     *
     * @return self
     */
    public function setURL($url);

    /**
     * @return array
     */
    public function getHeaders();

    /**
     * @param array $headers
     *
     * @return self
     */
    public function setHeaders(array $headers = array());

    /**
     * @return array
     */
    public function getData();

    /**
     * @param array $data
     *
     * @return self
     */
    public function setData(array $data = array());

    /**
     * @return array
     */
    public function getOptions();

    /**
     * @param string|array $options
     *
     * @return self
     */
    public function setOptions(array $options = array());

    /**
     * @return null|string
     */
    public function getMethod();

    /**
     * @param $method
     *
     * @return self
     */
    public function setMethod($method);

    /**
     * Self-test whether the transport can be used.
     *
     * @return bool
     */
    public function isAvailable();

    /**
     * Perform a request.
     *
     * @return string With raw HTTP result
     */
    public function call();

    /**
     * @return mixed
     */
    public function getResponse();
}
