<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA Configuration
    |--------------------------------------------------------------------------
    |
    | Configurez vos clés reCAPTCHA ici. Obtenez-les depuis :
    | https://www.google.com/recaptcha/admin
    |
    */

    'site_key' => env('RECAPTCHA_SITE_KEY', '6LcOVFssAAAAAEY51n3xCGvcCKSm7k3COumxKASB'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY', '6LcOVFssAAAAAIziKmtLk5oLdhQaorO7hupkNM1s'),
    'version' => env('RECAPTCHA_VERSION', 'v2'), // v2 ou v3
    'enabled' => env('RECAPTCHA_ENABLED', true), // Désactiver en local si nécessaire
];
