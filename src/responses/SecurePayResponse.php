<?php

namespace brightlabs\securepay\responses;

use craft\commerce\base\RequestResponseInterface;

/**
 * SecurePay Payment Response
 *
 * @author Brightlabs
 * @since 1.2.0
 */
class SecurePayResponse implements RequestResponseInterface
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
        if (!isset($this->data['errors']) && isset($this->data['status'])) {
            return $this->data['status'] === 'paid';
        }

        return false;
    }
    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return false;
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
            $message = 'SecurePay: ' . $this->data['gatewayResponseMessage'];
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
} 