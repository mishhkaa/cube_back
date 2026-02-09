function dlEvent(event) {
    if ([
        "headerButtonRequestDemo",
        "bannerBigButtonRequestDemo",
        "switchFeatureButtonRequestDemo",
        "mobileMenuButtonRequestDemo"
    ].includes(event.event)) {
        FbEvents.ViewContent();
    }

    if ([
        "leadCaptureForm",
        "contactForm",
        "popupCallback"
    ].includes(event.event)) {
        FbEvents.Lead();
    }
}

subscribeToDataLayer(dlEvent);
