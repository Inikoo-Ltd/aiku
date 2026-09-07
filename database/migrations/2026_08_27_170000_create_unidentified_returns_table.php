<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A box arrives at goods-in whose delivery note cannot be found. The operator photographs it
     * and copies whatever is written on it, so the office can identify it later. Identification
     * links it to the delivery note and the return created from it; until then delivery_note_id
     * stays null.
     */
    public function up(): void
    {
        Schema::create('unidentified_returns', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->nullOnDelete();
            $table->unsignedInteger('delivery_note_id')->nullable()->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->nullOnDelete();
            $table->unsignedBigInteger('return_delivery_note_id')->nullable();
            $table->foreign('return_delivery_note_id')->references('id')->on('return_delivery_notes')->nullOnDelete();
            $table->dateTimeTz('identified_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidentified_returns');
    }
};
