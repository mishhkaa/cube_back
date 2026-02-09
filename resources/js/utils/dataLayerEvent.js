(function (window, dataLayer) {
    window[dataLayer] = window[dataLayer] || []

    if (!window[dataLayer].useMedianGrp) {
        window[dataLayer].useMedianGrp = true

        const parseEvent = (args) => {
            if (!args.length) return args

            const [type, name, ecommerce] = args
            return (type === 'event' && typeof name === 'string')
                ? { event: name, ecommerce: ecommerce || null }
                : args
        }

        const originalPush = window[dataLayer].push

        window[dataLayer].push = function (...args) {
            originalPush.apply(window[dataLayer], args)
            window.document.dispatchEvent(new CustomEvent(dataLayer + 'Push', { detail: parseEvent(...args) }))
        }

        window.subscribeToDataLayer = function (callback) {
            window[dataLayer].forEach(event => callback(parseEvent(event)))
            window.document.addEventListener(dataLayer + 'Push', e => {
                try {
                    callback(e.detail)
                } catch (e) {
                    console.error(e)
                }
            })
        }
    }
})(window, 'dataLayer')
