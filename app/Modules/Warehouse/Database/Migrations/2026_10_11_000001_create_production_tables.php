<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10: kemas ulang, olah/produksi, bahan kemas, dan batch/lot.
 *
 * Kemas ulang dan produksi memakai struktur yang sama: resep (formula) berisi bahan per 1 kali olah,
 * lalu perintah olah (order) mencatat bahan rencana vs terpakai, hasil, susut, dan biaya olah.
 *  - repack:  25 kg Jagung Pipil + 1 Karung Kosong 25 kg = 1 Jagung Karung 25 kg
 *  - unpack:  kebalikannya (bongkar karung jadi curah)
 *  - production: giling / racik pakan dari beberapa bahan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_packaging')->default(false)->after('pack_name'); // karung kosong, benang, label
            $table->boolean('track_batch')->default(false)->after('is_packaging'); // modul batch_lot
        });

        Schema::create('production_formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12); // repack | production
            $table->string('name', 100);
            $table->foreignId('output_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('output_qty', 14, 3); // hasil per 1 kali olah (satuan dasar hasil)
            $table->bigInteger('cost_per_batch')->default(0); // perkiraan biaya olah per 1 kali (upah, listrik, mesin)
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'kind']);
        });

        Schema::create('production_formula_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3); // per 1 kali olah
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('number', 30);
            $table->string('kind', 12); // repack | unpack | production
            $table->foreignId('production_formula_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('output_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('batches', 14, 3)->default(1);          // berapa kali olah / berapa karung
            $table->decimal('planned_output_qty', 14, 3);
            $table->decimal('output_qty', 14, 3);                    // hasil aktual
            $table->decimal('input_weight', 14, 3)->default(0);      // total bahan yang satuannya sama dengan hasil
            $table->decimal('shrinkage_qty', 14, 3)->default(0);     // susut produksi = bahan - hasil
            $table->bigInteger('materials_cost')->default(0);
            $table->bigInteger('packaging_cost')->default(0);
            $table->bigInteger('labor_cost')->default(0);
            $table->bigInteger('utility_cost')->default(0);          // listrik
            $table->bigInteger('machine_cost')->default(0);
            $table->bigInteger('other_cost')->default(0);
            $table->bigInteger('unit_cost')->default(0);             // HPP per satuan hasil
            $table->string('batch_no', 40)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'outlet_id', 'created_at']);
        });

        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 12); // material | packaging | output
            $table->decimal('planned_qty', 14, 3)->default(0);
            $table->decimal('actual_qty', 14, 3);
            $table->bigInteger('unit_cost')->default(0);
            $table->bigInteger('total_cost')->default(0);
        });

        // Batch / lot per gudang. qty = sisa. Barang keluar mengambil batch yang kedaluwarsa duluan (FEFO), lalu yang paling lama (FIFO).
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 40);
            $table->date('received_on');
            $table->date('expires_at')->nullable();
            $table->decimal('moisture', 5, 2)->nullable(); // kadar air (%)
            $table->string('quality_note')->nullable();
            $table->decimal('initial_qty', 14, 3);
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_cost')->default(0);
            $table->timestamps();
            $table->index(['outlet_id', 'product_id', 'qty']);
            $table->index(['tenant_id', 'expires_at']);
        });

        Schema::create('stock_batch_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_change', 14, 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batch_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('production_formula_items');
        Schema::dropIfExists('production_formulas');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['is_packaging', 'track_batch']));
    }
};
