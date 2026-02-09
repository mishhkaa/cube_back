FbEvents.ViewContent()
const fbclid = FbEvents.getCookie('fbclid')
if (fbclid){
    FbEvents.setCookie('fbclid', fbclid)
    MedianGRPUtils.setUserId(fbclid)
    FbEvents.init();
}
document.addEventListener('DOMContentLoaded', () => {
    if (location.hostname.includes('promo.kolo.in')){
        setTimeout(function (){
            document.querySelectorAll('a[href*="my.kolo.in/authentication"]').forEach(e => {
                e.addEventListener('click', () => {
                    GAdsConversion.event('click_get_your_card')
                    FbEvents.CustomEvent('click_get_your_card')
                })
            })
        }, 2000)
    }
})
