<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('employee_id')->nullable()->index();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->unsignedBigInteger('split_from_id')->nullable()->index();
            $table->foreign('split_from_id')->references('id')->on('job_order_items');
        });
    }

    public function down(): void
    {
        Schema::table('job_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_from_id');
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
