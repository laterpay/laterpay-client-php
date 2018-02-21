<?php

namespace LaterPayClient;

use LaterPayClient\Auth\JWT;
use LaterPayClient\Auth\Signing;
use LaterPayClient\Http\Request;
use LaterPayClient\Http\TransportInterface;

/**
 * Class Client is main class for work with LaterPay API.
 *
 * @package LaterPayClient
 */
class Client
{
    /**
     * URLs depends on region and live/sandbox mode.
     *
     * @var array
     */
    protected static $URLs = array(
        'eu' => array(
            'live'    => array(
                'root'     => 'https://api.laterpay.net',
                'dialog'   => 'https://web.laterpay.net',
                'merchant' => 'https://merchant.laterpay.net/',
            ),
            'sandbox' => array(
                'root'   => 'https://api.sandbox.laterpaytest.net',
                'dialog' => 'https://web.sandbox.laterpaytest.net',
            ),
        ),
        'us' => array(
            'live'    => array(
                'root'     => 'https://api.uselaterpay.com',
                'dialog'   => 'https://web.uselaterpay.com',
                'merchant' => 'https://web.uselaterpay.com/merchant',
            ),
            'sandbox' => array(
                'root'   => 'https://api.sandbox.uselaterpaytest.com',
                'dialog' => 'https://web.sandbox.uselaterpaytest.com',
            ),
        ),
    );

    /**
     * API root URL for current instance.
     *
     * @var string
     */
    protected $rootURL;

    /**
     * API dialog URL for current instance
     *
     * @var string
     */
    protected $dialogURL;

    /**
     * Merchant ID is required.
     *
     * @var string
     */
    protected $merchantID;

    /**
     * API key is required.
     *
     * @var string
     */
    protected $APIKey;

    /**
     * Region for current instance.
     *
     * @var string
     */
    protected $region;

    /**
     * LaterPay token value.
     *
     * @var null|string
     */
    protected $token;

    /**
     * LaterPay token name for cookies.
     *
     * @var string
     */
    protected static $tokenName = 'laterpay_token';

    /**
     * Registered transport classes that can be used for connect to remote host.
     *
     * @var array
     */
    protected static $transports = array(
        'LaterPayClient\Http\Transport\WordPress',
        'LaterPayClient\Http\Transport\Curl',
        'LaterPayClient\Http\Transport\Native',
    );

    /**
     * Transport that will be used for connection to remote server.
     *
     * @var TransportInterface
     */
    protected $transport;

    /**
     * Is sandbox mode.
     *
     * @var bool
     */
    protected $sandboxMode;

    /**
     * Version of server API.
     *
     * @var int
     */
    protected static $apiVersion = 2;

    /**
     * User Agent header that will be send to remote host.
     *
     * @var string
     */
    protected static $userAgent = 'LaterPay Client - PHP - v0.3';

