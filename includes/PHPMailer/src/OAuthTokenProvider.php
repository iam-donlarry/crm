<?php
namespace PHPMailer\PHPMailer;

/**
 * OAuth Token Provider interface.
 * Matches the expected interface in standard PHPMailer.
 */
interface OAuthTokenProvider
{
    /**
     * Get a Sasl-compatible base64-encoded string for OAuth2 authentication.
     *
     * @return string
     */
    public function getOauth64();
}
