<?php

use Illuminate\Support\Facades\Route;
use ItHealer\LaravelEthereum\Http\Controllers\AlchemyWebhookController;

if (config('ethereum.alchemy.webhook.enabled', false)) {
    Route::post(config('ethereum.alchemy.webhook.path', 'ethereum/alchemy/webhook'), AlchemyWebhookController::class)
        ->middleware((array) config('ethereum.alchemy.webhook.middleware', []))
        ->name('ethereum.alchemy.webhook');
}
