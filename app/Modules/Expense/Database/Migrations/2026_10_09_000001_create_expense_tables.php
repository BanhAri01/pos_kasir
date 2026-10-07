<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengeluaran usaha (listrik, gaji, sewa, belanja kecil). Dipakai di laporan laba bersih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->bigInteger('amount');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete(); // bila diambil dari laci kasir
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'outlet_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
