<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['master_assets', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_indivisible')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['master_assets', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_indivisible');
            });
        }
    }
};
