<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Oct 2025 13:23:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_collection_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('master_collection_stats', 'number_parents')) {
                $table->unsignedSmallInteger('number_parents')->default(0);
                $table->unsignedSmallInteger('number_current_collections')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_collection_stats', function (Blueprint $table) {
            if (Schema::hasColumn('master_collection_stats', 'number_parents')) {
                $table->dropColumn('number_parents');
            }
            if (Schema::hasColumn('master_collection_stats', 'number_current_collections')) {
                $table->dropColumn('number_current_collections');
            }
        });
    }
};
