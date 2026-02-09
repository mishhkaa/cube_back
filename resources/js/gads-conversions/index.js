(function (window, accountId) {

    const gads_user_id = 'gads_user_id',
        gclidName = 'gclid',
        utils = window.MedianGRPUtils,
        source = 'gads'

    const gadsUserID = utils.getQueryParam(gads_user_id)
    if (gadsUserID) {
        utils.setCookie(gads_user_id, gadsUserID)
        utils.setUserId(gadsUserID)
    }

    const request = (data) => {
        const body = utils.filterObject(Object.assign({
            gclid: gclid || utils.getCookie(gclidName),
            click_id: utils.getUserId() || utils.getCookie(gads_user_id),
        }, data))

        let status
        return fetch(
            'https://api.median-grp.com/partners/gads-conversions/' + accountId,
            { body: utils.jsonToFormData(body), mode: 'cors', method: 'POST' },
        ).then(e => e.json()).then(e => {
            if (e.id){
                utils.setCookie(gads_user_id, e.id)
                utils.setUserId(e.id)
            }
            status = true
            return e
        }).catch(() => {
            status = false
        }).finally(() => {
            utils.pushLogEvent({ data, status, send: true, source })
        })
    }

    const event = (name, value, currency) => {
        if (!name) throw new Error('Event name is required')
        const externalId = utils.getCookie(gads_user_id)
        const data = utils.filterObject({
            event: name,
            value: value,
            currency: currency,
        })

        if (!externalId && !gclid && !utils.getCookie(gclidName)) {
            utils.pushLogEvent({ data, status: true, send: false, source })
            return new Promise((r) => r({ id: utils.getUserId() || 'f' + Date.now() }))
        }

        return request(data)
    }

    let gclid = utils.getQueryParam(gclidName) || utils.getQueryParam('gbraid') || utils.getQueryParam('wbraid')

    if (gclid) {
        utils.setCookie(gclidName, gclid)
        request({
            event: 'init',
        }, true)
    }
    window.sessionStorage.setItem('median-events-gads', accountId)
    window.GAdsConversion = {
        setCookie: (value, name = gads_user_id) => utils.setCookie(name, value),
        getCookie: (name = gads_user_id) => utils.getCookie(name),
        getQueryParam: (name = gclidName) => utils.getQueryParam(name),
        event,
    }
})(window, '[[accountId]]')
