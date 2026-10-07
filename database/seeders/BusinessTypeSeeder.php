<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Jenis usaha dan modul yang otomatis menyala saat mendaftar.
 * Modul core selalu menyala, jadi tidak perlu ditulis di sini.
 */
class BusinessTypeSeeder extends Seeder
{
    public const TYPES = [
        // A. Jual barang
        ['code' => 'toko_kelontong', 'name' => 'Toko / Warung Kelontong', 'category' => 'retail',
            'pos_layout' => 'retail', 'icon' => 'store',
            'description' => 'Sembako, jajanan, kebutuhan sehari-hari',
            'modules' => ['barcode', 'price_levels', 'kasbon', 'supplier_purchase', 'whatsapp_notification']],
        ['code' => 'toko_bangunan', 'name' => 'Toko Bangunan', 'category' => 'retail',
            'pos_layout' => 'retail', 'icon' => 'hammer',
            'description' => 'Semen, besi, cat, pipa, alat bangunan',
            'modules' => ['multi_unit', 'price_levels', 'credit_sales', 'delivery', 'supplier_purchase', 'whatsapp_notification']],
        ['code' => 'gudang_pakan', 'name' => 'Gudang / Distributor Pakan', 'category' => 'retail',
            'pos_layout' => 'retail', 'icon' => 'warehouse',
            'description' => 'Jagung, dedak, konsentrat, pakan ternak curah & karungan',
            'modules' => ['base_unit_stock', 'weighed_receiving', 'packaging_materials', 'repack', 'production', 'landed_cost',
                'batch_lot', 'multi_warehouse', 'shrinkage', 'supplier_purchase', 'price_levels', 'credit_sales', 'kasbon', 'delivery']],
        ['code' => 'toko_baju', 'name' => 'Toko Baju / Fashion', 'category' => 'retail',
            'pos_layout' => 'retail', 'icon' => 'shirt',
            'description' => 'Pakaian, celana, hijab, sepatu, aksesoris',
            'modules' => ['variant_matrix', 'barcode', 'barcode_label', 'returns_exchange', 'price_levels', 'promotions', 'supplier_purchase']],

        // B. Makanan & minuman
        ['code' => 'coffee_shop', 'name' => 'Kedai Kopi', 'category' => 'fnb',
            'pos_layout' => 'fnb', 'icon' => 'coffee',
            'description' => 'Kopi, minuman, makanan ringan',
            'modules' => ['variants_modifiers', 'recipe', 'tables', 'kitchen_display', 'queue']],
        ['code' => 'warung_makan', 'name' => 'Warung Makan', 'category' => 'fnb',
            'pos_layout' => 'fnb', 'icon' => 'utensils',
            'description' => 'Rumah makan, warteg, nasi padang, bakso',
            'modules' => ['tables']],

        // C. Jasa
        ['code' => 'barbershop', 'name' => 'Tempat Cukur', 'category' => 'service',
            'pos_layout' => 'service', 'icon' => 'scissors',
            'description' => 'Barbershop, pangkas rambut',
            'modules' => ['queue', 'booking', 'staff_commission', 'whatsapp_notification']],
        ['code' => 'salon', 'name' => 'Salon', 'category' => 'service',
            'pos_layout' => 'service', 'icon' => 'sparkles',
            'description' => 'Salon rambut, kecantikan, spa',
            'modules' => ['queue', 'booking', 'staff_commission', 'service_history', 'whatsapp_notification']],
        ['code' => 'gym', 'name' => 'Tempat Fitness', 'category' => 'service',
            'pos_layout' => 'service', 'icon' => 'dumbbell',
            'description' => 'Gym, senam, tempat olahraga',
            'modules' => ['membership', 'staff_commission', 'whatsapp_notification']],
        ['code' => 'laundry', 'name' => 'Laundry', 'category' => 'service',
            'pos_layout' => 'laundry', 'icon' => 'shirt',
            'description' => 'Cuci kiloan, satuan, setrika',
            'modules' => ['order_status', 'whatsapp_notification']],
        ['code' => 'lainnya', 'name' => 'Usaha Lainnya', 'category' => 'service',
            'pos_layout' => 'retail', 'icon' => 'layout-grid',
            'description' => 'Pilih sendiri fitur yang dibutuhkan',
            'modules' => []],
    ];

    public function run(): void
    {
        $moduleIds = Module::pluck('id', 'code');

        foreach (self::TYPES as $index => $data) {
            $type = BusinessType::updateOrCreate(['code' => $data['code']], [
                'name' => $data['name'],
                'category' => $data['category'],
                'pos_layout' => $data['pos_layout'],
                'icon' => $data['icon'],
                'description' => $data['description'],
                'sort_order' => $index,
                'is_active' => true,
            ]);

            $type->modules()->sync(
                collect($data['modules'])->map(fn ($code) => $moduleIds[$code])->all()
            );
        }
    }
}
