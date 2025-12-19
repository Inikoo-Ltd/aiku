<?php

/*
 *  Author: Oggie Sutrisna
 *  Created: Thu, 19 Dec 2024 Malaysia Time
 *  Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\Sowing\SowingEngineEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('sowings', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedInteger('delivery_note_id')->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->cascadeOnDelete();

            $table->unsignedBigInteger('delivery_note_item_id')->index();
            $table->foreign('delivery_note_item_id')->references('id')->on('delivery_note_items')->cascadeOnDelete();

            $table->decimal('quantity', 16, 3)->default(0);

            $table->unsignedInteger('org_stock_movement_id')->nullable()->index();
            $table->foreign('org_stock_movement_id')->references('id')->on('org_stock_movements');

            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');

            $table->unsignedSmallInteger('sower_user_id')->nullable()->index();
            $table->foreign('sower_user_id')->references('id')->on('users');

            $table->string('engine')->index()->default(SowingEngineEnum::AIKU->value);

            $table->unsignedSmallInteger('location_id')->nullable()->index();
            $table->foreign('location_id')->references('id')->on('locations');

            $table->jsonb('data');

            $table->dateTimeTz('sowed_at')->nullable();

            $table->timestampsTz();

            // Index for finding sowings by original picking reference (if needed)
            $table->unsignedBigInteger('original_picking_id')->nullable()->index();
            $table->foreign('original_picking_id')->references('id')->on('pickings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sowings');
    }
};
