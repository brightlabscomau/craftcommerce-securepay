<?php

namespace brightlabs\securepay\models;

use craft\commerce\models\payments\BasePaymentForm;

/**
 * SecurePay Payment Form
 *
 * Supports both traditional credit card input and JavaScript SDK tokenization
 * following Craft Commerce payment gateway patterns
 *
 * @author Brightlabs
 * @since 1.0
 */
class SecurePayPaymentForm extends BasePaymentForm
{
    /**
     * @var string|null Payment token from JavaScript SDK
     */
    public ?string $token = null;

    /**
     * @var string|null Payment method type (card, apple-pay, etc.)
     */
    public ?string $createdAt = null;

    /**
     * @var array|null DCC selection data
     */
    public ?string $scheme = null;

    
    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['token','createdAt','scheme'], 'required'];

        return $rules;
    }
    /**
     * @inheritdoc
     */

    /**
     * Check if this is a tokenized payment
     */
    public function isTokenizedPayment(): bool
    {
        return !empty($this->token);
    }

    /**
     * Check if this is an Apple Pay payment
     */
    public function isApplePayPayment(): bool
    {
        return $this->paymentMethod === 'apple-pay' || $this->paymentMethod === 'applepay';
    }

    /**
     * Get the payment method description for logging/display
     */
    public function getPaymentMethodDescription(): string
    {
        if ($this->isApplePayPayment()) {
            return 'Apple Pay';
        }

        if ($this->isTokenizedPayment()) {
            return 'Credit Card (Tokenized)';
        }

        return 'Credit Card (Direct)';
    }

} 