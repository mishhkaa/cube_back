var click_id_fbclid = FbEvents.getQueryParam();
if (click_id_fbclid){
    FbEvents.setCookie(click_id_fbclid )
}
FbEvents.init(true).then()
