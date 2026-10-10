<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('epr_material_mappings', function (Blueprint $table) {
            $table->string('scheme_code', 16)->nullable();
            $table->string('default_ram_rating', 8)->nullable();
        });

        $uk = [
            'paper_cardboard' => ['PC', 'green'],
            'composite'       => ['FC', 'red'],
            'plastic'         => ['PL', 'red'],
            'glass'           => ['GL', 'green'],
            'aluminium'       => ['AL', 'green'],
            'steel'           => ['ST', 'green'],
            'wood'            => ['WD', 'red'],
            'textile'         => ['OT', 'red'],
            'other'           => ['OT', 'red'],
        ];
        foreach ($uk as $category => [$code, $ram]) {
            DB::table('epr_material_mappings')
                ->where('scheme', 'uk')
                ->where('material_category', $category)
                ->update(['scheme_code' => $code, 'default_ram_rating' => $ram]);
        }
    }

    public function down(): void
    {
        Schema::table('epr_material_mappings', function (Blueprint $table) {
            $table->dropColumn(['scheme_code', 'default_ram_rating']);
        });
    }
};
