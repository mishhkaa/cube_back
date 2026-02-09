(function (window, document){
    const userIdName = 'median_user_id';
    const getCookie = (name) => {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'))
        return match ? match[2] : null
    }

    const setCookie = (name, value = null) => {
        const date = new Date()
        value !== null && date.setTime(date.getTime() + 2592000000)
        const expires = "; expires=" + date.toUTCString()
        document.cookie = name + "=" + (value || "") + expires + "; path=/"
    }

    const getQueryParam = (name) => {
        return new URLSearchParams(window.location.search).get(name);
    }

    const setUserId = (userId) => {
        localStorage.setItem(userIdName, userId)
        setCookie(userIdName, userId)
    }

    const median_user_id = getCookie(userIdName)
    if (median_user_id) {
        setUserId(median_user_id)
    }

    const storageKey = 'MedianGRPEventsLog',
        previsionStorageKey = storageKey + 'Prev'
    const previsionEvents = localStorage.getItem(storageKey)
    if (!window[storageKey + 'Init']){
        previsionEvents
            ? localStorage.setItem(previsionStorageKey, previsionEvents)
            : localStorage.removeItem(previsionStorageKey)
        localStorage.removeItem(storageKey)
        window[storageKey + 'Init'] = true
    }

    const buildFormData = (formData, data, parentKey) => {
        if (data && typeof data === 'object' && !(data instanceof Date) && !(data instanceof File)) {
            Object.keys(data).forEach(key => {
                buildFormData(formData, data[key], parentKey ? `${parentKey}[${key}]` : key)
            })
        } else {
            formData.append(parentKey, data === null ? '' : data)
        }
    }

    const jsonToFormData = data => {
        const formData = new FormData()
        buildFormData(formData, data)
        return formData
    }

    window[storageKey] = [];

    const pushLogEvent = data => {
        window[storageKey].push(data)
        localStorage.setItem(storageKey, JSON.stringify(window[storageKey]))
        try{window.dispatchEvent(new Event('median-events-new'))}catch (e){}
    }

    const filterObject = obj => {
        if (typeof obj !== 'object'){
            return null;
        }
        for (const propName in obj) {
            if (typeof obj[propName] !== 'number' && !obj[propName]) {
                delete obj[propName];
            }
        }
        const len = Object.keys(obj).length
        if (len === 0 || (len === 1 && obj.hasOwnProperty('currency'))){
            return null;
        }
        return obj;
    }

    const getUserId = () => getCookie(userIdName)

    const runWhenDOMLoaded = (fn) => {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', () => fn());
        }
    }

    window.MedianGRPUtils = {
        getCookie,
        setCookie,
        getQueryParam,
        jsonToFormData,
        pushLogEvent,
        filterObject,
        getUserId,
        setUserId,
        runWhenDOMLoaded
    }

})(window, document);
