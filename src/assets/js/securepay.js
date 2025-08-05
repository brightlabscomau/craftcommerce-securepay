/**
 * SecurePay Payment Form JavaScript
 * Handles SecurePay UI initialization and 3D Secure processing
 * 
 * Configuration is passed from PHP via window.SecurePayConfig object
 * 
 * @author Brightlabs
 * @version 1.0
 */

// Global variables
let form;
let cardholderNameInput;
let cartTokenInput;
let cartScheme;
let cardholderNameWrapper;
let submitButton;
let submitButtonText = 'Processing...';
let securePayThreedsUI;

// Initialize SecurePay when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeForm();
    
    if (window.SecurePayConfig) {
        if (window.SecurePayConfig.threeDSecure && window.SecurePayConfig.threeDSecureData) {
            initializeThreeDSecure(window.SecurePayConfig.threeDSecureData);
        }
        
        if (window.SecurePayConfig.cardComponent) {
            initializeSecurePayUI(window.SecurePayConfig.cardComponent);
        }
    }
});

/**
 * Initialize form elements and event listeners
 */
function initializeForm() {
    // Get the form that contains the div.securepay-payment-form
    var securepayDiv = document.querySelector('div.securepay-payment-form');
    if (securepayDiv) {
        form = securepayDiv.closest('form');
    }
    
    cardholderNameInput = form ? form.querySelector('input.securepayCardholderName') : null;
    cartTokenInput = form ? form.querySelector('input.securepayCardToken') : null;
    cartScheme = form ? form.querySelector('input.securepayCardScheme') : null;
    cardholderNameWrapper = cardholderNameInput ? cardholderNameInput.closest('.input-wrapper') : null;
    submitButton = form ? form.querySelector('button[type="submit"]') : null;
    
    if (submitButton) {
        submitButton.addEventListener('click', function(e) {
            // Prevent default form submission
            e.preventDefault();
            
            // Validate cardholder name
            if(cardholderNameInput){
                if (!cardholderNameInput.value.trim()) {
                    cardholderNameWrapper.classList.add('ng-invalid');
                    return false;
                }
            }
            if(!cartTokenInput.value.trim()){
                return false;
            }
            if(cardholderNameWrapper){
                cardholderNameWrapper.classList.remove('ng-invalid');
                securePayThreedsUI.startThreeDS();
            }
            else{
                form.requestSubmit();
            }
            submitButton.disabled = true;
            text = submitButton.textContent;
            submitButton.textContent = submitButtonText;
            submitButtonText = text;
        });
    }
    
    // Remove ng-invalid class when user starts typing
    if(cardholderNameInput){
        cardholderNameInput.addEventListener('input', function() {
            if (this.value.trim()) {
                cardholderNameWrapper.classList.remove('ng-invalid');
            }
        });
    }
}

/**
 * Initialize 3D Secure
 */
function initializeThreeDSecure(config) {
    var sp3dsConfig = {
        clientId: config.clientId,
        iframe: document.querySelector('._3ds-v2-challenge-iframe'),
        token: config.token,
        simpleToken: config.simpleToken,
        threeDSSessionId: config.threeDSSessionId,
        onRequestInputData: function(){
            cardholderName = document.querySelector('input.securepayCardholderName') ? document.querySelector('input.securepayCardholderName').value : '';
            cardToken = document.querySelector('input.securepayCardToken') ? document.querySelector('input.securepayCardToken').value : '';
            
            var requestData = {
                'cardTokenInfo':{
                    'cardholderName': cardholderName,
                    'cardToken': cardToken
                },
                'accountData':{
                    'emailAddress': config.emailAddress,
                },
                'threeDSInfo':{
                    'threeDSReqAuthMethodInd':'01'
                }
            };
            
            // Add billing address if available
            if (config.billingAddress) {
                requestData.billingAddress = config.billingAddress;
            }
            
            // Add shipping address if available
            if (config.shippingAddress) {
                requestData.shippingAddress = config.shippingAddress;
            }
            
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
                displayErrorsSecurePay(errors);
            }
        },
        onThreeDSError: async function(errors){
            displayErrorsSecurePay(errors);
        }
    };

    securePayThreedsUI = new window.SecurePayThreedsUI();
    securePayThreedsUI = securePayThreedsUI;
    securePayThreedsUI.initThreeDS(sp3dsConfig);
}

/**
 * Initialize SecurePay UI
 */
function initializeSecurePayUI(config) {
    var securepayCardComponentElem = document.querySelector('.securepay-card-component');
    var securepayCardComponentId = securepayCardComponentElem ? securepayCardComponentElem.id : null;
    
    if(securepayCardComponentId){
        window.mySecurePayUI = new securePayUI.init({ 
            containerId: securepayCardComponentId,
            scriptId: 'securepay-ui-js',
            clientId: config.clientId,
            merchantCode: config.merchantCode,
            style: config.style,
            card: {
                showCardIcons: config.showCardIcons,
                allowedCardTypes: config.allowedCardTypes,
                onFormValidityChange: function(valid) {
                    window.mySecurePayUI.tokenise();
                },
                onTokeniseSuccess: async function(tokenisedCard) {
                    document.querySelector('input.securepayCardToken').value = tokenisedCard.token;
                    document.querySelector('input.securepayCardExpiryMonth').value = tokenisedCard.expiryMonth;
                    document.querySelector('input.securepayCardExpiryYear').value = tokenisedCard.expiryYear;
                    document.querySelector('input.securepayCardBin').value = tokenisedCard.bin;
                    document.querySelector('input.securepayCardLast4').value = tokenisedCard.last4;
                    document.querySelector('input.securepayCardScheme').value = tokenisedCard.scheme;
                    document.querySelector('input.securepayCardCreatedAt').value = tokenisedCard.createdAt;
                },
                onTokeniseError: function(errors) {
                    errors = typeof errors === 'string' ? JSON.parse(errors) : errors;
                    //displayErrorsSecurePay(errors);
                }
            }
        });
    }
    else{
        console.log('SecurePay card component element not found on HTML DOM');
    }
}

/**
 * Display errors in the payment form
 */
function displayErrorsSecurePay(errors){
    if (errors && errors.errors && Array.isArray(errors.errors)) {
        var errorContainer = document.querySelector('.securepay-payment-form .errors');
        if (errorContainer) {
            errorContainer.innerHTML = '';
            var errorList = document.createElement('ul');
            errors.errors.forEach(function(error) {
                errorList.innerHTML += '<li>ERROR: ' + error.detail + '.<br/>The page will be refresh.</li>';
            });
            errorContainer.appendChild(errorList);
        }
    }
    
    if (submitButton) {
        submitButton.disabled = false;
        var currentText = submitButton.textContent;
        submitButton.textContent = submitButtonText;
        submitButtonText = currentText;
    }
    setTimeout(function() {
        window.location.reload();
    }, 2000);
}