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

        /*
         * Used as the order country when the payment provider tells us nothing. Recorded with a
         * source of "fallback" rather than being passed off as evidence, because it is not.
         */
        'home_country' => env('SHOP_HOME_COUNTRY', 'NL'),

        /*
         * Cross border B2C sales past this in a calendar year mean VAT is due at the customer
         * country rate and the shop has to register for the Union One Stop Shop. In cents.
         */
        'threshold' => (int) env('SHOP_VAT_THRESHOLD', 1000000),

        /*
         * Warn here first, so there is room to build the OSS work before the threshold bites
         * rather than discovering it afterwards. See SHOP-18.
         */
        'threshold_warning' => (int) env('SHOP_VAT_THRESHOLD_WARNING', 750000),

        /*
         * Who hears about it. Falls back to the supplier address.
         */
        'threshold_notify' => env('SHOP_VAT_THRESHOLD_NOTIFY'),
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

    'orders' => [

        /*
         * A pending order is one somebody started and never paid for. They accumulate forever
         * otherwise. Expired orders can no longer be retried. Zero or less switches this off.
         */
        'expire_pending_after_days' => (int) env('SHOP_EXPIRE_PENDING_ORDERS_AFTER_DAYS', 30),
    ],

    'backups' => [

        /*
         * Where nightly backups land. Local by default: it is off the app tree but still on the
         * same droplet, so it protects against a bad migration or an accidental delete, not
         * against losing the machine. Set the offsite disk below for that.
         */
        'path' => env('SHOP_BACKUP_PATH', storage_path('backups')),

        'keep_days' => (int) env('SHOP_BACKUP_KEEP_DAYS', 14),

        /*
         * Filesystem disk to copy each backup to, for example an S3 compatible bucket. Null keeps
         * everything on the droplet, which is the case a droplet level failure does not survive.
         */
        'offsite_disk' => env('SHOP_BACKUP_OFFSITE_DISK'),
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
