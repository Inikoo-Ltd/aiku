<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('job_order_item_tasks', function (Blueprint $table) {
            $table->unsignedInteger('combined_task_id')->nullable()->index();
            $table->foreign('combined_task_id')->references('id')->on('job_order_item_tasks')->nullOnDelete();
        });

        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->boolean('is_combined')->default(false);
        });

        Schema::create('manufacture_task_session_shares', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('manufacture_task_session_id')->index();
            $table->foreign('manufacture_task_session_id')->references('id')->on('manufacture_task_sessions')->cascadeOnDelete();
            $table->unsignedInteger('job_order_item_task_id')->index();
            $table->foreign('job_order_item_task_id')->references('id')->on('job_order_item_tasks');
            $table->decimal('share', 9, 6);
            $table->decimal('quantity_made', 16, 3)->default(0);
            $table->decimal('quantity_rejected', 16, 3)->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_task_session_shares');

        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropColumn('is_combined');
        });

        Schema::table('job_order_item_tasks', function (Blueprint $table) {
            $table->dropForeign(['combined_task_id']);
            $table->dropColumn('combined_task_id');
        });
    }
};
