<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outlets', 'order_token')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->string('order_token', 40)->nullable()->unique();
                $table->boolean('online_open')->default(true);
                $table->boolean('online_pickup')->default(true);
                $table->boolean('online_delivery')->default(false);
                $table->unsignedInteger('delivery_fee')->default(0);
                $table->unsignedInteger('online_min_order')->default(0);
            });
        }

        if (! Schema::hasColumn('self_orders', 'order_type')) {
            Schema::table('self_orders', function (Blueprint $table) {
                $table->string('order_type', 12)->default('dine_in')->after('table_id');
                $table->string('customer_phone', 20)->nullable()->after('customer_name');
                $table->string('address', 300)->nullable()->after('customer_phone');
                $table->unsignedInteger('delivery_fee')->default(0)->after('tax_amount');
            });
        }

        if (Schema::hasTable('modules') && ! DB::table('modules')->where('code', 'online_order')->exists()) {
            DB::table('modules')->insert([
                'code' => 'online_order',
                'name' => 'Toko Online (Pesan Antar & Ambil)',
                'description' => 'Bagikan link toko di WhatsApp / Instagram. Pelanggan pesan untuk diambil atau diantar.',
                'group' => 'shared',
                'is_core' => false,
                'icon' => 'shopping-bag',
                'depends_on' => null,
                'sort_order' => 41,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('self_orders', 'order_type')) {
            Schema::table('self_orders', function (Blueprint $table) {
                $table->dropColumn(['order_type', 'customer_phone', 'address', 'delivery_fee']);
            });
        }
        if (Schema::hasColumn('outlets', 'order_token')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->dropUnique(['order_token']);
                $table->dropColumn(['order_token', 'online_open', 'online_pickup', 'online_delivery', 'delivery_fee', 'online_min_order']);
            });
        }
        DB::table('modules')->where('code', 'online_order')->delete();
    }
};
