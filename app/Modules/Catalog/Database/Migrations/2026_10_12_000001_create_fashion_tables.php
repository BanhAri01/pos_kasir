<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 11: toko baju.
 *  - Varian 2 dimensi (ukuran x warna): setiap kombinasi = barang sendiri (stok, SKU, barcode, harga sendiri)
 *    yang menginduk ke satu "model". Sesuai keputusan: barang yang stoknya terpisah per varian = barang terpisah.
 *  - Promo / diskon musiman yang otomatis berlaku di kasir.
 *  - Barang titipan (konsinyasi) dengan bagi hasil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('category_id')->constrained('products')->cascadeOnDelete();
            $table->boolean('has_variants')->default(false)->after('parent_id');
            $table->json('variant_options')->nullable()->after('has_variants'); // model: [{"name":"Ukuran","values":["S","M"]}, ...]
            $table->json('variant_values')->nullable()->after('variant_options'); // varian: {"Ukuran":"M","Warna":"Hitam"}
            // Konsinyasi: barang titipan pemasok. Bagian toko dalam basis point (2000 = 20% dari harga jual).
            $table->foreignId('consignor_id')->nullable()->after('track_batch')->constrained('suppliers')->nullOnDelete();
            $table->unsignedInteger('consignment_share_bp')->nullable()->after('consignor_id');
            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                     // Diskon Lebaran
            $table->string('type', 10);                      // percent | amount
            $table->unsignedBigInteger('value');             // percent: basis point (1000 = 10%), amount: rupiah per barang
            $table->string('scope', 12)->default('all');     // all | categories | products
            $table->json('category_ids')->nullable();
            $table->json('product_ids')->nullable();          // model atau barang biasa; varian ikut modelnya
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'parent_id']);
            $table->dropConstrainedForeignId('consignor_id');
            $table->dropColumn('consignment_share_bp');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['has_variants', 'variant_options', 'variant_values']);
        });
    }
};
