<?php

namespace LaterPayClient\Http;

/**
 * Class TransportAbstract
 *
 * @package LaterPayClient\Http
 */
abstract class TransportAbstract implements TransportInterface
{

    /**
     * @var string
     */
    protected $url;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var array
     */
    protected $headers = array();

    /**
     * @var string
     */
    protected $method = Request::GET;

    /**
     * @var string|null
     */
    protected $response;

    /**
     * TransportAbstract constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return self
     */
    public function setURL($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     *
     * @return self
     */
    public function setHeaders(array $headers = array())
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public function setData(array $data = array())
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     *
     * @return self
     */
    public function setOptions(array $options = array())
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method
     *
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setMethod($method)
    {
        if ( ! in_array($method, array(
            Request::POST,
            Request::PUT,
            Request::GET,
            Request::HEAD,
            Request::DELETE,
            Request::PATCH,
        ), true)) {
            throw new \InvalidArgumentException(sprintf('Method %s not supported',
                $this->method));
        }

        $this->method = $method;

        return $this;
    }


    /**
     * Format a URL given GET data.
     *
     * @return self
     */
    protected function convertDataToURL()
    {
        if (empty($this->data)) {
            return $this;
        }

        $urlParts = parse_url($this->url);

        if (empty($urlParts['query'])) {
            $query = $urlParts['query'] = '';
        } else {
            $query = $urlParts['query'];
        }

        $query .= '&' . http_build_query($this->data, null, '&');
        $query = trim($query, '&');

        if (empty($urlParts['query'])) {
            $this->url .= '?' . $query;
        } else {
            $this->url = str_replace($urlParts['query'], $query, $this->url);
        }

        $this->data = array();

        return $this;
    }

    /**
     * Convert a key => value array to a 'key: value' array for headers.
     *
     * @param array $array dictionary of header values
     *
     * @return array list of headers
     */
    public static function convertToFlatten($array)
    {
        $return = array();
        foreach ($array as $key => $value) {
            $return[] = "$key: $value";
        }

        return $return;
    }

    /**
     * @return null|string
     */
    public function getResponse()
    {
        return $this->response;
    }
}
