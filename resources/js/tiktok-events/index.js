(function (window) {
    const ttclid = 'ttclid',
        source = 'tiktok',
        tiktok_user_id = 'tiktok_user_id',
        utils = window.MedianGRPUtils,
        stack = [];

    let sending = false,
        userData = null,
        debug = false,
        currency = 'USD',
        account_id = null,
        sendForOnlyFromAds = false;

    [ttclid, tiktok_user_id].forEach(function (name) {
        let value = utils.getQueryParam(name)
        if (value){
            utils.setCookie(name, value)
        }
    })

    const mapProducts = (products, custom_data) => {
        if (!products || !products.length) return custom_data
        let data = Object.assign(custom_data || {}, {
            content_ids: [],
            value: 0,
            contents: [],
            content_type: 'product',
            currency
        })
        products.map(e => {
            if (e.content_id || e.item_id) {
                data.content_ids.push(e.content_id || e.item_id)
                data.contents.push(utils.filterObject({
                    content_id: e.content_id || e.item_id,
                    content_name: e.content_name || e.item_name,
                    content_category: e.content_category || e.item_category,
                    quantity: parseInt(e.quantity) || 1,
                    price: parseFloat(e.price),
                    brand: e.brand || e.item_brand,
                }))
                data.value += (parseFloat(e.price) || 0) * parseInt(parseInt(e.quantity) || 1)
            } else console.log('content_id or item_id is a required')
        })
        return utils.filterObject(data)
    }

    const getUserData = () => {
        let defaultData = {
            user_agent: navigator.userAgent,
            ttclid: utils.getCookie(ttclid),
            ttp: utils.getCookie('_ttp'),
            external_id: utils.getUserId() || utils.getCookie(tiktok_user_id),
            locale: navigator.language
        }
        return utils.filterObject({...defaultData, ...userData})
    }

    const triggerSend = () => {
        if (sending) return;
        let item = stack.shift()
        if (!item) return;
        sending = true
        debug && console.log(item.data)
        let status;
        window.fetch(
            'https://api.median-grp.com/partners/tiktok-events/' + account_id,
            {body: utils.jsonToFormData(item.data), mode: 'cors', method: 'POST'}
        )
        .then(e => e.json())
        .then(e => {
            if (e) {
                utils.setCookie(tiktok_user_id, e.id)
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
            debug && console.log(status ? 'Ok' : 'Error')
            utils.pushLogEvent({data: item.data, status, send: true, source})
        })
    }

    const send = (event, properties) => {
        if (!account_id) throw 'Config is not init';

        const data = utils.filterObject({
            event,
            user: getUserData(),
            properties,
            page: {
                url: location.href,
                referrer: document.referrer
            }
        })

        if (sendForOnlyFromAds && !(utils.getCookie(ttclid) || utils.getCookie(tiktok_user_id))){
            utils.pushLogEvent( {data, status: true, send: false, source})
            return new Promise((r) => r({id: utils.getUserId() || utils.getCookie(tiktok_user_id) || 't' + Date.now()}));
        }

        let executor = {};
        const promise = new Promise((resolve, reject) => {
            executor = {resolve, reject}
        })

        stack.push({data, executor})
        triggerSend()
        return promise
    }

    const tiktokEvents = {
        debug: (enable = true) => {
            debug = enable
            return tiktokEvents
        },
        setConfig: (id, curr, onlyAds) => {
            account_id = id
            currency = curr
            sendForOnlyFromAds = onlyAds
            window.sessionStorage.setItem('median-events-tiktok', id)
        },
        setUserData: (data) => {
            if (typeof data !== 'object') throw 'A parameter data is not type object'
            userData = data
            return tiktokEvents
        },
        setCurrency: (curr) => {
            if (typeof curr !== 'string') throw 'A parameter currency should be a string'
            if (curr.length !== 3) throw 'A parameter currency should consist for 3 letters'
            currency = curr.toUpperCase()
            return tiktokEvents
        },
        getCurrency: () => currency,
        init: (update) => {
            const id = utils.getCookie(tiktok_user_id)
            if (id && !update) {
                return new Promise((r) => r({id}));
            }
            return send('init')
        },
        AddToWishlist: (products) => send('AddToWishlist', mapProducts(products)),
        ApplicationApproval: () => send('ApplicationApproval'),
        ViewContent: (products) => send('ViewContent', mapProducts(products)),
        AddToCart: (products) => send('AddToCart', mapProducts(products)),
        InitiateCheckout: (products) => send('InitiateCheckout', mapProducts(products)),
        Purchase: (products, order_id) => send('Purchase', mapProducts(products, {order_id})),
        AddPaymentInfo: () => send('AddPaymentInfo'),
        Lead: () => send('Lead'),
        Contact: () => send('Contact'),
        Download: () => send('Download'),
        Schedule: () => send('Schedule'),
        Search: (search_string) => send('Search', {search_string}),
        StartTrial: (value) => send('StartTrial', {value}),
        SubmitApplication: () => send('SubmitApplication'),
        Subscribe: () => send('Subscribe'),
        CompleteRegistration: () => send('CompleteRegistration'),
        getCookie: (name = tiktokEvents) => utils.getCookie(name),
        setCookie: (value, name = tiktokEvents) => utils.setCookie(name, value),
        getQueryParam: (name = ttclid) => utils.getQueryParam(name),
        mapProducts
    }
    window.TikTokEvents = tiktokEvents
})(window);
