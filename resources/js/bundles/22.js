function DlEvent(event){
    if (event.event === "newsletter") {
        FbEvents.Subscribe()
    }

    if (!event.ecommerce || !event.ecommerce.currency){
        return;
    }

    if ('view_item' === event.event) {
        fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items))
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items)
    }
    if ('add_to_wishlist' === event.event) {
        FbEvents.AddToWishlist(event.ecommerce.items)
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    if ('purchase' === event.event) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
    }
}

subscribeToDataLayer(DlEvent);

document.addEventListener('DOMContentLoaded', function () {
    jQuery(document).on('submit_success', function (e, d) {
        var $form = jQuery(e.target)
        if ($form.find('input[name="form_fields[customemail]"]').length) {
            FbEvents.Subscribe()
        }
    })
})

if (!localStorage.getItem('20second')){
    setTimeout(function (){
        localStorage.setItem('20second', '1')
        fbq('trackCustom', '20SecondView')
    }, 20000)
}