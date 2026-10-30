<?php

return [
    // Public NDSGPay client, “Correspondants disponibles”, checked 2026-10-29.
    // This is a signup catalogue, not merchant payment activation.
    'source' => 'https://ndsgpay.ndsgservices.com/app.html',
    // International numbers: national trunk zero is removed only where listed.
    'phones' => [
        'BEN' => ['calling_code' => '229', 'length' => 10, 'example' => '0197000000'],
        'CMR' => ['calling_code' => '237', 'length' => 9, 'example' => '670000000'],
        'COG' => ['calling_code' => '242', 'length' => 9, 'example' => '060000000'],
        'CIV' => ['calling_code' => '225', 'length' => 10, 'example' => '0700000000'],
        'GAB' => ['calling_code' => '241', 'length' => 8, 'example' => '77000000', 'trunk' => '0'],
        'KEN' => ['calling_code' => '254', 'length' => 9, 'example' => '712000000', 'trunk' => '0'],
        'UGA' => ['calling_code' => '256', 'length' => 9, 'example' => '770000000', 'trunk' => '0'],
        'COD' => ['calling_code' => '243', 'length' => 9, 'example' => '810000000', 'trunk' => '0'],
        'RWA' => ['calling_code' => '250', 'length' => 9, 'example' => '780000000', 'trunk' => '0'],
        'SEN' => ['calling_code' => '221', 'length' => 9, 'example' => '770000000'],
        'SLE' => ['calling_code' => '232', 'length' => 8, 'example' => '76000000', 'trunk' => '0'],
        'ZMB' => ['calling_code' => '260', 'length' => 9, 'example' => '970000000', 'trunk' => '0'],
    ],
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
