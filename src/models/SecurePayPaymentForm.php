<?php

namespace brightlabs\securepay\models;

use craft\commerce\models\payments\BasePaymentForm;

/**
 * SecurePay Payment Form
 *
 * Supports JavaScript SDK tokenisation
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
     * @var string|null Payment Created At
     */
    public ?string $createdAt = null;

    /**
     * @var string|null Payment Card Scheme
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
     * Check if this is a tokenised payment
     */
    public function isTokenisedPayment(): bool
    {
        return !empty($this->token);
    }

    /**
     * Get the payment method description for logging/display
     */
    public function getPaymentMethodDescription(): string
    {
        if ($this->isTokenisedPayment()) {
            return 'Credit Card (Tokenised)';
        }
        return 'Credit Card';
    }

} 