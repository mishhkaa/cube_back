setTimeout(function (){
    fbq('track', 'ViewContent')
}, 1000)
const event = MedianGRPUtils.getQueryParam('event');
if (event === 'catalog'){
    FbEvents.CustomEvent('Lead_Catalog')
    FbEvents.CustomEvent('Lead_All')
}
if (event === 'lead'){
    FbEvents.CustomEvent('Lead_Form')
    FbEvents.CustomEvent('Lead_All')
}
if (event === 'quiz'){
    FbEvents.CustomEvent('Lead_Quiz')
    FbEvents.CustomEvent('Lead_All')
}
