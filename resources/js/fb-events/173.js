setTimeout(function (){
    if (FbEvents.getQueryParam('fbclid') && location.hostname.includes('adsquiz.io')){
        var leadId = localStorage.getItem('lead_uuid');
        FbEvents.setCookie(leadId);
        FbEvents.init(true)
    }
}, 2000)