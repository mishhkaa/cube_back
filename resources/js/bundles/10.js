function DLEvent(event){
    if (event.event === "ga4_purchase"){
        GAdsConversion.event('purchase', event.ecommerce.value)
        setTimeout(function (){
            addRowToGoogleSheet({
                order_id: event.ecommerce.transaction_id,
                value: event.ecommerce.value,
                utm_source: trafficSourceData.utm_source,
                utm_medium: trafficSourceData.utm_medium,
                utm_campaign: trafficSourceData.utm_campaign,
            })
        }, 1000)
    }
}
subscribeToDataLayer(DLEvent);
