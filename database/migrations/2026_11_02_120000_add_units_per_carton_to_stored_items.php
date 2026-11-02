<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->unsignedInteger('units_per_carton')->nullable()->comment('pieces in a full carton, picks of a full carton are charged per carton');
        });
    }

    public function down(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->dropColumn('units_per_carton');
        });
    }
};
