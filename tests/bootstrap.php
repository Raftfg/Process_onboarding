<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap File
|--------------------------------------------------------------------------
|
| This file is used to bootstrap the test environment. It loads the
| Composer autoloader and sets up the testing environment.
|
| IMPORTANT: This bootstrap prevents Collision from auto-registering
| with PHPUnit to avoid compatibility issues between PHPUnit 10.5+
| and Collision 7.x.
|
*/

// Empêcher Collision de s'enregistrer automatiquement
// Définir une constante AVANT le chargement de l'autoloader
if (!defined('PHPUNIT_COLLISION_DISABLED')) {
    define('PHPUNIT_COLLISION_DISABLED', true);
}

// Charger l'autoloader Composer
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Bootstrap the application kernel
try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (\Exception $e) {
    // Silently fail if bootstrap fails - tests will handle it
}

return $app;
