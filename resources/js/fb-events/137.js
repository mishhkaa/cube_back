setTimeout(function (){
    if (!FbEvents.getQueryParam('fbclid')){
        return;
    }

    var leadId = localStorage.getItem('lead_uuid');
    FbEvents.setCookie(leadId);
    FbEvents.init(true)
}, 2000)
