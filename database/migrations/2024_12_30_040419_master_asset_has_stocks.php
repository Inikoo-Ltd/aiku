<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Dec 2024 12:04:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_asset_has_stocks', function (Blueprint $table) {
            $table->unsignedInteger('master_asset_id')->nullable();
            $table->foreign('master_asset_id')->references('id')->on('master_assets')->cascadeOnDelete();
            $table->unsignedInteger('stock_id')->nullable();
            $table->foreign('stock_id')->references('id')->on('stocks')->cascadeOnDelete();

            $table->decimal('quantity', 12, 3);
            $table->string('notes')->nullable();

            $table->timestampsTz();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_asset_has_stocks');
    }
};
