<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->string('unit_name', 32)->nullable()->comment('what the step counts, shown after its quantities on the floor (boxes, trays); null = the artefact\'s unit name, or packs for a step counted in packs');
        });
    }

    public function down(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn('unit_name');
        });
    }
};
