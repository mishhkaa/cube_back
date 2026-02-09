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

function GadsEvent (event) {
    var utm = getUTMs();
    var utmSource = utm.utm_source;
    if (utmSource && utmSource !== '(none)' && utmSource.toLowerCase() === 'facebook') {
        return;
    }
    
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('view_item' === event.event) {
        FbEvents.ViewContent(event.ecommerce.items[0].id, event.ecommerce.value)
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.ViewContent(event.ecommerce.items)
        }
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items.id, event.ecommerce.value)
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.AddToCart(event.ecommerce.items)
        }
    }
    if ('add_to_wishlist' === event.event) {
        FbEvents.AddToWishlist()
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.AddToWishlist()
        }
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.InitiateCheckout(event.ecommerce.items)
        }
    }
    if ('purchase' === event.event) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
        }
    }
}
subscribeToDataLayer(GadsEvent)