!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '259054948544925');
fbq('track', 'PageView');

window.view_item_send = false
function cApiEvent (event) {
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }

    if ('view_item' === event.event && !window.view_item_send && jQuery('body.single-product').length) {
        window.view_item_send = true;
        let item_price = event.ecommerce.items[0].price,
            id = event.ecommerce.items[0].internal_id || event.ecommerce.items[0].item_id,
            content_name = event.ecommerce.items[0].item_name
        fbq('track', 'ViewContent', FbEvents.mapProducts([{ id, item_price }], { content_name }))
    }

    if ('add_to_cart' === event.event && event.origin === 'fb-events') {
        FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.items[0].item_name)
    }

    if ('begin_checkout' === event.event) {
        var products = event.ecommerce.items.map(function (item) {
            return {
                id: item.internal_id || item.item_id,
                item_price: parseFloat(item.price),
                quantity: parseInt(item.quantity),
            }
        })
        FbEvents.InitiateCheckout(products)
    }
    if ('purchase_gtm' === event.event) {
        var products = event.ecommerce.items.map(function (item) {
            return {
                id: parseInt(item.item_id),
                item_price: parseFloat(item.price),
                quantity: parseInt(item.quantity),
            }
        })
        FbEvents.Purchase(products, event.ecommerce.transaction_id)
    }
}

subscribeToDataLayer( cApiEvent )

if (location.pathname.includes('product-category')){
    FbEvents.CustomEvent('ViewCategory')
}

jQuery(document.body).on('added_to_cart', function (e, t) {
    t.ga4 && dataLayer.push({
        event: 'add_to_cart',
        ecommerce: {
            items: [t.ga4],
            currency: 'UAH',
            value: t.ga4.price
        },
        origin: 'fb-events'
    })
})
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
    })
})

window.addEventListener('DOMContentLoaded', function () {
    window.jQuery && jQuery( document ).on( "ajaxComplete", function( event, request, settings ) {
        var url = settings.url;
        if (url.indexOf('api/lead/form') !== -1 || url.indexOf('api/cart/lead') !== -1) {
            localStorage.setItem('send_item', '1')
        }
    } );

    if (localStorage.getItem('send_item')){
        FbEvents.Lead()
        localStorage.removeItem('send_item')
    }
})
