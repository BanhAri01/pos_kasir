<?php

use App\Modules\Operations\Http\Controllers\BookingController;
use App\Modules\Operations\Http\Controllers\CommissionController;
use App\Modules\Operations\Http\Controllers\DeliveryController;
use App\Modules\Operations\Http\Controllers\DiningTableController;
use App\Modules\Operations\Http\Controllers\KitchenController;
use App\Modules\Operations\Http\Controllers\MembershipController;
use App\Modules\Operations\Http\Controllers\OrderBoardController;
use App\Modules\Operations\Http\Controllers\PublicOrderController;
use App\Modules\Operations\Http\Controllers\QueueController;
use App\Modules\Operations\Http\Controllers\ReceivableController;
use App\Modules\Operations\Http\Controllers\SelfOrderController;
use App\Modules\Operations\Http\Controllers\ServiceNoteController;
use Illuminate\Support\Facades\Route;

// Fitur per jenis usaha. Setiap grup dijaga modulnya: kalau modul dimatikan, halamannya tertutup.
Route::middleware(['auth', 'tenant'])->group(function () {
    // Karyawan operasional (kasir, barista, dapur, kapster, trainer) boleh membuka layar kerja.
    Route::middleware('can:use_pos')->group(function () {
        Route::middleware('module:kitchen_display')->group(function () {
            Route::get('dapur', [KitchenController::class, 'index'])->name('kitchen.index');
            Route::post('dapur/{ticket}/lanjut', [KitchenController::class, 'advance'])->name('kitchen.advance');
        });

        Route::middleware('module:qr_order')->prefix('kasir/api/pesanan-qr')->name('self-orders.')->group(function () {
            Route::get('/', [SelfOrderController::class, 'pending'])->middleware('throttle:120,1')->name('pending');
            Route::post('{uuid}/terima', [SelfOrderController::class, 'accept'])->whereUuid('uuid')->name('accept');
            Route::post('{uuid}/tolak', [SelfOrderController::class, 'reject'])->whereUuid('uuid')->name('reject');
        });

        Route::middleware('module:queue')->group(function () {
            Route::get('antrean', [QueueController::class, 'index'])->name('queue.index');
            Route::get('antrean/layar', [QueueController::class, 'display'])->name('queue.display');
            Route::post('antrean', [QueueController::class, 'store'])->name('queue.store');
            Route::post('antrean/panggil', [QueueController::class, 'next'])->name('queue.next');
            Route::put('antrean/{ticket}', [QueueController::class, 'update'])->name('queue.update');
        });

        Route::middleware('module:booking')->group(function () {
            Route::get('janji', [BookingController::class, 'index'])->name('bookings.index');
            Route::post('janji', [BookingController::class, 'store'])->name('bookings.store');
            Route::put('janji/{booking}', [BookingController::class, 'update'])->name('bookings.update');
        });

        Route::middleware('module:membership')->group(function () {
            Route::get('member', [MembershipController::class, 'index'])->name('members.index');
            Route::post('member/absen', [MembershipController::class, 'checkin'])->name('members.checkin');
            Route::post('member/{customer}/kode', [MembershipController::class, 'assignCode'])->name('members.code');
        });

        Route::middleware('module:order_status')->group(function () {
            Route::get('pesanan', [OrderBoardController::class, 'index'])->name('orders.index');
            Route::post('pesanan/{uuid}/lanjut', [OrderBoardController::class, 'move'])->whereUuid('uuid')->name('orders.move');
            Route::post('pesanan/{uuid}/ambil', [OrderBoardController::class, 'pickup'])->whereUuid('uuid')->name('orders.pickup');
        });

        Route::middleware('module:delivery')->group(function () {
            Route::get('pengiriman', [DeliveryController::class, 'index'])->name('deliveries.index');
            Route::get('pengiriman/{uuid}/cetak', [DeliveryController::class, 'print'])->whereUuid('uuid')->name('deliveries.print');
            Route::put('pengiriman/{uuid}', [DeliveryController::class, 'update'])->whereUuid('uuid')->name('deliveries.update');
            Route::get('transaksi/{uuid}/kirim', [DeliveryController::class, 'create'])->whereUuid('uuid')->name('deliveries.create');
            Route::post('transaksi/{uuid}/kirim', [DeliveryController::class, 'store'])->whereUuid('uuid')->name('deliveries.store');
        });

        Route::middleware('module:service_history')->group(function () {
            Route::post('pelanggan/{customer}/catatan', [ServiceNoteController::class, 'store'])->name('service-notes.store');
            Route::delete('catatan-layanan/{note}', [ServiceNoteController::class, 'destroy'])->name('service-notes.destroy');
        });
    });

    Route::middleware(['can:manage_customers', 'module:kasbon'])->group(function () {
        Route::get('kasbon', [ReceivableController::class, 'index'])->name('receivables.index');
        Route::post('kasbon', [ReceivableController::class, 'store'])->name('receivables.store');
        Route::get('kasbon/{customer}', [ReceivableController::class, 'show'])->name('receivables.show');
        Route::post('kasbon/{customer}/bayar', [ReceivableController::class, 'pay'])->name('receivables.pay');
        Route::post('kasbon/{customer}/ingatkan', [ReceivableController::class, 'remind'])->middleware('throttle:10,1')->name('receivables.remind');
    });

    Route::middleware(['can:manage_business', 'module:membership'])->group(function () {
        Route::post('member/paket', [MembershipController::class, 'storePlan'])->name('members.plans.store');
        Route::post('member/keanggotaan/{membership}/batal', [MembershipController::class, 'cancel'])->name('members.cancel');
    });

    Route::middleware(['can:manage_business', 'module:qr_order'])->group(function () {
        Route::get('pesan-qr', [SelfOrderController::class, 'settings'])->name('self-orders.settings');
        Route::put('pesan-qr/bayar', [SelfOrderController::class, 'updatePayment'])->middleware('throttle:10,1')->name('self-orders.payment');
        Route::get('pesan-qr/cetak', [SelfOrderController::class, 'printQr'])->name('self-orders.print');
        Route::post('pesan-qr/{table}/ganti', [SelfOrderController::class, 'regenerate'])->name('self-orders.regenerate');
    });

    Route::middleware(['can:manage_business', 'module:tables'])->group(function () {
        Route::get('meja', [DiningTableController::class, 'index'])->name('tables.index');
        Route::post('meja', [DiningTableController::class, 'store'])->name('tables.store');
        Route::put('meja/{table}', [DiningTableController::class, 'update'])->name('tables.update');
        Route::delete('meja/{table}', [DiningTableController::class, 'destroy'])->name('tables.destroy');
    });

    Route::middleware(['can:view_reports', 'module:staff_commission'])->group(function () {
        Route::get('komisi', [CommissionController::class, 'index'])->name('commissions.index');
        Route::post('komisi/aturan', [CommissionController::class, 'storeRule'])->middleware('can:manage_business')->name('commissions.rules.store');
        Route::delete('komisi/aturan/{rule}', [CommissionController::class, 'destroyRule'])->middleware('can:manage_business')->name('commissions.rules.destroy');
        Route::post('komisi/{userId}/bayar', [CommissionController::class, 'pay'])->whereNumber('userId')->middleware('can:manage_business')->name('commissions.pay');
    });
});

Route::get('m/{token}', [PublicOrderController::class, 'show'])->where('token', '[A-Za-z0-9]{20,40}')->middleware('throttle:60,1')->name('self-order.show');
Route::post('m/{token}', [PublicOrderController::class, 'store'])->where('token', '[A-Za-z0-9]{20,40}')->middleware('throttle:8,1')->name('self-order.store');
Route::get('pesanan-saya/{uuid}', [PublicOrderController::class, 'status'])->whereUuid('uuid')->middleware('throttle:60,1')->name('self-order.status');
Route::get('pesanan-saya/{uuid}/cek', [PublicOrderController::class, 'poll'])->whereUuid('uuid')->middleware('throttle:60,1')->name('self-order.poll');
Route::post('webhook/midtrans/pesanan/{tenantUuid}', [PublicOrderController::class, 'webhook'])->whereUuid('tenantUuid')->middleware('throttle:120,1')->name('self-order.webhook');
