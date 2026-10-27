<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['organisation_catalogue_stats', 'group_catalogue_stats'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedSmallInteger('number_shops_type_digital_agency')->default(0);
                $table->unsignedSmallInteger('number_current_shops_type_digital_agency')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['organisation_catalogue_stats', 'group_catalogue_stats'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['number_shops_type_digital_agency', 'number_current_shops_type_digital_agency']);
            });
        }
    }
};
