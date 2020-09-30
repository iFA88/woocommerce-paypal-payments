import dccInputFactory from "../Helper/DccInputFactory";

class CreditCardRenderer {

    constructor(defaultConfig, errorHandler) {
        this.defaultConfig = defaultConfig;
        this.errorHandler = errorHandler;
    }

    render(wrapper, contextConfig) {

        if (
            (
                this.defaultConfig.context !== 'checkout'
                && this.defaultConfig.context !== 'pay-now'
            )
            || wrapper === null
            || document.querySelector(wrapper) === null
        ) {
            return;
        }
        if (
            typeof paypal.HostedFields === 'undefined'
            || ! paypal.HostedFields.isEligible()
        ) {
            const wrapperElement = document.querySelector(wrapper);
            wrapperElement.parentNode.removeChild(wrapperElement);
            return;
        }

        const gateWayBox = document.querySelector('.payment_box.payment_method_ppcp-credit-card-gateway');
        const oldDisplayStyle = gateWayBox.style.display;
        gateWayBox.style.display = 'block';

        const hideDccGateway = document.querySelector('#ppcp-hide-dcc');
        if (hideDccGateway) {
            hideDccGateway.parentNode.removeChild(hideDccGateway);
        }

        const cardNumberField = document.querySelector('#ppcp-credit-card-gateway-card-number');

        const stylesRaw = window.getComputedStyle(cardNumberField);
        let styles = {};
        Object.values(stylesRaw).forEach( (prop) => {
            if (! stylesRaw[prop]) {
                return;
            }
            styles[prop] = '' + stylesRaw[prop];
        });

        const cardNumber = dccInputFactory(cardNumberField);
        cardNumberField.parentNode.replaceChild(cardNumber, cardNumberField);

        const cardExpiryField = document.querySelector('#ppcp-credit-card-gateway-card-expiry');
        const cardExpiry = dccInputFactory(cardExpiryField);
        cardExpiryField.parentNode.replaceChild(cardExpiry, cardExpiryField);

        const cardCodeField = document.querySelector('#ppcp-credit-card-gateway-card-cvc');
        const cardCode = dccInputFactory(cardCodeField);
        cardCodeField.parentNode.replaceChild(cardCode, cardCodeField);

        gateWayBox.style.display = oldDisplayStyle;

        const formWrapper = '.payment_box payment_method_ppcp-credit-card-gateway';
        if (
            this.defaultConfig.enforce_vault
            && document.querySelector(formWrapper + ' .ppcp-credit-card-vault')
        ) {
            document.querySelector(formWrapper + ' .ppcp-credit-card-vault').checked = true;
            document.querySelector(formWrapper + ' .ppcp-credit-card-vault').setAttribute('disabled', true);
        }
        paypal.HostedFields.render({
            createOrder: contextConfig.createOrder,
            styles: {
                'input': styles
            },
            fields: {
                number: {
                    selector: '#ppcp-credit-card-gateway-card-number',
                    placeholder: this.defaultConfig.hosted_fields.labels.credit_card_number,
                },
                cvv: {
                    selector: '#ppcp-credit-card-gateway-card-cvc',
                    placeholder: this.defaultConfig.hosted_fields.labels.cvv,
                },
                expirationDate: {
                    selector: '#ppcp-credit-card-gateway-card-expiry',
                    placeholder: this.defaultConfig.hosted_fields.labels.mm_yyyy,
                }
            }
        }).then(hostedFields => {
            const submitEvent = (event) => {
                if (event) {
                    event.preventDefault();
                }
                this.errorHandler.clear();
                const state = hostedFields.getState();
                const formValid = Object.keys(state.fields).every(function (key) {
                    return state.fields[key].isValid;
                });

                if (formValid) {

                    let vault = document.querySelector(wrapper + ' .ppcp-credit-card-vault') ?
                        document.querySelector(wrapper + ' .ppcp-credit-card-vault').checked : false;
                    vault = this.defaultConfig.enforce_vault || vault;

                    hostedFields.submit({
                        contingencies: ['3D_SECURE'],
                        vault
                    }).then((payload) => {
                        payload.orderID = payload.orderId;
                        return contextConfig.onApprove(payload);
                    });
                } else {
                    this.errorHandler.message(this.defaultConfig.hosted_fields.labels.fields_not_valid);
                }
            }
            hostedFields.on('inputSubmitRequest', function () {
                submitEvent(null);
            });
            document.querySelector(wrapper + ' button').addEventListener(
                'click',
                submitEvent
            );
        });

        document.querySelector('#payment_method_ppcp-credit-card-gateway').addEventListener(
            'click',
            () => {
                document.querySelector('label[for=ppcp-credit-card-gateway-card-number]').click();
            }
        )
    }
}
export default CreditCardRenderer;