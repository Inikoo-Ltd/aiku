<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 15:40:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();

            $table->boolean('status')->default(true);
            $table->unsignedInteger('barcode_id')->index();
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->timestampsTz();
            $table->dateTimeTz('withdrawn_at')->nullable();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_barcodes');
    }
};
