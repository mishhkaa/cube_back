if (location.pathname.includes('lesson/vstupnij-urok') && document.referrer.includes('register')){
    FbEvents.CompleteRegistration()
}
function cApiEvent(event){
    if (event.event === "Form_submitted" && event.ecommerce.stranica === "cryptology.education/base-camp"){
        FbEvents.CompleteRegistration()
    }
}
subscribeToDataLayer(cApiEvent)
jQuery( document ).on( "ajaxComplete", function( event, request, settings ) {
    var url = settings.url;
    var res = $.parseJSON(request.responseText);
    if (url.indexOf('propbase') !== -1 && res.status === "success") {
        FbEvents.CompleteRegistration();
    }
} );
