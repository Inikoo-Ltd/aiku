<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('production_material_request_items', function (Blueprint $table) {
            $table->date('needed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_material_request_items', function (Blueprint $table) {
            $table->dropColumn('needed_by');
        });
    }
};
