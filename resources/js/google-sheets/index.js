!function (accountId){
    function buildFormData(formData, data, parentKey) {
        if (data && typeof data === 'object' && !(data instanceof Date) && !(data instanceof File)) {
            Object.keys(data).forEach(key => {
                buildFormData(formData, data[key], parentKey ? `${parentKey}[${key}]` : key);
            });
        } else {
            const value = data == null ? '' : data;
            formData.append(parentKey, value);
        }
    }
    function objectToFormData(data) {
        const formData = new FormData();
        buildFormData(formData, data);
        return formData;
    }
    window.addRowToGoogleSheet = function (data){
        if (typeof data !== 'object') throw new Error('data must be an object or array');
        if (!(data instanceof FormData)){
            if (!Array.isArray(data)){
                data = [data]
            }
            data = objectToFormData(data)
        }
        return fetch('https://api.median-grp.com/partners/google-sheets/' + accountId, {
            body: data,
            method: 'POST',
            mode: 'cors',
        })
    }
}(11111)
