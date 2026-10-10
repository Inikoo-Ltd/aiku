<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->unsignedInteger('job_order_item_task_id')->nullable()->change();
            $table->unsignedSmallInteger('manufacture_task_id')->nullable()->change();
            $table->unsignedBigInteger('job_order_id')->nullable()->index();
            $table->foreign('job_order_id')->references('id')->on('job_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropForeign(['job_order_id']);
            $table->dropColumn('job_order_id');
            $table->unsignedInteger('job_order_item_task_id')->nullable(false)->change();
            $table->unsignedSmallInteger('manufacture_task_id')->nullable(false)->change();
        });
    }
};
