<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant', 'can:manage_business', 'module:whatsapp_notification'])->prefix('pengaturan/whatsapp')->group(function () {
    Route::get('/', [WhatsAppSettingsController::class, 'index'])->name('settings.whatsapp');
    Route::put('akun', [WhatsAppSettingsController::class, 'saveAccount'])->name('settings.whatsapp.account');
    Route::put('pesan/{code}', [WhatsAppSettingsController::class, 'saveTemplate'])->name('settings.whatsapp.template');
    Route::post('uji', [WhatsAppSettingsController::class, 'test'])->middleware('throttle:5,1')->name('settings.whatsapp.test');
});
