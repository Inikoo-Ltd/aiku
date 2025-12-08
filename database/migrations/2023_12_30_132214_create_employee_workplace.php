<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Dec 2023 21:22:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_workplace', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('workplace_id');
            $table->foreign('workplace_id')->references('id')->on('workplaces')->onUpdate('cascade')->onDelete('cascade');
            $table->timestampsTz();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_workplace');
    }
};
