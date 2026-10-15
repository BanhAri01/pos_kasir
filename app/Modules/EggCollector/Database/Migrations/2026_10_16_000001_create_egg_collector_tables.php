<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengepul telur: jenis telur (campur & hasil sortir), harga harian, dan catatan sortir.
 *  - Telur dibeli sebagai Telur Campur, lalu disortir (sering saat ada pesanan) menjadi Besar/Kecil/Retak.
 *  - Modal telur campur dibagi ke hasil sortir menurut nilai jual hari itu; pecah/susut dicatat sebagai rugi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10); // mixed | grade
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'product_id']);
        });

        Schema::create('egg_daily_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('price_date');
            $table->bigInteger('buy_price')->nullable();
            $table->bigInteger('sell_price');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['outlet_id', 'product_id', 'price_date']);
            $table->index(['tenant_id', 'price_date']);
        });

        Schema::create('egg_sortings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 30);
            $table->foreignId('mixed_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('input_qty', 14, 3);
            $table->decimal('output_qty', 14, 3);
            $table->decimal('shrinkage_qty', 14, 3)->default(0);
            $table->bigInteger('input_cost');
            $table->bigInteger('shrinkage_cost')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'outlet_id', 'created_at']);
        });

        Schema::create('egg_sorting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egg_sorting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('sell_price');
            $table->bigInteger('unit_cost');
            $table->bigInteger('total_cost');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_sorting_items');
        Schema::dropIfExists('egg_sortings');
        Schema::dropIfExists('egg_daily_prices');
        Schema::dropIfExists('egg_products');
    }
};
