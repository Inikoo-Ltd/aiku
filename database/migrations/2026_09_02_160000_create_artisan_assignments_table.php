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
        Schema::create('artisan_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('employee_id')->index();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('artisanable_type');
            $table->unsignedInteger('artisanable_id');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestampsTz();
            $table->unique(['employee_id', 'artisanable_type', 'artisanable_id']);
            $table->index(['artisanable_type', 'artisanable_id', 'position']);
        });

        Schema::table('artefact_families', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_employee_id');
        });
        Schema::table('artefacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artisan_assignments');
    }
};
