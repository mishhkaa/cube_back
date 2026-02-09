document.addEventListener('submit', function(event) {
    const form = event.target;
    if (form.tagName.toLowerCase() === 'form' && jQuery(form).find('textarea[name="user_message"]').length) {
        FbEvents.Lead()
    }
}, true);

jQuery( document ).on( "ajaxComplete", function( event, request, settings ) {
    var url = settings.url;
    if (url.indexOf('client/partner') !== -1 && url.indexOf('client/partner/password') === -1 && request.status === 201) {
        FbEvents.Lead();
    }
} );
