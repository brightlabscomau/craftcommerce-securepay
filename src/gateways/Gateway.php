<?php

namespace brightlabs\securepay\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
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
use SecurePayApi\Request\CardPayment\InitiatePaymentOrderRequest;
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
    public string $labelFontFamily = 'Helvetica, sans-serif';

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
    public string $inputFontFamily = 'Helvetica, sans-serif';

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
     * @var bool
     */
    public bool $threeDSecure = false;
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

    private ?Order $order = null;

    private int $maxEmailLength = 254;

    private int $maxAddressFieldLength = 50;

    private int $maxZipCodeLength = 16;
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * Set the credentials for the gateway in sandbox mode
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        if($this->sandboxMode){
            $this->merchantCode = '5AR0055';
            $this->clientId = '0oaxb9i8P9vQdXTsn3l5';
            $this->clientSecret = '0aBsGU3x1bc-UIF_vDBA2JzjpCPHjoCP7oI6jisp';
        }
        // get credential and SecurePay Authentication
        $this->getCredential();
    }

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
        
        $jsUrl = $this->sandboxMode 
            ? Endpoint::URL_SANDBOX_SCRIPT
            : Endpoint::URL_LIVE_SCRIPT;

        $jsUrl3DS2 = $this->sandboxMode 
            ? Endpoint::URL_SANDBOX_3DS2_SCRIPT
            : Endpoint::URL_LIVE_3DS2_SCRIPT;   
        
        // Register the SecurePay JavaScript SDK
        $view->registerScript('1', View::POS_END, [
            'src' => $jsUrl,
            'id'  => 'securepay-ui-js'
        ]); 
        $js = "";
        if($this->threeDSecure){
            $initiatePayment = $this->initiatePayment();
            Craft::$app->getSession()->set('initiatePayment', $initiatePayment);
            $billingAddress = $this->order->getBillingAddress();
            $shippingAddress = $this->order->getShippingAddress();
            
            if(!isset($initiatePayment['errors'])){
                $view->registerScript('', View::POS_END, [
                    'src' => $jsUrl3DS2,
                    'id'  => 'securepay-ui-js-3ds2'
                ]); 
                $js .= "document.addEventListener('DOMContentLoaded', function() {
                    var sp3dsConfig = {
                        clientId: '".$initiatePayment['threedSecureDetails']['providerClientId']."',
                        iframe: document.getElementById('3ds-v2-challenge-iframe'),
                        token: '".$initiatePayment['orderToken']."',
                        simpleToken: '".$initiatePayment['threedSecureDetails']['simpleToken']."',
                        threeDSSessionId: '".$initiatePayment['threedSecureDetails']['sessionId']."',
                        onRequestInputData: function(){
                            cardholderName = document.querySelector('input#cardholderName') ? document.querySelector('input#cardholderName').value : '';
                            cardToken = document.querySelector('input#cartToken') ? document.querySelector('input#cartToken').value : '';
                            var requestData = {
                                'cardTokenInfo':{
                                    'cardholderName':cardholderName,
                                    'cardToken':cardToken
                                },
                                'accountData':{
                                    'emailAddress':'".substr($this->order->email, 0, $this->maxEmailLength)."',
                                },
                                'billingAddress':{
                                    'city':'".substr($billingAddress->locality, 0, $this->maxAddressFieldLength)."',
                                    'state':'".$billingAddress->administrativeArea."',
                                    'country':'".$billingAddress->countryCode."',
                                    'zipCode':'".substr($billingAddress->postalCode, 0, $this->maxZipCodeLength)."',
                                    'streetAddress':'".substr($billingAddress->addressLine1, 0, $this->maxAddressFieldLength)."',
                                    'detailedStreetAddress':'".substr($billingAddress->addressLine2, 0, $this->maxAddressFieldLength)."',
                                    'detailedStreetAddressAdditional':'".substr($billingAddress->addressLine3, 0, $this->maxAddressFieldLength)."'
                                },
                                'shippingAddress':{
                                    'city':'".substr($shippingAddress->locality, 0, $this->maxAddressFieldLength)."',
                                    'state':'".$shippingAddress->administrativeArea."',
                                    'country':'".$shippingAddress->countryCode."',
                                    'zipCode':'".substr($shippingAddress->postalCode, 0, $this->maxZipCodeLength)."',
                                    'streetAddress':'".substr($shippingAddress->addressLine1, 0, $this->maxAddressFieldLength)."',
                                    'detailedStreetAddress':'".substr($shippingAddress->addressLine2, 0, $this->maxAddressFieldLength)."',
                                    'detailedStreetAddressAdditional':'".substr($shippingAddress->addressLine3, 0, $this->maxAddressFieldLength)."'
                                },
                                'threeDSInfo':{
                                    'threeDSReqAuthMethodInd':'01'
                                }
                            };
                            console.log(requestData);
                            return requestData;
                        },
                        onThreeDSResultsResponse: async function(response){
                            if(response.authenticationValue && response.liabilityShiftIndicator == 'Y'){
                                form.requestSubmit();
                            }
                            else{
                                var errors = {
                                    'errors': [
                                        {
                                            'code': '3DS2_CARD_UNSUPPORTED',
                                            'detail': 'Card Unsupported for 3D Secure'
                                        }
                                    ]
                                };
                                displayErrors(errors);
                            }
                        },
                        onThreeDSError: async function(errors){
                            displayErrors(errors);
                        }
                    };

                    securePayThreedsUI = new window.SecurePayThreedsUI();
                    securePayThreedsUI = securePayThreedsUI;
                    securePayThreedsUI.initThreeDS(sp3dsConfig);
                });";
            }
        }

        $js .= "
        // Initialize SecurePay when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            window.mySecurePayUI = new securePayUI.init({
                containerId: 'securepay-card-component',
                scriptId: 'securepay-ui-js',
                clientId: '".$this->clientId."',
                merchantCode: '".$this->merchantCode."',
                style: {
                    backgroundColor: '#" . $this->backgroundColour . "',
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
                        errors = typeof errors === 'string' ? JSON.parse(errors) : errors;
                        //displayErrors(errors);
                    }
                }
            });
        });";

        $view->registerJs($js, View::POS_END);
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
        return new SecurePayResponse(['errors' => [['code' => '-1', 'detail' => 'Complete Authorize not supported']]]);
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
            'threeDSecure' => '3D Secure',
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
        $rules[] = [['sandboxMode', 'threeDSecure', 'showCardIcons', 'cardPayments'], 'boolean'];
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
            $cache_key = "securepay_token_" . (!$this->sandboxMode ? 'live' : 'test'). '_' . md5($this->merchantCode . $this->clientId . $this->clientSecret);
            $token = $cache->getOrSet($cache_key, function()  {
                try {
					$request = new ClientCredentialsRequest(!$this->sandboxMode, $this->clientId, $this->clientSecret);
					$response = $request->execute();

					if (method_exists($response, 'getFirstError') && $response->getFirstError()) {
						$message = $response->getFirstError()->getDetail();
                        Craft::error($message, __METHOD__);
						throw new Exception($message);
					}
					$token = $response->getAccessToken();
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
            if($this->threeDSecure){
                $initiatePayment = Craft::$app->getSession()->get('initiatePayment');
                $paymentData['threeDSecure'] = [
                    'initiatedOrderId' => $initiatePayment['orderId'],
                    'liabilityShiftIndicator' => 'Y'
                ];
            }
            // Prepare payment data according to SecurePay API documentation
            $createPaymentRequest = new CreatePaymentRequest($this->credential->isLive(),	$this->credential, $paymentData);
            $createPaymentResult = $createPaymentRequest->execute()->toArray();

            if(isset($createPaymentResult['errors']))
                Craft::error('createPaymentRequest ERROR: '. json_encode($createPaymentResult),__METHOD__);
            else
                Craft::info('createPaymentRequest Response: '. json_encode($createPaymentResult),__METHOD__);
            
        } catch (\Exception $e) {
            Craft::error('createPaymentRequest ERROR: ' . $e->getMessage(), __METHOD__);
            $createPaymentResult = ['errors' => [['code' => '-1', 'detail' => $e->getMessage()]]];
        }
        return new SecurePayResponse($createPaymentResult);

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
            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'ip' => $this->_getOrderIp($order),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
            ];

            // Prepare payment data according to SecurePay API documentation
            $RefundPaymentRequest = new RefundPaymentRequest($this->credential->isLive(),	$this->credential, $paymentData, $order->id);
            $refundPaymentResult = $RefundPaymentRequest->execute()->toArray();
            
            if(isset($refundPaymentResult['errors']))
                Craft::error('RefundPaymentRequest ERROR: '. json_encode($refundPaymentResult),__METHOD__);
            else
                Craft::info('RefundPaymentRequest Response: '. json_encode($refundPaymentResult),__METHOD__);
            
        } catch (\Exception $e) {
            Craft::error('RefundPaymentRequest ERROR: ' . $e->getMessage(), __METHOD__);
            $refundPaymentResult = ['errors' => [['code' => '-1', 'detail' => $e->getMessage()]]];
        }
        return new SecurePayResponse($refundPaymentResult);

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
            if($this->threeDSecure){
                $initiatePayment = Craft::$app->getSession()->get('initiatePayment');
                $paymentData['threeDSecure'] = [
                    'initiatedOrderId' => $initiatePayment['orderId'],
                    'liabilityShiftIndicator' => 'Y'
                ];
            }
            // Prepare payment data according to SecurePay API documentation
            $createPreAuthRequest = new CreatePreAuthRequest($this->credential->isLive(),	$this->credential, $paymentData);
            $createPreAuthResult = $createPreAuthRequest->execute()->toArray();
            // check if there are errors in the response
            if(isset($createPreAuthResult['errors']))
                Craft::error('CreatePreAuthRequest ERROR: '. json_encode($createPreAuthResult),__METHOD__);
            else
                Craft::info('CreatePreAuthRequest Response: '. json_encode($createPreAuthResult),__METHOD__);
            
        } catch (\Exception $e) {
            Craft::error('CreatePreAuthRequest ERROR: ' . $e->getMessage(), __METHOD__);
            $createPreAuthResult = ['errors' => [['code' => '-1', 'detail' => $e->getMessage()]]];
        }
        return new SecurePayResponse($createPreAuthResult);
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
            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'ip' => $this->_getOrderIp($order),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
            ];
            // Prepare payment data according to SecurePay API documentation
            $capturePreAuthRequest = new CapturePreAuthRequest($this->credential->isLive(),	$this->credential, $paymentData ,$order->id);
            $capturePreAuthResult = $capturePreAuthRequest->execute()->toArray();
            // check if there are errors in the response
            if(isset($capturePreAuthResult['errors']))
                Craft::error('CapturePreAuthRequest ERROR: '. json_encode($capturePreAuthResult),__METHOD__);
            else
                Craft::info('CapturePreAuthRequest Response: '. json_encode($capturePreAuthResult),__METHOD__);
            
        } catch (\Exception $e) {
            Craft::error('CapturePreAuthRequest ERROR: ' . $e->getMessage(), __METHOD__);
            $capturePreAuthResult = ['errors' => [['code' => '-1', 'detail' => $e->getMessage()]]];
        }
        return new SecurePayResponse($capturePreAuthResult);
    }
    /**
     * 
     * Initiate a payment using SecurePay API following Commerce patterns
     * @return array
     * @since 1.3.0
     */
    private function initiatePayment(): array
    {
        try {
            $this->order = Commerce::getInstance()->getCarts()->getCart();
            // Safely get the total amount (as a float or integer depending on your setup)
            $total = $this->order ? $this->order->getTotal() : 0;
            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'ip' => $this->_getOrderIp($this->order),
                'amount' => $this->_convertAmount($total),
                'orderType' => $this->threeDSecure ? 'THREED_SECURE' : 'DYNAMIC_CURRENCY_CONVERSION',
            ];
            // Prepare payment data according to SecurePay API documentation
            $initiatePaymentOrderRequest = new InitiatePaymentOrderRequest($this->credential->isLive(),	$this->credential, $paymentData);
            $initiatePaymentOrderResult = $initiatePaymentOrderRequest->execute()->toArray();
            // check if there are errors in the response
            if(isset($initiatePaymentOrderResult['errors']))
                Craft::error('initiatePaymentOrderRequest ERROR: '. json_encode($initiatePaymentOrderResult),__METHOD__);
            else
                Craft::info('initiatePaymentOrderRequest Response: '. json_encode($initiatePaymentOrderResult),__METHOD__);
            
        } catch (\Exception $e) {
            Craft::error('initiatePaymentOrderRequest ERROR: ' . $e->getMessage(), __METHOD__);
            $initiatePaymentOrderResult = ['errors' => [['code' => '-1', 'detail' => $e->getMessage()]]];
        }
        return $initiatePaymentOrderResult;
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
            return $credential['accessToken'] ?? null;
        } catch (\Exception $e) {
            Craft::error('Error getting access token: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
} 