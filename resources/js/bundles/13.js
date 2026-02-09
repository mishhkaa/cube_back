if (!window.dataLayer) window.dataLayer = [];

function GadsEvent (event) {
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }

    // 1. Перегляд товару — view_item
    if ('view_item' === event.event) {
        FbEvents.ViewContent(event.ecommerce.items)
        // Відправка в аналітику
        window.dataLayer.push({
            event: 'view_item',
            ecommerce: event.ecommerce
        })
    }
    
    // 2. Додавання в кошик — add_to_cart
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items)
        // Відправка в аналітику
        window.dataLayer.push({
            event: 'add_to_cart',
            ecommerce: event.ecommerce
        })
    }
    
    if ('add_to_wishlist' === event.event) {
        FbEvents.AddToWishlist()
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    
    // 3. Покупка — purchase (фінал)
    if ('purchase' === event.event) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
        GAdsConversion.event('purchase', event.ecommerce.value, event.ecommerce.currency)
        // Відправка в аналітику
        window.dataLayer.push({
            event: 'purchase',
            ecommerce: event.ecommerce
        })
        setTimeout(function (){
            addRowToGoogleSheet({
                order_id: event.ecommerce.transaction_id,
                value: FbEvents.mapProducts(event.ecommerce.items).value || 0,
                utm_source: trafficSourceData.utm_source,
                utm_medium: trafficSourceData.utm_medium,
                utm_campaign: trafficSourceData.utm_campaign,
                utm_content: trafficSourceData.utm_content,
                utm_term: trafficSourceData.utm_term,
            })
        }, 1000)
    }
}
subscribeToDataLayer(GadsEvent)

if (location.pathname.includes('/checkout/delivery')){
    FbEvents.AddPaymentInfo()
}
