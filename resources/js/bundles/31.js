// ===========================
//  INIT
// ===========================
FbEvents?.init();

// Очищаємо кошик тільки при перезавантаженні сторінки (F5, Ctrl+R)
// Не очищаємо при першому завантаженні (navigate), щоб зберегти дані при переході на /thanks
(function() {
    try {
        const navEntry = performance.getEntriesByType('navigation')[0];
        if (navEntry) {
            const navType = navEntry.type;
            // Очищаємо тільки при reload (F5, Ctrl+R)
            // Не очищаємо при navigate (перше завантаження) та back_forward (навігація назад/вперед)
            if (navType === 'reload') {
                sessionStorage.removeItem('fb_cart_items');
            }
        } else {
            // Fallback для старих браузерів
            if (performance.navigation) {
                // type: 0 = navigate, 1 = reload, 2 = back_forward
                // Очищаємо тільки при reload (type === 1)
                if (performance.navigation.type === 1) {
                    sessionStorage.removeItem('fb_cart_items');
                }
            }
        }
    } catch (e) {
        // Якщо не вдалося визначити тип навігації, не очищаємо
        console.warn('Cannot determine navigation type:', e);
    }
})();

// ===========================
//  HELPERS
// ===========================
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

function getItems(event) {
    if (event?.ecommerce?.items) return event.ecommerce.items;
    if (event?.ecommerce?.add?.items) return event.ecommerce.add.items;
    if (event?.ecommerce?.add?.products) return event.ecommerce.add.products;
    return [];
}

function getCartStorageKey() {
    return 'fb_cart_items';
}

function saveCartData(newItems, newValue, newCurrency) {
    try {
        const key = getCartStorageKey();
        const existing = JSON.parse(sessionStorage.getItem(key)) || { items: [], value: 0, currency: 'UAH' };

        const mergedItems = [...existing.items];
        newItems.forEach((newItem) => {
            const existingItem = mergedItems.find(i => i.id === newItem.id);
            if (existingItem) {
                existingItem.quantity += newItem.quantity;
                existingItem.item_price = newItem.item_price;
            } else {
                mergedItems.push(newItem);
            }
        });

        const totalValue = mergedItems.reduce((s, i) => s + (i.item_price * i.quantity), 0);
        const currency = newCurrency || existing.currency || 'UAH';
        const cartData = { items: mergedItems, value: totalValue, currency };

        sessionStorage.setItem(key, JSON.stringify(cartData));
        console.log('[Cart] 💾 Updated cart data:', cartData);
    } catch (e) {
        console.warn('Cannot save cart data:', e);
    }
}

function getCartData() {
    try {
        const data = sessionStorage.getItem(getCartStorageKey());
        return data ? JSON.parse(data) : { items: [], value: 0, currency: 'UAH' };
    } catch {
        return { items: [], value: 0, currency: 'UAH' };
    }
}

function getRealCartFromDataLayer() {
    try {
        if (window.dataLayer && Array.isArray(window.dataLayer)) {
            for (let i = dataLayer.length - 1; i >= 0; i--) {
                const event = dataLayer[i];
                if (event && event.ecommerce && event.ecommerce.items) {
                    const items = event.ecommerce.items.map(i => ({
                        id: i.item_id || i.id || 'unknown',
                        quantity: Number(i.quantity) || 1,
                        item_price: Number(i.price) || 0
                    }));
                    const value = Number(event.ecommerce.value) || items.reduce((s, i) => s + (i.item_price * i.quantity), 0);
                    const currency = event.ecommerce.currency || 'UAH';
                    return { items, value, currency };
                }
            }
        }
    } catch (e) {
        console.warn('Cannot get real cart from dataLayer:', e);
    }
    return null;
}

function syncCartWithRealState() {
    const realCart = getRealCartFromDataLayer();
    if (realCart && realCart.items && realCart.items.length > 0) {
        const cartData = {
            items: realCart.items,
            value: realCart.value,
            currency: realCart.currency
        };
        sessionStorage.setItem(getCartStorageKey(), JSON.stringify(cartData));
        return cartData;
    } else {
        sessionStorage.removeItem(getCartStorageKey());
        return { items: [], value: 0, currency: 'UAH' };
    }
}

