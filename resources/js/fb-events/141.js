var isSendingMessage = false;
function cApiEvent (event) {
    if (event.event === 'chat' && event.type === 'userMessage' && !isSendingMessage){
        FbEvents.CustomEvent('LeadChat')
        FbEvents.CustomEvent('LeadAll')
        isSendingMessage = true;
    }
    if (event.event === "gaTriggerEvent" && event.gaEventAction === "Call requested" && event.gaEventCategory === "CallCatcher"){
        FbEvents.CustomEvent('LeadCall')
        FbEvents.CustomEvent('LeadAll')
    }
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.items[0].item_namee)
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }

    if ('purchase' === event.event) {
        FbEvents.CustomEvent('LeadForm', FbEvents.mapProducts(event.ecommerce.items, {
            order_id: event.ecommerce.transaction_id
        }))
        FbEvents.CustomEvent('LeadAll')
    }

}
FbEvents.init()
subscribeToDataLayer( cApiEvent )

document.addEventListener('DOMContentLoaded', function () {
    if (location.pathname.indexOf('/products/') === 0 && document.querySelector('.button.add-to-cart')){
        let item_price = parseInt(jQuery('#product_configuration_price').text().replace(' ', '')),
            id = jQuery('.button.add-to-cart').first().data('product-id'),
            content_name = jQuery('.product-configurator__product-title').first().find('h3').text().trim()
        fbq('track', 'ViewContent', FbEvents.mapProducts([{ id, item_price }], { content_name }))
    }
})

jQuery( document ).on( "ajaxComplete", function( event, request, settings ) {
    console.log(request, settings)
    if (settings.data.includes('action=product_call_me_form') && request.responseText === '1'){
        FbEvents.CustomEvent('LeadForm')
        FbEvents.CustomEvent('LeadAll')
    }
} );

jQuery('.fur-buttons .add-to-cart').click(function (){
    var $btn = jQuery(this);
    if ($btn.parent().prev('.product-card__link').length){
        var name = $btn.parent().prev('.product-card__link').find('.ttl span').text().trim()
        var price = parseInt($btn.parent().prev('.product-card__link').find('.ttl').text().trim().replace(' ', '').replace(/\D/g, ''))
    }else{
        var name = $btn.closest('.column-info').find('.product-intro__title').text().trim()
        var price = parseInt($btn.closest('.column-info').find('.fur-price').text().trim().replace(' ', '').replace(/\D/g, ''));
    }
    dataLayer.push({
        'event': 'add_to_cart',
        ecommerce: {
            items: [{
                item_id: $btn.data('product-id'),
                item_name: name,
                price: price,
                quantity: 1,
            }]
        }
    })
})
