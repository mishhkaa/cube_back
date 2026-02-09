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

function dlEvent_27(event) {
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
                if (localStorage.getItem('purchase_27_' + txId)) {
                    purchaseDedupe.add(txId);
                    return;
                }
            } catch (e) {}
            
            // Записуємо в обидва місця ПЕРЕД відправкою події
            purchaseDedupe.add(txId);
            try {
                localStorage.setItem('purchase_27_' + txId, '1');
            } catch (e) {}
            
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.Purchase === 'function') {
                if (event.eventId && typeof FbEvents.setEventId === 'function') {
                    FbEvents.setEventId(event.eventId);
                }
                FbEvents.Purchase(event.ecommerce.items, txId);
            }
            
            // Відправка в Google Sheets (функція addRowToGoogleSheet вже визначена через Google Sheets integration)
            setTimeout(function () {
                if (typeof addRowToGoogleSheet === 'function') {
                    // Підготовка значень з перевірками
                    var value = 0;
                    try {
                        if (typeof FbEvents !== 'undefined' && typeof FbEvents.mapProducts === 'function') {
                            var mapped = FbEvents.mapProducts(event.ecommerce.items);
                            value = mapped ? (mapped.value || 0) : 0;
                        }
                    } catch (e) {
                        console.error('[Bundle 27] Error mapping products:', e);
                    }
                    
                    var utmSource = '';
                    var utmMedium = '';
                    var utmCampaign = '';
                    var utmContent = '';
                    var utmTerm = '';
                    
                    if (typeof trafficSourceData !== 'undefined' && trafficSourceData) {
                        utmSource = trafficSourceData.utm_source || '';
                        utmMedium = trafficSourceData.utm_medium || '';
                        utmCampaign = trafficSourceData.utm_campaign || '';
                        utmContent = trafficSourceData.utm_content || '';
                        utmTerm = trafficSourceData.utm_term || '';
                    }
                    
                    var adUrl = '';
                    try {
                        adUrl = localStorage.getItem('adUrl') || '';
                    } catch (e) {}
                    
                    var payload = {
                        order_id: txId,
                        value: value,
                        utm_source: utmSource,
                        utm_medium: utmMedium,
                        utm_campaign: utmCampaign,
                        utm_content: utmContent,
                        utm_term: utmTerm,
                        url: adUrl,
                    };
                    
                    // Перевірка, що payload не порожній
                    var hasNonEmptyValue = false;
                    for (var key in payload) {
                        if (payload[key] !== '' && payload[key] !== null && payload[key] !== undefined) {
                            hasNonEmptyValue = true;
                            break;
                        }
                    }
                    
                    if (!hasNonEmptyValue) {
                        console.warn('[Bundle 27] ⚠️ Payload is empty, skipping Google Sheets');
                        return;
                    }
                    
                    console.log('[Bundle 27] 📊 Sending to Google Sheets:', payload);
                    
                    try {
                        var result = addRowToGoogleSheet(payload);
                        console.log('[Bundle 27] ✅ addRowToGoogleSheet called');
                        
                        if (result && typeof result.then === 'function') {
                            result.then(function(res) {
                                console.log('[Bundle 27] ✅ Google Sheets response:', res);
                                if (res && res.status) {
                                    console.log('[Bundle 27] Status:', res.status);
                                    if (res.status !== 200) {
                                        console.error('[Bundle 27] ❌ Non-200 status:', res.status);
                                    }
                                }
                            }).catch(function(err) {
                                console.error('[Bundle 27] ❌ Google Sheets error:', err);
                            });
                        }
                    } catch (e) {
                        console.error('[Bundle 27] ❌ Error calling addRowToGoogleSheet:', e);
                    }
                } else {
                    console.error('[Bundle 27] ❌ addRowToGoogleSheet is not a function');
                }
            }, 1000);
        }
    } catch (e) {
        console.error('[Bundle 27] Error processing event:', e, event);
    }
}

// Підписка на події dataLayer
if (typeof subscribeToDataLayer === 'function') {
    try {
        subscribeToDataLayer(dlEvent_27);
    } catch (e) {
        console.error('[Bundle 27] Error subscribing to dataLayer:', e);
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
        console.error('[Bundle 27] Error attaching wishlist handler:', e);
    }
}, 3000);
