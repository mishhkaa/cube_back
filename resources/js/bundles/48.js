if (!window.dataLayer) window.dataLayer = [];

// --- V12: ViewContent на сторінці товару (/catalog/...), InitiateCheckout на /checkout, Lead при сабміті #checkout-form та на /form/thanks?status=checkout ---
(function v12DomTracking() {
    function sendInitiateCheckoutOnCheckoutPage() {
        if (typeof window.location === 'undefined' || window.location.pathname.indexOf('/checkout') === -1) return;
        if (typeof FbEvents === 'undefined' || typeof FbEvents.InitiateCheckout !== 'function') return;
        FbEvents.InitiateCheckout([]);
    }

    function getLeadValueFromPage() {
        try {
            var search = (typeof window.location !== 'undefined' && window.location.search) || '';
            if (!search) return undefined;
            var params = new URLSearchParams(search);
            var value = params.get('value') || params.get('order_value') || params.get('total') || params.get('amount') || params.get('sum') || params.get('order_sum');
            if (value != null && value !== '') {
                var num = parseFloat(String(value).replace(/[\s,]/g, '').replace(',', '.'));
                if (!isNaN(num)) return num;
            }
            var el = document.querySelector('[data-order-value], [data-order-total], [data-lead-value], .order-total, .order-value');
            if (el) {
                var dataVal = el.getAttribute('data-order-value') || el.getAttribute('data-order-total') || el.getAttribute('data-lead-value') || (el.textContent && el.textContent.trim());
                if (dataVal) {
                    var n = parseFloat(String(dataVal).replace(/[\s,]/g, '').replace(',', '.'));
                    if (!isNaN(n)) return n;
                }
            }
        } catch (e) {}
        return undefined;
    }

    function sendLeadOnThanksPage() {
        var path = typeof window.location !== 'undefined' ? window.location.pathname || '' : '';
        var search = typeof window.location !== 'undefined' ? window.location.search || '' : '';
        if (path.indexOf('/form/thanks') === -1) return;
        if (search.indexOf('status=checkout') === -1) return;
        if (typeof FbEvents === 'undefined' || typeof FbEvents.Lead !== 'function') return;
        var key = 'median_48_lead_thanks_sent';
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
        var value = getLeadValueFromPage();
        FbEvents.Lead(value);
    }

    function getLeadValueFromCheckoutForm() {
        var totalEl = document.getElementById('total');
        if (totalEl && totalEl.textContent) {
            var t = (totalEl.textContent || '').replace(/[\s\u00a0]/g, '').replace(',', '.');
            var num = parseFloat(t);
            if (!isNaN(num)) return num;
        }
        var carts = document.querySelectorAll('#checkout-form .cart-inner');
        var sum = 0;
        for (var i = 0; i < carts.length; i++) {
            var cart = carts[i];
            var qtyInput = cart.querySelector('.product-quantity, input[name^="products["]');
            var qty = 1;
            var price = 0;
            if (qtyInput) {
                var val = qtyInput.value || qtyInput.getAttribute('value');
                if (val != null && val !== '') qty = parseInt(val, 10) || 1;
                var p = qtyInput.getAttribute('data-price');
                if (p != null && p !== '') price = parseFloat(String(p).replace(/[\s,]/g, '').replace(',', '.')) || 0;
            }
            if (!price) {
                var priceEl = cart.querySelector('.cart-inner-price span, .cart-price span, [data-price]');
                if (priceEl) {
                    var t = (priceEl.getAttribute('data-price') || priceEl.textContent || '').replace(/[\s\u00a0]/g, '').replace(',', '.');
                    if (t) price = parseFloat(t) || 0;
                }
            }
            sum += price * qty;
        }
        return sum > 0 ? sum : undefined;
    }

    function sendLeadOnCheckoutFormSubmit() {
        var form = document.getElementById('checkout-form');
        if (!form || typeof FbEvents === 'undefined' || typeof FbEvents.Lead !== 'function') return;
        var dedupeKey = 'median_48_lead_checkout_sent';
        try {
            if (sessionStorage.getItem(dedupeKey)) return;
            sessionStorage.setItem(dedupeKey, '1');
            setTimeout(function () { try { sessionStorage.removeItem(dedupeKey); } catch (err) {} }, 5000);
        } catch (err) {}
        var value = getLeadValueFromCheckoutForm();
        FbEvents.Lead(value);
    }

    function attachCheckoutFormLead() {
        var form = document.getElementById('checkout-form');
        if (!form) return;
        form.addEventListener('submit', function () {
            sendLeadOnCheckoutFormSubmit();
        }, true);
    }

    function sendViewContentOnProductPage() {
        var path = typeof window.location !== 'undefined' ? window.location.pathname || '' : '';
        if (path.indexOf('/catalog/') === -1) return;
        if (typeof FbEvents === 'undefined' || typeof FbEvents.ViewContent !== 'function') return;
        var btn = document.querySelector('.add-to-cart-btn, button[data-sku]');
        var sku = null;
        if (btn) {
            sku = btn.getAttribute('data-sku') || btn.getAttribute('data-product-id') || btn.getAttribute('data-id');
        }
        if (!sku) {
            var match = path.match(/\/catalog\/([^\/]+)/);
            sku = match ? match[1] : 'product';
        }
        FbEvents.ViewContent([{ id: String(sku), quantity: 1 }]);
    }

    function attachListeners() {
        sendInitiateCheckoutOnCheckoutPage();
        sendViewContentOnProductPage();
        sendLeadOnThanksPage();
        attachCheckoutFormLead();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachListeners);
    } else {
        attachListeners();
    }
})();

