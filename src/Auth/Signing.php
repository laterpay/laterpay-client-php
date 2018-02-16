<?php

namespace LaterPayClient\Auth;

use LaterPayClient\Http\Request;

/**
 * Class Signing
 *
 * @package LaterPayClient\Auth
 */
class Signing
{

    /**
     * Contains the hash algorithm.
     *
     * @var string
     */
    protected static $algorithm = 'sha224';

    /**
     *
     * @param string $knownStr
     * @param string $givenStr
     *
     * @return boolean
     *
     * @throws \InvalidArgumentException
     */
    protected static function timeIndependentHMACCompare($knownStr, $givenStr)
    {
        if (empty($knownStr)) {
            throw new \InvalidArgumentException('This function cannot safely compare against an empty given string.');
        }

        $res       = strlen($givenStr) ^ strlen($knownStr);
        $given_len = strlen($givenStr);
        $known_len = strlen($knownStr);

        for ($i = 0; $i < $given_len; ++$i) {
            $res |= ord($knownStr[$i % $known_len]) ^ ord($givenStr[$i]);
        }

        return $res === 0;
    }

    /**
     *
     * @param string $secret
     * @param string|array $parts
     *
     * @return string
     */
    public static function createHMAC($secret, $parts)
    {
        if (is_array($parts)) {
            $data = implode('', $parts);
        } else {
            $data = (string)$parts;
        }

        return hash_hmac(static::$algorithm, $data, $secret);
    }

    /**
     *
     * @param string|array $signature
     * @param string $secret
     * @param array $params
     * @param string $url
     * @param string $method
     *
     * @return boolean
     * @throws \InvalidArgumentException
     */
    public static function verify($signature, $secret, $params, $url, $method)
    {
        if (is_array($signature)) {
            $signature = $signature[0];
        }

        $mac = static::sign($secret, $params, $url, $method);

        return static::timeIndependentHMACCompare($signature, $mac);
    }

    /**
     * Request parameter dictionaries are handled in different ways in
     * different libraries, this function is required to ensure we always have
     * something of the format
     * { key: [ value1, value2, ... ] }.
     *
     * @param array $params
     *
     * @return array
     */
    protected static function normaliseParamStructure($params)
    {
        $out = array();

        // this is tricky - either we have (a, b), (a, c) or we have (a, (b, c))
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                // this is (a, (b, c))
                $out[$name] = $value;
            } else {
                // this is (a, b), (a, c)
                if ( ! in_array($name, $out)) {
                    $out[$name] = array();
                }
                $out[$name][] = $value;
            }
        }

        return $out;
    }

    /**
     * Create base message.
     *
     * @param array $params mapping of all parameters that should be signed
     * @param string $url full URL of the target endpoint, no URL parameters
     * @param string $method
     *
     * @return string
     */
    protected static function createBaseMessage($params, $url, $method = Request::POST)
    {
        $msg    = '{method}&{url}&{params}';
        $method = strtoupper($method);

        $data   = array();
        $url    = rawurlencode(utf8_encode($url));
        $params = static::normaliseParamStructure($params);

        $keys = array_keys($params);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $value = $params[$key];
            $key   = rawurlencode(utf8_encode($key));

            $value = (array)$value;

            sort($value, SORT_STRING);
            foreach ($value as $v) {
                if (function_exists('mb_detect_encoding') &&
                    mb_detect_encoding($v, 'UTF-8') !== 'UTF-8') {
                    $encodedValue = rawurlencode(utf8_encode($v));
                } else {
                    $encodedValue = rawurlencode($v);
                }
                $data[] = $key . '=' . $encodedValue;
            }
        }

        $paramStr = rawurlencode(implode('&', $data));
        $result   = str_replace(array('{method}', '{url}', '{params}'),
            array($method, $url, $paramStr), $msg);

        return $result;
    }

    /**
     * Create signature for given 'params', 'url', and HTTP method.
     *
     * How params are canonicalized:
     * - 'urllib.quote' every key and value that will be signed
     * - sort the params list
     * - '&'-join the params
     *
     * @param string $secret secret used to create signature
     * @param array $params mapping of all parameters that should be signed
     * @param string $url full URL of the target endpoint, no URL parameters
     * @param string $method
     *
     * @return string
     */
    protected static function sign($secret, array $params = array(), $url, $method = Request::POST)
    {
        $secret = utf8_encode($secret);

        if (isset($params['hmac'])) {
            unset($params['hmac']);
        }

        if (isset($params['gettoken'])) {
            unset($params['gettoken']);
        }

        $aux = explode('?', $url);
        $url = $aux[0];
        $msg = static::createBaseMessage($params, $url, $method);

        return static::createHMAC($secret, $msg);
    }

    /**
     * Sign and encode a URL 'url' with a 'secret' key called via a HTTP
     * 'method'. It adds the signature to the URL as the URL parameter "hmac"
     * and also adds the required timestamp parameter 'ts' if it's not already
     * in the 'params' dictionary. 'unicode()' instances in params are handled
     * correctly.
     *
     * @param string $secret
     * @param array $params
     * @param string $url
     * @param string $method HTTP method
     *
     * @return string Query params
     */
    public static function signAndEncode($secret, array $params = array(), $url, $method = Request::GET)
    {
        if ( ! isset($params['ts'])) {
            $params['ts'] = (string)time();
        }

        if (isset($params['hmac'])) {
            unset($params['hmac']);
        }

        // get the keys in alphabetical order
        $keys = array_keys($params);
        sort($keys, SORT_STRING);
        $queryPairs = array();
        foreach ($keys as $key) {
            $aux = $params[$key];
            $key = utf8_encode($key);
            $aux = (array)$aux;

            sort($aux, SORT_STRING);
            foreach ($aux as $value) {
                if (function_exists('mb_detect_encoding') &&
                    mb_detect_encoding($value, 'UTF-8') !== 'UTF-8') {
                    $value = rawurlencode(utf8_encode($value));
                }
                $queryPairs[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }

        // build the query string
        $encoded = implode('&', $queryPairs);

        // hash the query string data
        $hmac = static::sign($secret, $params, $url, $method);

        return $encoded . '&hmac=' . $hmac;
    }
}