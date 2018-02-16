<?php

namespace LaterPayClient\Http\Transport;

use LaterPayClient\Http\Request;
use LaterPayClient\Http\TransportAbstract;

/**
 * Curl transport.
 *
 * @package LaterPayClient\Http\Transport
 */
class Curl extends TransportAbstract
{

    /**
     * @var Resource
     */
    protected $ch;

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
            ->initConnection()
            ->executeCall()
            ->closeConnection();

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
     */
    protected function initConnection()
    {
        $this->ch = curl_init($this->url);

        switch ($this->method) {
            case Request::POST:
            case Request::PATCH:
            case Request::PUT:
                $this->data = http_build_query($this->data, null, '&');
                curl_setopt($this->ch, CURLOPT_POST, true);
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->data);
                break;
            case Request::GET:
            case Request::HEAD:
            case Request::DELETE:
                $this->convertDataToURL();
                break;
            default:
                break;
        }

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);

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
        $this->response = (string)curl_exec($this->ch);
        $errorMessage   = curl_error($this->ch);

        if ( ! empty($errorMessage)) {
            throw new \RuntimeException($errorMessage);
        }

        return $this;
    }

    /**
     *
     * @return self
     */
    protected function closeConnection()
    {
        curl_close($this->ch);

        return $this;
    }

    /**
     * Check availability of current transport before using.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return extension_loaded('curl');
    }
}