(function subscribeToDataLayerSafe(callback) {
    var originalPush = window.dataLayer.push;
    window.dataLayer.push = function () {
        var result = originalPush.apply(window.dataLayer, arguments);
        try {
            var ev = arguments[0];
            if (ev && typeof ev === 'object' && ev.event) {
                callback(ev);
            } else if (arguments.length >= 2 && arguments[0] === 'event' && typeof arguments[1] === 'string') {
                callback({ event: arguments[1], ecommerce: arguments[2] || null });
            }
        } catch (e) {
            console.error(e);
        }
        return result;
    };
})(DlEvent);

function DlEvent(event) {
    if (!event || !event.event) return;

    if (['Lead', 'lead'].includes(event.event)) {
        FbEvents.Lead();
        return;
    }
    if (['Contact', 'contact'].includes(event.event)) {
        FbEvents.Contact();
        return;
    }
    if (event.event === 'newsletter') {
        FbEvents.Subscribe();
        return;
    }

    if (!event.ecommerce || !event.ecommerce.currency) {
        return;
    }

    if (!event.ecommerce.items) return;

    if ('view_item' === event.event) {
        FbEvents.ViewContent(event.ecommerce.items);
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items);
    }
    if ('add_to_wishlist' === event.event) {
        FbEvents.AddToWishlist(event.ecommerce.items);
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items);
    }
    if ('purchase' === event.event) {
        if (event.eventId) FbEvents.setEventId(event.eventId);
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id);
    }
}

// --- Перевірка в консолі: Median48Test.check() | .sendViewContent('5') | .sendInitiateCheckout() | .sendLead() ---
window.Median48Test = {
    check: function () {
        var ok = typeof FbEvents !== 'undefined';
        var btns = document.querySelectorAll('.add-to-cart-btn, button[data-sku]');
        var path = typeof location !== 'undefined' ? location.pathname : '';
        console.log('[Median48] FbEvents:', ok ? 'OK' : 'НЕ ЗНАЙДЕНО');
        console.log('[Median48] dataLayer:', typeof window.dataLayer !== 'undefined' ? 'OK (length ' + (window.dataLayer && window.dataLayer.length) + ')' : 'НЕ ЗНАЙДЕНО');
        console.log('[Median48] pathname:', path, path.indexOf('/checkout') !== -1 ? '(checkout)' : '');
        console.log('[Median48] Кнопки .add-to-cart-btn / [data-sku]:', btns.length, btns);
        return { FbEvents: ok, dataLayer: !!window.dataLayer, pathname: path, buttonsCount: btns.length };
    },
    sendViewContent: function (sku) {
        sku = sku || '5';
        if (typeof FbEvents === 'undefined' || typeof FbEvents.ViewContent !== 'function') {
            console.error('[Median48] FbEvents або FbEvents.ViewContent відсутні');
            return;
        }
        FbEvents.ViewContent([{ id: String(sku), quantity: 1 }]);
        console.log('[Median48] Відправлено ViewContent, sku:', sku);
    },
    sendInitiateCheckout: function () {
        if (typeof FbEvents === 'undefined' || typeof FbEvents.InitiateCheckout !== 'function') {
            console.error('[Median48] FbEvents або FbEvents.InitiateCheckout відсутні');
            return;
        }
        FbEvents.InitiateCheckout([]);
        console.log('[Median48] Відправлено InitiateCheckout');
    },
    sendLead: function () {
        if (typeof FbEvents === 'undefined' || typeof FbEvents.Lead !== 'function') {
            console.error('[Median48] FbEvents або FbEvents.Lead відсутні');
            return;
        }
        FbEvents.Lead();
        console.log('[Median48] Відправлено Lead');
    },
};