// ===========================
//  MAIN EVENT HANDLER
// ===========================
function cApiEvent(event) {
    const items = getItems(event);
    const mappedItems = items.map(i => ({
        id: i.item_id || i.id || 'unknown',
        quantity: Number(i.quantity) || 1,
        item_price: Number(i.price) || 0
    }));

    const value = Number(event?.ecommerce?.value) || mappedItems.reduce((s, i) => s + (i.item_price * i.quantity), 0);
    const currency = String(event?.ecommerce?.currency || 'UAH');

    const external_id =
        window.external_id ||
        getCookie('external_id') ||
        getCookie('median_user_id') ||
        'unknown_event';

    // Якщо є товари — зберігаємо в sessionStorage
    if (mappedItems.length) saveCartData(mappedItems, value, currency);

    // AddToCart
    if (['add_to_cart', 'addToCart', 'add'].includes(event.event)) {
        FbEvents?.AddToCart(mappedItems);
        fbq?.('track', 'AddToCart', {
            content_type: 'product',
            value,
            currency,
            external_id,
            contents: mappedItems
        });
    }

    // FormSubmission (через dataLayer)
    if (event.event === 'form_submission') {
        let cartItems = [];
        let cartValue = 0;
        let cartCurrency = 'UAH';

        if (event.ecommerce && event.ecommerce.items && event.ecommerce.items.length > 0) {
            cartItems = event.ecommerce.items.map(i => ({
                id: i.item_id || i.id || 'unknown',
                quantity: Number(i.quantity) || 1,
                item_price: Number(i.price) || 0
            }));
            cartValue = Number(event.ecommerce.value) || cartItems.reduce((s, i) => s + (i.item_price * i.quantity), 0);
            cartCurrency = event.ecommerce.currency || 'UAH';
        } else {
            const syncedCart = syncCartWithRealState();
            if (syncedCart.items && syncedCart.items.length > 0) {
                cartItems = syncedCart.items;
                cartValue = syncedCart.value;
                cartCurrency = syncedCart.currency;
            } else {
                const storedCart = getCartData();
                if (storedCart.items && storedCart.items.length > 0) {
                    cartItems = storedCart.items;
                    cartValue = storedCart.value;
                    cartCurrency = storedCart.currency;
                } else if (mappedItems && mappedItems.length > 0) {
                    cartItems = mappedItems;
                    cartValue = value || cartItems.reduce((s, i) => s + (i.item_price * i.quantity), 0);
                    cartCurrency = currency || 'UAH';
                }
            }
        }

        const finalItems = cartItems.length ? cartItems : mappedItems;
        let finalValue = cartValue || value;
        if (!finalValue && finalItems.length > 0) {
            finalValue = finalItems.reduce((s, i) => s + ((i.item_price || i.price || 0) * (i.quantity || 1)), 0);
        }
        finalValue = Number(finalValue) || 0;
        const finalCurrency = cartCurrency || currency || 'UAH';

        let customData = {
            value: finalValue,
            currency: finalCurrency
        };

        if (finalItems && finalItems.length > 0 && FbEvents?.mapProducts) {
            const mappedData = FbEvents.mapProducts(finalItems, {});
            customData = {
                ...mappedData,
                value: finalValue,
                currency: finalCurrency
            };
        }

        fbq?.('trackCustom', 'FormSubmission', {
            content_type: 'product',
            value: finalValue,
            currency: finalCurrency,
            external_id,
            contents: finalItems
        });

        FbEvents?.CustomEvent?.('FormSubmission', customData);

        console.log('[Meta Event] 📩 FormSubmission via cApiEvent:', {
            value: finalValue,
            currency: finalCurrency,
            external_id,
            items: finalItems,
            customData
        });
    }
}

