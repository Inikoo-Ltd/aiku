<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sample of an in-house SKU is made as a trial run, a job order the floor works like any other, not as a
 * staff task.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->unsignedBigInteger('job_order_id')->nullable()->index();
            $table->foreign('job_order_id')->references('id')->on('job_orders')->nullOnDelete();
        });

        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->dropForeign(['staff_task_id']);
            $table->dropColumn('staff_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->unsignedInteger('staff_task_id')->nullable()->index();
            $table->foreign('staff_task_id')->references('id')->on('staff_tasks')->nullOnDelete();
        });

        Schema::table('product_development_samples', function (Blueprint $table) {
            $table->dropForeign(['job_order_id']);
            $table->dropColumn('job_order_id');
        });
    }
};
