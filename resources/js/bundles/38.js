if (document.cookie.includes('utm_source=') || MedianGRPUtils.getQueryParam('utm_source')) {
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

function GadsEvent38 (event) {
    if (!event || !event.event) {
        return;
    }
    
    var utm = getUTMs();
    var utmSource = utm.utm_source;
    
    if (!utmSource || utmSource === '(none)' || utmSource.toLowerCase() !== 'facebook') {
        return;
    }
    
    if (typeof FbEvents === 'undefined') {
        return;
    }
    
    if (!event.ecommerce || !event.ecommerce.items) {
        return;
    }
    
    // Перевірка що items є масивом і не порожній
    if (!Array.isArray(event.ecommerce.items) || event.ecommerce.items.length === 0) {
        return;
    }
    
    try {
    if ('view_item' === event.event) {
            if (typeof FbEvents.ViewContent === 'function') {
                FbEvents.ViewContent(event.ecommerce.items, event.ecommerce.value);
            }
    }
        
    if ('add_to_cart' === event.event) {
            if (typeof FbEvents.AddToCart === 'function') {
                FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.value);
            }
    }
        
    if ('add_to_wishlist' === event.event) {
            if (typeof FbEvents.AddToWishlist === 'function') {
                FbEvents.AddToWishlist(event.ecommerce.items);
            }
    }
        
    if ('begin_checkout' === event.event) {
            if (typeof FbEvents.InitiateCheckout === 'function') {
                FbEvents.InitiateCheckout(event.ecommerce.items);
            }
    }
        
    if ('purchase' === event.event) {
            if (typeof FbEvents.Purchase === 'function') {
                var txId = event.ecommerce.transaction_id;
                if (txId) {
                    FbEvents.Purchase(event.ecommerce.items, txId);
                }
            }
        }
    } catch (e) {
        console.error('[Bundle 38] Error sending event:', e, event);
    }
}

subscribeToDataLayer(GadsEvent38);

