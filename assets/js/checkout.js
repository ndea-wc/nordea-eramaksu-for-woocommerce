const settings = window.wc.wcSettings.getSetting('nordea-eramaksu_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('nordea-eramaksu', 'nordea-eramaksu');

const Content = () => {
    const description = window.wp.htmlEntities.decodeEntities(settings.description || '');
    return description ? `<p>${description}</p>` : '';
};

const labelHtml = wp.element.createElement(
    'label',
    { class: 'ndea-checkout-label' },  // Apply flexbox to align the title and image horizontally
    settings.title,  // Title first
    wp.element.createElement('img', {
        src: settings.icon,
        alt: 'Maksa sinulle sopivissa erissä',
        class: 'ndea-checkout-logo'
    })
);

const Block_Gateway = {
    name: 'nordea-eramaksu',
    label: labelHtml,
    content: wp.element.createElement('div', { dangerouslySetInnerHTML: { __html: Content() } }),
    edit: wp.element.createElement('div', { dangerouslySetInnerHTML: { __html: Content() } }),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );

document.addEventListener('DOMContentLoaded', function () {
    // Check if nordeaPaymentData is defined
    if (typeof nordeaPaymentData !== 'undefined') {
        //console.log('NordeaPaymentData:', nordeaPaymentData); // Debug: Verify data

        const { registerCheckoutFilters } = window.wc.blocksCheckout || {};
        if (!registerCheckoutFilters) {
            console.error('registerCheckoutFilters is not available'); // Debug: Check filter registration
            return;
        }

        const cartAmountText = nordeaPaymentData.cartAmountText; // Dynamic message
        const continueShoppingUrl = nordeaPaymentData.continueShoppingUrl;
        const continueShoppingText = nordeaPaymentData.continueShoppingText;
        const startRange = nordeaPaymentData.startRange; // Minimum cart amount
        const endRange = nordeaPaymentData.endRange; // Maximum cart amount

        const applyNordeaPaymentFilter = () => {
           // console.log('applyNordeaPaymentFilter executed'); // Debug

            registerCheckoutFilters('nordea-eramaksu-total-filter', {
                totalLabel: (value, extensions, args) => {

                    const queryString = window.location.search;
                    const urlParams = new URLSearchParams(queryString);
                    let statusMessage = urlParams.get('status');
                    let errorMessage = document.querySelector('#nordea_return_payment_error_message');
                    if (statusMessage && !errorMessage) {
                        let notifyArea = document.querySelector('.wc-block-components-notices');
                          // Add error message
                        const message = document.createElement('div');
                        message.id = 'nordea_return_payment_error_message';
                        message.className = 'wc-block-components-notice-banner is-error';
                        message.role = 'alert';

                        // Add SVG icon to the message
                        const svgIcon = `
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                                <path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z"></path>
                            </svg>`;
                        message.innerHTML = svgIcon + 
                            `<span>${statusMessage === 'cancel' ? nordeaPaymentData.paymentCancelledMessage : nordeaPaymentData.paymentFailedMessage}</span>`;

                        notifyArea.appendChild(message);
                    }

                    if (args.cart && args.cart.cartTotals) {
                        let totalPrice = parseFloat(args.cart.cartTotals.total_price || 0);
                        const shippingTotal = parseFloat(args.cart.cartTotals.shipping_total || 0);
                        totalPrice += shippingTotal;

                        const isCents = totalPrice >= 1000;
                        if (isCents) totalPrice /= 100;

                        if (!Number.isInteger(totalPrice)) {
                            totalPrice = parseFloat(totalPrice.toFixed(2));
                        }


                        const nordeaRadio = document.querySelector(
                            'input[name="radio-control-wc-payment-method-options"][value="nordea-eramaksu"]'
                        );
                        const nordeaDiv = nordeaRadio?.closest('.wc-block-components-radio-control-accordion-option');

                        if (nordeaDiv) {
                            let errorMessage = nordeaDiv.querySelector('#nordea_payment_message');
                            let continueLink = nordeaDiv.querySelector('.continue-shopping');
                            if (errorMessage) errorMessage.remove();
                            if (continueLink) continueLink.remove();

                            if (totalPrice < startRange || totalPrice > endRange) {
                                if (nordeaRadio.checked) {
                                    nordeaRadio.checked = false;
                                    nordeaRadio.dispatchEvent(new Event('change'));
                                    location.reload();
                                }

                                nordeaRadio.disabled = true;
                                nordeaRadio.dispatchEvent(new Event('change'));

                                const message = document.createElement('div');
                                message.id = 'nordea_payment_message';
                                message.className = 'custom-notice';
                                message.role = 'alert';
                                message.innerText = cartAmountText;
                                nordeaDiv.appendChild(message);

                                const link = document.createElement('a');
                                link.href = continueShoppingUrl;
                                link.className = 'continue-shopping custom-link';
                                link.innerText = continueShoppingText;
                                nordeaDiv.appendChild(link);

                               // console.log('Nordea payment disabled with message'); // Debug
                            } else {
                                nordeaRadio.disabled = false;
                            }
                        }

                        return value + " ";
                    }
                    return value + " ";
                },
            });
        };

        const observer = new MutationObserver((mutationsList) => {
          //  console.log('Mutations detected:', mutationsList); // Debug
            applyNordeaPaymentFilter();
        });

        observer.observe(document.body, { childList: true, subtree: true });
    } 
});


