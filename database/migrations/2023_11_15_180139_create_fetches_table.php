<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Nov 2023 12:28:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetches', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->index();
            $table->unsignedInteger('number_items')->default(0);
            $table->unsignedInteger('number_no_changes')->default(0);
            $table->unsignedInteger('number_updates')->default(0);
            $table->unsignedInteger('number_stores')->default(0);
            $table->unsignedInteger('number_errors')->default(0);
            $table->dateTimeTz('finished_at')->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetches');
    }
};
