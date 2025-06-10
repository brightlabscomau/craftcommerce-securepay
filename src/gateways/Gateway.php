<?php

namespace craft\securepay\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\elements\Order;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\securepay\models\SecurePayPaymentForm;
use craft\securepay\responses\PaymentResponse;
use craft\web\Response as WebResponse;
use craft\web\View;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * SecurePay Gateway
 *
 * Following the official Craft Commerce payment gateway patterns
 * @see https://craftcms.com/docs/commerce/5.x/extend/payment-gateway-types.html
 *
 * @author Brightlabs
 * @since 1.0
 */
class Gateway extends BaseGateway
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $clientId = '';

    /**
     * @var string
     */
    public string $clientSecret = '';

    /**
     * @var string
     */
    public string $merchantCode = '';

    /**
     * @var bool
     */
    public bool $sandboxMode = true;

    /**
     * @var bool
     */
    public bool $fraudDetection = false;

    /**
     * @var bool
     */
    public bool $threeDSecure = false;

    /**
     * @var bool
     */
    public bool $applePay = false;

    /**
     * @var bool
     */
    public bool $dynamicCurrencyConversion = false;


    /**
     * @var string Fraud detection provider (fraudguard or aci)
     */
    public string $fraudProvider = 'fraudguard';



    /**
     * @var string Background color for JS SDK
     */
    public string $backgroundColor = '#ffffff';

    /**
     * @var string Label font family for JS SDK
     */
    public string $labelFontFamily = 'Arial, Helvetica, sans-serif';

    /**
     * @var string Label font size for JS SDK
     */
    public string $labelFontSize = '1.1rem';

    /**
     * @var string Label font color for JS SDK
     */
    public string $labelFontColor = '#000080';

    /**
     * @var string Input font family for JS SDK
     */
    public string $inputFontFamily = 'Arial, Helvetica, sans-serif';

    /**
     * @var string Input font size for JS SDK
     */
    public string $inputFontSize = '1.1rem';

    /**
     * @var string Input font color for JS SDK
     */
    public string $inputFontColor = '#000080';

    /**
     * @var array Allowed card types for JS SDK
     */
    public array $allowedCardTypes = ['visa', 'mastercard', 'amex', 'diners'];

    /**
     * @var bool Show card icons in JS SDK
     */
    public bool $showCardIcons = true;

    /**
     * @var bool Show PayPal payments
     */
    public bool $paypalPayments = false;

    /**
     * @var bool Show direct entry payments
     */
    public bool $directEntryPayments = false;

    /**
     * @var bool Show card payments
     */
    public bool $cardPayments = true;

    /**
     * @var string|null Cached access token
     */
    private ?string $accessToken = null;

    /**
     * @var int Token expiration timestamp
     */
    private int $tokenExpiresAt = 0;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'SecurePay');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('securepay/gateway-settings', [
            'gateway' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params = []): ?string
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel(),
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        // Always register JavaScript SDK
        $this->registerJavaScriptSDK($view);
        
        $html = $view->renderTemplate('securepay/payment-form', $params);

        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * Register SecurePay JavaScript SDK following Craft Commerce patterns
     */
    private function registerJavaScriptSDK($view): void
    {
        $baseUrl = $this->sandboxMode 
            ? 'https://payments-stest.npe.auspost.zone'
            : 'https://payments.auspost.net.au';

        // Register the SecurePay JavaScript SDK
        $view->registerJsFile($baseUrl . '/v3/ui/client/securepay-ui.min.js', [
            'defer' => true,
            'id'  => 'securepay-ui-js'
        ]);

        // Initialize SecurePay configuration
        $config = [
            'baseUrl' => $baseUrl,
            'clientId' => $this->clientId,
            'merchantCode' => $this->merchantCode,
            'threeDSecure' => $this->threeDSecure,
            'fraudDetection' => $this->fraudDetection,
            'applePay' => $this->applePay,
            'dynamicCurrencyConversion' => $this->dynamicCurrencyConversion,
        ];

        $js = "
        window.securePayConfig = " . Json::encode($config) . ";
                // Initialize SecurePay when DOM is loaded
                document.addEventListener('DOMContentLoaded', function() {
                window.mySecurePayUI = new securePayUI.init({
                containerId: 'securepay-card-component',
                scriptId: 'securepay-ui-js',
                clientId: window.securePayConfig.clientId,
                merchantCode: window.securePayConfig.merchantCode,
                style: {
                    backgroundColor: '#" . $this->backgroundColor . "',
                    label: {
                    font: {
                        family: '" . $this->labelFontFamily . "',
                        size: '" . $this->labelFontSize . "',
                        color: '#" . $this->labelFontColor . "'
                    }
                    },
                    input: {
                        font: {
                            family: '" . $this->inputFontFamily . "',
                            size: '" . $this->inputFontSize . "',
                            color: '#" . $this->inputFontColor . "'
                        }
                    }
                },
                card: { // card specific config options / callbacks
                    showCardIcons: " . ($this->showCardIcons ? 'true' : 'false') . ",
                    allowedCardTypes: " . Json::encode($this->allowedCardTypes) . ",
                    onTokeniseSuccess: function(tokenisedCard) {
                        alert(1);
                        console.log(tokenisedCard);
                        // card was successfully tokenised
                        // here you could make a payment using the SecurePay API (via your application server)
                    },
                    onTokeniseError: function(errors) {
                        alert(2);
                        console.log(errors);
                        // error while tokenising card 
                    }
                }
            });
        });
        ";

        $view->registerJs($js, View::POS_HEAD);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new SecurePayPaymentForm();
    }

    /**
     * @inheritdoc
     * Check if this gateway is available for the given order
     */
    public function availableForUseWithOrder(Order $order): bool
    {
        // Log the availability check for debugging
        Craft::info('SecurePay availability check for order ID: ' . $order->id, __METHOD__);
        
        // Basic validation - must have credentials
        if (!$this->clientId || !$this->clientSecret || !$this->merchantCode) {
            Craft::info('SecurePay unavailable: Missing credentials (clientId: ' . ($this->clientId ? 'set' : 'missing') . 
                       ', clientSecret: ' . ($this->clientSecret ? 'set' : 'missing') . 
                       ', merchantCode: ' . ($this->merchantCode ? 'set' : 'missing') . ')', __METHOD__);
            return false;
        }

        // Check if gateway is enabled
        if (!$this->enabled) {
            Craft::info('SecurePay unavailable: Gateway is disabled', __METHOD__);
            return false;
        }

        // Don't allow $0 transactions (but allow partial payments)
        $outstandingBalance = $order->getOutstandingBalance();
        if ($outstandingBalance <= 0) {
            Craft::info('SecurePay unavailable: Order has no outstanding balance (' . $outstandingBalance . ')', __METHOD__);
            return false;
        }

        // Check parent availability
        $parentAvailable = parent::availableForUseWithOrder($order);
        if (!$parentAvailable) {
            Craft::info('SecurePay unavailable: Parent gateway check failed', __METHOD__);
            return false;
        }

        // Additional business logic can be added here
        // For example, restrict to certain countries:
        // if ($order->billingAddress && $order->billingAddress->countryCode !== 'AU') {
        //     Craft::info('SecurePay unavailable: Country restriction (country: ' . $order->billingAddress->countryCode . ')', __METHOD__);
        //     return false;
        // }

        Craft::info('SecurePay available for order ID: ' . $order->id, __METHOD__);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return $this->createPayment($transaction, $form, false);
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        try {
            $captureData = [
                'amount' => $this->convertAmount($transaction->paymentAmount),
                'currency' => $transaction->paymentCurrency,
            ];

            $response = $this->sendRequest('POST', '/v2/payments/preauth/' . $reference . '/capture', $captureData);
            
            return new PaymentResponse($response);
        } catch (\Exception $e) {
            Craft::error('SecurePay capture error: ' . $e->getMessage(), __METHOD__);
            return new PaymentResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        // For 3D Secure completions or webhook-driven completions
        return $this->getTransactionStatus($transaction);
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        // For 3D Secure completions or webhook-driven completions
        return $this->getTransactionStatus($transaction);
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        // SecurePay doesn't support stored payment methods in this basic implementation
        // This could be extended to support payment instruments in the future
        throw new \Exception('Payment sources are not supported by this gateway.');
    }

    /**
     * @inheritdoc
     */
    public function deletePaymentSource($token): bool
    {
        // Would delete stored payment instrument if supported
        return false;
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return $this->createPayment($transaction, $form, true);
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        try {
            $refundData = [
                'amount' => $this->convertAmount($transaction->paymentAmount),
                'currency' => $transaction->paymentCurrency,
                'reason' => 'Refund requested',
            ];

            $response = $this->sendRequest('POST', '/v2/payments/' . $transaction->reference . '/refund', $refundData);
            
            return new PaymentResponse($response);
        } catch (\Exception $e) {
            Craft::error('SecurePay refund error: ' . $e->getMessage(), __METHOD__);
            return new PaymentResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        $response = Craft::$app->getResponse();
        
        try {
            $request = Craft::$app->getRequest();
            $body = $request->getRawBody();
            $data = Json::decode($body);

            // Verify webhook signature if SecurePay provides one
            // This would be gateway-specific implementation

            // Process the webhook data
            $this->handleWebhookEvent($data);

            $response->setStatusCode(200);
            $response->data = 'OK';
        } catch (\Exception $e) {
            Craft::error('SecurePay webhook error: ' . $e->getMessage(), __METHOD__);
            $response->setStatusCode(400);
            $response->data = 'Error processing webhook';
        }

        return $response;
    }

    // Support Methods (Required by Commerce)
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return true; // For 3D Secure and webhook completions
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return true; // For 3D Secure and webhook completions
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return false; // Could be true if implementing payment instruments
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true; // For 3D Secure, fraud detection, and async notifications
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialPayments(): bool
    {
        return true;
    }

    // Configuration Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getPaymentTypeOptions(): array
    {
        return [
            'purchase' => Craft::t('commerce', 'Purchase (Immediate Capture)'),
            'authorize' => Craft::t('commerce', 'Authorize (Capture Later)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'clientId' => 'Client ID',
            'clientSecret' => 'Client Secret', 
            'merchantCode' => 'Merchant Code',
            'sandboxMode' => 'Sandbox Mode',
            'fraudDetection' => 'Fraud Detection',
            'threeDSecure' => '3D Secure',
            'applePay' => 'Apple Pay',
            'dynamicCurrencyConversion' => 'Dynamic Currency Conversion',
            'fraudProvider' => 'Fraud Detection Provider',
            'backgroundColor' => 'Background Color',
            'labelFontFamily' => 'Label Font Family',
            'labelFontSize' => 'Label Font Size',
            'labelFontColor' => 'Label Font Color',
            'inputFontFamily' => 'Input Font Family',
            'inputFontSize' => 'Input Font Size',
            'inputFontColor' => 'Input Font Color',
            'allowedCardTypes' => 'Allowed Card Types',
            'showCardIcons' => 'Show Card Icons',
            'paypalPayments' => 'PayPal Payments',
            'directEntryPayments' => 'Direct Entry Payments',
            'cardPayments' => 'Card Payments',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['clientId', 'clientSecret', 'merchantCode'], 'required'];
        $rules[] = [['clientId', 'clientSecret', 'merchantCode', 'fraudProvider', 'paymentType', 'backgroundColor', 'labelFontFamily', 'labelFontSize', 'labelFontColor', 'inputFontFamily', 'inputFontSize', 'inputFontColor'], 'string'];
        $rules[] = [['sandboxMode', 'fraudDetection', 'threeDSecure', 'applePay', 'dynamicCurrencyConversion', 'showCardIcons', 'paypalPayments', 'directEntryPayments', 'cardPayments'], 'boolean'];
        $rules[] = [['fraudProvider'], 'in', 'range' => ['fraudguard', 'aci']];
        $rules[] = [['paymentType'], 'in', 'range' => ['purchase', 'authorize']];
        $rules[] = [['allowedCardTypes'], 'each', 'rule' => ['in', 'range' => ['visa', 'mastercard', 'amex', 'diners']]];

        return $rules;
    }

    // Private Methods
    // =========================================================================

    /**
     * Create a payment using SecurePay API following Commerce patterns
     */
    private function createPayment(Transaction $transaction, BasePaymentForm $form, bool $capture): RequestResponseInterface
    {
        try {
            $order = $transaction->getOrder();

            // Prepare payment data according to SecurePay API documentation
            $paymentData = [
                'merchant' => [
                    'code' => $this->merchantCode,
                ],
                'customer' => [
                    'customerNumber' => $order->customerId ?: StringHelper::randomString(8),
                    'firstName' => $order->billingAddress->firstName ?? '',
                    'lastName' => $order->billingAddress->lastName ?? '',
                    'email' => $order->email,
                    'phone' => $order->billingAddress->phone ?? '',
                ],
                'transaction' => [
                    'reference' => $transaction->hash,
                    'amount' => $this->convertAmount($transaction->paymentAmount),
                    'currency' => $transaction->paymentCurrency,
                    'capture' => $capture,
                    'description' => 'Order #' . $order->number,
                ],
            ];

            // Handle JavaScript SDK tokenized payments
            if (!empty($form->token)) {
                $paymentData['payment'] = [
                    'token' => $form->token,
                ];
            } else {
                // Direct card payment (when not using JS SDK)
                $paymentData['payment'] = [
                    'card' => [
                        'number' => $form->number,
                        'expiryMonth' => str_pad($form->month, 2, '0', STR_PAD_LEFT),
                        'expiryYear' => $form->year,
                        'cvv' => $form->cvv,
                        'cardHolderName' => trim($form->firstName . ' ' . $form->lastName),
                    ],
                ];
            }

            // Add billing address
            if ($order->billingAddress) {
                $paymentData['billing'] = [
                    'address' => [
                        'line1' => $order->billingAddress->address1,
                        'line2' => $order->billingAddress->address2 ?? '',
                        'city' => $order->billingAddress->city,
                        'state' => $order->billingAddress->stateText,
                        'postcode' => $order->billingAddress->zipCode,
                        'country' => $order->billingAddress->countryCode,
                    ],
                ];
            }

            // Add shipping address if different from billing
            if ($order->shippingAddress && !$order->hasMatchingAddresses()) {
                $paymentData['shipping'] = [
                    'address' => [
                        'line1' => $order->shippingAddress->address1,
                        'line2' => $order->shippingAddress->address2 ?? '',
                        'city' => $order->shippingAddress->city,
                        'state' => $order->shippingAddress->stateText,
                        'postcode' => $order->shippingAddress->zipCode,
                        'country' => $order->shippingAddress->countryCode,
                    ],
                ];
            }

            // Add 3D Secure configuration
            if ($this->threeDSecure) {
                $paymentData['threeDSecure'] = [
                    'enabled' => true,
                    'challengeIndicator' => '01', // No preference
                    'returnUrl' => UrlHelper::actionUrl('commerce/payments/complete-payment', [
                        'commerceTransactionHash' => $transaction->hash
                    ]),
                ];
            }

            // Add fraud detection configuration
            if ($this->fraudDetection) {
                $paymentData['fraud'] = [
                    'enabled' => true,
                    'provider' => $this->fraudProvider,
                ];
            }

            // Add DCC configuration
            if ($this->dynamicCurrencyConversion) {
                $paymentData['dcc'] = [
                    'enabled' => true,
                ];
            }

            $response = $this->sendRequest('POST', '/v2/payments', $paymentData);
            
            Craft::info('SecurePay payment created: ' . ($response['txnReference'] ?? 'unknown'), __METHOD__);
            
            return new PaymentResponse($response);
        } catch (\Exception $e) {
            Craft::error('SecurePay payment error: ' . $e->getMessage(), __METHOD__);
            return new PaymentResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get transaction status for completion methods
     */
    private function getTransactionStatus(Transaction $transaction): RequestResponseInterface
    {
        try {
            if (!$transaction->reference) {
                throw new \Exception('No transaction reference available');
            }

            $response = $this->sendRequest('GET', '/v2/payments/' . $transaction->reference);
            
            return new PaymentResponse($response);
        } catch (\Exception $e) {
            Craft::error('SecurePay status check error: ' . $e->getMessage(), __METHOD__);
            return new PaymentResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle incoming webhook events
     */
    private function handleWebhookEvent(array $data): void
    {
        // Implementation would depend on SecurePay webhook format
        // Common events: payment.completed, payment.failed, 3ds.completed, etc.
        
        if (isset($data['eventType']) && isset($data['transactionReference'])) {
            Craft::info('SecurePay webhook received: ' . $data['eventType'] . ' for ' . $data['transactionReference'], __METHOD__);
            
            // Process different event types
            switch ($data['eventType']) {
                case 'payment.completed':
                case '3ds.completed':
                    // Update transaction status
                    break;
                case 'payment.failed':
                case '3ds.failed':
                    // Handle failure
                    break;
                default:
                    Craft::warning('Unknown SecurePay webhook event: ' . $data['eventType'], __METHOD__);
            }
        }
    }

    /**
     * Get access token for API requests
     */
    private function getAccessToken(): string
    {
        // Check if we have a valid token
        if ($this->accessToken && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        // Get new token
        $client = new Client();
        $authUrl = $this->sandboxMode 
            ? 'https://welcome.api2.sandbox.auspost.com.au/oauth/token'
            : 'https://welcome.api2.auspost.com.au/oauth/token';

        try {
            $response = $client->post($authUrl, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'audience' => 'https://api.payments.auspost.com.au',
                ],
            ]);

            $body = Json::decode($response->getBody()->getContents());
            
            $this->accessToken = $body['access_token'];
            $this->tokenExpiresAt = time() + ($body['expires_in'] - 300); // Subtract 5 minutes for safety

            return $this->accessToken;
        } catch (RequestException $e) {
            throw new \Exception('Failed to get access token: ' . $e->getMessage());
        }
    }

    /**
     * Send request to SecurePay API
     */
    private function sendRequest(string $method, string $endpoint, array $data = []): array
    {
        $baseUrl = $this->sandboxMode 
            ? 'https://payments-stest.npe.auspost.zone'
            : 'https://payments.auspost.net.au';

        $client = new Client();
        $accessToken = $this->getAccessToken();

        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $client->request($method, $baseUrl . $endpoint, $options);

            return Json::decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            $message = $e->getMessage();
            
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                try {
                    $responseData = Json::decode($responseBody);
                    $message = $responseData['message'] ?? $message;
                } catch (\Exception $decodeError) {
                    // Use original message if JSON decode fails
                }
            }

            throw new \Exception($message);
        }
    }

    /**
     * Convert amount to cents (SecurePay expects amounts in cents)
     */
    private function convertAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }
} 