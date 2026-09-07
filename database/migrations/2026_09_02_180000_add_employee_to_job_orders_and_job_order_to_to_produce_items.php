<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('employee_id')->nullable()->index();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->unsignedBigInteger('job_order_id')->nullable()->index();
            $table->foreign('job_order_id')->references('id')->on('job_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partner_shopping_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_order_id');
        });
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
