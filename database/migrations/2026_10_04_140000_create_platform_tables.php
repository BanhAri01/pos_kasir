<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel milik platform (dikelola Super Admin, tanpa tenant_id):
 * jenis usaha, modul, dan modul bawaan per jenis usaha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 20);   // retail | fnb | service
            $table->string('pos_layout', 20); // retail | fnb | service | laundry
            $table->string('icon', 50);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('group', 20);       // core | shared
            $table->boolean('is_core')->default(false); // core = selalu aktif
            $table->json('depends_on')->nullable();      // daftar kode modul
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_type_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unique(['business_type_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_modules');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('business_types');
    }
};
