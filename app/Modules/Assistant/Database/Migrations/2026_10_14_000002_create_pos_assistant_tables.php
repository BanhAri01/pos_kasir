<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_type', 20); // user | assistant
            $table->string('channel', 20)->default('web'); // web | whatsapp
            $table->string('sender_phone', 30)->nullable();
            $table->text('message');
            $table->json('tools_called')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->decimal('cost_idr', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'sender_phone']);
        });

        Schema::create('tenant_assistant_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 30);
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_number']);
        });

        Schema::create('assistant_daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_messages')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost_idr', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_daily_usage');
        Schema::dropIfExists('tenant_assistant_phones');
        Schema::dropIfExists('assistant_messages');
    }
};
