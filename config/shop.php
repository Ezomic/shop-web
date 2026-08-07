<?php

declare(strict_types=1);

return [

    'vat' => [

        /*
         * VAT percentage applied to every order. Prices are VAT inclusive, so this splits the
         * gross price the customer pays into net and VAT rather than adding anything on top.
         *
         * Defaults to the Dutch standard rate of 21 deliberately. A downloadable script may well
         * qualify as a digital publication at the reduced 9 percent rate, but that is a
         * classification call for an accountant, and under charging leaves you owing the
         * difference while over charging only costs margin. Confirm, then change this one value.
         */
        'rate' => (int) env('SHOP_VAT_RATE', 21),
    ],

    /*
     * Printed on every invoice. A Dutch invoice has to carry the supplier name, address, VAT
     * number and KvK number, so these are not decoration.
     */
    'supplier' => [
        'name' => env('SHOP_SUPPLIER_NAME', 'Thijssen Software'),
        'address' => env('SHOP_SUPPLIER_ADDRESS', ''),
        'postcode_city' => env('SHOP_SUPPLIER_POSTCODE_CITY', ''),
        'country' => env('SHOP_SUPPLIER_COUNTRY', 'Nederland'),
        'vat_number' => env('SHOP_SUPPLIER_VAT_NUMBER', ''),
        'coc_number' => env('SHOP_SUPPLIER_COC_NUMBER', ''),
        'email' => env('SHOP_SUPPLIER_EMAIL', 'noreply@thijssensoftware.nl'),
    ],

    'downloads' => [

        /*
         * How long an emailed download link stays valid. The customer's order page always mints a
         * fresh link, so this only bounds how long a forwarded or leaked mail keeps working.
         */
        'link_ttl_days' => (int) env('SHOP_DOWNLOAD_LINK_TTL_DAYS', 14),

        /*
         * How often a single download link may be used before it has to be re-issued. Zero or less
         * disables the cap.
         */
        'max_uses' => (int) env('SHOP_DOWNLOAD_MAX_USES', 10),
    ],

];