// ===========================
//  FORM SUBMISSION HANDLER (TILDA)
// ===========================
document.addEventListener('tildaform:aftersuccess', function () {
    let cartItems = [];
    let cartValue = 0;
    let cartCurrency = 'UAH';

    // Спочатку перевіряємо sessionStorage
    const storedCart = getCartData();
    if (storedCart.items && storedCart.items.length > 0) {
        cartItems = storedCart.items;
        cartValue = storedCart.value;
        cartCurrency = storedCart.currency;
        console.log('[FormSubmission] 📦 Using stored cart data:', { items: cartItems, value: cartValue, currency: cartCurrency });
    } else {
        // Якщо немає в sessionStorage, пробуємо синхронізувати з dataLayer
        const syncedCart = syncCartWithRealState();
        if (syncedCart.items && syncedCart.items.length > 0) {
            cartItems = syncedCart.items;
            cartValue = syncedCart.value;
            cartCurrency = syncedCart.currency;
            console.log('[FormSubmission] 📦 Using synced cart data:', { items: cartItems, value: cartValue, currency: cartCurrency });
        } else {
            console.warn('[FormSubmission] ⚠️ No cart data found in sessionStorage or dataLayer');
        }
    }

    const external_id =
        window.external_id ||
        getCookie('external_id') ||
        getCookie('median_user_id') ||
        'form_' + Date.now();

    let finalValue = cartValue;
    if (!finalValue && cartItems.length > 0) {
        finalValue = cartItems.reduce((s, i) => s + ((i.item_price || i.price || 0) * (i.quantity || 1)), 0);
    }
    finalValue = Number(finalValue) || 0;
    const finalCurrency = cartCurrency || 'UAH';

    let customData = {
        value: finalValue,
        currency: finalCurrency
    };

    if (cartItems && cartItems.length > 0 && FbEvents?.mapProducts) {
        const mappedData = FbEvents.mapProducts(cartItems, {});
        customData = {
            ...mappedData,
            value: finalValue,
            currency: finalCurrency
        };
    }

    fbq?.('trackCustom', 'FormSubmission', {
        content_type: 'product',
        value: finalValue,
        currency: finalCurrency,
        external_id,
        contents: cartItems
    });

    FbEvents?.CustomEvent?.('FormSubmission', customData);

    console.log('[Meta Event] 📩 FormSubmission sent (from session):', {
        value: finalValue,
        currency: finalCurrency,
        external_id,
        items: cartItems,
        customData
    });
});

// ===========================
//  CRM EVENTS
// ===========================
function sendLead(value = 0, currency = 'UAH', external_id) {
    const { items } = getCartData();
    fbq?.('track', 'Lead', {
        content_type: 'product',
        value: Number(value) || 0,
        currency,
        external_id: external_id || 'unknown_lead',
        contents: items
    });
    console.log('[Meta Event] 🚀 Lead sent:', { value, currency, external_id, items });
}

function sendPurchase(value = 0, currency = 'UAH', external_id) {
    const { items } = getCartData();
    FbEvents?.Purchase(value);
    fbq?.('track', 'Purchase', {
        content_type: 'product',
        value: Number(value) || 0,
        currency,
        external_id: external_id || 'unknown_purchase',
        contents: items
    });
    console.log('[Meta Event] 💰 Purchase sent:', { value, currency, external_id, items });
}

function sendContact(external_id) {
    FbEvents?.Contact();
    fbq?.('trackCustom', 'Contact', {
        content_type: 'product',
        external_id: external_id || 'unknown_contact'
    });
    console.log('[Meta Event] ☎️ Contact sent:', { external_id });
}

// ===========================
//  DATALAYER LISTENER
// ===========================
if (typeof subscribeToDataLayer === 'function') {
    subscribeToDataLayer(cApiEvent);
}

// ===========================
//  SALES DRIVE INTEGRATION
// ===========================
(function () {
    console.log('[SalesDrive] 🚀 Init external_id check...');
    const externalId =
        window.external_id ||
        getCookie('external_id') ||
        getCookie('median_user_id') ||
        null;

    if (!externalId) {
        console.warn('[SalesDrive] ⚠️ external_id not found.');
        return;
    }

    console.log('[SalesDrive] ✅ external_id found:', externalId);

    function addExternalIdToForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach((form) => {
            if (!form.querySelector('input[name="external_user_id"]')) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'external_user_id';
                hidden.value = externalId;
                form.appendChild(hidden);
            }
        });
    }

    // одразу додаємо поле
    addExternalIdToForms();

    // лише якщо динамічні форми
    const dynamicCheck = setInterval(() => {
        const before = document.querySelectorAll('input[name="external_user_id"]').length;
        addExternalIdToForms();
        const after = document.querySelectorAll('input[name="external_user_id"]').length;
        if (after > before) console.log('[SalesDrive] ✅ Forms updated with external_id.');
    }, 2000);

    document.addEventListener('submit', (e) => {
        const field = e.target.querySelector('input[name="external_user_id"]');
        console.log(
            field
                ? `[SalesDrive] 📤 Form sent with external_id = ${field.value}`
                : '[SalesDrive] 💤 Form without external_id.'
        );
    });
})();
