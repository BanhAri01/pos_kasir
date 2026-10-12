<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'deposit_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->bigInteger('deposit_balance')->default(0)->after('credit_limit');
            });
        }

        if (! Schema::hasTable('customer_deposits')) {
            Schema::create('customer_deposits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20); // deposit | withdrawal | deduction | overpayment
                $table->bigInteger('amount');
                $table->bigInteger('balance_after');
                $table->string('notes', 300)->nullable();
                $table->string('source', 30)->default('warehouse'); // warehouse | kasbon | pos
                $table->foreignId('receivable_id')->nullable()->constrained('receivables')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_deposits');

        if (Schema::hasColumn('customers', 'deposit_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('deposit_balance');
            });
        }
    }
};
