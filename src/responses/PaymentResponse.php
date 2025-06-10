<?php

namespace brightlabs\securepay\responses;

use craft\commerce\base\RequestResponseInterface;

/**
 * SecurePay Payment Response
 *
 * @author Brightlabs
 * @since 1.0
 */
class PaymentResponse implements RequestResponseInterface
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * Constructor
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        if (isset($this->data['error'])) {
            return false;
        }

        // SecurePay API v2 response patterns
        if (isset($this->data['status'])) {
            return in_array(strtolower($this->data['status']), [
                'approved', 
                'captured', 
                'refunded', 
                'success',
                'completed'
            ]);
        }

        // Check for transaction status
        if (isset($this->data['transaction']['status'])) {
            return in_array(strtolower($this->data['transaction']['status']), [
                'approved', 
                'captured', 
                'refunded', 
                'success',
                'completed'
            ]);
        }

        // Check for payment status
        if (isset($this->data['payment']['status'])) {
            return in_array(strtolower($this->data['payment']['status']), [
                'approved', 
                'captured', 
                'refunded', 
                'success',
                'completed'
            ]);
        }

        // Check response code
        if (isset($this->data['responseCode'])) {
            return in_array($this->data['responseCode'], ['00', '000', 'approved']);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        // Check for 3D Secure redirect
        if (isset($this->data['threeDSecure']['redirectRequired']) && $this->data['threeDSecure']['redirectRequired']) {
            return true;
        }

        // Check for general redirect
        return isset($this->data['redirect_url']) || 
               isset($this->data['redirectUrl']) ||
               isset($this->data['threeDSecure']['redirectUrl']);
    }

    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return $this->data['redirectMethod'] ?? 'GET';
    }

    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        if (isset($this->data['threeDSecure']['redirectData'])) {
            return $this->data['threeDSecure']['redirectData'];
        }

        return $this->data['redirectData'] ?? [];
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        // 3D Secure redirect URL
        if (isset($this->data['threeDSecure']['redirectUrl'])) {
            return $this->data['threeDSecure']['redirectUrl'];
        }

        // General redirect URLs
        return $this->data['redirect_url'] ?? 
               $this->data['redirectUrl'] ?? 
               '';
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        // Try multiple possible reference fields
        return $this->data['txnReference'] ?? 
               $this->data['reference'] ?? 
               $this->data['transactionReference'] ??
               $this->data['transaction']['reference'] ??
               $this->data['payment']['reference'] ??
               $this->data['id'] ??
               '';
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->data['responseCode'] ?? 
               $this->data['code'] ?? 
               $this->data['transaction']['responseCode'] ??
               $this->data['payment']['responseCode'] ??
               '';
    }

    /**
     * @inheritdoc
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        if (isset($this->data['error'])) {
            if (is_array($this->data['error'])) {
                return $this->data['error']['message'] ?? 
                       $this->data['error']['description'] ?? 
                       'Payment error occurred';
            }
            return $this->data['error'];
        }

        // Try multiple possible message fields
        return $this->data['responseText'] ?? 
               $this->data['message'] ?? 
               $this->data['description'] ??
               $this->data['transaction']['responseText'] ??
               $this->data['payment']['responseText'] ??
               $this->data['status'] ??
               '';
    }

    /**
     * @inheritdoc
     */
    public function redirect(): void
    {
        // This method should be implemented if redirect functionality is needed
        // For now, we'll let Craft Commerce handle the redirect
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        if (isset($this->data['status'])) {
            return in_array(strtolower($this->data['status']), [
                'processing', 
                'pending', 
                'in_progress',
                'awaiting_authentication'
            ]);
        }

        if (isset($this->data['transaction']['status'])) {
            return in_array(strtolower($this->data['transaction']['status']), [
                'processing', 
                'pending', 
                'in_progress',
                'awaiting_authentication'
            ]);
        }

        // Check for 3D Secure processing
        if (isset($this->data['threeDSecure']['status'])) {
            return in_array(strtolower($this->data['threeDSecure']['status']), [
                'challenge_required',
                'authenticating',
                'pending'
            ]);
        }

        return false;
    }

    /**
     * Check if this is a successful 3D Secure authentication
     */
    public function isThreeDSecureSuccessful(): bool
    {
        if (!isset($this->data['threeDSecure'])) {
            return false;
        }

        $threeDSecure = $this->data['threeDSecure'];
        
        return isset($threeDSecure['status']) && 
               in_array(strtolower($threeDSecure['status']), ['authenticated', 'success']);
    }

    /**
     * Check if 3D Secure authentication is required
     */
    public function requiresThreeDSecure(): bool
    {
        return isset($this->data['threeDSecure']['redirectRequired']) && 
               $this->data['threeDSecure']['redirectRequired'];
    }

    /**
     * Get fraud detection results
     */
    public function getFraudResult(): ?array
    {
        return $this->data['fraud'] ?? 
               $this->data['fraudResult'] ?? 
               null;
    }

    /**
     * Check if fraud detection flagged this transaction
     */
    public function isFraudulent(): bool
    {
        $fraudResult = $this->getFraudResult();
        
        if (!$fraudResult) {
            return false;
        }

        return isset($fraudResult['decision']) && 
               strtolower($fraudResult['decision']) === 'reject';
    }

    /**
     * Get Dynamic Currency Conversion quote
     */
    public function getDccQuote(): ?array
    {
        return $this->data['dcc'] ?? 
               $this->data['dccQuote'] ?? 
               null;
    }

    /**
     * Check if this transaction used Dynamic Currency Conversion
     */
    public function usesDcc(): bool
    {
        $dccQuote = $this->getDccQuote();
        return $dccQuote !== null && isset($dccQuote['applied']) && $dccQuote['applied'];
    }
} 