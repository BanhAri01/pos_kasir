<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog: kategori, satuan, barang/layanan, dan pengaturan barang per outlet.
 * Uang = BIGINT rupiah. Jumlah = DECIMAL(14,3) (laundry kg, meter, kubik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'sort_order']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('symbol', 20);
            $table->boolean('allow_decimal')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('goods'); // goods | service | package | ingredient | membership
            $table->string('name', 150);
            $table->string('code', 50)->nullable();     // kode barang (SKU) buatan sendiri
            $table->string('barcode', 64)->nullable();
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->bigInteger('price')->default(0);       // harga jual (rupiah)
            $table->bigInteger('cost_price')->default(0);  // modal / HPP (rupiah)
            $table->string('pricing_mode', 20)->default('fixed'); // fixed | per_weight | open_price
            $table->boolean('track_stock')->default(true);
            $table->decimal('min_stock', 14, 3)->nullable(); // peringatan stok menipis
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // untuk layanan
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_sample')->default(false); // data contoh saat daftar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'updated_at']); // sinkronisasi offline (Fase 5)
        });

        Schema::create('product_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price')->nullable();   // harga khusus outlet ini (null = harga umum)
            $table->boolean('is_available')->default(true);
            $table->date('sold_out_on')->nullable();   // "Habis hari ini", otomatis normal besok
            $table->timestamps();
            $table->unique(['product_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_outlet');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
    }
};
