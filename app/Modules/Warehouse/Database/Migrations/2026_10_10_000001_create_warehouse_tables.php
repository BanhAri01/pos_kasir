<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9: fondasi gudang.
 *  - Gudang = outlet dengan type "warehouse" (stok, kirim stok, hitung stok tetap memakai outlet_id).
 *  - Lokasi / blok penyimpanan di dalam gudang.
 *  - Isi kemasan per barang ("1 karung = 50 kg") untuk tampilan "setara karung".
 *  - Terima barang pakai timbangan (berat nota vs berat asli) + biaya angkut/bongkar (HPP).
 *  - Nilai rupiah selisih hitung stok & kirim stok membawa modal rata-rata.
 *
 * Stok tetap DECIMAL(14,3) dalam satuan dasar (kg), jadi tepat sampai gram.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('type', 12)->default('store')->after('code'); // store | warehouse
        });

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60); // Blok A, Rak 2, Lantai atas
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('product_id')->constrained('warehouse_locations')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            // Isi 1 kemasan dalam satuan dasar, hanya untuk tampilan "setara 25 karung".
            $table->decimal('pack_size', 14, 3)->nullable()->after('min_stock');
            $table->string('pack_name', 20)->nullable()->after('pack_size'); // karung, sak
        });

        Schema::table('suppliers', function (Blueprint $table) {
            // Berat 1 kemasan menurut nota pemasok (bisa diubah setiap terima barang).
            $table->decimal('default_pack_weight', 14, 3)->nullable()->after('notes');
        });

        Schema::table('purchases', function (Blueprint $table) {
            // Biaya tambahan masuk ke HPP, tapi BUKAN utang ke pemasok.
            $table->bigInteger('freight_cost')->default(0)->after('total');
            $table->bigInteger('unloading_cost')->default(0)->after('freight_cost');
            $table->bigInteger('other_cost')->default(0)->after('unloading_cost');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('pack_count', 14, 3)->nullable();     // jumlah karung dari pemasok
            $table->decimal('pack_weight', 14, 3)->nullable();    // berat per karung menurut nota
            $table->decimal('received_qty', 14, 3)->nullable();   // berat timbangan asli (satuan dasar)
            $table->decimal('weight_diff', 14, 3)->default(0);    // asli - nota (minus = kurang)
            $table->bigInteger('extra_cost')->default(0);         // bagian biaya angkut/bongkar
            $table->bigInteger('landed_unit_cost')->nullable();   // HPP per satuan dasar
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->bigInteger('unit_cost')->nullable(); // modal rata-rata saat dihitung, untuk nilai selisih
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->bigInteger('unit_cost')->nullable(); // modal di gudang asal, dibawa ke tujuan
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('stock_opname_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('purchase_items', fn (Blueprint $t) => $t->dropColumn(['pack_count', 'pack_weight', 'received_qty', 'weight_diff', 'extra_cost', 'landed_unit_cost']));
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['freight_cost', 'unloading_cost', 'other_cost']));
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('default_pack_weight'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['pack_size', 'pack_name']));
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
        Schema::dropIfExists('warehouse_locations');
        Schema::table('outlets', fn (Blueprint $t) => $t->dropColumn('type'));
    }
};
