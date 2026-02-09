function dlEvent(event){
    if (event.event === "headerButtonRequestDemo"){
        FbEvents.ViewContent()
    }
    if (["leadCaptureForm", "contactForm", "popupCallback"].includes(event.event)){
        FbEvents.Lead()
    }
}
subscribeToDataLayer(dlEvent)
