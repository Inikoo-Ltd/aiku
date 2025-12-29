<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Dec 2025 20:17:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('family_id')->index()->nullable();
            $table->foreign('family_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->unsignedInteger('department_id')->index()->nullable();
            $table->foreign('department_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->unsignedInteger('sub_department_id')->index()->nullable();
            $table->foreign('sub_department_id')->references('id')->on('product_categories')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['family_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['sub_department_id']);
            $table->dropColumn(['family_id', 'department_id', 'sub_department_id']);
        });
    }
};
