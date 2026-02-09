(function (window) {
    const twclid = 'twclid',
        source = 'x',
        x_user_id = 'x_user_id',
        utils = window.MedianGRPUtils,
        stack = [];

    let sending = false,
        userData = null,
        currency = 'USD',
        account_id = null,
        sendForOnlyFromAds = false,
        mapEvents = {},
        conversionId = null;

    [twclid, x_user_id].forEach(function (name) {
        let value = utils.getQueryParam(name)
        if (value){
            utils.setCookie(name, value)
        }
    })

    const mapProducts = (products, custom_data) => {
        if (!products || !products.length) return custom_data
        let data = Object.assign(custom_data || {}, {
            value: 0,
            contents: [],
            currency
        })
        products.map(e => {
            if (e.content_id || e.item_id) {
                data.contents.push(utils.filterObject({
                    content_id: e.content_id || e.item_id,
                    content_name: e.content_name || e.item_name,
                    content_type: e.content_type || e.item_category,
                    num_items: e.num_items || e.quantity,
                    content_price: e.content_price || e.price,
                }))
                data.value += (e.content_price || e.price || 0) * parseInt(e.num_items || e.quantity || 1)
            } else console.log('content_id or item_id is a required')
        })
        return utils.filterObject(data)
    }

    const getUserData = () => {
        let defaultData = {
            user_agent: navigator.userAgent,
            [twclid]: utils.getCookie(twclid),
            external_id: utils.getUserId() || utils.getCookie(x_user_id),
        }
        return utils.filterObject({...defaultData, ...userData})
    }

    const triggerSend = () => {
        if (sending) return;
        let item = stack.shift()
        if (!item) return;
        sending = true
        let status;
        window.fetch(
            'https://api.median-grp.com/partners/x-events/' + account_id,
            {body: utils.jsonToFormData(item.data), mode: 'cors', method: 'POST'}
        )
        .then(e => e.json())
        .then(e => {
            if (e) {
                utils.setCookie(x_user_id, e.id)
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
            utils.pushLogEvent({data: {...item.data, event: item.event}, status, send: true, source})
        })
    }

    const send = (event, params) => {
        if (!account_id) throw 'Config is not init';
        if (!mapEvents[event]) throw 'The event is not added'

        const data = utils.filterObject({
            event_id: mapEvents[event],
            identifiers: getUserData(),
            conversionId,
            ...(params || {})
        })
        conversionId = null;

        if (sendForOnlyFromAds && !(utils.getCookie(twclid) || utils.getCookie(x_user_id))){
            utils.pushLogEvent( {data: {...data, event}, status: true, send: false, source})
            return new Promise((r) => r({id: utils.getUserId() || utils.getCookie(x_user_id) || 'x' + Date.now()}));
        }

        let executor = {};
        const promise = new Promise((resolve, reject) => {
            executor = {resolve, reject}
        })

        stack.push({data, executor, event})
        triggerSend()
        return promise
    }

    const XEvents = {
        setConfig: (id, curr, onlyAds, events) => {
            account_id = id
            currency = curr
            sendForOnlyFromAds = onlyAds
            mapEvents = events
            window.sessionStorage.setItem('median-events-x', id)
        },
        setUserData: (data) => {
            if (typeof data !== 'object') throw 'A parameter data is not type object'
            userData = data
            return XEvents
        },
        setCurrency: (curr) => {
            if (typeof curr !== 'string') throw 'A parameter currency should be a string'
            if (curr.length !== 3) throw 'A parameter currency should consist for 3 letters'
            currency = curr.toUpperCase()
            return XEvents
        },
        setConversionId: (id) => {
            conversionId = id
            return XEvents
        },
        getCurrency: () => currency,
        init: (update) => {
            const id = utils.getCookie(x_user_id)
            if (id && !update) {
                return new Promise((r) => r({id}));
            }
            return send('init')
        },
        AddToCart: (products) => send('AddToCart', mapProducts(products)),
        AddToWishlist: (products) => send('AddToWishlist', mapProducts(products)),
        AddPaymentInfo: (products) => send('AddPaymentInfo', mapProducts(products)),
        CheckoutInitiated: (products) => send('CheckoutInitiated', mapProducts(products)),
        ContentView: (products) => send('ContentView', mapProducts(products)),
        ProductCustomization: (products) => send('ProductCustomization', mapProducts(products)),
        Custom: () => send('Custom'),
        Download: () => send('Download'),
        Lead: (status, products) => send('Lead', mapProducts(products, {status: status ? 'started' : 'completed'})),
        Purchase: (products, conversion_id) => send('Purchase', mapProducts(products, {conversion_id})),
        Search: (search_string) => send('Search', {search_string}),
        StartTrial: (value) => send('StartTrial', {value}),
        Subscribe: (status, value) => send('Subscribe', {status: status ? 'started' : 'completed', value}),
        PageView: () => send('PageView'),
        getEventId: (event) => mapEvents[event],
        getCookie: (name = x_user_id) => utils.getCookie(name),
        setCookie: (value, name = x_user_id) => utils.setCookie(name, value),
        getQueryParam: (name = twclid) => utils.getQueryParam(name),
        mapProducts
    }
    window.XEvents = XEvents
})(window);
