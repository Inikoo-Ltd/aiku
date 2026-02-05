<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 14:18:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedSmallInteger('migration_pivot')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('migration_pivot');
        });
    }
};
