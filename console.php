<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Kurama\AppFactory;
use Kurama\Examples\CachedController;
use Kurama\Request;
use Kurama\Response;

$app = AppFactory::create(['debug' => true]);

$app->get('/users/{id:\d+}', [CachedController::class, 'show'])->name('user.show');
$app->get('/health', [CachedController::class, 'health']);
$app->post('/echo', fn (Request $request): Response => Response::json($request->all()));

return $app;
