<?php

use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_production_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->foreign('task_id')->references('id')->on('tasks');
            $table->unsignedBigInteger('production_id');
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedInteger('number_users')->default(0);
            $table->decimal('task_materials_cost');
            $table->decimal('task_energy_cost');
            $table->decimal('task_other_cost');
            $table->decimal('task_work_cost');
            $table->string('operative_reward_terms')->default(ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT->value);
            $table->string('operative_reward_allowance_type')->default(ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY->value);
            $table->double('operative_reward_amount')->unsigned();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_production_stats');
    }
};
