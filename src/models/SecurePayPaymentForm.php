<?php

namespace brightlabs\securepay\models;

use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;

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
    public ?string $cardToken = null;
    /**
     * @var string|null Payment Card Scheme
     */
    public ?string $cardScheme = null;

    /**
     * @var string|null Payment Card Expiry Month
     */
    public ?string $cardExpiryMonth = null;

    /**
     * @var string|null Payment Card Expiry Year
     */
    public ?string $cardExpiryYear = null;

    /**
     * @var string|null Payment Card Bin
     */
    public ?string $cardBin = null;

    /**
     * @var string|null Payment Card Last 4
     */
    public ?string $cardLast4 = null;

    /**
     * @var string|null Payment Card Created At
     */
    public ?string $cardCreatedAt = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['cardToken','cardScheme','cardExpiryMonth','cardExpiryYear','cardBin','cardLast4','cardCreatedAt'], 'required'];
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
        return !empty($this->cardToken);
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
    /**
     * Populate the payment form from a payment source
     * @param PaymentSource $paymentSource the source to ue
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
        $cardInfo = json_decode($paymentSource->response, true);
        $this->cardToken = $cardInfo['cardToken'];
        $this->cardScheme = $cardInfo['cardScheme'];
        $this->cardExpiryMonth = $cardInfo['cardExpiryMonth'];
        $this->cardExpiryYear = $cardInfo['cardExpiryYear'];
        $this->cardBin = $cardInfo['cardBin'];
        $this->cardLast4 = $cardInfo['cardLast4'];
        $this->cardCreatedAt = $cardInfo['cardCreatedAt'];
    }

} 