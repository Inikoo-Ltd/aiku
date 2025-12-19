<?php

/*
 *  Author: Oggie Sutrisna
 *  Created: Thu, 19 Dec 2024 Malaysia Time
 *  Copyright (c) 2024, Raul A Perusquia Flores
 */

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

            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedInteger('delivery_note_id')->nullable()->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->nullOnDelete();

            $table->unsignedBigInteger('delivery_note_item_id')->nullable()->index();
            $table->foreign('delivery_note_item_id')->references('id')->on('delivery_note_items')->nullOnDelete();

            $table->decimal('quantity', 16, 6)->default(0);

            $table->unsignedInteger('org_stock_movement_id')->nullable()->index();
            $table->foreign('org_stock_movement_id')->references('id')->on('org_stock_movements')->nullOnDelete();

            $table->unsignedInteger('return_id')->nullable()->index();
            $table->foreign('return_id')->references('id')->on('returns')->nullOnDelete();

            $table->unsignedInteger('return_item_id')->nullable()->index();
            $table->foreign('return_item_id')->references('id')->on('return_items')->nullOnDelete();

            $table->unsignedInteger('stock_delivery_id')->nullable()->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries')->nullOnDelete();

            $table->unsignedInteger('stock_delivery_item_id')->nullable()->index();
            $table->foreign('stock_delivery_item_id')->references('id')->on('stock_delivery_items')->nullOnDelete();


            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');

            $table->unsignedSmallInteger('sower_user_id')->nullable()->index();
            $table->foreign('sower_user_id')->references('id')->on('users');
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->foreign('location_id')->references('id')->on('locations');

            $table->unsignedBigInteger('original_picking_id')->nullable()->index();
            $table->foreign('original_picking_id')->references('id')->on('pickings')->nullOnDelete();

            $table->jsonb('data');

            $table->dateTimeTz('sowed_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sowings');
    }
};
