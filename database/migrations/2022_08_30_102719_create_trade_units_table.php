<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 18:31:50 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_units', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index();
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('barcode_id')->index()->nullable();
            $table->foreign('barcode_id')->references('id')->on('barcodes')->onUpdate('cascade')->onDelete('cascade');
            $table->string('barcode')->index()->nullable();
            $table->unsignedInteger('net_weight')->nullable()->comment('(grams)');
            $table->unsignedInteger('gross_weight')->nullable()->comment('incl packing (grams)');
            $table->unsignedInteger('marketing_weight')->nullable()->comment('to be shown in website (grams)');

            $table->jsonb('dimensions')->nullable();
            $table->double('volume')->nullable()->comment('in cubic meters');
            $table->string('type')->default('piece')->index()->nullable()->comment('unit type');
            $table->unsignedInteger('image_id')->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_slug')->index()->nullable();
            $table->string('source_id')->nullable()->index();
            $table->jsonb('sources');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_units');
    }
};
