<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok per outlet + catatan setiap perubahan (audit trail).
 * Aturan: tabel stocks HANYA diubah lewat StockService, yang selalu menulis stock_movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3)->default(0); // dalam satuan dasar barang
            $table->bigInteger('avg_cost')->default(0); // modal rata-rata per satuan (rupiah)
            $table->timestamps();
            $table->unique(['outlet_id', 'product_id']);
            $table->index(['tenant_id', 'outlet_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // sale | sale_void | refund | purchase | adjust_in | adjust_out | opname
            // | transfer_in | transfer_out | recipe_usage | initial
            $table->string('type', 20);
            $table->decimal('qty_change', 14, 3);
            $table->decimal('qty_before', 14, 3);
            $table->decimal('qty_after', 14, 3);
            $table->bigInteger('unit_cost')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'product_id', 'created_at']);
        });

        // Stok masuk / keluar manual (misal: barang datang, rusak, hilang)
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('number', 30);
            $table->string('type', 10); // in | out
            $table->string('reason', 30); // barang_datang | rusak | hilang | dipakai_sendiri | lainnya
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('unit_cost')->nullable();
        });

        // Hitung stok (stock opname)
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('status', 15)->default('completed'); // draft | completed
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 14, 3);
            $table->decimal('counted_qty', 14, 3);
            $table->decimal('difference', 14, 3);
        });

        // Kirim stok antar outlet
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('to_outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('status', 15)->default('sent'); // sent | received | canceled
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
        });

        // Nomor dokumen berurutan per tenant/outlet/hari (online).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('period', 10);
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['tenant_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
