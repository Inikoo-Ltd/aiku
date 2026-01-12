<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jan 2026 23:40:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('new_products', function (Blueprint $table) {
            $table->id();
            $table->string('model_type')->index();
            $table->unsignedInteger('model_id')->index();
            $table->dateTimeTz('notified_at')->nullable()->index();
            $table->unique([
                'model_type',
                'model_id'
            ]);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('new_products');
    }
};
