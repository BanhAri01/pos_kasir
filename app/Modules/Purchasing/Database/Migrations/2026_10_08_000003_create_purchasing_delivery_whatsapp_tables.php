<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * supplier_purchase (belanja ke pemasok + utang pemasok), delivery (surat jalan),
 * whatsapp_notification (akun, template, log pesan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('number', 30);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_invoice_no', 50)->nullable();
            $table->date('purchased_on');
            $table->bigInteger('total');
            $table->bigInteger('paid_amount')->default(0);
            $table->date('due_date')->nullable();
            $table->string('payment_status', 10)->default('paid'); // unpaid | partial | paid
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('conversion_qty', 14, 3)->default(1);
            $table->bigInteger('unit_cost');
            $table->bigInteger('subtotal');
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('number', 30);
            $table->string('type', 10)->default('delivery'); // delivery | pickup
            $table->string('recipient_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('vehicle', 50)->nullable();
            $table->bigInteger('fee')->default(0);
            $table->string('status', 12)->default('pending'); // pending | on_the_way | delivered | failed
            $table->timestamp('delivered_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->decimal('qty', 14, 3);
            $table->string('unit_name', 30)->nullable();
        });

        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->unique()->constrained()->cascadeOnDelete(); // null = nomor platform
            $table->string('provider', 20); // fonnte | wablas | cloud_api | log
            $table->string('sender_phone', 20)->nullable();
            $table->text('credentials')->nullable(); // terenkripsi
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('to_phone', 20);
            $table->string('template_code', 40)->nullable();
            $table->text('body');
            $table->string('status', 10)->default('queued'); // queued | sent | failed
            $table->string('provider', 20)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->nullableMorphs('related');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['whatsapp_messages', 'message_templates', 'whatsapp_accounts', 'delivery_items', 'deliveries', 'purchase_payments', 'purchase_items', 'purchases', 'suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
