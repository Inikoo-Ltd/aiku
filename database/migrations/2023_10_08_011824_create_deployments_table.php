<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Oct 2023 10:05:17 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('scope')->nullable()->index();
            $table->string('publisher_type')->nullable();
            $table->unsignedInteger('publisher_id')->nullable();
            $table->unsignedInteger('snapshot_id')->nullable();
            $table->foreign('snapshot_id')->references('id')->on('snapshots')->onUpdate('cascade')->onDelete('cascade');
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
            $table->index(['model_type', 'model_id', 'scope']);
            $table->index(['publisher_id', 'publisher_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
