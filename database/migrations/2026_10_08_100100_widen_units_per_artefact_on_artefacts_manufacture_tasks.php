<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->decimal('units_per_artefact', 16, 6)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->decimal('units_per_artefact', 12, 3)->default(1)->change();
        });
    }
};
