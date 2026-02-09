function MedianGetExternalId(){
    GAdsConversion.event('click_start_button')
    return localStorage.getItem('median_user_id') || null;
}
