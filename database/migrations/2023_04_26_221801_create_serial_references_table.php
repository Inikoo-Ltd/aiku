<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_references', function (Blueprint $table) {
            $table->increments('id');
            $table->string('container_type');
            $table->unsignedInteger('container_id');
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('model')->index();
            $table->unsignedInteger('serial')->default(0);
            $table->string('format')->default('%06d');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->index(['container_type', 'container_id']);
            $table->index(['container_type', 'container_id', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_references');
    }
};
