<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Kurama\AppFactory;
use Kurama\Examples\CachedController;

$app = AppFactory::create(['debug' => true]);

$app->get('/users/{id:\d+}', [CachedController::class, 'show'])->name('user.show');
$app->get('/health', [CachedController::class, 'health']);

return $app;
