<?php

return [
    // Desteklenen diller
    'supported_locales' => ['tr', 'en', 'de', 'es', 'fr', 'pt', 'it'],

    // Varsayılan dil
    'default' => env('APP_LOCALE', 'tr'),

    // Fallback dil
    'fallback' => 'tr',

    // Dil adları
    'names' => [
        'tr' => 'Türkçe',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'pt' => 'Português',
        'it' => 'Italiano',
    ],
];
