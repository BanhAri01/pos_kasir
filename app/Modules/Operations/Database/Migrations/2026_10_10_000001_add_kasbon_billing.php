<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'billing_day')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedTinyInteger('billing_day')->nullable();
                $table->string('bill_token', 40)->nullable()->unique();
            });
        }

        if (! Schema::hasIndex('receivables', 'receivables_tenant_status_due_index')) {
            Schema::table('receivables', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'due_date'], 'receivables_tenant_status_due_index');
            });
        }

        Schema::create('receivable_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->bigInteger('amount');
            $table->string('note', 300)->nullable();
            $table->string('file_path');
            $table->string('file_mime', 60);
            $table->string('status', 10)->default('pending');
            $table->bigInteger('approved_amount')->nullable();
            $table->string('reject_reason', 200)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'uuid']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_proofs');

        Schema::table('receivables', function (Blueprint $table) {
            $table->dropIndex('receivables_tenant_status_due_index');
        });

        if (Schema::hasColumn('customers', 'billing_day')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique(['bill_token']);
                $table->dropColumn(['billing_day', 'bill_token']);
            });
        }
    }
};
