<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('artefact_families', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedInteger('artefact_department_id')->index();
            $table->foreign('artefact_department_id')->references('id')->on('artefact_departments');
            $table->unsignedInteger('org_stock_family_id')->nullable()->index();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code', 64)->collation('und_ns');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('number_artefacts')->default(0);
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['artefact_department_id', 'code']);
        });

        Schema::table('artefacts', function (Blueprint $table) {
            $table->unsignedInteger('artefact_family_id')->nullable()->index();
            $table->foreign('artefact_family_id')->references('id')->on('artefact_families');
        });
    }

    public function down(): void
    {
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('artefact_family_id');
        });
        Schema::dropIfExists('artefact_families');
    }
};
