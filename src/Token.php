<?php

namespace Onetoweb\Lightspeed;

use Stringable;
use DateTime;

/**
 * Token.
 * 
 * @author Jonathan van 't Ende <jvantende@onetoweb.nl>
 * @copyright Onetoweb B.V.
 */
class Token implements Stringable
{
    /**
     * @param string $accessToken
     * @param string $refreshToken
     * @param int $expireIn
     */
    public function __construct(
        
        #[\SensitiveParameter]
        private string $accessToken,
        
        #[\SensitiveParameter]
        private string $refreshToken,
        
        private DateTime $expires
    ) {
        
    }
    
    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->accessToken;
    }
    
    /**
     * @return string
     */
    public function getAccessToken():  string
    {
        return $this->accessToken;
    }
    
    /**
     * @return string
     */
    public function getRefreshToken():  string
    {
        return $this->refreshToken;
    }
    
    /**
     * @return DateTime
     */
    public function getExpires(): DateTime
    {
        return $this->expires;
    }
    
    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        return (bool) ($this->expires < new DateTime());
    }
}
