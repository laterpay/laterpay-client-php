<?php

namespace LaterPayClient\Auth;

/**
 * Class JWT using for encode and decode data using secret key.
 * In most cases secret key is a Client API key.
 *
 * @see https://github.com/firebase/php-jwt
 * @package LaterPayClient\Auth
 */
class JWT
{

    /**
     * Headers for creating token.
     *
     * @var array
     */
    protected static $headers = array(
        'typ' => 'JWT',
        'alg' => 'HS256',
    );

    /**
     * When checking nbf, iat or expiration times,
     * we want to provide some extra leeway time to
     * account for clock skew.
     */
    protected static $leeway = 30;

    /**
     * JWT constructor is closed because object is static.
     */
    private function __construct()
    {
    }

    /**
     * Method encodes your data and return signed token.
     *
     * @param string $secret
     * @param array $payload
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function encode($secret, array $payload = array())
    {
        if (empty($secret)) {
            throw new \InvalidArgumentException('Key may not be empty');
        }

        $data   = array();
        $data[] = self::encodeJWTData(static::$headers);
        $data[] = self::encodeJWTData($payload);
        $data[] = self::base64URLEncode(hash_hmac('sha256',
            implode('.', $data), $secret, true));

        return implode('.', $data);
    }

    /**
     * Method tries to decode your token and returns array as result.
     *
     * @param string $secret
     * @param string $token
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public static function decode($secret, $token)
    {
        if (empty($secret)) {
            throw new \InvalidArgumentException('Key may not be empty');
        }

        $timestamp = time();
        $tks       = explode('.', $token);

        if (count($tks) !== 3) {
            throw new \UnexpectedValueException('Wrong number of segments');
        }

        list($headb64, $bodyb64, $cryptob64) = $tks;

        if (null === static::jsonDecode(static::base64URLDecode($headb64))) {
            throw new \UnexpectedValueException('Invalid header encoding');
        }
        if (null === $payload = static::jsonDecode(static::base64URLDecode($bodyb64))) {
            throw new \UnexpectedValueException('Invalid claims encoding');
        }
        if (false === ($sig = static::base64URLDecode($cryptob64))) {
            throw new \UnexpectedValueException('Invalid signature encoding');
        }

        // Check the signature
        if ( ! static::verify("$headb64.$bodyb64", $sig, $secret)) {
            throw new \LogicException('Signature verification failed');
        }
        // Check if the nbf if it is defined. This is the time that the
        // token can actually be used. If it's not yet that time, abort.
        if (isset($payload->nbf) && $payload->nbf > ($timestamp + static::$leeway)) {
            throw new \LogicException(
                'Cannot handle token prior to ' . date(\DateTime::ISO8601,
                    $payload->nbf)
            );
        }
        // Check that this token has been created before 'now'. This prevents
        // using tokens that have been created for later use (and haven't
        // correctly used the nbf claim).
        if (isset($payload->iat) && $payload->iat > ($timestamp + static::$leeway)) {
            throw new \LogicException(
                'Cannot handle token prior to ' . date(\DateTime::ISO8601,
                    $payload->iat)
            );
        }
        // Check if this token has expired.
        if (isset($payload->exp) && ($timestamp - static::$leeway) >= $payload->exp) {
            throw new \LogicException('Expired token');
        }

        return (array)$payload;
    }

    /**
     * Encode JWT data to base64 URL.
     *
     * @param $data
     *
     * @return string
     */
    protected static function encodeJWTData($data)
    {
        return static::base64URLEncode(json_encode($data));
    }

    /**
     * URL-safe Base64 Encode.
     *
     * @param string $input
     *
     * @return string
     */
    protected static function base64URLEncode($input)
    {
        return str_replace('=', '',
            strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    protected static function base64URLDecode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input  .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Decode a JSON string into a PHP object.
     *
     * @param string $input JSON string
     *
     * @return object Object representation of JSON string
     */
    protected static function jsonDecode($input)
    {
        if (PHP_VERSION_ID >= 50400 && ! (defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
             * to specify that large ints (like Steam Transaction IDs) should be treated as
             * strings, rather than the PHP default behaviour of converting them to floats.
             */
            $obj = json_decode($input, false, 512, JSON_BIGINT_AS_STRING);
        } else {
            /** Not all servers will support that, however, so for older versions we must
             * manually detect large ints in the JSON string and quote them (thus converting
             *them to strings) before decoding, hence the preg_replace() call.
             */
            $max_int_length       = strlen((string)PHP_INT_MAX) - 1;
            $json_without_bigints = preg_replace('/:\s*(-?\d{' . $max_int_length . ',})/',
                ': "$1"', $input);
            $obj                  = json_decode($json_without_bigints);
        }

        return $obj;
    }

    /**
     * Verify a signature with the message, key and method. Not all methods
     * are symmetric, so we must have a separate verify and sign method.
     *
     * @param string $msg The original message (header and body)
     * @param string $signature The original signature
     * @param string|resource $secret For HS*, a string key works. for RS*,
     *     must be a resource of an openssl public key
     *
     * @return bool
     */
    protected static function verify($msg, $signature, $secret)
    {
        $hash = hash_hmac('sha256', $msg, $secret, true);

        if (function_exists('hash_equals')) {
            return hash_equals($signature, $hash);
        }

        $len    = min(static::safeStrlen($signature),
            static::safeStrlen($hash));
        $status = 0;
        for ($i = 0; $i < $len; $i++) {
            $status |= (ord($signature[$i]) ^ ord($hash[$i]));
        }
        $status |= (static::safeStrlen($signature) ^ static::safeStrlen($hash));

        return ($status === 0);
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string $str
     *
     * @return int
     */
    protected static function safeStrlen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }

        return strlen($str);
    }
}