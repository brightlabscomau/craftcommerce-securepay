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
use SecurePayApi\Request\CardPayment\CreatePaymentInstrumentRequest;
use SecurePayApi\Request\CardPayment\DeletePaymentInstrumentRequest;
use craft\elements\User;

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
     * @var string
     */
    public string $clientIdFinal = '';

    /**
     * @var string
     */
    public string $clientSecretFinal = '';

    /**
     * @var string
     */
    public string $merchantCodeFinal = '';
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
     * @var bool Check if credential is valid
     */
    public bool $isCredentialValid = false;
    /**
     * @var string|null tokenised card token
     */
    private ?string $cardToken = null;
    /**
     * @var string|null tokenised card Created At
     */
    private ?string $cardExpiryMonth = null;
    /**
     * @var string|null tokenised card Expiry Year
     */
    private ?string $cardExpiryYear = null;
    /**
     * @var string|null tokenised card Bin
     */
    private ?string $cardBin = null;
    /**
     * @var string|null tokenised card Last 4
     */
    private ?string $cardLast4 = null;
    /**
     * @var string|null tokenised card Scheme
     */
    private ?string $cardScheme = null;
    /**
     * @var string|null tokenised card Created At
     */
    private ?string $cardCreatedAt = null;
    
   

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
            $this->clientIdFinal = '0oaxb9i8P9vQdXTsn3l5';
            $this->clientSecretFinal = '0aBsGU3x1bc-UIF_vDBA2JzjpCPHjoCP7oI6jisp';
            $this->merchantCodeFinal = '5AR0055';
        }
        else{
            $this->clientIdFinal = $this->clientId;
            $this->clientSecretFinal = $this->clientSecret;
            $this->merchantCodeFinal = $this->merchantCode;
        }
        // get credential and SecurePay Authentication
        try {
            $this->getCredential();
            $this->isCredentialValid = true;
        } catch (Exception $e) {
            Craft::error('SecurePay authentication error: ' . $e->getMessage(), __METHOD__);
            $this->isCredentialValid = false;
        }
    }

    /**
     * @inheritdoc
     * for displaying the name of the gateway in the admin panel
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'SecurePay');
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
        return true; // Could be true if implementing payment instruments
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
        
        // Register SecurePay CSS and JavaScript asset bundle
        $view->registerAssetBundle(\brightlabs\securepay\assets\SecurePayAsset::class);
        
        $html = $view->renderTemplate('securepay/payment-form', $params);

        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * Register SecurePay JavaScript SDK following Craft Commerce patterns
     */
    private function registerJavaScriptSDK($view): void
    {
        if($this->isCredentialValid){
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

            // Prepare configuration object
            $config = [
                'threeDSecure' => $this->threeDSecure,
                'cardComponent' => [
                    'clientId' => $this->clientIdFinal,
                    'merchantCode' => $this->merchantCodeFinal,
                    'style' => [
                        'backgroundColor' => '#' . $this->backgroundColour,
                        'label' => [
                            'font' => [
                                'family' => $this->labelFontFamily,
                                'size' => $this->labelFontSize,
                                'color' => '#' . $this->labelFontColour
                            ]
                        ],
                        'input' => [
                            'font' => [
                                'family' => $this->inputFontFamily,
                                'size' => $this->inputFontSize,
                                'color' => '#' . $this->inputFontColour
                            ]
                        ]
                    ],
                    'showCardIcons' => $this->showCardIcons,
                    'allowedCardTypes' => $this->allowedCardTypes
                ]
            ];

            // Handle 3D Secure configuration
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

                    $threeDSecureData = [
                        'clientId' => $initiatePayment['threedSecureDetails']['providerClientId'],
                        'token' => $initiatePayment['orderToken'],
                        'simpleToken' => $initiatePayment['threedSecureDetails']['simpleToken'],
                        'threeDSSessionId' => $initiatePayment['threedSecureDetails']['sessionId'],
                        'emailAddress' => substr($this->order->email, 0, $this->maxEmailLength)
                    ];

                    if($billingAddress){
                        $threeDSecureData['billingAddress'] = [
                            'city' => substr($billingAddress->locality, 0, $this->maxAddressFieldLength),
                            'state' => $billingAddress->administrativeArea,
                            'country' => $billingAddress->countryCode,
                            'zipCode' => substr($billingAddress->postalCode, 0, $this->maxZipCodeLength),
                            'streetAddress' => substr($billingAddress->addressLine1, 0, $this->maxAddressFieldLength),
                            'detailedStreetAddress' => substr($billingAddress->addressLine2, 0, $this->maxAddressFieldLength),
                            'detailedStreetAddressAdditional' => substr($billingAddress->addressLine3, 0, $this->maxAddressFieldLength)
                        ];
                    }

                    if($shippingAddress){
                        $threeDSecureData['shippingAddress'] = [
                            'city' => substr($shippingAddress->locality, 0, $this->maxAddressFieldLength),
                            'state' => $shippingAddress->administrativeArea,
                            'country' => $shippingAddress->countryCode,
                            'zipCode' => substr($shippingAddress->postalCode, 0, $this->maxZipCodeLength),
                            'streetAddress' => substr($shippingAddress->addressLine1, 0, $this->maxAddressFieldLength),
                            'detailedStreetAddress' => substr($shippingAddress->addressLine2, 0, $this->maxAddressFieldLength),
                            'detailedStreetAddressAdditional' => substr($shippingAddress->addressLine3, 0, $this->maxAddressFieldLength)
                        ];
                    }

                    $config['threeDSecureData'] = $threeDSecureData;
                }
            }

            // Register configuration as JavaScript variable
            $configJs = 'window.SecurePayConfig = ' . Json::encode($config) . ';';
            $view->registerJs($configJs, View::POS_BEGIN);
        }
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        $request = Craft::$app->getRequest();
        // when the payment source added via the user panel
        if($request->getBodyParam('paymentForm')){
            $paymentForm = $request->getBodyParam('paymentForm')['creditCardSecurepay'];
            $this->cardToken =  $paymentForm ['cardToken'];
            $this->cardExpiryMonth = $paymentForm ['cardExpiryMonth'];
            $this->cardExpiryYear = $paymentForm ['cardExpiryYear'];
            $this->cardBin = $paymentForm ['cardBin'];
            $this->cardLast4 = $paymentForm ['cardLast4'];
            $this->cardCreatedAt = $paymentForm ['cardCreatedAt'];
            $this->cardScheme = $paymentForm ['cardScheme'];
        }
        // when the payment process via checkout page
        else{
            $this->cardToken =  $request->getBodyParam('cardToken');
            $this->cardExpiryMonth = $request->getBodyParam('cardExpiryMonth');
            $this->cardExpiryYear = $request->getBodyParam('cardExpiryYear');
            $this->cardBin = $request->getBodyParam('cardBin');
            $this->cardLast4 = $request->getBodyParam('cardLast4');
            $this->cardCreatedAt = $request->getBodyParam('cardCreatedAt');
            $this->cardScheme = $request->getBodyParam('cardScheme');
        }
        $securePayPaymentForm = new SecurePayPaymentForm();
        $securePayPaymentForm->cardToken = $this->cardToken;
        $securePayPaymentForm->cardExpiryMonth = $this->cardExpiryMonth;
        $securePayPaymentForm->cardExpiryYear = $this->cardExpiryYear;
        $securePayPaymentForm->cardBin = $this->cardBin;
        $securePayPaymentForm->cardLast4 = $this->cardLast4;
        $securePayPaymentForm->cardScheme = $this->cardScheme;
        $securePayPaymentForm->cardCreatedAt = $this->cardCreatedAt;
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
        if (!$this->clientIdFinal || !$this->clientSecretFinal || !$this->merchantCodeFinal) {
            Craft::info('SecurePay unavailable: Missing credentials (clientId: ' . ($this->clientIdFinal ? 'set' : 'missing') . 
                       ', clientSecret: ' . ($this->clientSecretFinal ? 'set' : 'missing') . 
                       ', merchantCode: ' . ($this->merchantCodeFinal ? 'set' : 'missing') . ')', __METHOD__);
            return false;
        }
        if(!$this->isCredentialValid){
            Craft::info('SecurePay unavailable: Credential is not valid', __METHOD__);
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
     * @since 1.4.1
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        // Is Craft request the commerce/pay controller action?
        $request = Craft::$app->getRequest();
        $description =  $request->getBodyParam('description');
        $description = !empty($description) ? $description : $this->cardScheme .' card '. $this->cardBin .'••••'. $this->cardLast4;
        $cardInfo = [
            'cardToken' => $this->cardToken,
            'cardExpiryMonth' => $this->cardExpiryMonth,
            'cardExpiryYear' => $this->cardExpiryYear,
            'cardBin' => $this->cardBin,
            'cardLast4' => $this->cardLast4,
            'cardScheme' => $this->cardScheme,
            'cardCreatedAt' => $this->cardCreatedAt,
        ];
        try {   
            $createPaymentInstrumentRequest = new CreatePaymentInstrumentRequest($this->credential->isLive(), $this->credential, $customerId, $this->cardToken, $this->_getOrderIp());
            $createPaymentInstrumentResult = $createPaymentInstrumentRequest->execute()->toArray();
            // check if there are errors in the response
            if(isset($createPaymentInstrumentResult['errors'])){
                Craft::error('createPaymentInstrumentRequest ERROR: '. json_encode($createPaymentInstrumentResult),__METHOD__);
                throw new \Exception('Could not create payment source: ' . $createPaymentInstrumentResult['errors'][0]['detail']);
            }
            else{
                Craft::info('createPaymentInstrumentRequest Response: '. json_encode($createPaymentInstrumentResult),__METHOD__);
                $paymentSource = new PaymentSource();
                $paymentSource->customerId = $customerId;
                $paymentSource->gatewayId = $this->id;
                $paymentSource->token = $this->cardToken;
                $paymentSource->response = json_encode($cardInfo);
                $paymentSource->description = $description;
                return $paymentSource;
            }

        } catch (\Exception $e) {
            Craft::error('SecurePay createPaymentSource error: ' . $e->getMessage(), __METHOD__);
            throw new \Exception('Could not create payment source: ' . $e->getMessage());
        }   
    }

    /**
     * @inheritdoc
     * @since 1.4.1
     */
    public function deletePaymentSource($token): bool
    {
        $customerId = Craft::$app->user->getIdentity()->id;
        try {   
            $deletePaymentInstrumentRequest = new DeletePaymentInstrumentRequest($this->credential->isLive(), $this->credential, $customerId, $token, $this->_getOrderIp());
            $deletePaymentInstrumentResult = $deletePaymentInstrumentRequest->execute()->toArray();
            // check if there are errors in the response
            if(isset($deletePaymentInstrumentResult['errors'])){
                Craft::error('deletePaymentInstrumentRequest ERROR: '. json_encode($deletePaymentInstrumentResult),__METHOD__);
                return false;
            }
            else{
                return true;
            }

        } catch (\Exception $e) {
            Craft::error('SecurePay deletePaymentSource error: ' . $e->getMessage(), __METHOD__);
            return false;            
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
        if(!$this->sandboxMode){
            $rules[] = [['clientId', 'clientSecret', 'merchantCode'], 'required'];
        }
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
            $cache_key = "securepay_token_" . (!$this->sandboxMode ? 'live' : 'test'). '_' . md5($this->merchantCodeFinal . $this->clientIdFinal . $this->clientSecretFinal);
            $token = $cache->getOrSet($cache_key, function()  {
                try {
					$request = new ClientCredentialsRequest(!$this->sandboxMode, $this->clientIdFinal, $this->clientSecretFinal);
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
            $this->credential = new Credential(!$this->sandboxMode, $this->merchantCodeFinal, $this->clientIdFinal, $this->clientSecretFinal, $token);
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
            $currentUser = Craft::$app->getUser()->getIdentity();
            
            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'token' => $form->cardToken,
                'ip' => $this->_getOrderIp(),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
                'currency' => $this->defaultCurrency, //$transaction->paymentCurrency,
            ];
            if ($order->id) {
                $paymentData['orderId'] = (string) $order->id.''; // --> can cause INVALID_ORDER_ID
            }
            // only add customerCode if the order has a payment source and the user is logged in
            if($currentUser instanceof User && $order->paymentSourceId !== null){
                $paymentData['customerCode'] = (string) $order->customerId.''; // --> can cause INVALID_ORDER_ID
            }
            // add threeDSecure if enabled
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
            if(isset($createPaymentResult['errors'])){
                Craft::error('createPaymentRequest ERROR: '. json_encode($createPaymentResult),__METHOD__);
            }
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
                'ip' => $this->_getOrderIp(),
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
            $currentUser = Craft::$app->getUser()->getIdentity();

            $paymentData = [
                'merchantCode' => $this->credential->getMerchantCode(),
                'preAuthType' => 'PRE_AUTH', //PRE_AUTH,INITIAL_AUTH
                'token' => $this->cardToken,
                'ip' => $this->_getOrderIp(),
                'amount' => $this->_convertAmount($transaction->paymentAmount),
                'currency' => $this->defaultCurrency, //$transaction->paymentCurrency,
            ];
            if ($order->id) {
                $paymentData['orderId'] = (string) $order->id.""; // --> can cause INVALID_ORDER_ID
            }
            // only add customerCode if the order has a payment source and the user is logged in
            if($currentUser instanceof User && $order->paymentSourceId !== null){
                $paymentData['customerCode'] = (string) $order->customerId.""; // --> can cause INVALID_ORDER_ID
            }
            // add threeDSecure if enabled
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
                'ip' => $this->_getOrderIp(),
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
                'ip' => $this->_getOrderIp(),
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
     * @return string
     */
    private function _getOrderIp(): string
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