(function () {
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement } = window.wp.element;
    const { getPaymentMethodData } = window.wc.wcSettings;

    const settings = getPaymentMethodData('ne_payment_lab', {});

    const label = settings.title || 'Payment Lab — Test Payment';
    const description = settings.description ||
        'Educational test payment. No real money will be charged.';

    const Content = () => createElement('p', null, description);

    const Label = () => createElement('span', null, label);

    registerPaymentMethod({
        name: 'ne_payment_lab',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products'],
        },
    });
})();
