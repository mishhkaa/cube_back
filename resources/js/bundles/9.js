MedianGRPUtils.runWhenDOMLoaded(() => {
    document.querySelectorAll('a[href*="mngr.cc"]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault()
            GAdsConversion.event('get_started').then(() => {
                if (GAdsConversion.getCookie()) {
                    location.href = el.href + '?gads_user_id=' + MedianGRPUtils.getUserId()
                }
                FbEvents.CustomEvent('GetStarted').then(() => {
                    if (FbEvents.getCookie()) {
                        location.href = el.href + '?fb_partners_user_id=' + MedianGRPUtils.getUserId()
                    }else{
                        location.href = el.href
                    }
                })
            })
        })
    })
})

function onUrlChange (callback) {
    const originalPushState = history.pushState
    const originalReplaceState = history.replaceState

    history.pushState = function (...args) {
        originalPushState.apply(this, args)
        callback(location.href)
    }

    history.replaceState = function (...args) {
        originalReplaceState.apply(this, args)
        callback(location.href)
    }

    window.addEventListener('popstate', () => {
        callback(location.href)
    })
}

if (!localStorage.getItem('sendLeadEvent')) {
    onUrlChange(() => {
        if (location.pathname === '/profile/' && !localStorage.getItem('sendLeadEvent')) {
            FbEvents.CompleteRegistration()
            GAdsConversion.event('lead_form')
            localStorage.setItem('sendLeadEvent', '1')
        }
    })
}
