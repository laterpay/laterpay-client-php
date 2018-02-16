laterpay-client-php
===================

[LaterPay](http://www.laterpay.net/) PHP client.

If you're using WordPress then you probably want to look at [laterpay-wordpress-plugin](https://github.com/laterpay/laterpay-wordpress-plugin).

**NOTE** This code is not yet fully documented

Requirements
=========
``PHP ^5.3``

Example
=========
require 'laterpay-client-php/vendor/autoloader.php';

$merchantID = ''; // set your Merchant ID

$APIKey = ''; // set your API key

$region = 'eu'; // set your region

$signUpURL = (new \LaterPayClient\Client($merchantID, $APIKey, $region))->getSignupDialogURL('http://domain.ltd');
