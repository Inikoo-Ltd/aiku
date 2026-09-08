<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:01 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('job_order_item_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedInteger('job_order_id')->index();
            $table->foreign('job_order_id')->references('id')->on('job_orders');
            $table->unsignedInteger('job_order_item_id')->index();
            $table->foreign('job_order_item_id')->references('id')->on('job_order_items');
            $table->unsignedSmallInteger('manufacture_task_id')->index();
            $table->foreign('manufacture_task_id')->references('id')->on('manufacture_tasks');
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('state')->default(JobOrderItemTaskStateEnum::TODO->value)->index();
            $table->decimal('quantity_required', 16, 3);
            $table->decimal('quantity_made', 16, 3)->default(0);
            $table->decimal('quantity_rejected', 16, 3)->default(0);
            $table->timestampsTz();
            $table->unique(['job_order_item_id', 'manufacture_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_item_tasks');
    }
};
