function dlEvent(event) {
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('view_item' === event.event) {
        FbEvents.ViewContent(event.ecommerce.items[0].id, event.ecommerce.value)
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items.id, event.ecommerce.value)
    }
    if ('add_to_wishlist' === event.event) {
        FbEvents.AddToWishlist()
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    if ('purchase' === event.event) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
    }
}
subscribeToDataLayer(dlEvent)

