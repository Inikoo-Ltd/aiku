<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_delivery_item_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedBigInteger('stock_delivery_item_id');
            $table->foreign('stock_delivery_item_id')->references('id')->on('stock_delivery_items')->cascadeOnDelete();
            $table->unsignedInteger('batch_code_id')->index();
            $table->foreign('batch_code_id')->references('id')->on('batch_codes');
            $table->decimal('quantity', 18, 6);
            $table->timestampsTz();
            $table->unique(['stock_delivery_item_id', 'batch_code_id']);
        });

        Schema::table('stock_families', function (Blueprint $table) {
            $table->boolean('is_batch_tracked')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_families', function (Blueprint $table) {
            $table->dropColumn('is_batch_tracked');
        });
        Schema::dropIfExists('stock_delivery_item_batches');
    }
};
