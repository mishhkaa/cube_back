if (!window.dataLayer) window.dataLayer = [];

// INIT
if (typeof FbEvents !== 'undefined' && typeof FbEvents.init === 'function') {
    FbEvents.init();
}

if (document.cookie.includes('utm_source=')) {
    try {
        localStorage.setItem('adUrl', document.location.href);
    } catch (e) {}
}

var viewContentItemsForAddToWishlist = null;

/* ===== helpers (GA4-safe) ===== */
function getItems(event) {
    if (event?.ecommerce?.items && Array.isArray(event.ecommerce.items)) {
        return event.ecommerce.items;
    }
    if (event?.items && Array.isArray(event.items)) {
        return event.items;
    }
    return null;
}

function getTransactionId(event) {
    return event?.ecommerce?.transaction_id || event?.transaction_id || null;
}

/* ===== main handler ===== */
function dlEvent_6(event) {
    if (!event || !event.event) return;

    try {
        // quick_order без ecommerce
        if (event.event === 'quick_order') {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.CustomEvent === 'function') {
                FbEvents.CustomEvent('QuickOrder');
            }
            return;
        }

        // Обробка purchase окремо (може мати іншу структуру)
        if (event.event === 'purchase') {
            var txId = getTransactionId(event);
            if (!txId) return;

            var purchaseItems = getItems(event);
            if (!purchaseItems || !Array.isArray(purchaseItems) || purchaseItems.length === 0) {
                // Якщо items не знайдено через getItems, спробуємо напряму
                purchaseItems = event.ecommerce?.items || event.items || null;
                if (!purchaseItems || !Array.isArray(purchaseItems) || purchaseItems.length === 0) {
                    return;
                }
            }

            // Антидубль через localStorage
            try {
                if (localStorage.getItem('purchase_' + txId)) {
                    return;
                }
                localStorage.setItem('purchase_' + txId, '1');
            } catch (e) {}

            // Відправка подій
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.Purchase === 'function') {
                if (event.eventId && typeof FbEvents.setEventId === 'function') {
                    FbEvents.setEventId(event.eventId);
                }
                FbEvents.Purchase(purchaseItems, txId);
            }

            if (typeof TikTokEvents !== 'undefined' && typeof TikTokEvents.Purchase === 'function') {
                TikTokEvents.Purchase(purchaseItems, txId);
            }
            return;
        }

        // Для інших подій використовуємо стандартну перевірку
        const items = getItems(event);
        if (!items || !Array.isArray(items) || items.length === 0) return;

        if (event.event === 'view_item') {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.ViewContent === 'function') {
                FbEvents.ViewContent(items);
            }
            viewContentItemsForAddToWishlist = items;
        }

        if (event.event === 'add_to_cart') {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.AddToCart === 'function') {
                FbEvents.AddToCart(items);
            }
        }

        if (event.event === 'begin_checkout') {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.InitiateCheckout === 'function') {
                FbEvents.InitiateCheckout(items);
            }
        }
    } catch (e) {
        console.error('[Bundle 6] Error processing event:', e, event);
    }
}

/* ===== subscribe ===== */
if (typeof subscribeToDataLayer === 'function') {
    try {
        subscribeToDataLayer(dlEvent_6);
    } catch (e) {
        console.error('[Bundle 6] Error subscribing to dataLayer:', e);
    }
}

/* ===== wishlist ===== */
setTimeout(function () {
    try {
        if (typeof jQuery !== 'undefined') {
            jQuery('a[href*="?add_to_wishlist="]').on('click', function () {
                if (viewContentItemsForAddToWishlist) {
                    if (typeof FbEvents !== 'undefined' && typeof FbEvents.AddToWishlist === 'function') {
                        FbEvents.AddToWishlist(viewContentItemsForAddToWishlist);
                    }
                }
            });
        }
    } catch (e) {
        console.error('[Bundle 6] Error attaching wishlist handler:', e);
    }
}, 3000);
