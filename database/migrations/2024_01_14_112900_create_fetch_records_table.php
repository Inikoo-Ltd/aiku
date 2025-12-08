<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Jan 2024 19:30:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetch_records', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fetch_id');
            $table->foreign('fetch_id')->references('id')->on('fetches')->onUpdate('cascade')->onDelete('cascade');
            $table->string('type')->index();
            $table->string('error_on')->nullable()->index();
            $table->string('source_id')->nullable()->index();
            $table->string('model_type')->nullable()->index();
            $table->string('model_id')->nullable()->index();
            $table->jsonb('model_data');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_records');
    }
};
