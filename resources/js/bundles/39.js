function dlEvent(event) {
    if (event && event.event === "formSuccess") {
        FbEvents.Lead();
    }
}

subscribeToDataLayer(dlEvent);

document.addEventListener('wpcf7mailsent', function () {
    FbEvents.Lead();
}, false);
