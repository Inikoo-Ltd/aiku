<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('multiple_master', function (Blueprint $table) {
            DB::transaction(function () {
                foreach ([
                    'master_product_categories',
                    'assets',
                    'master_assets',
                    'shops',
                    'master_shops',
                    'master_collections',
                ] as $table) {
                    if (! Schema::hasColumn($table, 'offers_data')) {
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('multiple_master', function (Blueprint $table) {
            DB::transaction(function () {
                // Drop the added column from each table
                foreach ([
                    'master_product_categories',
                    'assets',
                    'master_assets',
                    'shops',
                    'master_shops',
                    'master_collections',
                ] as $table) {
                    if (Schema::hasColumn($table, 'offers_data')) {
                        Schema::table($table, function (Blueprint $table) {
                            $table->dropColumn('offers_data');
                        });
                    }
                }
            });
        });
    }
};
