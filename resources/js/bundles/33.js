function dlEvent(event) {
    if (!event || !event.event) return;

    switch (event.event) {

        case 'form_contact_submit':
            gtag('event', 'form_contact_submit');
            break;

        case 'binotel_phone_click':
            gtag('event', 'binotel_phone_click');
            break;

        case 'mobile_phone_click':
            gtag('event', 'mobile_phone_click');
            break;

        case 'one_minute_on_s':
            gtag('event', 'one_minute_on_s');
            break;

        case 'scroll_50_per':
            gtag('event', 'scroll_50_per');
            break;

        case 'telegram_click':
            gtag('event', 'telegram_click');
            break;

        case 'viber_click':
            gtag('event', 'viber_click');
            break;

        case 'instagram_click':
            gtag('event', 'instagram_click');
            break;

        case 'purchase':
            if (event.ecommerce) {
                gtag('event', 'purchase', {
                    value: event.ecommerce.value,
                    currency: event.ecommerce.currency,
                    transaction_id: event.ecommerce.transaction_id,
                    items: event.ecommerce.items
                });
            }
            break;

        default:
            console.log('Unhandled event:', event);
            break;
    }
}

subscribeToDataLayer(dlEvent);
