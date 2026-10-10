<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('org_stock_movement_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedBigInteger('org_stock_movement_id')->index();
            $table->foreign('org_stock_movement_id')->references('id')->on('org_stock_movements')->cascadeOnDelete();
            $table->unsignedInteger('org_stock_id');
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->cascadeOnDelete();
            $table->unsignedInteger('location_id');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->unsignedInteger('batch_code_id')->index();
            $table->foreign('batch_code_id')->references('id')->on('batch_codes');
            $table->decimal('quantity', 18, 6);
            $table->timestampsTz();
            $table->index(['location_id', 'org_stock_id', 'batch_code_id']);
            $table->index(['org_stock_id', 'batch_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_stock_movement_batches');
    }
};
