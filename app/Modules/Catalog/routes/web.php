<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('barang/cari', ProductSearchController::class)->name('products.search');
    Route::delete('barang/data-contoh', [ProductController::class, 'destroySamples'])->name('products.samples.destroy');
    Route::post('barang/{id}/pulihkan', [ProductController::class, 'restore'])->whereNumber('id')->name('products.restore');
    Route::put('barang/{product}/habis', [ProductController::class, 'soldOut'])->name('products.sold-out');

    Route::resource('barang', ProductController::class)
        ->except('show')
        ->parameters(['barang' => 'product'])
        ->names('products');

    Route::get('kategori', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('kategori', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('kategori/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('kategori/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

Route::middleware(['auth', 'tenant', 'can:manage_products'])->group(function () {
    Route::middleware('module:variants_modifiers')->group(function () {
        Route::get('pilihan', [App\Modules\Catalog\Http\Controllers\ModifierGroupController::class, 'index'])->name('modifiers.index');
        Route::post('pilihan', [App\Modules\Catalog\Http\Controllers\ModifierGroupController::class, 'store'])->name('modifiers.store');
        Route::put('pilihan/{group}', [App\Modules\Catalog\Http\Controllers\ModifierGroupController::class, 'update'])->name('modifiers.update');
        Route::delete('pilihan/{group}', [App\Modules\Catalog\Http\Controllers\ModifierGroupController::class, 'destroy'])->name('modifiers.destroy');
    });

    // Toko baju: varian ukuran x warna, label barcode, promo
    Route::middleware('module:variant_matrix')->group(function () {
        Route::get('barang/{product}/varian', [App\Modules\Catalog\Http\Controllers\VariantController::class, 'edit'])->name('products.variants.edit');
        Route::put('barang/{product}/varian', [App\Modules\Catalog\Http\Controllers\VariantController::class, 'update'])->name('products.variants.update');
    });

    Route::middleware('module:barcode_label')->group(function () {
        Route::get('label-barcode', [App\Modules\Catalog\Http\Controllers\LabelController::class, 'index'])->name('labels.index');
        Route::post('label-barcode/barcode-otomatis', [App\Modules\Catalog\Http\Controllers\LabelController::class, 'generateBarcodes'])->name('labels.generate');
    });

    Route::middleware('module:promotions')->group(function () {
        Route::get('promo', [App\Modules\Catalog\Http\Controllers\PromotionController::class, 'index'])->name('promotions.index');
        Route::post('promo', [App\Modules\Catalog\Http\Controllers\PromotionController::class, 'store'])->name('promotions.store');
        Route::put('promo/{promotion}', [App\Modules\Catalog\Http\Controllers\PromotionController::class, 'update'])->name('promotions.update');
        Route::delete('promo/{promotion}', [App\Modules\Catalog\Http\Controllers\PromotionController::class, 'destroy'])->name('promotions.destroy');
    });

    Route::middleware('module:price_levels')->group(function () {
        Route::get('tipe-harga', [App\Modules\Catalog\Http\Controllers\PriceLevelController::class, 'index'])->name('price-levels.index');
        Route::post('tipe-harga', [App\Modules\Catalog\Http\Controllers\PriceLevelController::class, 'store'])->name('price-levels.store');
        Route::put('tipe-harga/{level}', [App\Modules\Catalog\Http\Controllers\PriceLevelController::class, 'update'])->name('price-levels.update');
        Route::delete('tipe-harga/{level}', [App\Modules\Catalog\Http\Controllers\PriceLevelController::class, 'destroy'])->name('price-levels.destroy');
    });
});

// Laporan toko baju
Route::middleware(['auth', 'tenant', 'can:view_reports'])->prefix('laporan')->group(function () {
    Route::get('varian', [App\Modules\Catalog\Http\Controllers\FashionReportController::class, 'variants'])->middleware('module:variant_matrix')->name('reports.variants');
    Route::get('titipan', [App\Modules\Catalog\Http\Controllers\FashionReportController::class, 'consignment'])->middleware('module:consignment')->name('reports.consignment');
});
