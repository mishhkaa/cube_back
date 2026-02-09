<?php

namespace App\Classes\Enums;

enum XEventName: string
{
    case ADD_TO_CART = 'AddToCart';
    case ADD_TO_WISHLIST = 'AddToWishlist';
    case ADD_PAYMENT_INFO = 'AddPaymentInfo';
    case CHECKOUT_INITIATED = 'CheckoutInitiated';
    case CONTENT_VIEW = 'ContentView';
    case PRODUCT_CUSTOMIZATION = 'ProductCustomization';
    case CUSTOM = 'Custom';
    case DOWNLOAD = 'Download';
    case LEAD = 'Lead';
    case PURCHASE = 'Purchase';
    case SEARCH = 'Search';
    case START_TRIAL = 'StartTrial';
    case SUBSCRIBE = 'Subscribe';
    case PAGE_VIEW = 'PageView';
}
