<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 19:49:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->decimal('sku_value', 18, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->decimal('sku_value', 16)->nullable()->change();
        });
    }
};
