<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'loyalty_points')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->integer('loyalty_points')->default(0);
            });
        }

        if (! Schema::hasTable('loyalty_entries')) {
            Schema::create('loyalty_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 10);
                $table->integer('points');
                $table->string('note', 200)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['sale_id', 'type'], 'loyalty_sale_type_unique');
                $table->index(['tenant_id', 'customer_id'], 'loyalty_tenant_customer_idx');
            });
        }

        if (Schema::hasTable('modules') && ! DB::table('modules')->where('code', 'loyalty')->exists()) {
            DB::table('modules')->insert([
                'code' => 'loyalty',
                'name' => 'Poin Pelanggan',
                'description' => 'Pelanggan dapat poin setiap belanja, lalu tukar poin dengan potongan harga.',
                'group' => 'shared',
                'is_core' => false,
                'icon' => 'gift',
                'depends_on' => null,
                'sort_order' => 40,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entries');
        if (Schema::hasColumn('customers', 'loyalty_points')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('loyalty_points');
            });
        }
        DB::table('modules')->where('code', 'loyalty')->delete();
    }
};
