const style = document.createElement('style');

style.textContent = `
    .ne-save-success {
        border-color: #00a32a !important;
    }

    .ne-save-error {
        border-color: #d63638 !important;
    }
`;

document.head.appendChild(style);

function syncShippingClassSelectors(productId, selectedClass) {
    document.querySelectorAll(
        `[data-product-id="${productId}"][data-ne-shipping-field="shipping_class"]`
    ).forEach((select) => {
        const currentValue = select.value;

        if (selectedClass && currentValue !== selectedClass) {
            select.value = '';
        }
    });
}

document.addEventListener('change', async (event) => {
    const field = event.target.closest('[data-ne-shipping-field]');

    if (!field) {
        return;
    }

    const productId = field.dataset.productId;

    if (!productId) {
        return;
    }

    const fieldName = field.dataset.neShippingField;
    const value = field.value;

    field.disabled = true;

    const body = new URLSearchParams({
        action: 'ne_flat_rate_save_product_field',
        nonce: NEFlatRateShipping.nonce,
        product_id: productId,
        field: fieldName,
        value,
    });

    try {
        const response = await fetch(NEFlatRateShipping.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body,
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.data?.message || 'Unable to save product field.'
            );
        }

        if (fieldName === 'shipping_class') {
            syncShippingClassSelectors(productId, value);
        }

        field.classList.remove('ne-save-error');
        field.classList.add('ne-save-success');

        window.setTimeout(() => {
            field.classList.remove('ne-save-success');
        }, 1200);
    } catch (error) {
        field.classList.remove('ne-save-success');
        field.classList.add('ne-save-error');

        window.alert(error.message);
    } finally {
        field.disabled = false;
    }
});