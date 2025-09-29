<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Sept 2025 12:07:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 16, 6)->change();
        });

    }


    public function down(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 16, 3)->change();
        });


    }
};
