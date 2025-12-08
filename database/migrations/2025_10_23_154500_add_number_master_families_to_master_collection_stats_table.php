<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Oct 2025 15:45:13 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
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
            if (! Schema::hasColumn('master_collection_stats', 'number_master_families')) {
                $table->unsignedSmallInteger('number_master_families')->default(0);
                $table->unsignedSmallInteger('number_current_master_families')->default(0);
                $table->unsignedSmallInteger('number_master_products')->default(0);
                $table->unsignedSmallInteger('number_current_master_products')->default(0);
                $table->unsignedSmallInteger('number_indirect_master_products')->default(0);
                $table->unsignedSmallInteger('number_indirect_current_master_products')->default(0);
                $table->unsignedSmallInteger('number_master_collections')->default(0);
                $table->unsignedSmallInteger('number_current_master_collections')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_collection_stats', function (Blueprint $table) {
            $columns = [
                'number_master_families',
                'number_current_master_families',
                'number_master_products',
                'number_current_master_products',
                'number_indirect_master_products',
                'number_indirect_current_master_products',
                'number_master_collections',
                'number_current_master_collections',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('master_collection_stats', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
