document.addEventListener( 'wpcf7mailsent', function( event ) {
    if ( ['115567', '1149', '98291', '5', '6384'].includes(event.detail.contactFormId) ) {
        FbEvents.CustomEvent('LeadForm')
        FbEvents.CustomEvent('LeadAll')
    }
}, false );

var isSendingMessage = false;
function cApiEvent(event){
    if (event.event === 'chat' && event.type === 'userMessage' && !isSendingMessage){
        FbEvents.CustomEvent('LeadChat')
        FbEvents.CustomEvent('LeadAll')
        isSendingMessage = true;
    }
    if (event.event === "gaTriggerEvent" && event.gaEventAction === "Call requested" && event.gaEventCategory === "CallCatcher"){
        FbEvents.CustomEvent('LeadCall')
        FbEvents.CustomEvent('LeadAll')
    }
}
subscribeToDataLayer(cApiEvent)
