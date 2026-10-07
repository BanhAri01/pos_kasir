<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berat setiap karung selalu sama (dikemas sendiri) atau tidak (karung pemasok beda-beda).
 * Hanya yang beratnya selalu sama boleh dihitung per karung saat hitung stok, supaya selisih hitung
 * tidak muncul hanya karena "rata-rata" dan karyawan tidak dituduh curang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('pack_weight_fixed')->default(false)->after('pack_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('pack_weight_fixed'));
    }
};
