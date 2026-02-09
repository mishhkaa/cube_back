function DlEvent(event){
    if (event.event === "newsletter") {
        FbEvents.Subscribe()
    }

    if (!event.ecommerce || !event.ecommerce.currency){
        return;
    }

    if ('view_item' === event.event) {
        FbEvents.ViewContent(event.ecommerce.items)
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
