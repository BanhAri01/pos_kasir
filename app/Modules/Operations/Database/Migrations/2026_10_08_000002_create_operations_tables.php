<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul bersama untuk operasional:
 *  tables (meja), kitchen_display, queue, booking, staff_commission, membership,
 *  order_status (laundry), kasbon & credit_sales (piutang), service_history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('area', 50)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->string('label', 40);          // "Meja 3" / "Antrean A-12" / "Bungkus"
            $table->string('order_type', 20)->nullable();
            $table->json('items');                 // [{name, qty, note, modifiers}]
            $table->string('note')->nullable();
            $table->string('status', 12)->default('new'); // new | preparing | ready | served
            $table->string('sale_uuid', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'outlet_id', 'status']);
        });

        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->date('queue_date');
            $table->unsignedSmallInteger('number');
            $table->string('customer_name', 100)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_note')->nullable();
            $table->string('status', 12)->default('waiting'); // waiting | called | serving | done | skipped
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['outlet_id', 'queue_date', 'number']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20)->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('start_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->dateTime('end_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->string('status', 12)->default('booked'); // booked | arrived | done | canceled | no_show
            $table->string('note')->nullable();
            $table->bigInteger('total_price')->default(0);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'outlet_id', 'start_at']);
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('duration_minutes');
            $table->bigInteger('price');
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 10); // percent | fixed
            $table->bigInteger('value'); // percent: basis point; fixed: rupiah
            $table->timestamps();
        });

        Schema::create('staff_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('status', 10)->default('pending'); // pending | paid | canceled
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // dijual lewat kasir
            $table->string('kind', 12)->default('gym'); // gym | pt_session | package
            $table->unsignedSmallInteger('duration_value');
            $table->string('duration_unit', 6); // day | week | month | year
            $table->unsignedSmallInteger('session_quota')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'product_id']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('sessions_remaining')->nullable();
            $table->string('status', 10)->default('active'); // active | canceled
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id', 'ends_on']);
        });

        Schema::create('member_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10)->default('manual'); // qr | manual
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('checked_in_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->timestamps();
        });

        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('color', 20)->default('info');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_final')->default(false);
            $table->boolean('notify_customer')->default(false);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('order_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('order_statuses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10)->default('kasbon'); // kasbon | tempo
            $table->bigInteger('amount');
            $table->bigInteger('paid_amount')->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 10)->default('open'); // open | paid
            $table->string('note')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id', 'status']);
        });

        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->bigInteger('amount');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method_type', 20)->default('cash');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at'); // dateTime: MariaDB memberi ON UPDATE otomatis pada timestamp NOT NULL
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
        });

        Schema::create('customer_service_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'customer_service_notes', 'receivable_payments', 'receivables', 'order_status_histories', 'order_statuses',
            'member_checkins', 'memberships', 'membership_plans', 'staff_commissions', 'commission_rules',
            'booking_items', 'bookings', 'queue_tickets', 'kitchen_tickets', 'dining_tables',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
