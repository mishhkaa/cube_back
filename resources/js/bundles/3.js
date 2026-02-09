function cApiEvent (event) {
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('view_item' === event.event) {
        fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items))
        ttq.track('ViewContent', TikTokEvents.mapProducts(event.ecommerce.items));
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items)
        ttq.track('AddToCart', TikTokEvents.mapProducts(event.ecommerce.items));
        // TikTokEvents.AddToCart(event.ecommerce.items)
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
        ttq.track('InitiateCheckout', TikTokEvents.mapProducts(event.ecommerce.items));
        // TikTokEvents.InitiateCheckout(event.ecommerce.items)
    }
    if ('purchase' === event.event) {
        if (event.eventId){
            FbEvents.setEventId(event.eventId)
        }
        FbEvents.Lead()
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
        ttq.track('Purchase', TikTokEvents.mapProducts(event.ecommerce.items, {order_id: event.ecommerce.transaction_id}));
        // TikTokEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
    }
}
subscribeToDataLayer(cApiEvent)
