if(FbEvents.getQueryParam('register') === 'true' && !localStorage.getItem('tracking_reg')){
    FbEvents.CompleteRegistration()
    localStorage.setItem('tracking_reg', '1')
}