    /**
     * LaterPayClient constructor.
     *
     * @param string $merchantID Merchant ID
     * @param string $APIKey Merchant's API key
     * @param string $region Merchant's region. "eu" or "us"
     * @param bool $sandboxMode Set sandbox(test) mode for testing
     *
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($merchantID, $APIKey, $region = 'eu', $sandboxMode = false)
    {
        $this->merchantID = (string)$merchantID;
        $this->APIKey     = (string)$APIKey;

        $this
            ->setRegion($region)
            ->setSandboxMode($sandboxMode)
            ->checkTokenInCookie()
            ->prepareTransport()
            ->prepareURLs();
    }

    /**
     * Method checks fist active transport and
     * create an instance of it to further remote calls.
     *
     * @return self
     */
    protected function prepareTransport()
    {
        foreach (static::$transports as $className) {
            /**
             * @var $transport TransportInterface
             */
            $transport = new $className;

            if ($transport->isAvailable()) {
                $this->transport = $transport;

                return $this;
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function prepareURLs()
    {
        $this->rootURL   = static::$URLs[$this->region][$this->sandboxMode ? 'sandbox' : 'live']['root'];
        $this->dialogURL = static::$URLs[$this->region][$this->sandboxMode ? 'sandbox' : 'live']['dialog'];

        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantID()
    {
        return $this->merchantID;
    }

    /**
     * Set cookie with token.
     *
     * @param string $token token key
     *
     * @return self
     */
    public function setToken($token)
    {
        $this->token = $token;

        if ( ! headers_sent()) {
            setcookie(static::$tokenName, $token, strtotime('+1 day'), '/');
        }

        return $this;
    }

    /**
     * Get LaterPay token value
     *
     * @return null|string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param $name
     *
     * @return self
     */
    public function setTokenName($name)
    {
        static::$tokenName = $name;
        $this->checkTokenInCookie();

        return $this;
    }

    /**
     * @return null|string
     */
    public function getTokenName()
    {
        return static::$tokenName;
    }

    /**
     * Delete the token from cookies.
     *
     * @return self
     */
    public function deleteToken()
    {
        if ( ! headers_sent()) {
            setcookie(static::$tokenName, '', time() - 100000, '/');
        }

        unset($_COOKIE[static::$tokenName], $this->token);

        return $this;
    }

    /**
     * @param $region
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    protected function setRegion($region)
    {
        $region = strtolower($region);

        if ( ! in_array($region, array('eu', 'us'), true)) {
            throw new \InvalidArgumentException('Not supported region');
        }

        $this->region = $region;

        $this->prepareURLs();

        return $this;
    }

    /**
     * Get current region
     *
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * @return self
     */
    protected function checkTokenInCookie()
    {
        if ( ! empty($_COOKIE[static::$tokenName])) {
            $this->token = $_COOKIE[static::$tokenName];
        }

        return $this;
    }

    /**
     * Method allows to change default transport or use priority in default transports.
     * In parameter you can pass full class' name (for PHP 5.3) or an object.
     *
     * @param string|TransportInterface $transport
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function setTransport($transport)
    {
        if (is_object($transport) &&
            ! $transport instanceof TransportInterface) {
            throw new \InvalidArgumentException(sprintf('Transport %s is not supported', get_class($transport)));
        }

        if (is_string($transport) &&
            ( ! class_exists($transport) ||
              ! array_key_exists('LaterPayClient\Http\TransportInterface', class_implements($transport)))) {
            throw new \InvalidArgumentException(sprintf('Transport %s is not supported',
                $transport));
        }

        if (is_string($transport)) {
            $transport = new $transport;
        }

        if ($transport->isAvailable()) {
            $this->transport = $transport;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @return array
     */
    public static function getAvailableTransports()
    {
        return static::$transports;
    }

    /**
     * Get API key.
     *
     * @return string|null
     */
    public function getAPIKey()
    {
        return $this->APIKey;
    }

    /**
     * @return bool
     */
    public function isSandboxMode()
    {
        return $this->sandboxMode;
    }

    /**
     * @param bool $sandboxMode
     *
     * @return self
     */
    protected function setSandboxMode($sandboxMode = true)
    {
        $this->sandboxMode = true === $sandboxMode;

        return $this;
    }

    /**
     * Get token redirect URL.
     *
     * @param string $returnURL URL
     *
     * @return string $url
     */
    public function getTokenRedirectURL($returnURL)
    {
        $url    = $this->getTokenURL();
        $params = $this->signAndEncode(
            array(
                'redir' => $returnURL,
                'cp'    => $this->merchantID,
            ),
            $url);
        $url    .= '?' . $params;

        return $url;
    }

    /**
     * Get identify URL.
     *
     * @param string $returnURL
     * @param array $contentIDs
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getIdentifyURL($returnURL, array $contentIDs)
    {
        $url = $this->dialogURL . '/ident';

        $payload = array(
            'back' => $returnURL,
            'ids'  => $contentIDs,
        );

        return $url . '/' . $this->merchantID . '/' . JWT::encode($this->APIKey, $payload);
    }

    /**
     * Get controls balance URL.
     *
     * @param string|null $forcelang
     *
     * @return string $url
     */
    public function getControlsBalanceURL($forcelang = null)
    {
        $data = array('cp' => $this->merchantID);

        if (null !== $forcelang) {
            $data['forcelang'] = $forcelang;
        }

        $data['xdmprefix'] = substr(uniqid('', true), 0, 10);
        $base_url          = $this->dialogURL . '/controls/balance';
        $params            = $this->signAndEncode($data, $base_url);

        return $base_url . '?' . $params;
    }

    /**
     * Get account links URL.
     *
     * @param string|null $show Possible options: ('g', 'gg', 'l', 's', 'ss')
     *     or combination of them
     * @param string|null $cssURL
     * @param string|null $nextURL
     * @param string|null $forceLang
     * @param bool $useJSEvents
     *
     * @return string URL
     */
    public function getAccountLinks(
        $show = null,
        $cssURL = null,
        $nextURL = null,
        $forceLang = null,
        $useJSEvents = false
    ) {
        $data = array(
            'next' => $nextURL,
            'cp'   => $this->merchantID,
        );

        if (null !== $forceLang) {
            $data['forcelang'] = $forceLang;
        }

        if (null !== $cssURL) {
            $data['css'] = $cssURL;
        }

        if (null !== $show) {
            $data['show'] = $show;
        }

        if ($useJSEvents) {
            $data['jsevents'] = '1';
        }

        $data['xdmprefix'] = substr(uniqid('', true), 0, 10);

        $url    = $this->dialogURL . '/controls/links';
        $params = $this->signAndEncode($data, $url);

        return implode('?', array($url, $params));
    }

    /**
     * Get access URL.
     *
     * @return string
     */
    public function getAccessURL()
    {
        return $this->rootURL . '/access';
    }

    /**
     * Get token URL.
     *
     * @return string
     */
    public function getTokenURL()
    {
        return $this->rootURL . '/gettoken';
    }

    /**
     * Get health URL.
     *
     * @return string
     */
    public function getHealthURL()
    {
        return $this->rootURL . '/validatesignature';
    }

    /**
     * Get dialog API url
     *
     * @param string $returnURL
     *
     * @return string
     */
    protected function getDialogAPIURL($returnURL)
    {
        return $this->dialogURL . '/dialog-api?url=' . rawurlencode($returnURL);
    }

    /**
     * Get URL for the LaterPay login form.
     *
     * @param string $returnURL
     * @param boolean $useJSEvents
     *
     * @return string $url
     */
    public function getLoginDialogURL($returnURL, $useJSEvents = false)
    {
        $aux = $useJSEvents ? '&jsevents=1' : '';
        $url = $this->dialogURL . '/account/dialog/login?next=' . rawurlencode($returnURL) . $aux . '&cp=' . $this->merchantID;

        return $this->getDialogAPIURL($url);
    }

    /**
     * Get URL for the LaterPay signup form.
     *
     * @param string $returnURL
     * @param boolean $useJSEvents
     *
     * @return string $url
     */
    public function getSignupDialogURL($returnURL, $useJSEvents = false)
    {
        $aux = $useJSEvents ? '&jsevents=1' : '';
        $url = $this->dialogURL . '/account/dialog/signup?next=' . rawurlencode($returnURL) . $aux . '&cp=' . $this->merchantID;

        return $this->getDialogAPIURL($url);
    }

    /**
     * Get URL for logging out a user from LaterPay.
     *
     * @param string $returnURL
     * @param boolean $useJSEvents
     *
     * @return string $url
     */
    public function getLogoutDialogURL($returnURL, $useJSEvents = false)
    {
        $aux = $useJSEvents ? '&jsevents=1' : '';
        $url = $this->dialogURL . '/account/dialog/logout?next=' . rawurlencode($returnURL) . $aux . '&cp=' . $this->merchantID;

        return $this->getDialogAPIURL($url);
    }

    /**
     * Build purchase url
     *
     * @param array $data
     * @param string $endpoint
     * @param array $options
     *
     * @return string $url
     */
    protected function getWebURL(array $data, $endpoint, array $options = array())
    {
        $default_options = array(
            'dialog'   => true,
            'jsevents' => false,
        );

        // merge with defaults
        $options = array_merge($default_options, $options);

        // add merchant id if not specified
        if ( ! isset($data['cp'])) {
            $data['cp'] = $this->merchantID;
        }

        // force to return lptoken
        $data['return_lptoken'] = 1;

        // jsevent for dialog if specified
        if ($options['jsevents']) {
            $data['jsevents'] = 1;
        }

        // is dialog url
        if ($options['dialog']) {
            $prefix = $this->dialogURL . '/dialog';
        } else {
            $prefix = $this->dialogURL;
        }

        // build purchase url
        $base_url = implode('/', array($prefix, $endpoint));
        $params   = $this->signAndEncode($data, $base_url);

        return $base_url . '?' . $params;
    }

    /**
     * Get purchase url for Pay Now revenue
     *
     * @param array $data
     * @param array $options
     *
     * @return string $url
     */
    public function getBuyURL(array $data, array $options = array())
    {
        return $this->getWebURL($data, 'buy', $options);
    }

    /**
     * Get purchase url for Pay Later revenue
     *
     * @param array $data
     * @param array $options
     *
     * @return string $url
     */
    public function getAddURL(array $data, array $options = array())
    {
        return $this->getWebURL($data, 'add', $options);
    }

    /**
     * Get purchase url for subscriptions.
     *
     * @param $data
     * @param array $options
     *
     * @return string
     */
    public function getSubscriptionURL(array $data, array $options = array())
    {
        return $this->getWebURL($data, 'subscribe', $options);
    }

    /**
     * Get URL for donations.
     *
     * @param array $data
     * @param array $options
     *
     * @return string
     */
    public function getDonateURL(array $data, array $options = array())
    {
        $postfix = isset($options['model']) && $options['model'] === 'sis' ? 'pay_now' : 'pay_later';

        return $this->getWebURL($data, 'donate/' . $postfix, $options);
    }

    /**
     * Get URL for contributions.
     *
     * @param array $data
     * @param array $options
     *
     * @return string
     */
    public function getContributeURL(array $data, array $options = array())
    {
        $postfix = isset($options['model']) && $options['model'] === 'sis' ? 'pay_now' : 'pay_later';

        return $this->getWebURL($data, 'contribute/' . $postfix, $options);
    }

    /**
     * Check if user has access to a given item / given array of items.
     *
     * @param array|string $IDs array with posts ids
     * @param null|string $productKey
     *
     * @return array response
     *
     * @throws \RuntimeException
     */
    public function getAccess($IDs, $productKey = null)
    {
        $IDs = (array)$IDs;

        if ( ! $this->token || empty($IDs)) {
            return array();
        }

        $params = array(
            'lptoken'    => $this->token,
            'cp'         => $this->merchantID,
            'article_id' => $IDs,
        );

        if (null !== $productKey) {
            $params['product'] = $productKey;
        }

        return $this->makeRequest($this->getAccessURL(), $params);
    }

    /**
     * Update token.
     *
     * @param $url
     *
     * @return void
     */
    public function acquireToken($url)
    {
        header('Location: ' . $this->getTokenRedirectURL($url));
        exit;
    }

    /**
     * Send request to $url.
     *
     * @param string $url URL to send request to
     * @param array $params
     * @param string $method
     *
     * @return array $response
     *
     * @throws \RuntimeException
     */
    protected function makeRequest($url, array $params = array(), $method = Request::GET)
    {
        $params  = $this->signAndEncode($params, $url, $method);
        $headers = array(
            'X-LP-APIVersion' => static::$apiVersion,
            'User-Agent'      => static::$userAgent,
        );

        if (null === $this->transport) {
            throw new \RuntimeException('No available transports');
        }

        $response = $this
            ->transport
            ->setURL($url . '?' . $params)
            ->setHeaders($headers)
            ->setMethod($method)
            ->call();

        $response = (array)json_decode($response, true);

        if (empty($response)) {
            throw new \RuntimeException('connection_error');
        }

        if (isset($response['status']) && $response['status'] === 'invalid_token') {
            $this->deleteToken();
        }

        if ( ! empty($response['new_token'])) {
            $this->setToken($response['new_token']);
        }

        return $response;
    }

    /**
     * Sign and encode all request parameters.
     *
     * @param array $params
     * @param $url
     * @param string $method
     *
     * @return string query params
     */
    public function signAndEncode(array $params = array(), $url, $method = Request::GET)
    {
        return Signing::signAndEncode($this->APIKey, $params, $url, $method);
    }

    /**
     * Method to check API availability.
     *
     * @return boolean
     */
    public function checkHealth()
    {
        if (null === $this->transport) {
            return false;
        }

        $headers = array(
            'X-LP-APIVersion' => static::$apiVersion,
            'User-Agent'      => static::$userAgent,
        );

        $url    = $this->getHealthURL();
        $params = $this->signAndEncode(array(
            'salt' => md5(microtime(true)),
            'cp'   => $this->merchantID,
        ), $url);
        $url    .= '?' . $params;

        try {
            $this->transport
                ->setURL($url)
                ->setHeaders($headers)
                ->call();
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }
}