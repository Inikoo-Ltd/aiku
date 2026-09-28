<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 12:49:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const TABLES = ['artefact_labels', 'artefact_compliance_items'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('stock_id')->nullable()->index();
                $table->foreign('stock_id')->references('id')->on('stocks');
                $table->unsignedInteger('org_stock_id')->nullable()->change();
            });

            DB::statement("update $tableName set stock_id = org_stocks.stock_id from org_stocks where org_stocks.id = $tableName.org_stock_id");
        }

        Schema::table('stocks', function (Blueprint $table) {
            $table->jsonb('label_mandatory_information')->default('[]');
        });

        DB::statement("
            update stocks set label_mandatory_information = mandatory.information
            from (
                select org_stocks.stock_id, jsonb_agg(distinct information.value) as information
                from org_stocks, jsonb_array_elements_text(org_stocks.label_mandatory_information) as information(value)
                where org_stocks.stock_id is not null
                group by org_stocks.stock_id
            ) as mandatory
            where mandatory.stock_id = stocks.id
        ");

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('label_mandatory_information');
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->jsonb('label_mandatory_information')->default('[]');
        });

        DB::statement('update org_stocks set label_mandatory_information = stocks.label_mandatory_information from stocks where stocks.id = org_stocks.stock_id');

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('label_mandatory_information');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['stock_id']);
                $table->dropColumn('stock_id');
            });
        }
    }
};
