<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Nov 2025 20:40:14 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $table = 'trade_unit_has_ingredients';

            if (!Schema::hasColumn($table, 'position')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->smallInteger('position')->nullable();
                });


                DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_position_nonneg CHECK (position IS NULL OR position >= 0)");
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $table = 'trade_unit_has_ingredients';

            try {
                DB::statement("ALTER TABLE $table DROP CONSTRAINT IF EXISTS {$table}_position_nonneg");
            } catch (\Throwable) {
                //
            }

            if (Schema::hasColumn($table, 'position')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('position');
                });
            }
        });
    }
};
