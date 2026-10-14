<?php

use App\Modules\Assistant\Http\Controllers\AssistantController;
use App\Modules\Assistant\Http\Controllers\AssistantWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:view_reports'])->group(function () {
    Route::get('asisten', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('asisten/tanya', [AssistantController::class, 'ask'])->middleware('throttle:20,1')->name('assistant.ask');

    Route::middleware('can:manage_business')->group(function () {
        Route::post('asisten/nomor', [AssistantController::class, 'storePhone'])->middleware('throttle:10,1')->name('assistant.phones.store');
        Route::post('asisten/nomor/{phone}/verifikasi', [AssistantController::class, 'verifyPhone'])->middleware('throttle:10,1')->name('assistant.phones.verify');
        Route::delete('asisten/nomor/{phone}', [AssistantController::class, 'destroyPhone'])->name('assistant.phones.destroy');
    });
});

Route::post('webhook/asisten/{secret}', AssistantWebhookController::class)
    ->where('secret', '[A-Za-z0-9_-]{16,128}')
    ->middleware('throttle:120,1')
    ->name('assistant.webhook');
