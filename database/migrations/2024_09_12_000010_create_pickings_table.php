<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 01 Sept 2022 19:01:58 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Stubs\Migrations\HasPicking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasPicking;
    public function up(): void
    {
        Schema::create('pickings', function (Blueprint $table) {
            $table = $this->pickingHead($table);

            $table->string('state')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('not_picked_reason')->default(PickingNotPickedReasonEnum::NA->value)->index();
            $table->string('not_picked_note')->nullable();

            $table->decimal('quantity_required', 16, 3)->default(0);
            $table->decimal('quantity_picked', 16, 3)->nullable();

            $table->unsignedInteger('org_stock_movement_id')->nullable()->index();
            $table->foreign('org_stock_movement_id')->references('id')->on('org_stock_movements');

            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');

            $table->unsignedSmallInteger('picker_id')->nullable()->index();
            $table->foreign('picker_id')->references('id')->on('users');

            $table->string('engine')->index()->default(PickingEngineEnum::AIKU->value);

            $table->unsignedSmallInteger('location_id')->nullable()->index();
            $table->foreign('location_id')->references('id')->on('locations');

            $table->jsonb('data');


            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('picking_at')->nullable();
            $table->dateTimeTz('picking_blocked_at')->nullable();
            $table->dateTimeTz('done_at')->nullable();

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('pickings');
    }
};
