<?php

namespace brightlabs\securepay\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\elements\Order;
use craft\helpers\Json;
use brightlabs\securepay\models\SecurePayPaymentForm;
use brightlabs\securepay\responses\SecurePayResponse;
use craft\web\Response as WebResponse;
use craft\web\View;
use yii\base\Exception;
use SecurePayApi\Endpoint;
use SecurePayApi\Model\Credential;
use SecurePayApi\Request\ClientCredentialsRequest;
use SecurePayApi\Request\CardPayment\CreatePaymentRequest;
use SecurePayApi\Request\CardPayment\RefundPaymentRequest;
use SecurePayApi\Request\CardPayment\CreatePreAuthRequest;
use SecurePayApi\Request\CardPayment\CapturePreAuthRequest;
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
     * @var string Background colour for JS SDK
     */
    public string $backgroundColour = '#ffffff';

    /**
     * @var string Label font family for JS SDK
     */
    public string $labelFontFamily = 'Arial, Helvetica, sans-serif';

    /**
     * @var string Label font size for JS SDK
     */
    public string $labelFontSize = '1.1rem';

    /**
     * @var string Label font colour for JS SDK
     */
    public string $labelFontColour = '#000080';

    /**
     * @var string Input font family for JS SDK
     */
    public string $inputFontFamily = 'Arial, Helvetica, sans-serif';

    /**
     * @var string Input font size for JS SDK
     */
    public string $inputFontSize = '1.1rem';

    /**
     * @var string Input font colour for JS SDK
     */
    public string $inputFontColour = '#000080';

    /**
     * @var array Allowed card types for JS SDK
     */
    public array $allowedCardTypes = ['visa', 'mastercard', 'amex', 'diners'];

    /**
     * @var bool Show card icons in JS SDK
     */
    public bool $showCardIcons = true;

    /**
     * @var bool Show card payments
     */
    public bool $cardPayments = true;

    /**
     * @var int Token expiration timestamp
     */
    private int $tokenExpiresAt = 0;
    /**
     * @var Credential|null Credential instance
     */
    private ?Credential $credential = null;

    /**
     * @var string|null tokenised card token
     */
    private ?string $cardTokenise = null;
    /**
     * @var string|null tokenised card Created At
     */
    private ?string $cardCreatedAt = null;
    /**
     * @var string|null tokenised card Scheme
     */
    private ?string $cardScheme = null;

    private string $defaultCurrency = 'AUD';
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * for displaying the name of the gateway in the admin panel
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'SecurePay');
    }

    
    /**
     * @inheritdoc
     * for displaying the settings in the admin panel
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
            ? Endpoint::ENDPOINT_API_SANDBOX
            : Endpoint::ENDPOINT_API_LIVE;
            
        $jsUrl = $this->sandboxMode 
            ? Endpoint::URL_SANDBOX_SCRIPT
            : Endpoint::URL_LIVE_SCRIPT;

        // Register the SecurePay JavaScript SDK
        $view->registerScript('', View::POS_END, [
            'src' => $jsUrl,
            'id'  => 'securepay-ui-js'
        ]); 
        // Initialize SecurePay configuration
        $config = [
            'baseUrl' => $baseUrl,
            'clientId' => $this->clientId,
            'merchantCode' => $this->merchantCode,
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
                    backgroundColour: '#" . $this->backgroundColour . "',
                    label: {
                    font: {
                        family: '" . $this->labelFontFamily . "',
                        size: '" . $this->labelFontSize . "',
                        color: '#" . $this->labelFontColour . "'
                    }
                    },
                    input: {
                        font: {
                            family: '" . $this->inputFontFamily . "',
                            size: '" . $this->inputFontSize . "',
                            color: '#" . $this->inputFontColour . "'
                        }
                    }
                },
                card: { // card specific config options / callbacks
                    showCardIcons: " . ($this->showCardIcons ? 'true' : 'false') . ",
                    allowedCardTypes: " . Json::encode($this->allowedCardTypes) . ",
                    onFormValidityChange: function(valid) {
                        window.mySecurePayUI.tokenise();
                        
                    },
                    onTokeniseSuccess: async function(tokenisedCard) {
                        console.log(tokenisedCard);
                        document.getElementById('cartToken').value = tokenisedCard.token;
                        document.getElementById('cartCreatedAt').value = tokenisedCard.createdAt;
                        document.getElementById('cartScheme').value = tokenisedCard.scheme;
                    },
                    onTokeniseError: function(errors) {
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
        $this->cardTokenise = Craft::$app->getRequest()->getBodyParam('token');
        $this->cardCreatedAt = Craft::$app->getRequest()->getBodyParam('createdAt');
        $this->cardScheme = Craft::$app->getRequest()->getBodyParam('scheme');
        $securePayPaymentForm = new SecurePayPaymentForm();
        $securePayPaymentForm->token = $this->cardTokenise;
        $securePayPaymentForm->createdAt = $this->cardCreatedAt;
        $securePayPaymentForm->scheme = $this->cardScheme;
        return $securePayPaymentForm;
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
        if (!$this->isFrontendEnabled) {
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
        return $this->authorisePayment($transaction, $form);
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return $this->capturePayment($transaction, $reference);

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
        return $this->refundPayment($transaction);
    }
    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new SecurePayResponse(['error' => 'Complete Authorize not supported']);
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
        return false; // For 3D Secure and webhook completions
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
            'authorize' => Craft::t('commerce', 'Authorise (Capture Later)'),
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
            'backgroundColour' => 'Background Colour',
            'labelFontFamily' => 'Label Font Family',
            'labelFontSize' => 'Label Font Size',
            'labelFontColour' => 'Label Font Colour',
            'inputFontFamily' => 'Input Font Family',
            'inputFontSize' => 'Input Font Size',
            'inputFontColour' => 'Input Font Colour',
            'allowedCardTypes' => 'Allowed Card Types',
            'showCardIcons' => 'Show Card Icons',
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
        $rules[] = [['clientId', 'clientSecret', 'merchantCode', 'paymentType', 'backgroundColour', 'labelFontFamily', 'labelFontSize', 'labelFontColour', 'inputFontFamily', 'inputFontSize', 'inputFontColour'], 'string'];
        $rules[] = [['sandboxMode', 'showCardIcons', 'cardPayments'], 'boolean'];
        $rules[] = [['paymentType'], 'in', 'range' => ['purchase', 'authorize']];
        $rules[] = [['allowedCardTypes'], 'each', 'rule' => ['in', 'range' => ['visa', 'mastercard', 'amex', 'diners']]];

        return $rules;
    }

    // Private Methods
    // =========================================================================
    /**
     * Get or create SecurePay credential with caching
     * @return mixed
     * @throws Exception
     */
    public function getCredential()
    {

        if ($this->credential === null) {
            $cache = Craft::$app->getCache();
            $cache_key = "securepay_token2_" . (!$this->sandboxMode ? 'live' : 'test'). '_' . md5($this->merchantCode . $this->clientId . $this->clientSecret);
            $token = $cache->getOrSet($cache_key, function()  {
                try {
					$request = new ClientCredentialsRequest(!$this->sandboxMode, $this->clientId, $this->clientSecret);
					$response = $request->execute();

					if (method_exists($response, 'getFirstError') && $response->getFirstError()) {
						$message = $response->getFirstError()->getDetail();
                        Craft::error($message, __METHOD__);
						throw new Exception($message);
					}
					$token = $response->_getAccessToken();
                    // Create credential object (you may need to create this class)
                    return $token;
                   
				} catch (\Exception $e) {
                    $message = $e->getMessage() ?: get_class($e);
                    Craft::error('SecurePay getCredential ERROR: ' . $message . '. Mode: ' . (!$this->sandboxMode ? 'Live' : 'Test'), __METHOD__);
                    throw new Exception($message);
                }
            }, 86400); // Default 1 day
            $this->credential = new Credential(!$this->sandboxMode, $this->merchantCode, $this->clientId, $this->clientSecret, $token);
            
        }
    }
    /**
     * Create a payment using SecurePay API following Commerce patterns
     * @param Transaction $transaction
     * @param BasePaymentForm $form
     * @param bool $capture
     * @return RequestResponseInterface
     */
    private function createPayment(Transaction $transaction, BasePaymentForm $form, bool $capture): RequestResponseInterface
    {
        try {
            // get credential and SecurePay Authentication
            $this->getCredential();
            // get order and payment data
            $order = $transaction->getOrder();
            
            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'token' => $this->cardTokenise,
                'ip' => $this->_getOrderIp($order),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
                'currency' => $this->defaultCurrency, //$transaction->paymentCurrency,
            ];

            if ($order->id) {
                $paymentData['orderId'] = (string) $order->id.""; // --> can cause INVALID_ORDER_ID
            }
            if($order->customerId && 0){
                $paymentData['customerCode'] = (string) $order->customerId.""; // --> can cause INVALID_ORDER_ID
            }

            // Prepare payment data according to SecurePay API documentation
            $createPaymentRequest = new CreatePaymentRequest($this->credential->isLive(),	$this->credential, $paymentData);
            
            try {
                $createPaymentResult = $createPaymentRequest->execute()->toArray();
                 Craft::info('createPaymentRequest Response: '. json_encode($createPaymentResult),__METHOD__);

              } catch (\Exception $e) {
                $this->error_message = $e->getMessage();
                Craft::error('createPaymentRequest ERROR: '. $e->getMessage(), __METHOD__);
              }
            return new SecurePayResponse($createPaymentResult);
        } catch (\Exception $e) {
            Craft::error('SecurePay payment error: ' . $e->getMessage(), __METHOD__);
            return new SecurePayResponse(['error' => $e->getMessage()]);
        }
    }
    /**
     * 
     * Refund a payment using SecurePay API following Commerce patterns
     * @param Transaction $transaction
     * @return RequestResponseInterface
     * @since 1.1.0
     */
    private function refundPayment(Transaction $transaction): RequestResponseInterface
    {
        try {
            // get order and payment data
            $order = $transaction->getOrder();
                if($order->currency != $this->defaultCurrency || $transaction->paymentCurrency != $this->defaultCurrency){
                Craft::error('SecurePay refund payment error: ' . 'Currency mismatch', __METHOD__);
                return new SecurePayResponse(['status' => 'failed', 'gatewayResponseCode' => '-1', 'gatewayResponseMessage' => 'Only AUD is supported']);
            }
            // get credential and SecurePay Authentication
            $this->getCredential();
            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'ip' => $this->_getOrderIp($order),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
            ];

            // Prepare payment data according to SecurePay API documentation
            $RefundPaymentRequest = new RefundPaymentRequest($this->credential->isLive(),	$this->credential, $paymentData, $order->id);
            
            try {
                $refundPaymentResult = $RefundPaymentRequest->execute()->toArray();
                 Craft::info('RefundPaymentRequest Response: '. json_encode($refundPaymentResult),__METHOD__);

              } catch (\Exception $e) {
                $this->error_message = $e->getMessage();
                Craft::error('RefundPaymentRequest ERROR: '. $e->getMessage(), __METHOD__);
              }
            return new SecurePayResponse($refundPaymentResult);
        } catch (\Exception $e) {
            Craft::error('SecurePay refund payment error: ' . $e->getMessage(), __METHOD__);
            return new SecurePayResponse(['error' => $e->getMessage()]);
        }
    }
    /**
     * 
     * Authorise a payment using SecurePay API following Commerce patterns
     * @param Transaction $transaction
     * @param BasePaymentForm $form
     * @return RequestResponseInterface
     * @since 1.2.0
     */
    private function authorisePayment(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        try {
            // get order and payment data
            $order = $transaction->getOrder();
            // get credential and SecurePay Authentication
            $this->getCredential();

            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'preAuthType' => 'PRE_AUTH', //PRE_AUTH,INITIAL_AUTH
                'token' => $this->cardTokenise,
                'ip' => $this->_getOrderIp($order),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
                'currency' => $this->defaultCurrency, //$transaction->paymentCurrency,
            ];
            if ($order->id) {
                $paymentData['orderId'] = (string) $order->id.""; // --> can cause INVALID_ORDER_ID
            }
            if($order->customerId && 0){
                $paymentData['customerCode'] = (string) $order->customerId.""; // --> can cause INVALID_ORDER_ID
            }
            // Prepare payment data according to SecurePay API documentation
            $createPreAuthRequest = new CreatePreAuthRequest($this->credential->isLive(),	$this->credential, $paymentData);
            
            try {
                $createPreAuthResult = $createPreAuthRequest->execute()->toArray();
               
                 Craft::info('CreatePreAuthRequest Response: '. json_encode($createPreAuthResult),__METHOD__);

              } catch (\Exception $e) {
                $this->error_message = $e->getMessage();
                Craft::error('CreatePreAuthRequest ERROR: '. $e->getMessage(), __METHOD__);
              }
            return new SecurePayResponse($createPreAuthResult);
        } catch (\Exception $e) {
            Craft::error('SecurePay authorise payment error: ' . $e->getMessage(), __METHOD__);
            return new SecurePayResponse(['error' => $e->getMessage()]);
        }
    }
    /**
     * 
     * Authorise a payment using SecurePay API following Commerce patterns
     * @param Transaction $transaction
     * @param BasePaymentForm $form
     * @return RequestResponseInterface
     * @since 1.2.0
     */
    private function capturePayment(Transaction $transaction, string $reference): RequestResponseInterface
    {
        try {
            // get order and payment data
            $order = $transaction->getOrder();
            // get credential and SecurePay Authentication
            $this->getCredential();

            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'ip' => $this->_getOrderIp($order),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
            ];
            // Prepare payment data according to SecurePay API documentation
            $capturePreAuthRequest = new CapturePreAuthRequest($this->credential->isLive(),	$this->credential, $paymentData ,$order->id);
            
            try {
                $capturePreAuthResult = $capturePreAuthRequest->execute()->toArray();
               
                 Craft::info('CapturePreAuthRequest Response: '. json_encode($capturePreAuthResult),__METHOD__);

              } catch (\Exception $e) {
                $this->error_message = $e->getMessage();
                Craft::error('CapturePreAuthRequest ERROR: '. $e->getMessage(), __METHOD__);
              }
            return new SecurePayResponse($capturePreAuthResult);
        } catch (\Exception $e) {
            Craft::error('SecurePay capture payment error: ' . $e->getMessage(), __METHOD__);
            return new SecurePayResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * Convert amount to cents (SecurePay expects amounts in cents)
     * @param float $amount
     * @return int
     */
    private function _convertAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Get customer IP address for Craft CMS
     * @param Order $order
     * @return string
     */
    private function _getOrderIp($order): string
    {
        $ip_address = '';
        
        try {
            // Try to get IP from request
            $request = Craft::$app->getRequest();
            $ip_address = $request->getUserIP();
            
            if (!$ip_address) {
                // Fallback to server variables
                $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? 
                             $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                             $_SERVER['REMOTE_ADDR'] ?? '';
            }
        } catch (\Exception $e) {
            Craft::error('Error getting IP address: ' . $e->getMessage(), __METHOD__);
        }
        
        return $ip_address ?: '127.0.0.1';
    }

    /**
     * Get user IP address (fallback method)
     * @return string
     */
    private function _getUserIpAddr(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            // IP from shared internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // IP passed from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        return $ip;
    }

    /**
     * Get access token from credential
     * @return string|null
     */
    public function _getAccessToken(): ?string
    {
        try {
            $credential = $this->getCredential();
            return $credential['accessToken'] ?? null;
        } catch (\Exception $e) {
            Craft::error('Error getting access token: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
} 