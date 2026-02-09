(function (window) {
    const fb_partners_user_id = 'fb_partners_user_id',
        fbclid = 'fbclid',
        source = 'fb',
        utils = window.MedianGRPUtils,
        stack = []

    let sending = false,
        userData = null,
        currency = 'USD',
        partner_id = null,
        sendForOnlyFromAds = false,
        eventId = null;

    [fbclid, fb_partners_user_id].forEach(function (e) {
        const utm = utils.getQueryParam(e)
        if (utm){
            utils.setCookie(e, utm || null)
        }
    })

    const mapProducts = (products, custom_data) => {
        if (!products || !products.length) return custom_data
        let data = Object.assign(custom_data || {}, {
            content_ids: [],
            value: 0,
            contents: [],
            currency
        })
        products.map(e => {
            if (e.id || e.item_id) {
                data.content_ids.push(e.id || e.item_id)
                data.contents.push({
                    id: e.id || e.item_id,
                    quantity: parseInt(e.quantity) || 1,
                    item_price: parseFloat(e.item_price) || parseFloat(e.price) || 0,
                })
                data.value += (parseFloat(e.item_price) || parseFloat(e.price) || 0) * (parseInt(e.quantity) || 1)
            } else console.log('id or item_id is a required')
        })
        return data
    }

    const getUserData = () => {
        let defaultData = {
            client_user_agent: navigator.userAgent,
            fbp: utils.getCookie('_fbp'),
            external_id: utils.getUserId() || utils.getCookie(fb_partners_user_id)
        }
        return utils.filterObject({...defaultData, ...userData})
    }

    const triggerSend = () => {
        if (sending) return;
        const item = stack.shift()
        if (!item) return;
        sending = true
        let status;
        window.fetch(
            'https://api.median-grp.com/partners/fbcapi',
            {body: utils.jsonToFormData(item.data), mode: 'cors', method: 'POST'}
        )
            .then(e => e.json())
            .then(e => {
                if (e) {
                    utils.setCookie(fb_partners_user_id, e.id)
                    utils.setUserId(e.id)
                }
                item.executor.resolve(e)
                status = true
            })
            .catch(() => {
                status = false
                item.executor.reject()
            })
            .finally(() => {
                sending = false
                triggerSend()
                utils.pushLogEvent({data: item.data, status, send: true, source})
            })
    }

    const send = (event_name, custom_data) => {
        if (!partner_id) throw 'Config is not init';

        const data = utils.filterObject({
            event_name,
            partner_id,
            [fbclid]: utils.getQueryParam(fbclid) || utils.getCookie(fbclid),
            event_source_url: location.href,
            user_data: getUserData(),
            custom_data: utils.filterObject(custom_data),
            event_id: eventId
        })

        eventId = null;

        if (sendForOnlyFromAds && !(utils.getCookie(fb_partners_user_id) || utils.getCookie(fbclid))){
            utils.pushLogEvent( {data, status: true, send: false, source} )
            return new Promise((r) => r({id: utils.getUserId() || utils.getCookie(fb_partners_user_id) || 'f' + Date.now()}));
        }

        let executor = {};
        const promise = new Promise((resolve, reject) => {
            executor = {resolve, reject}
        })

        stack.push({data, executor})
        triggerSend()
        return promise
    }

    const FbEvents = {
        setConfig: (id, curr, onlyAds) => {
            partner_id = id
            currency = curr
            sendForOnlyFromAds = onlyAds
            window.sessionStorage.setItem('median-events-fb', id)
        },
        setUserData: (data) => {
            if (typeof data !== 'object') throw 'A parameter data is not type object'
            userData = data
            return FbEvents
        },
        setCurrency: (curr) => {
            if (typeof curr !== 'string') throw 'A parameter currency should be a string'
            if (curr.length !== 3) throw 'A parameter currency should consist for 3 letters'
            currency = curr.toUpperCase()
            return FbEvents
        },
        setEventId: (id) => {
            eventId = id
            return FbEvents
        },
        getCurrency: () => currency,
        init: (update) => {
            const id = utils.getCookie(fb_partners_user_id)
            if (id && !update) {
                return new Promise((r) => r({id}));
            }
            return send('init')
        },
        AddToWishlist: (products, content_name) => {
            return send('AddToWishlist', mapProducts(products, {content_name}))
        },
        ViewContent: (content_id, item_price, content_name, quantity, content_category) => {
            let data = {}
            if (content_id) {
                if (Array.isArray(content_id)) {
                    data = mapProducts(content_id, {content_category: item_price})
                }else {
                    data = mapProducts([{
                        id: content_id,
                        quantity,
                        item_price
                    }], {content_name})
                }
            }
            return send('ViewContent', data)
        },
        AddToCart: (content_id, item_price, quantity, content_name) => {
            let data = {}
            if (content_id) {
                if (Array.isArray(content_id)) {
                    data = mapProducts(content_id, {content_category: item_price})
                }else {
                    data = mapProducts([{
                        id: content_id,
                        quantity,
                        item_price
                    }], {content_name})
                }
            }
            return send('AddToCart', data)
        },
        InitiateCheckout: (products) => send('InitiateCheckout', mapProducts(products)),
        Purchase: (products, order_id, content_category) => {
            return send('Purchase', mapProducts(products, { order_id, content_category}))
        },
        AddPaymentInfo: (products) => send('AddPaymentInfo', mapProducts(products)),
        Lead: (value) => send('Lead', {value, currency}),
        Contact: () => send('Contact'),
        SubmitApplication: () => send('SubmitApplication'),
        Subscribe: (value, predicted_ltv) => {
            return send('Subscribe', {
                value, predicted_ltv,
                currency
            })
        },
        CompleteRegistration: (content_name, value, status = true) => {
            return send('CompleteRegistration', {
                status: status,
                value, content_name,
                currency
            })
        },
        CustomEvent: (eventName, params) => {
            if (/\s/.test(eventName.trim())) throw new Error(`eventName not can contains spaces`)
            if (params && typeof params !== 'object') throw 'A second parameter "params" is not object!'
            return send(eventName, params)
        },
        getCookie: (name = fb_partners_user_id) => utils.getCookie(name),
        setCookie: (value, name = fb_partners_user_id) => utils.setCookie(name, value),
        getQueryParam: (name = fbclid) => utils.getQueryParam(name),
        mapProducts
    }
    window.FbEvents = FbEvents
})(window);
