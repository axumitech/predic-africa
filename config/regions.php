<?php

return [
    // Public NDSGPay client, “Correspondants disponibles”, checked 2026-10-29.
    // This is a signup catalogue, not merchant payment activation.
    'source' => 'https://ndsgpay.ndsgservices.com/app.html',
    'currencies' => [
        'XAF' => 'Franc CFA BEAC',
        'XOF' => 'Franc CFA BCEAO',
        'CDF' => 'Franc congolais',
        'KES' => 'Shilling kényan',
        'RWF' => 'Franc rwandais',
        'UGX' => 'Shilling ougandais',
        'ZMW' => 'Kwacha zambien',
        'SLE' => 'Leone sierra-léonais',
    ],
    'countries' => [
        'BEN' => ['name' => 'Bénin', 'currencies' => ['XOF']],
        'CMR' => ['name' => 'Cameroun', 'currencies' => ['XAF']],
        'COG' => ['name' => 'Congo-Brazzaville', 'currencies' => ['XAF']],
        'CIV' => ['name' => 'Côte d’Ivoire', 'currencies' => ['XOF']],
        'GAB' => ['name' => 'Gabon', 'currencies' => ['XAF']],
        'KEN' => ['name' => 'Kenya', 'currencies' => ['KES']],
        'UGA' => ['name' => 'Ouganda', 'currencies' => ['UGX']],
        'COD' => ['name' => 'République démocratique du Congo', 'currencies' => ['CDF']],
        'RWA' => ['name' => 'Rwanda', 'currencies' => ['RWF']],
        'SEN' => ['name' => 'Sénégal', 'currencies' => ['XOF']],
        'SLE' => ['name' => 'Sierra Leone', 'currencies' => ['SLE']],
        'ZMB' => ['name' => 'Zambie', 'currencies' => ['ZMW']],
    ],
];
