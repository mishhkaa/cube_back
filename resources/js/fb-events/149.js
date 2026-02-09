function cApiEvent (event) {
    if (event.event === 'Binotel GetCall - Call requested') {
        FbEvents.CustomEvent('Callback')
        return
    }
    if (event.event === 'Binotel Chat - Chat happened') {
        FbEvents.CustomEvent('btForm')
        return
    }
    if (event.event === 'registration') {
        FbEvents.CompleteRegistration()
        return
    }
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('view_item' === event.event) {
        let content_name = event.ecommerce.items[0].item_name
        fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items, { content_name }))
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.items[0].item_name)
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    if ('purchase' === event.event) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
    }
}
subscribeToDataLayer(cApiEvent)
