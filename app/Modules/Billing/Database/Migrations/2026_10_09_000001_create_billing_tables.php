<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'plan')) {
                $table->string('plan', 20)->default('bisnis')->after('status');
            }
            if (! Schema::hasColumn('tenants', 'paid_until')) {
                $table->date('paid_until')->nullable()->after('trial_ends_at');
            }
            if (! Schema::hasColumn('tenants', 'midtrans_server_key')) {
                $table->text('midtrans_server_key')->nullable();
                $table->text('midtrans_client_key')->nullable();
                $table->boolean('midtrans_production')->default(false);
                $table->string('qr_payment', 10)->default('cashier');
            }
        });

        if (! Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference', 64)->unique();
                $table->string('gateway', 20);
                $table->string('plan', 20);
                $table->unsignedTinyInteger('months');
                $table->string('channel', 10);
                $table->unsignedBigInteger('base_amount');
                $table->unsignedBigInteger('fee_amount')->default(0);
                $table->unsignedBigInteger('amount');
                $table->string('status', 12)->default('pending');
                $table->string('method', 40)->nullable();
                $table->string('gateway_ref', 100)->nullable();
                $table->text('redirect_url')->nullable();
                $table->date('period_from')->nullable();
                $table->date('period_until')->nullable();
                $table->string('note')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('last_payload')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status'], 'subpay_tenant_status_idx');
            });
        }

        if (! Schema::hasColumn('dining_tables', 'qr_token')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                $table->string('qr_token', 40)->nullable()->unique();
            });
        }

        if (! Schema::hasTable('self_orders')) {
            Schema::create('self_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
                $table->foreignId('table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
                $table->uuid('uuid')->unique();
                $table->string('code', 12);
                $table->string('customer_name', 60);
                $table->string('note', 200)->nullable();
                $table->json('items');
                $table->unsignedBigInteger('subtotal');
                $table->unsignedBigInteger('service_charge_amount')->default(0);
                $table->unsignedBigInteger('tax_amount')->default(0);
                $table->unsignedBigInteger('total');
                $table->unsignedBigInteger('fee_amount')->default(0);
                $table->string('pay_method', 10)->default('cashier');
                $table->string('payment_status', 10)->default('unpaid');
                $table->string('payment_reference', 64)->nullable()->unique();
                $table->text('payment_url')->nullable();
                $table->string('payment_channel', 40)->nullable();
                $table->json('payment_payload')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('status', 10)->default('new');
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('handled_at')->nullable();
                $table->string('reject_reason', 200)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'outlet_id', 'status'], 'selforder_outlet_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('self_orders');
        if (Schema::hasColumn('dining_tables', 'qr_token')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                $table->dropUnique(['qr_token']);
                $table->dropColumn('qr_token');
            });
        }
        Schema::dropIfExists('subscription_payments');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'paid_until', 'midtrans_server_key', 'midtrans_client_key', 'midtrans_production', 'qr_payment']);
        });
    }
};
