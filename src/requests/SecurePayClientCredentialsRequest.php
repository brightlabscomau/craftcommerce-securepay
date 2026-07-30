<?php

namespace brightlabs\securepay\requests;

use SecurePayApi\Request\ClientCredentialsRequest;

/**
 * OAuth client-credentials request using SecurePay's current auth endpoints.
 *
 * The upstream fgct/securepay-api package still points at the retired
 * welcome.api2.*.auspost.com.au URLs; this class uses the spa.pmnts.io hosts.
 */
class SecurePayClientCredentialsRequest extends ClientCredentialsRequest
{
    public const ENDPOINT_AUTH_SANDBOX = 'https://auth.sandbox.spa.pmnts.io/oauth/token';
    public const ENDPOINT_AUTH_LIVE = 'https://auth.spa.pmnts.io/oauth/token';

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $url = $this->isLive
            ? self::ENDPOINT_AUTH_LIVE
            : self::ENDPOINT_AUTH_SANDBOX;

        $data = [
            'grant_type' => 'client_credentials',
            'audience' => 'https://api.payments.auspost.com.au',
        ];

        $headers = [
            self::HEADER_AUTHORIZATION => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
        ];

        return $this->request($url, self::METHOD_POST, $data, $headers, self::CONTENT_TYPE_FORM);
    }
}
