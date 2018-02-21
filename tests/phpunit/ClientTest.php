<?php

use PHPUnit\Framework\TestCase;
use LaterPayClient\Client;

class ClientTest extends TestCase
{
    protected static $merchantID = '';
    protected static $APIKey = '';
    protected static $region = 'eu';

    protected static function createClient($merchantID = null, $APIKey = null, $region = null, $sandbox = null)
    {
        if (null === $merchantID) {
            $merchantID = self::$merchantID;
        }

        if (null === $APIKey) {
            $APIKey = self::$APIKey;
        }

        if (null === $region) {
            $region = self::$region;
        }

        return new Client($merchantID, $APIKey, $region, $sandbox);
    }

    public function testCreatingClient()
    {
        $this->assertNotNull(self::createClient());
    }

    public function testApiKeys()
    {
        $client = self::createClient(123456789, 987654321);
        $this->assertEquals(123456789, $client->getMerchantID());
        $this->assertEquals(987654321, $client->getAPIKey());
    }

    public function testRegion()
    {
        $client = self::createClient(null, null, 'us');
        $this->assertEquals('us', $client->getRegion());

        $client = self::createClient(null, null, 'US');
        $this->assertEquals('us', $client->getRegion());
    }

    public function testSandboxMode()
    {

        // sandbox is disabled by default
        $this->assertFalse(self::createClient()->isSandboxMode());

        // trying to set sandbox enabled on creating client. Allowed only bool
        $this->assertTrue(self::createClient(null, null, null, true)->isSandboxMode());
        $this->assertFalse(self::createClient(null, null, null, false)->isSandboxMode());
        $this->assertFalse(self::createClient(null, null, null, 'something')->isSandboxMode());
        $this->assertFalse(self::createClient(null, null, null, 1)->isSandboxMode());
        $this->assertFalse(self::createClient(null, null, null, 0)->isSandboxMode());
    }

    public function testTransports()
    {
        // transport exists
        $client = self::createClient();
        $this->assertNotNull($client->getTransport());
        $this->assertNotEmpty(Client::getAvailableTransports());

        // set transport by class name
        $client    = self::createClient();
        $transport = '\LaterPayClient\Http\Transport\Native';
        $client->setTransport($transport);
        $this->assertEquals(new \LaterPayClient\Http\Transport\Native(), $client->getTransport());

        // set transport by object
        $client = self::createClient();
        $client->setTransport(new \LaterPayClient\Http\Transport\Native());
        $this->assertEquals(new \LaterPayClient\Http\Transport\Native(), $client->getTransport());

        // trying to set transport with object that doesn't exists
        $client = self::createClient();
        $client->setTransport(new \LaterPayClient\Http\Transport\Native());
        try {
            $client->setTransport('\SomeClassThatNotExists');
        } catch (\Exception $e) {
        }
        $this->assertEquals(new \LaterPayClient\Http\Transport\Native(), $client->getTransport());

        // trying to set transport that doesn't implement TransportInterface
        $client = self::createClient();
        $client->setTransport(new \LaterPayClient\Http\Transport\Native());
        try {
            $client->setTransport(new someClassThatExists());
        } catch (\Exception $e) {
        }
        $this->assertEquals(new \LaterPayClient\Http\Transport\Native(), $client->getTransport());
    }

    public function testToken()
    {
        // by default token should be null
        $this->assertNull(self::createClient()->getToken());

        // trying to set new token to client
        $token  = bin2hex(random_bytes(22));
        $client = self::createClient();
        $client->setToken($token);
        $this->assertEquals($token, $client->getToken());

        // trying to set initial token from cookie using default token name
        $_COOKIE[$client->getTokenName()] = bin2hex(random_bytes(22));
        $client                           = self::createClient();
        $this->assertEquals($_COOKIE[$client->getTokenName()], $client->getToken());

        // trying to use own cookie to detect token
        $_COOKIE['justTestCookie'] = bin2hex(random_bytes(22));
        $client                    = self::createClient();
        $client->setTokenName('justTestCookie');
        $this->assertEquals($_COOKIE['justTestCookie'], $client->getToken());
    }

    public function testEndpoints()
    {
        $client = $client = self::createClient();

        $this->assertNotEmpty($client->getAccessURL());
        $this->assertNotEmpty($client->getIdentifyURL('/', array(1)));
        $this->assertNotEmpty($client->getLoginDialogURL('/'));
        $this->assertNotEmpty($client->getSignupDialogURL('/'));
        $this->assertNotEmpty($client->getLogoutDialogURL('/'));
        $this->assertNotEmpty($client->getControlsBalanceURL());
        $this->assertNotEmpty($client->getAddURL(array('1')));
        $this->assertNotEmpty($client->getBuyURL(array('1')));
        $this->assertNotEmpty($client->getDonateURL(array('1')));
        $this->assertNotEmpty($client->getContributeURL(array('1')));
        $this->assertNotEmpty($client->getBuyURL(array('1')));
        $this->assertNotEmpty($client->getHealthURL());
    }

    public function testHealth()
    {
        // with sandbox mode
        $client = self::createClient(null, null, null, true);
        $this->assertTrue($client->checkHealth());

        // without sandbox mode
        $client = self::createClient(null, null, null, false);
        $this->assertFalse($client->checkHealth());
    }
}

class someClassThatExists
{
}
