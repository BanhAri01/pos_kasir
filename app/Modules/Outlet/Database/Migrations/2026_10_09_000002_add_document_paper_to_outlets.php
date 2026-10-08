<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('outlets', 'document_paper')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->string('document_paper', 10)->default('a4')->after('receipt_paper');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outlets', 'document_paper')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->dropColumn('document_paper');
            });
        }
    }
};
