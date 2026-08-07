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
