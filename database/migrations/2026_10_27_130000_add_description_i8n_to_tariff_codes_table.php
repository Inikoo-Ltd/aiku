<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->jsonb('description_i8n')->nullable();
        });

        $translations = json_decode(file_get_contents(database_path('seeders/datasets/tariff-code-heading-translations.json')), true);
        foreach ($translations as $hsCode => $descriptions) {
            DB::table('tariff_codes')->where('hs_code', (string) $hsCode)->update(['description_i8n' => json_encode($descriptions, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        Schema::table('tariff_codes', function (Blueprint $table) {
            $table->dropColumn('description_i8n');
        });
    }
};
