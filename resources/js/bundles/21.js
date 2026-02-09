function dlEvent(event){
  if (event.event === 'purchase'){
    GAdsConversion.event('purchase', event.ecommerce.value, event.ecommerce.currency)
  }
}
subscribeToDataLayer(dlEvent)
