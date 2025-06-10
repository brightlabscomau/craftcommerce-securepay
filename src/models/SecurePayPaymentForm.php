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
    public ?string $paymentMethod = null;

    /**
     * @var array|null DCC selection data
     */
    public ?array $dccSelection = null;

    /**
     * @var string|null Device fingerprint for fraud detection
     */
    public ?string $deviceFingerprint = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // If we have a token from the JavaScript SDK, we don't need traditional card validation
        if ($this->token) {
            // Remove required validation for card fields when using token
            $requiredRules = array_filter($rules, function($rule) {
                return !(is_array($rule) && isset($rule[1]) && $rule[1] === 'required');
            });
            
            // Add token validation
            $requiredRules[] = [['token'], 'required'];
            $requiredRules[] = [['token'], 'string'];
            
            return $requiredRules;
        }

        // Traditional validation for direct card entry
        $rules[] = [['paymentMethod'], 'string'];
        $rules[] = [['deviceFingerprint'], 'string'];
        $rules[] = [['dccSelection'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();
        
        $labels['token'] = 'Payment Token';
        $labels['paymentMethod'] = 'Payment Method';
        $labels['dccSelection'] = 'Currency Selection';
        $labels['deviceFingerprint'] = 'Device Fingerprint';

        return $labels;
    }

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

    /**
     * Populate from request data following Commerce patterns
     */
    public function populateFromData(array $data): void
    {
        // Handle standard form population
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Handle special cases for JavaScript SDK data
        if (isset($data['paymentToken'])) {
            $this->token = $data['paymentToken'];
        }

        if (isset($data['paymentMethod'])) {
            $this->paymentMethod = $data['paymentMethod'];
        }

        if (isset($data['dcc-selection'])) {
            $this->dccSelection = [
                'currency' => $data['dcc-selection'],
                'accepted' => true,
            ];
        }
    }
} 