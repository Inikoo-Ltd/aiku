<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 12:19:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->json('opening_hours')->default('{}');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->json('opening_hours')->default('{}');
        });
    }


    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }
};
