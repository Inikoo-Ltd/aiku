<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 May 2025 20:21:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->index();
            $table->string('name')->index();
            $table->unsignedInteger('number_models')->default(0);
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
