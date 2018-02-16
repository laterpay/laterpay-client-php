<?php

namespace LaterPayClient\Http\Transport;

use LaterPayClient\Http\Request;
use LaterPayClient\Http\TransportAbstract;

/**
 * Native HTTP transport.
 *
 * @package LaterPayClient\Http\Transport
 */
class Native extends TransportAbstract
{
    /**
     * @var resource
     */
    protected $context;

    /**
     * @return string
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function call()
    {
        $this
            ->processOptions()
            ->processData()
            ->executeCall();

        return $this->response;
    }

    /**
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    protected function processOptions()
    {
        if (empty($this->url)) {
            throw new \InvalidArgumentException('No URL provided');
        }

        $this->timeout = isset($this->options['timeout']) ? (int)$this->options['timeout'] : 30;

        $this->options['http'] = array(
            'method' => $this->method
        );

        if ( ! empty($this->headers)) {
            $this->options['http']['header'] = implode("\r\n", self::convertToFlatten($this->headers)) . "\r\n";
        }

        return $this;
    }

    /**
     *
     * @return self
     */
    protected function processData()
    {
        switch ($this->method) {
            case Request::POST:
            case Request::PATCH:
            case Request::PUT:
                $this->data = $this->options['http']['content'] = http_build_query($this->data, null, '&');
                break;
            case Request::GET:
            case Request::HEAD:
            case Request::DELETE:
                $this->convertDataToURL();
                break;
            default:
                break;
        }

        return $this;
    }

    /**
     *
     * @return self
     *
     * @throws \RuntimeException
     */
    protected function executeCall()
    {
        $this->context  = stream_context_create($this->options);
        $this->response = file_get_contents($this->url, null, $this->context);

        if ($this->response === false) {
            throw new \RuntimeException('Could not resolve host.');
        }

        return $this;
    }

    /**
     * Check availability of current transport before using.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return function_exists('file_get_contents') && ini_get('allow_url_fopen');
    }
}
