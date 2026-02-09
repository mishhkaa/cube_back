window.lastEvents = []

function rememberLastEventTwoSeconds(event){
    window.lastEvents.push(event)
    setTimeout(function (){
        window.lastEvents = window.lastEvents.filter(function (i){ return  i !== event})
    }, 2000)
}

function cApiEvent (event) {
    if ("begin_checkout" === event.event && event.ecommerce.ecomm_prodid) {
        FbEvents.CustomEvent('InitiateCheckout', {
            value: event.ecommerce.value,
            currency: event.ecommerce.currency,
            content_ids: event.ecommerce.ecomm_prodid,
            content_type: 'product',
        })
    }
    if (!event.ecommerce || !event.ecommerce.items) {
        return
    }
    if ('view_item' === event.event && !window.lastEvents.includes('view_item') && event.ecommerce.items[0].price) {
        let content_name = event.ecommerce.items[0].item_name
        fbq('track', 'ViewContent', FbEvents.mapProducts(event.ecommerce.items, { content_name }))
        rememberLastEventTwoSeconds('view_item')
    }
    if ('add_to_cart' === event.event && !window.lastEvents.includes('add_to_cart') && event.ecommerce.items[0].price) {
        FbEvents.AddToCart(event.ecommerce.items, event.ecommerce.items[0].item_name)
        rememberLastEventTwoSeconds('add_to_cart')
    }
    if ("begin_checkout" === event.event) {
        FbEvents.InitiateCheckout(event.ecommerce.items)
    }
    if ('purchase' === event.event && !window.lastEvents.includes('purchase') && event.ecommerce.transaction_id) {
        FbEvents.Purchase(event.ecommerce.items, event.ecommerce.transaction_id)
        rememberLastEventTwoSeconds('purchase')
    }

}
subscribeToDataLayer(cApiEvent)

!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '643334178229476');
fbq('track', 'PageView');
