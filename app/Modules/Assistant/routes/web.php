<?php

use App\Modules\Assistant\Http\Controllers\AssistantController;
use App\Modules\Assistant\Http\Controllers\AssistantWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('asisten', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('asisten/tanya', [AssistantController::class, 'ask'])->middleware('throttle:30,1')->name('assistant.ask');
    Route::post('asisten/telepon', [AssistantController::class, 'storePhone'])->name('assistant.phone.store');
    Route::delete('asisten/telepon/{phone}', [AssistantController::class, 'destroyPhone'])->name('assistant.phone.destroy');
});

Route::post('webhook/fonnte/assistant', [AssistantWebhookController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->name('assistant.webhook');
