function cApiEvent (event) {
    if (event.event === 'lead') {
        FbEvents.Lead()
    }
}
subscribeToDataLayer(cApiEvent)
