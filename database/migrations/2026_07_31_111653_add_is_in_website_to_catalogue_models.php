<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['products', 'product_categories', 'collections'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_in_website')->default(false)->index();
            });
        }
    }


    public function down(): void
    {
        foreach (['products', 'product_categories', 'collections'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_in_website');
            });
        }
    }
};
