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

var view_item_send = false;

function cApiEvent(event) {
    var utm = getUTMs();
    var utmSource = utm.utm_source;
    if (utmSource && utmSource !== '(none)' && utmSource.toLowerCase() === 'facebook') {
        return;
    }
    
    if (!event.ecommerce || !event.ecommerce.items) {
        return;
    }

    if ('view_item' === event.event && !view_item_send && typeof jQuery !== 'undefined' && jQuery('body.single-product').length) {
        view_item_send = true;
        var item_price = event.ecommerce.items[0].price,
            id = event.ecommerce.items[0].internal_id || event.ecommerce.items[0].item_id,
            content_name = event.ecommerce.items[0].item_name;
        
        fbq('track', 'ViewContent', FbEvents.mapProducts([{ id, item_price }], { content_name }));
        
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.ViewContent(event.ecommerce.items);
        }
    }

    if ('add_to_cart' === event.event && event.origin === 'fb-events') {
        FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.items[0].item_name);
        
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.AddToCart(event.ecommerce.items);
        }
    }

    if ('begin_checkout' === event.event) {
        var products = event.ecommerce.items.map(function (item) {
            return {
                id: item.internal_id || item.item_id,
                item_price: parseFloat(item.price),
                quantity: parseInt(item.quantity),
            };
        });
        
        FbEvents.InitiateCheckout(products);
        
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.InitiateCheckout(event.ecommerce.items);
        }
    }
    
    if ('purchase_gtm' === event.event) {
        var products = event.ecommerce.items.map(function (item) {
            return {
                id: parseInt(item.item_id),
                item_price: parseFloat(item.price),
                quantity: parseInt(item.quantity),
            };
        });
        
        FbEvents.Purchase(products, event.ecommerce.transaction_id);
        
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id);
        }
    }
}

subscribeToDataLayer(cApiEvent);

if (location.pathname.includes('product-category')){
    FbEvents.CustomEvent('ViewCategory');
}

if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('added_to_cart', function (e, t) {
        t.ga4 && dataLayer.push({
            event: 'add_to_cart',
            ecommerce: {
                items: [t.ga4],
                currency: 'UAH',
                value: t.ga4.price
            },
            origin: 'fb-events'
        });
    });

    jQuery(document.body).on('buy_one_click', function (e, t) {
        typeof t === 'object' && dataLayer.push({
            event: 'purchase_gtm',
            ecommerce: {
                items: [{
                    item_id: t.data.product_id,
                    item_name: t.data.product_name,
                    price: parseInt(t.data.value),
                    quantity: 1
                }],
                transaction_id: t.data.order_id,
                value: parseInt(t.data.value),
                currency: 'UAH'
            }
        });
    });
}

function handleLeadEvents() {
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on("ajaxComplete", function(event, request, settings) {
            var url = settings.url;
            if (url.indexOf('api/lead/form') !== -1 || url.indexOf('api/cart/lead') !== -1) {
                localStorage.setItem('send_item', '1');
            }
        });
    }

    if (localStorage.getItem('send_item')){
        FbEvents.Lead();
        
        if (typeof TikTokEvents !== 'undefined') {
            TikTokEvents.Lead();
        }
        
        localStorage.removeItem('send_item');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', handleLeadEvents);
} else {
    handleLeadEvents();
}
