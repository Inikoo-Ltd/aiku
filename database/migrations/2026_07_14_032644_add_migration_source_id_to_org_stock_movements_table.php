<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 11:26:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->string('migration_source_id', 24)->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropColumn('migration_source_id');
        });
    }
};
