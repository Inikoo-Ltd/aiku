<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('artefact_families', function (Blueprint $table) {
            $table->unsignedSmallInteger('maker_employee_id')->nullable()->index();
            $table->foreign('maker_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('artefacts', function (Blueprint $table) {
            $table->unsignedSmallInteger('maker_employee_id')->nullable()->index();
            $table->foreign('maker_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->unsignedInteger('org_partner_id')->nullable()->change();
            $table->unsignedSmallInteger('partner_organisation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('artefact_families', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_employee_id');
        });
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_employee_id');
        });
    }
};
