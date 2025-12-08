<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 10:14:34 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('manufacture_tasks', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code', 64)->index()->collation('und_ns');
            $table->string('name');
            $table->decimal('task_materials_cost');
            $table->decimal('task_energy_cost');
            $table->decimal('task_other_cost');
            $table->decimal('task_work_cost');
            $table->boolean('status')->default(true);
            $table->float('task_lower_target');
            $table->float('task_upper_target');
            $table->string('operative_reward_terms')->default(ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT->value);
            $table->string('operative_reward_allowance_type')->default(ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY->value);
            $table->double('operative_reward_amount')->unsigned();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_tasks');
    }
};
