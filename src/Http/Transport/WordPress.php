<?php

namespace LaterPayClient\Http\Transport;

use LaterPayClient\Http\Request;
use LaterPayClient\Http\TransportAbstract;

/**
 * WordPress HTTP transport
 *
 * @package LaterPayClient\Http\Transport
 */
class WordPress extends TransportAbstract
{

    /**
     * @var int
     */
    protected $timeout = 30;

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

        $this->timeout = isset($this->options['timeout']) ? (int)$this->options['timeout'] : $this->timeout;

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
        switch ($this->method) {
            case Request::POST:
            case Request::PUT:
            case Request::PATCH:
                $rawResponse    = wp_remote_post(
                    $this->url,
                    array(
                        'headers' => $this->headers,
                        'body'    => $this->data,
                        'timeout' => $this->timeout,
                    )
                );
                $this->response = wp_remote_retrieve_body($rawResponse);
                break;
            case Request::HEAD:
                $rawResponse    = wp_remote_head(
                    $this->url,
                    array(
                        'headers' => $this->headers,
                        'timeout' => $this->timeout,
                    )
                );
                $this->response = wp_remote_retrieve_body($rawResponse);
                break;
            case Request::GET:
            case Request::DELETE:
            default:
                $this->convertDataToURL();
                $rawResponse    = wp_remote_get(
                    $this->url,
                    array(
                        'headers' => $this->headers,
                        'timeout' => $this->timeout,
                    )
                );
                $this->response = wp_remote_retrieve_body($rawResponse);
                break;
        }

        $response_code = wp_remote_retrieve_response_code($rawResponse);

        if (empty($response_code)) {
            throw new \RuntimeException(
                wp_remote_retrieve_response_message($rawResponse)
            );
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
        return function_exists('wp_remote_get') && function_exists('wp_remote_post');
    }
}