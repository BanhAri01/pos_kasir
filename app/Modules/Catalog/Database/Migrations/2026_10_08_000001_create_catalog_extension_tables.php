<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul bersama untuk katalog & harga:
 *  - variants_modifiers : pilihan ukuran / tambahan dengan harga tambahan
 *  - recipe             : bahan baku per menu (potong stok bahan + hitung HPP)
 *  - multi_unit         : banyak satuan dengan konversi (sak, dus, meter)
 *  - price_levels       : harga grosir / harga per tipe pelanggan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);                     // Ukuran, Gula, Es, Topping
            $table->string('selection', 10)->default('single'); // single | multiple
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('max_select')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->bigInteger('price_delta')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('modifier_group_product', function (Blueprint $table) {
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['modifier_group_id', 'product_id']);
        });

        Schema::create('sale_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name', 60);
            $table->string('name', 60);
            $table->bigInteger('price_delta')->default(0);
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qty', 14, 3); // jumlah bahan per 1 menu (dalam satuan bahan)
            $table->timestamps();
            $table->unique(['product_id', 'ingredient_id']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('conversion_qty', 14, 3); // 1 dus = 40 pcs -> 40
            $table->bigInteger('price');
            $table->string('barcode', 64)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'unit_id']);
            $table->index(['tenant_id', 'barcode']);
        });

        Schema::create('price_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // Grosir, Tukang, Kontraktor
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // price_level_id null + min_qty > 1 = harga grosir berdasarkan jumlah untuk semua pembeli.
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('price_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 14, 3)->default(1);
            $table->bigInteger('price');
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('price_level_id')->nullable()->after('member_code')->constrained()->nullOnDelete();
            $table->bigInteger('credit_limit')->nullable()->after('price_level_id'); // batas kasbon
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_level_id');
            $table->dropColumn('credit_limit');
        });
        foreach (['product_prices', 'price_levels', 'product_units', 'recipe_items', 'sale_item_modifiers', 'modifier_group_product', 'modifier_options', 'modifier_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
