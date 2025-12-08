<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 09:24:05 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_return_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->default('Pallet')->comment('Pallet|StoredItem')->index();
            $table->unsignedInteger('pallet_return_id');
            $table->foreign('pallet_return_id')->references('id')->on('pallet_returns');
            $table->unsignedInteger('pallet_id');
            $table->foreign('pallet_id')->references('id')->on('pallets');

            $table->unsignedInteger('stored_item_id')->nullable();
            $table->foreign('stored_item_id')->references('id')->on('stored_items');

            $table->unsignedInteger('pallet_stored_item_id')->nullable();
            $table->foreign('pallet_stored_item_id')->references('id')->on('pallet_stored_items');

            $table->decimal('quantity_ordered', 16, 3);
            $table->decimal('quantity_dispatched', 16, 3)->default(0);
            $table->decimal('quantity_fail', 16, 3)->default(0);
            $table->decimal('quantity_cancelled', 16, 3)->default(0);

            $table->unsignedInteger('picking_location_id')->nullable();
            $table->foreign('picking_location_id')->references('id')->on('locations');

            $table->string('state')->default(PalletReturnItemStateEnum::IN_PROCESS->value);
            $table->timestampsTz();

        });
        DB::statement('CREATE UNIQUE INDEX pallet_return_items_unique ON pallet_return_items (pallet_return_id, pallet_id, stored_item_id) NULLS NOT DISTINCT');

    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_return_items');
    }
};
