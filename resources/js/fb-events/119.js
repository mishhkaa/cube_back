if (location.pathname.includes('/lesson/vstupnij-urok') && !localStorage.getItem('tracking_reg')) {
    FbEvents.CompleteRegistration()
    localStorage.setItem('tracking_reg', '1')
}

jQuery( document ).on( "ajaxComplete", function( event, request, settings ) {
    var url = settings.url;
    var res = $.parseJSON(request.responseText);
    if (url.indexOf('propbase') !== -1 && res.status === "success") {
        FbEvents.CompleteRegistration();
    }
    if (url.indexOf('online-school') !== -1 && res.success){
        FbEvents.CompleteRegistration();
    }
} );
