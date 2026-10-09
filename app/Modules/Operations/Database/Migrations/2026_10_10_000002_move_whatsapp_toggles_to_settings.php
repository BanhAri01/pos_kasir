<?php

use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_KASBON = "Halo {nama}, ini pengingat dari *{usaha}*.\n\nCatatan utang Anda saat ini: *{sisa}*.\nMohon dilunasi bila sudah ada rezeki. Terima kasih 🙏";

    public function up(): void
    {
        if (! Schema::hasTable('message_templates')) {
            return;
        }

        DB::table('message_templates')->where('is_active', false)->orderBy('id')->get()->each(function ($row) {
            $tenant = DB::table('tenants')->where('id', $row->tenant_id)->first(['id', 'settings']);
            if ($tenant) {
                $settings = json_decode($tenant->settings ?? '[]', true) ?: [];
                $settings['wa_off'] = array_values(array_unique([...($settings['wa_off'] ?? []), $row->code]));
                DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
            }

            $isDefault = in_array($row->body, [WhatsAppService::DEFAULT_TEMPLATES[$row->code] ?? null, self::OLD_KASBON], true);
            $isDefault
                ? DB::table('message_templates')->where('id', $row->id)->delete()
                : DB::table('message_templates')->where('id', $row->id)->update(['is_active' => true]);
        });

        DB::table('message_templates')->where('code', 'kasbon_reminder')->where('body', self::OLD_KASBON)->delete();
    }

    public function down(): void {}
};
