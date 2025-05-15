<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 12:55:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('delivery_note_shipment');

        Schema::create('model_has_shipments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model_type')->index();
            $table->unsignedInteger('model_id');
            $table->unsignedInteger('shipment_id')->index();
            $table->foreign('shipment_id')->references('id')->on('shipments')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['model_type', 'model_id','shipment_id'], 'model_has_shipments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_shipments');

        Schema::create('delivery_note_shipment', function (Blueprint $table) {
            $table->increments('id');

            $table->timestampsTz();
        });
    }
};
