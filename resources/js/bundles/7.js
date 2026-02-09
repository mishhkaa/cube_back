

if (document.cookie.includes('utm_source=')) {
    localStorage.setItem('adUrl', document.location.href);
}

function getUTMsFromUrl(u) {
    try {
        var url = new URL(u || location.href);
        var p = new URLSearchParams(url.search);
        return {
            utm_source: p.get('utm_source') || '(none)',
            utm_medium: p.get('utm_medium') || '(none)',
            utm_campaign: p.get('utm_campaign') || '(none)',
            utm_content: p.get('utm_content') || '(none)',
            utm_term: p.get('utm_term') || '(none)'
        };
    } catch (e) {
        return {
            utm_source: '(none)',
            utm_medium: '(none)',
            utm_campaign: '(none)',
            utm_content: '(none)',
            utm_term: '(none)'
        };
    }
}

function getUTMs() {
    var fromState = (typeof trafficSourceData === 'object' && trafficSourceData) ? {
        utm_source: trafficSourceData.utm_source || '(none)',
        utm_medium: trafficSourceData.utm_medium || '(none)',
        utm_campaign: trafficSourceData.utm_campaign || '(none)',
        utm_content: trafficSourceData.utm_content || '(none)',
        utm_term: trafficSourceData.utm_term || '(none)'
    } : null;

    if (fromState) return fromState;
    return getUTMsFromUrl(localStorage.getItem('adUrl') || location.href);
}

var viewContentItemsForAddToWishlist = null;

function eventDL(event){
    if (event.event === "subscribe"){
        FbEvents.Subscribe()
        TikTokEvents.Subscribe()
        return;
    }
    if (["Send_form_smoker", "Send_form_teaching"].includes(event.event)){
        FbEvents.CustomEvent(event.event)
        TikTokEvents.Lead()
        return;
    }
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('view_item' === event.event) {
        viewContentItemsForAddToWishlist = event.ecommerce.items;
        setTimeout(function () {
            fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items))
            ttq.track('ViewContent', TikTokEvents.mapProducts(event.ecommerce.items));
        }, 2000)
        return;
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items)
        TikTokEvents.AddToCart(event.ecommerce.items)
        return;
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
        TikTokEvents.InitiateCheckout(event.ecommerce.items)
        return;
    }
    if ('purchase' === event.event) {
        var txId = event.ecommerce.transaction_id;
        if (!txId) return;
        if (localStorage.getItem('purchase_' + txId)) return;
        localStorage.setItem('purchase_' + txId, '1');

        TikTokEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)

        var mapped = (typeof FbEvents.mapProducts === 'function') ? FbEvents.mapProducts(event.ecommerce.items) : { value: 0 };
        var utm = getUTMs();
        var payload = {
            date_time: new Date().toISOString(),
            order_id: txId,
            value: mapped.value || 0,
            utm_source: utm.utm_source,
            utm_medium: utm.utm_medium,
            utm_campaign: utm.utm_campaign,
            utm_content: utm.utm_content,
            utm_term: utm.utm_term,
            url: localStorage.getItem('adUrl') || location.href
        };

        if (typeof addRowToGoogleSheet === 'function') {
            setTimeout(function () { addRowToGoogleSheet(payload); }, 800);
        }
    }
}
subscribeToDataLayer(eventDL)

setTimeout(function () {
    document.querySelectorAll('a[href*="?add_to_wishlist="]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (viewContentItemsForAddToWishlist) {
                FbEvents.AddToWishlist(viewContentItemsForAddToWishlist);
            }
        });
    });
}, 3000);

(function() {
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }
    
    function getExternalId() {
        var externalId = getCookie('median_user_id');
        if (!externalId && typeof MedianGRPUtils !== 'undefined' && MedianGRPUtils.getUserId) {
            externalId = MedianGRPUtils.getUserId();
        }
        if (!externalId) {
            try {
                externalId = localStorage.getItem('median_user_id');
            } catch(e) {}
        }
        if (externalId) {
            console.log('[Bundle 7] External ID found:', externalId);
        } else {
            console.log('[Bundle 7] External ID not found in cookie, MedianGRPUtils, or localStorage');
        }
        return externalId;
    }
    
    function attachCheckoutHandler() {
        var checkoutForm = document.querySelector('form.checkout, form[name="checkout"], form.woocommerce-checkout');
        if (!checkoutForm) {
            console.log('[Bundle 7] Checkout form not found');
            return;
        }
        
        var orderCommentsField = checkoutForm.querySelector('#order_comments, textarea[name="order_comments"]');
        if (!orderCommentsField) {
            console.log('[Bundle 7] Order comments field not found');
            return;
        }
        
        if (checkoutForm.dataset.medianCheckoutHandled) {
            return;
        }
        checkoutForm.dataset.medianCheckoutHandled = 'true';
        
        console.log('[Bundle 7] Checkout handler attached to form');
        
        checkoutForm.addEventListener('submit', function(e) {
            var externalId = getExternalId();
            if (!externalId) {
                console.log('[Bundle 7] External ID not found, skipping');
                return;
            }
            
            var currentValue = (orderCommentsField.value || '').trim();
            var externalIdMarker = '["' + externalId + '"]';
            
            if (currentValue && !currentValue.includes(externalIdMarker)) {
                var newValue = currentValue + ' ' + externalIdMarker;
                orderCommentsField.value = newValue;
                console.log('[Bundle 7] ✅ External ID added to existing comment:', {
                    externalId: externalId,
                    originalValue: currentValue,
                    newValue: newValue
                });
            } else if (!currentValue) {
                orderCommentsField.value = externalIdMarker;
                console.log('[Bundle 7] ✅ External ID added to empty field:', {
                    externalId: externalId,
                    value: externalIdMarker
                });
            } else {
                console.log('[Bundle 7] ℹ️ External ID already exists in comment');
            }
        }, true);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachCheckoutHandler);
    } else {
        attachCheckoutHandler();
    }
    
    setTimeout(attachCheckoutHandler, 1000);
    
    var observer = new MutationObserver(function() {
        attachCheckoutHandler();
    });
    
    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
