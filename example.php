<?php

require 'vendor/autoload.php';

session_start();

use Onetoweb\Lightspeed\Client;
use Onetoweb\Lightspeed\Token;

// params
$clientId = 'client_id';
$clientSecret = 'client_secret';
$accountId = 'account_id';

// get lightspeed client
$client = new Client($clientId, $clientSecret);
$client->setAccountId($accountId);


// set update token callback
$client->setUpdateTokenCallback(function(Token $token) {
    
    //  store token
    $_SESSION['token'] = [
        'access_token' => $token->getAccessToken(),
        'refresh_token' => $token->getRefreshToken(),
        'expires' => $token->getExpires(),
    ];
});

// set bucket level callback
$client->setBucketLevelCallback(function(float $level, float $max) {
    
    if ($level > ($max / 2)) {
        sleep(30);
    }
    
});


if (isset($_SESSION['token'])) {
    
    // load token from storage
    $token = new Token(
        $_SESSION['token']['access_token'],
        $_SESSION['token']['refresh_token'],
        $_SESSION['token']['expires']
    );
    
    // add token to client
    $client->setToken($token);
    
} elseif (isset($_GET['code'])) {
    
    //  get access token form authorization code
    $client->requestAccessToken($_GET['code']);
    
} else {
    
    // redirect to authorization page to request an authorization code
    header('Location: '.$client->getAuthorizeLink(['employee:all']));
    exit;
}


/**
 * Example api endpoints.
 */

// fetch item
$itemId = 42;
$item = $client->accountRequest(Client::METHOD_GET, "Item/$itemId.json");

