<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 22:20:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('job_order_items', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('job_order_id')->index();
            $table->foreign('job_order_id')->references('id')->on('job_orders');
            $table->unsignedInteger('artefact_id')->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts');
            $table->string('slug')->nullable()->unique()->collation('und_ns');
            $table->string('reference')->nullable()->index()->collation('und_ci');
            $table->string('status')->index()->default(JobOrderItemStatusEnum::RECEIVING->value);
            $table->string('state')->index()->default(JobOrderStateEnum::IN_PROCESS->value);
            $table->text('notes')->nullable();
            $table->integer('quantity');

            $table->jsonb('data');
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_items');
    }
};
