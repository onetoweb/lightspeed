<?php

namespace Onetoweb\Lightspeed;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Utils;
use Onetoweb\Lightspeed\Exception\{RequestException, TokenException, AccountException};
use Onetoweb\Lightspeed\Token;
use DateTime;

/**
 * Lightspeed Api client.
 *
 * @author Jonathan van 't Ende <jvantende@onetoweb.nl>
 * @copyright Onetoweb B.V.
 */
class Client
{
    const BASE_URL = 'https://api.lightspeedapp.com/';
    
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    
    /**
     * @var string
     */
    private $clientId;
    
    /**
     * @var string
     */
    private $clientSecret;
    
    /**
     * @var Token
     */
    private $token;
    
    /**
     * @var callable
     */
    private $updateTokenCallback;
    
    /**
     * @var string
     */
    private $accountId;
    
    /**
     * @var array
     */
    private $bucketLevel;
    
    /**
     * @var callable
     */
    private $bucketLevelCallback;
    
    /**
     * @param string $clientId
     * @param string $clientSecret
     */
    public function __construct(string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }
    
    /**
     * @param array $scope
     * @param string $state
     *
     * @return string
     */
    public function getAuthorizeLink(array $scope, string $state = null): string
    {
        return "https://cloud.lightspeedapp.com/oauth/authorize.php?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => implode('+', $scope),
            'state' => $state,
        ]);
    }
    
    /**
     * @param Token $token
     *
     * @return void
     */
    public function setToken(Token $token): void
    {
        $this->token = $token;
    }
    
    /**
     * @param string $accountId
     *
     * @return void
     */
    public function setAccountId(string $accountId): void
    {
        $this->accountId = $accountId;
    }
    
    /**
     * @param callable $updateTokenCallback
     *
     * @return void
     */
    public function setUpdateTokenCallback(callable $updateTokenCallback): void
    {
        $this->updateTokenCallback = $updateTokenCallback;
    }
    
    /**
     * @return Token|null
     */
    public function getToken(): ?Token
    {
        return $this->token;
    }
    
    /**
     * @param string $code
     *
     * @return void
     */
    public function requestAccessToken(string $code): void
    {
        $accessToken = $this->request(self::METHOD_POST, '/oauth/access_token.php', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ]);
        
        $this->updateToken($accessToken);
    }
    
    /**
     * @param array $accessToken
     *
     * @return void
     */
    private function updateToken(array $accessToken): void
    {
        $expires = new DateTime();
        $expires->setTimestamp(($expires->getTimestamp() + $accessToken['expires_in']) - 1);
        
        if (isset($accessToken['refresh_token'])) {
            $refreshToken = $accessToken['refresh_token'];
        } else {
            $refreshToken = $this->token->getRefreshToken();
        }
        
        $this->token = new Token($accessToken['access_token'], $refreshToken, $expires);
        
        if ($this->updateTokenCallback !== null) {
            
            // update token callback
            ($this->updateTokenCallback)($this->token);
        }
    }
    
    /**
     * @return void
     */
    private function refreshAccessToken(): void
    {
        $accessToken = $this->request(self::METHOD_POST, '/oauth/access_token.php', [
            'refresh_token' => $this->token->getRefreshToken(),
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token'
        ]);
        
        $this->updateToken($accessToken);
    }
    
    /**
     * @param callable $bucketLevelCallback
     *
     * @return void
     */
    public function setBucketLevelCallback(callable $bucketLevelCallback): void
    {
        $this->bucketLevelCallback = $bucketLevelCallback;
    }
    
    /**
     * @return array|null
     */
    public function getBucketLevel(): ?array
    {
        return $this->bucketLevel;
    }
    
    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data = []
     * @param array $query = []
     * @param string $filepath = null
     * @param string $filename = null
     *
     * @return array
     */
    public function accountRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $query = [],
        string $filepath = null,
        string $filename = null
    ): array {
        
        if ($this->accountId == null) {
            throw new AccountException('account id not set');
        }
        
        if ($endpoint) {
            $accountEndpoint = "/API/Account/{$this->accountId}/$endpoint";
        }
        
        return $this->request($method, $accountEndpoint, $data, $query, $filepath, $filename);
    }
    
    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data = []
     * @param array $query = []
     * @param string $filepath = null
     * @param string $filename = null
     *
     * @return array
     */
    public function request(
        string $method,
        string $endpoint,
        array $data = [],
        array $query = [],
        string $filepath = null,
        string $filename = null
    ): array {
        
        $options = [];
        
        if (count($data) > 0) {
            
            if ($filepath !== null) {
                
                // add data to multipart
                $multipart[] = [
                    'name' => 'data',
                    'contents' => json_encode($data)
                ];
                
                // build filepart
                $filepart = [
                    'name' => 'image',
                    'contents' => Utils::tryFopen($filepath, 'r'),
                ];
                
                // add filename to filepart
                if ($filename !== null) {
                    $filepart['filename'] = $filename;
                }
                
                // add filepart to multipart
                $multipart[] = $filepart;
                
                $options[RequestOptions::MULTIPART] = $multipart;
                
            } else {
                $options[RequestOptions::JSON] = $data;
            }
        }
        
        if (count($query) > 0) {
            $options[RequestOptions::QUERY] = $query;
        }
        
        if ($endpoint != '/oauth/access_token.php') {
            
            if ($this->token == null) {
                throw new TokenException('token not set');
            }
            
            if ($this->token->isExpired()) {
                $this->refreshAccessToken();
            }
            
            $options[RequestOptions::HEADERS]['Authorization'] = "Bearer {$this->token->getAccessToken()}";
        }
        
        try {
            
            $guzzleClient = new GuzzleClient([
                'base_uri' => self::BASE_URL,
            ]);
            
            $response = $guzzleClient->request($method, $endpoint, $options);
            
            if ($response->hasHeader('X-LS-API-Bucket-Level')) {
                
                // set bucket Level
                list(
                    $level,
                    $max
                ) = explode('/', $response->getHeaderLine('X-LS-API-Bucket-Level'));
                
                settype($level, 'float');
                settype($max, 'float');
                
                $this->bucketLevel = [
                    'level' => $level,
                    'max' => $max
                ];
                
                if ($this->bucketLevelCallback !== null) {
                    
                    // execute bucket level callback
                    ($this->bucketLevelCallback)($level, $max);
                }
            }
            
            return json_decode($response->getBody()->getContents(), true);
            
        } catch (GuzzleRequestException $exception) {
            
            if ($exception->hasResponse()) {
                throw new RequestException($exception->getResponse()->getBody()->getContents(), $exception->getCode(), $exception);
            }
            
            throw new RequestException('client error', $exception->getCode(), $exception);
        }
    }
}