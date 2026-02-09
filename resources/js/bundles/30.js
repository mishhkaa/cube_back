function cApiEvent(event) {
    if (!event.ecommerce) {
        if (['Lead', 'lead'].includes(event.event)) {
            FbEvents.Lead();
            fbq('track', 'Lead');
        }
        if (['Contact', 'contact'].includes(event.event)) {
            FbEvents.Contact();
            fbq('track', 'Contact');
        }
        return;
    }

    if (!event.ecommerce.items) return;

    if (['view_item', 'viewItem', 'detail', 'productView'].includes(event.event)) {
        FbEvents.ViewContent(event.ecommerce.items);
        fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items));
    }

    if (['add_to_cart', 'addToCart', 'add_to_cart_button'].includes(event.event)) {
        FbEvents.AddToCart(event.ecommerce.items);
        fbq('track', 'AddToCart', FbEvents.mapProducts(event.ecommerce.items));
    }

    if (['begin_checkout', 'beginCheckout'].includes(event.event)) {
        FbEvents.InitiateCheckout(event.ecommerce.items);
        fbq('track', 'InitiateCheckout', FbEvents.mapProducts(event.ecommerce.items));
    }

    if (['purchase', 'Purchase'].includes(event.event)) {
        if (event.eventId) FbEvents.setEventId(event.eventId);
        FbEvents.Lead();
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id);
        fbq('track', 'Purchase', FbEvents.mapProducts(event.ecommerce.items));
    }
}

subscribeToDataLayer(cApiEvent);
