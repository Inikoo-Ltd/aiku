<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Aug 2025 10:34:13 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('in_process')->index()->default(false);
            $table->boolean('mark_for_discontinued')->index()->default(false);
            $table->datetimeTz('mark_for_discontinued_at')->nullable();
            $table->datetimeTz('discontinued_at')->nullable();
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->boolean('in_process')->index()->default(false);
            $table->boolean('mark_for_discontinued')->index()->default(false);
            $table->datetimeTz('mark_for_discontinued_at')->nullable();
            $table->datetimeTz('discontinued_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('mark_for_discontinued');
            $table->dropColumn('mark_for_discontinued_at');
            $table->dropColumn('discontinued_at');
            $table->dropColumn('in_process');
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn('mark_for_discontinued');
            $table->dropColumn('mark_for_discontinued_at');
            $table->dropColumn('discontinued_at');
            $table->dropColumn('in_process');
        });
    }
};
