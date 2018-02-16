<?php

namespace LaterPayClient\Http;

/**
 * Class Request describes methods that allowed in current library
 *
 * @package LaterPayClient\Http
 */
class Request
{

    /**
     * @var string
     */
    const POST = 'POST';

    /**
     * @var string
     */
    const PUT = 'PUT';

    /**
     * @var string
     */
    const GET = 'GET';

    /**
     * @var string
     */
    const HEAD = 'HEAD';

    /**
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
     * Request constructor.
     */
    protected function __construct()
    {
    }
}