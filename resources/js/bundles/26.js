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

// In-memory cache для захисту від race condition
var purchaseDedupe = new Set();

function dlEvent_26(event) {
    if (!event || !event.event) return;
    
    try {
        // Обробка quick_order (може бути без ecommerce)
        if (event.event === 'quick_order') {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.CustomEvent === 'function') {
                FbEvents.CustomEvent('QuickOrder');
            }
            return;
        }
        
        if (!event.ecommerce || !event.ecommerce.items) return;
        
        // Перевірка що items є масивом і не порожній
        if (!Array.isArray(event.ecommerce.items) || event.ecommerce.items.length === 0) {
            return;
        }
        
        if ('view_item' === event.event) {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.ViewContent === 'function') {
                FbEvents.ViewContent(event.ecommerce.items);
            }
            viewContentItemsForAddToWishlist = event.ecommerce.items;
        }
        
        if ('add_to_cart' === event.event) {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.AddToCart === 'function') {
                FbEvents.AddToCart(event.ecommerce.items);
            }
        }
        
        if ('begin_checkout' === event.event) {
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.InitiateCheckout === 'function') {
                FbEvents.InitiateCheckout(event.ecommerce.items);
            }
        }
        
        if ('purchase' === event.event) {
            var txId = event.ecommerce.transaction_id;
            if (!txId) return;
            
            // Антидубль: спочатку перевіряємо in-memory cache (захист від race condition)
            if (purchaseDedupe.has(txId)) {
                return;
            }
            
            // Потім перевіряємо localStorage
            try {
                if (localStorage.getItem('purchase_26_' + txId)) {
                    purchaseDedupe.add(txId);
                    return;
                }
            } catch (e) {}
            
            // Записуємо в обидва місця ПЕРЕД відправкою події
            purchaseDedupe.add(txId);
            try {
                localStorage.setItem('purchase_26_' + txId, '1');
            } catch (e) {}
            
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.Purchase === 'function') {
                if (event.eventId && typeof FbEvents.setEventId === 'function') {
                    FbEvents.setEventId(event.eventId);
                }
                FbEvents.Purchase(event.ecommerce.items, txId);
            }
        }
    } catch (e) {
        console.error('[Bundle 26] Error processing event:', e, event);
    }
}

// Підписка на події dataLayer
if (typeof subscribeToDataLayer === 'function') {
    try {
        subscribeToDataLayer(dlEvent_26);
    } catch (e) {
        console.error('[Bundle 26] Error subscribing to dataLayer:', e);
    }
}

setTimeout(function () {
    try {
        document.querySelectorAll('a[href*="?add_to_wishlist="]').forEach(function (el) {
            el.addEventListener('click', function () {
                if (viewContentItemsForAddToWishlist) {
                    if (typeof FbEvents !== 'undefined' && typeof FbEvents.AddToWishlist === 'function') {
                        FbEvents.AddToWishlist(viewContentItemsForAddToWishlist);
                    }
                }
            });
        });
    } catch (e) {
        console.error('[Bundle 26] Error attaching wishlist handler:', e);
    }
}, 3000);
