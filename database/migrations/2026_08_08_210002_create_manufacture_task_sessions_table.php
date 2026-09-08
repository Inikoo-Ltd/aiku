<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:02 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('manufacture_task_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedInteger('job_order_item_task_id')->index();
            $table->foreign('job_order_item_task_id')->references('id')->on('job_order_item_tasks');
            $table->unsignedSmallInteger('manufacture_task_id')->index();
            $table->foreign('manufacture_task_id')->references('id')->on('manufacture_tasks');
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedSmallInteger('employee_id')->nullable()->index();
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->string('state')->default(ManufactureTaskSessionStateEnum::OPEN->value)->index();
            $table->dateTimeTz('started_at');
            $table->dateTimeTz('ended_at')->nullable();
            $table->decimal('quantity_made', 16, 3)->default(0);
            $table->decimal('quantity_rejected', 16, 3)->default(0);
            $table->decimal('task_work_cost', 18, 3)->nullable();
            $table->string('operative_reward_terms')->nullable();
            $table->string('operative_reward_allowance_type')->nullable();
            $table->decimal('operative_reward_amount', 18, 3)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_task_sessions');
    }
};
