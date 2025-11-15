<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Nov 2025 18:22:19 Central Indonesia Time, Sanur, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            foreach ([
                'transactions',
                'invoice_transactions',
                'products',
                'product_categories',
                'collections',
            ] as $table) {
                if (!Schema::hasColumn($table, 'offers_data')) {
                    Schema::table($table, function (Blueprint $table) {
                        $table->json('offers_data')
                              ->nullable()
                              ->default(DB::raw("'{}'::json"));
                    });
                } else {
                    DB::statement("ALTER TABLE $table ALTER COLUMN offers_data SET DEFAULT '{}'::json");
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            // Drop the added column from each table
            foreach ([
                'transactions',
                'invoice_transactions',
                'products',
                'product_categories',
                'collections',
            ] as $table) {
                if (Schema::hasColumn($table, 'offers_data')) {
                    Schema::table($table, function (Blueprint $table) {
                        $table->dropColumn('offers_data');
                    });
                }
            }
        });
    }
};
