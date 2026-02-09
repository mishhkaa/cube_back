function cApiEvent (event) {
    if (['click_email', 'click_phone_number'].includes(event.event)) {
        FbEvents.Contact()
        return
    }
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('begin_checkout' === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    if ('purchase' === event.event) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
    }
    if ('add_to_cart' === event.event) {
        FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.items[0].item_name)
    }
    if ('view_item' === event.event) {
        let content_name = event.ecommerce.items[0].item_name;
        fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items, { content_name }))
    }
    if (event.event === 'add_to_wishlist') {
        FbEvents.AddToWishlist(event.ecommerce.items, event.ecommerce.items[0].item_name)
    }
}

subscribeToDataLayer( cApiEvent )

// Purchase на форму "купити в 1 клік" (ajax=buy_click, buy-click[product_id], buy-click[product_count])

    function initBuyClickForm() {
        var form = document.querySelector('form.js_ajax input[name="ajax"][value="buy_click"]')
        if (!form) return
        form = form.closest('form')
        if (!form || form._fbPurchaseBound) return
        form._fbPurchaseBound = true
        form.addEventListener('submit', function () {
            var productIdInput = form.querySelector('input[name="buy-click[product_id]"]')
            var countInput = form.querySelector('input[name="buy-click[product_count]"]')
            if (!productIdInput) return
            var productId = (productIdInput.value || '').trim()
            var quantity = 1
            if (countInput && countInput.value) {
                var n = parseInt(countInput.value, 10)
                if (!isNaN(n) && n > 0) quantity = n
            }
            if (!productId) return
            var items = [{ id: productId, quantity: quantity, item_price: 0 }]
            var txId = 'buy_click_' + Date.now() + '_' + productId
            if (typeof FbEvents !== 'undefined' && typeof FbEvents.Purchase === 'function') {
                FbEvents.Purchase(items, txId)
            }
        })
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBuyClickForm)
    } else {
        initBuyClickForm()
    }

