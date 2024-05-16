<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default instance
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'instances' => [
        'default' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'tax' => 21,

    /*
    |--------------------------------------------------------------------------
    | Shoppingcart database settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the connection that the shoppingcart should use when
    | storing and restoring a cart.
    |
    */

    'database' => [

        'connection' => null,

        'table' => [
            'shoppingcart'  => 'shoppingcart',
            'coupons'       => 'coupons',
            'fees'          => 'cartfees',
        ],

        'save_on_logout' => false,

        'save_instances' => [
            'default',
            // 'wishlist',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Coupon Settings
    |--------------------------------------------------------------------------
    |
    | 
    |
    */
    'coupon' => [
        'allow_multiple' => false,
        'auto_coupons' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Settings
    |--------------------------------------------------------------------------
    |
    | 
    |
    */
    'fee' => [
        'auto_fees' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compare Price Settings
    |--------------------------------------------------------------------------
    | Default Multiplier of Price if Custome Compare Price not set
    | Discount: Automatic Line Discount From ComparePrice to Price
    |
    */
    'compare_price' => [
        'default_multiplier' => floatval('1.'. rand(20, 80)),
        'discount' => false,
        'discount_code' => 'hotdeal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formated numbers if you don't
    | set them in the method call.
    |
    */

    'format' => [

        'decimals' => 2,

        'decimal_point' => '.',

        'thousand_seperator' => ','

    ],

];
