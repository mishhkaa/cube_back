var defaultFbLead = FbEvents.Lead;
FbEvents.Lead = function (...args){
    defaultFbLead.apply(this, args)
    GAdsConversion.event('lead')
}
FbEvents.ViewContent()
