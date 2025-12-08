<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jun 2023 03:31:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\HumanResources\ClockingMachine\ClockingMachineStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('clocking_machines', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('workplace_id')->index();
            $table->foreign('workplace_id')->references('id')->on('workplaces');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name')->index()->collation('und_ns');
            $table->string('type')->index();
            $table->string('status')->default(ClockingMachineStatusEnum::DISCONNECTED->value);
            $table->string('device_name')->nullable();
            $table->string('device_uuid')->index()->unique()->nullable();
            $table->string('qr_code')->nullable()->index()->unique();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletes();
            $table->string('source_id')->nullable()->unique();
            $table->unique(['group_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clocking_machines');
    }
};
