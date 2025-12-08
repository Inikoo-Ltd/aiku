<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 12 Sept 2022 23:18:30 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id')->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('group_job_position_id')->nullable();
            $table->foreign('group_job_position_id')->references('id')->on('job_position_categories')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('name')->index()->collation('und_ci');
            $table->string('scope')->index();
            $table->string('department')->nullable();
            $table->string('team')->nullable();
            $table->jsonb('data');
            $table->boolean('locked')->default(true)->comment('Seeded job positions should be locked');
            $table->timestampsTz();
            $table->index(['code', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_positions');
    }
};
