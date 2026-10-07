<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasir: pelanggan, metode pembayaran, buka/tutup kasir (shift), penjualan, pembayaran, refund.
 *
 * - Uang = BIGINT rupiah. Persen = basis point.
 * - Data yang bisa dibuat di HP saat offline punya kolom uuid (unik per tenant)
 *   supaya sinkronisasi aman diulang tanpa dobel (Fase 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('type', 20); // cash | qris | transfer | ewallet | card | kasbon | other
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('member_code', 40)->nullable(); // kartu member / QR (Fase 6)
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->dateTime('opened_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->bigInteger('opening_cash')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('expected_cash')->nullable();
            $table->bigInteger('counted_cash')->nullable();
            $table->bigInteger('cash_difference')->nullable(); // + lebih, - kurang
            $table->string('closing_note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'outlet_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('type', 5); // in | out
            $table->bigInteger('amount');
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('number', 40);
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('table_id')->nullable();      // meja (Fase 6)
            $table->unsignedBigInteger('split_from_id')->nullable(); // split bill (Fase 6)
            $table->string('order_type', 20)->default('walk_in');    // walk_in | dine_in | take_away | delivery
            $table->string('status', 15)->default('completed');      // open | completed | void
            $table->string('payment_status', 20)->default('paid');   // unpaid | partial | paid | refunded | partially_refunded
            $table->bigInteger('subtotal')->default(0);              // setelah diskon per barang
            $table->string('discount_type', 10)->nullable();         // percent | amount
            $table->bigInteger('discount_value')->default(0);        // bp untuk percent, rupiah untuk amount
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('service_charge_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('rounding_amount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('paid_amount')->default(0);           // uang yang diterima (termasuk kembalian)
            $table->bigInteger('change_amount')->default(0);
            $table->bigInteger('due_amount')->default(0);            // sisa belum dibayar (kasbon/tempo, Fase 6)
            $table->bigInteger('refunded_amount')->default(0);
            $table->date('due_date')->nullable();
            $table->string('note')->nullable();
            $table->string('queue_number', 10)->nullable();
            $table->unsignedBigInteger('order_status_id')->nullable(); // laundry (Fase 6)
            $table->timestamp('estimated_ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('device_created_at')->nullable();      // waktu di HP (bisa offline)
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'number']);
            $table->index(['tenant_id', 'outlet_id', 'completed_at']);
            $table->index(['tenant_id', 'status', 'completed_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            // nullOnDelete: riwayat penjualan tetap ada walau barangnya dihapus permanen.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('product_unit_id')->nullable(); // multi satuan (Fase 6)
            $table->string('name', 150);         // salinan nama saat dijual
            $table->string('unit_name', 30)->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('conversion_qty', 14, 3)->default(1);
            $table->bigInteger('unit_price');     // harga satuan yang benar-benar dipakai
            $table->bigInteger('original_price'); // harga di sistem saat itu
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('subtotal');       // unit_price x qty - diskon
            $table->bigInteger('cost_amount')->default(0); // modal (HPP) total baris, untuk laba
            $table->string('note')->nullable();   // "pedas", "tanpa sayur"
            $table->unsignedBigInteger('staff_id')->nullable(); // kapster/trainer (Fase 6)
            $table->string('kitchen_status', 15)->nullable();   // layar dapur (Fase 6)
            $table->decimal('refunded_qty', 14, 3)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'product_id']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method_type', 20); // salinan tipe (cash/qris/...)
            $table->string('method_name', 50);
            $table->bigInteger('amount');      // nilai yang dipakai membayar (tanpa kembalian)
            $table->string('reference')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('number', 30);
            $table->bigInteger('amount');
            $table->string('reason');
            $table->boolean('restock')->default(true);
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method_type', 20)->default('cash');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->bigInteger('amount');
        });
    }

    public function down(): void
    {
        foreach (['refund_items', 'refunds', 'sale_payments', 'sale_items', 'sales', 'cash_movements', 'shifts', 'customers', 'payment_methods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
