<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'referral_code')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('referral_code', 12)->nullable()->unique();
                $table->foreignId('referred_by_id')->nullable()->constrained('tenants')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('referral_rewards')) {
            Schema::create('referral_rewards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('referred_tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedSmallInteger('days');
                $table->string('note', 200)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
        if (Schema::hasColumn('tenants', 'referral_code')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('referred_by_id');
                $table->dropUnique(['referral_code']);
                $table->dropColumn('referral_code');
            });
        }
    }
};
