<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
                $table->unsignedInteger('org_stock_id')->nullable()->index();
                $table->foreign('org_stock_id')->references('id')->on('org_stocks');
            });

            DB::statement("update $tableName set org_stock_id = artefacts.org_stock_id from artefacts where artefacts.id = $tableName.artefact_id");

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('org_stock_id')->nullable(false)->change();
                $table->dropForeign(['artefact_id']);
                $table->unsignedInteger('artefact_id')->nullable()->change();
                $table->foreign('artefact_id')->references('id')->on('artefacts')->nullOnDelete();
            });
        }

        Schema::table('artefact_labels', function (Blueprint $table) {
            $table->jsonb('on_artwork')->default('[]');
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->jsonb('label_mandatory_information')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('label_mandatory_information');
        });

        Schema::table('artefact_labels', function (Blueprint $table) {
            $table->dropColumn('on_artwork');
        });

        foreach (self::TABLES as $tableName) {
            DB::table($tableName)->whereNull('artefact_id')->delete();

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['artefact_id']);
                $table->unsignedInteger('artefact_id')->nullable(false)->change();
                $table->foreign('artefact_id')->references('id')->on('artefacts')->cascadeOnDelete();
                $table->dropForeign(['org_stock_id']);
                $table->dropColumn('org_stock_id');
            });
        }
    }
};
