function _once(k,ttl){try{var n=Date.now();var v=JSON.parse(sessionStorage.getItem(k)||'0');if(v && n-v<ttl)return false;sessionStorage.setItem(k,JSON.stringify(n));return true}catch(_){return true}}
function _id(p){return(p||'evt')+'-'+Math.random().toString(36).slice(2)+Date.now()}

function DlEvent(e){
    if(!e||!e.event) return;

    if(e.event==='GetQuote' && _once('oq',3000)){
        try{ FbEvents.setEventId(_id('gq')).CustomEvent('GetQuote', e.params||null) }catch(_){}
    }
    if(e.event==='Lead' && _once('ol',5000)){
        try{ FbEvents.setEventId(_id('ld')).Lead(1) }catch(_){}
    }

    if(e.event==='gtm.linkClick' && e.gtm){
        var txt=(e.gtm.elementText||'').trim();
        var cls=(e.gtm.elementClasses||'');
        if ((/get a quote/i.test(txt) || (/\buicore-btn\b/.test(cls)&&/\buicore-inverted\b/.test(cls))) && _once('oq',3000)){
            try{ FbEvents.setEventId(_id('gq')).CustomEvent('GetQuote',{place:'header'}) }catch(_){}
        }
    }

    if(e.event==='gtm.elementVisibility' && e.gtm && e.gtm.elementId==='about_us_subscribe_success' && _once('ol',5000)){
        try{ FbEvents.setEventId(_id('ld')).Lead(1) }catch(_){}
    }
}
subscribeToDataLayer && subscribeToDataLayer(DlEvent);

(function(){
    function ready(f){ if(document.readyState!=='loading') f(); else document.addEventListener('DOMContentLoaded',f) }

    ready(function(){
        document.addEventListener('click',function(ev){
            var a=ev.target.closest('.uicore-cta-wrapper a.uicore-btn.uicore-inverted');
            if(!a) return;
            if(_once('oq',3000)){
                try{ FbEvents.setEventId(_id('gq')).CustomEvent('GetQuote',{place:'header'}) }catch(_){}
                window.dataLayer && dataLayer.push({event:'GetQuote',params:{place:'header'}});
            }
        },true);

        var f=document.getElementById('about_us_subscribe_form_form');
        if(f){
            f.addEventListener('submit',function(){
                setTimeout(function(){
                    if(_once('ol',5000)){
                        try{ FbEvents.setEventId(_id('ld')).Lead(1) }catch(_){}
                        window.dataLayer && dataLayer.push({event:'Lead'});
                    }
                },1000);
            });
        }

        if(window.jQuery){
            jQuery(document).on('ajaxComplete',function(_,__,settings){
                var url=(settings&&settings.url)||'';
                if(url.indexOf('about_us_form_send')!==-1 && _once('ol',5000)){
                    try{ FbEvents.setEventId(_id('ld')).Lead(1) }catch(_){}
                    window.dataLayer && dataLayer.push({event:'Lead'});
                }
            });
        }
    });
})();
