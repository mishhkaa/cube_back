FbEvents.init()
if (location.pathname.includes('/vashu-zayavku-oformleno/')){
    if (!localStorage.getItem('send_lead')){
        setTimeout(function(){
            (function(i){function c(o,e,r){if(e&&typeof e=="object"&&!(e instanceof Date)&&!(e instanceof File))Object.keys(e).forEach(n=>{c(o,e[n],r?`${r}[${n}]`:n)});else{const n=e??"";o.append(r,n)}}function t(o){const e=new FormData;return c(e,o),e}window.addRowToGoogleSheet=function(o){if(typeof o!="object")throw new Error("data must be an object or array");return o instanceof FormData||(Array.isArray(o)||(o=[o]),o=t(o)),fetch("https://api.median-grp.com/partners/google-sheets/"+i,{body:o,method:"POST",mode:"cors"})}})(12);
            addRowToGoogleSheet({
                utm_source: window.trafficSourceData.utm_source,
                utm_medium: window.trafficSourceData.utm_medium,
                utm_campaign: window.trafficSourceData.utm_campaign,
                utm_term: window.trafficSourceData.utm_term,
                utm_content: window.trafficSourceData.utm_content,
                fb_partners_user_id: FbEvents.getCookie(),
                ga_client_id: window.trafficSourceData.ga_client_id,
                ga_session_id: window.trafficSourceData.ga_session_id,
                url: document.referrer
            })
        }, 1000)
        localStorage.setItem('send_lead', '1')
    }
}else {
    localStorage.setItem('send_lead', '')
}
if(location.pathname.includes('success-free-event')){
    FbEvents.Contact()
}
