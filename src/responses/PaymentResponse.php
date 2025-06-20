<?php

namespace brightlabs\securepay\responses;

use craft\commerce\base\RequestResponseInterface;

/**
 * SecurePay Payment Response
 *
 * @author Brightlabs
 * @since 1.0
 */
// successfull data respond
 // Array ( [createdAt] => 2025-06-20T03:43:21.588919675Z [amount] => 86800 [currency] => AUD [status] => paid [bankTransactionId] => 664437 [gatewayResponseCode] => 00 [gatewayResponseMessage] => Transaction successful [customerCode] => anonymous [merchantCode] => 5AR0055 [ip] => 192.168.97.1 [token] => 1738992071975167 [orderId] => 940992 )
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
        // SecurePay API v2 response patterns
        if (!isset($this->data['error']) && isset($this->data['status'])) {
            return $this->data['status'] === 'paid';
        }

        return false;
    }
    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return true;
    }
    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        // Check for general redirect
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        // General redirect URLs
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        $reference = '';
        // Try multiple possible reference fields
        if(isset($this->data['bankTransactionId'])){
            $reference = $this->data['bankTransactionId'];
        }
        return $reference;
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        $code = '';
        if (isset($this->data['errors'])) {
            if(is_array($this->data['errors'])){
                $code = [];
                foreach($this->data['errors'] as $error){
                    $code[] = $error['code'];
                }
                $code = implode(', ', $code);
            }
        }
        elseif(isset($this->data['gatewayResponseCode'])){
            $code = $this->data['gatewayResponseCode'];
        }
        return $code;
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
        $message = '';
        if (isset($this->data['errors'])) {
            if(is_array($this->data['errors'])){
                $message = [];
                foreach($this->data['errors'] as $error){
                    $message[] = $error['detail'];
                }
                $message = implode(', ', $message);
            }
        }
        elseif(isset($this->data['gatewayResponseMessage'])){
            $message = $this->data['gatewayResponseMessage'];
        }
        return $message;
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