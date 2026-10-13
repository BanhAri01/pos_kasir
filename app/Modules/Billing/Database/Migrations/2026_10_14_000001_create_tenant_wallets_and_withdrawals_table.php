<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0); // saldo aktif yang bisa ditarik
            $table->bigInteger('total_qris_income')->default(0); // akumulasi seluruh QRIS masuk
            $table->bigInteger('pending_withdrawal')->default(0); // saldo yang sedang proses ditarik
            $table->string('bank_name', 64)->nullable();
            $table->string('bank_account_number', 64)->nullable();
            $table->string('bank_account_holder', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('tenant_wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']); // credit = uang masuk, debit = uang keluar
            $table->string('category', 32); // qris_sale, withdrawal, withdrawal_refund, fee_deduction, adjustment
            $table->bigInteger('amount'); // nominal kotor
            $table->bigInteger('fee')->default(0); // potongan MDR / admin
            $table->bigInteger('net_amount'); // nominal bersih yang masuk/keluar ke dompet
            $table->bigInteger('balance_after'); // sisa saldo setelah mutasi
            $table->string('reference', 64)->nullable(); // kode transaksi / nomor nota / withdrawal uuid
            $table->string('description', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('tenant_wallets')->cascadeOnDelete();
            $table->bigInteger('amount'); // jumlah yang diminta
            $table->bigInteger('admin_fee')->default(0); // biaya transfer antar bank bila ada
            $table->bigInteger('net_amount'); // jumlah yang ditransfer ke rekening toko
            $table->string('bank_name', 64);
            $table->string('bank_account_number', 64);
            $table->string('bank_account_holder', 128);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('transfer_proof', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_mutations');
        Schema::dropIfExists('tenant_wallets');
    }
};
