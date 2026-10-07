<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('business_type_id')->constrained();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('timezone', 40)->default('Asia/Jakarta'); // WIB / WITA / WIT
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('trial'); // trial | active | past_due | suspended
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('receipt_header')->nullable();
            $table->string('receipt_footer')->nullable();
            $table->string('receipt_paper', 5)->default('58'); // 58 | 80 | a4
            // Persen disimpan sebagai basis point: 1100 = 11%.
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->unsignedInteger('service_charge_bp')->default(0);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('outlet_user', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['outlet_id', 'user_id']);
        });

        // HP/tablet toko yang sudah pernah login pemilik. Dipakai untuk masuk cepat dengan PIN
        // dan (Fase 5) untuk nomor nota offline: {code}-{yymmdd}-{urut}.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 4);
            $table->string('name')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', fn (Blueprint $table) => $table->dropColumn('tenant_id'));
        Schema::dropIfExists('devices');
        Schema::dropIfExists('outlet_user');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('tenant_modules');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['tenant_id']));
        Schema::dropIfExists('tenants');
    }
};
