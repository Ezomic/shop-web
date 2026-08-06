<?php

declare(strict_types=1);

return [

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
