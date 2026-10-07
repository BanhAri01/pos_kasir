<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkronisasi kasir offline.
 * - sync_batches   : catatan setiap kiriman dari HP (untuk menelusuri masalah).
 * - sync_conflicts : hal yang perlu diperiksa pemilik setelah sinkron (stok minus, data tidak bisa diproses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('item_count');
            $table->unsignedSmallInteger('ok_count')->default(0);
            $table->unsignedSmallInteger('conflict_count')->default(0);
            $table->unsignedSmallInteger('error_count')->default(0);
            $table->timestamps();
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // stock_negative | rejected
            $table->string('entity_type', 30)->nullable(); // sale | shift.open | cash | customer.create
            $table->string('entity_uuid', 64)->nullable();
            $table->string('message');
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_batches');
    }
};
