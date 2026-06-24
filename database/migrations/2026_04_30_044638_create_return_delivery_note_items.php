<?php

use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('return_delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedBigInteger('return_delivery_note_id')->index();
            $table->foreign('return_delivery_note_id')->references('id')->on('return_delivery_notes');

            $table->unsignedBigInteger('delivery_note_items_id')->index();
            $table->foreign('delivery_note_items_id')->references('id')->on('delivery_note_items');


            $table->unsignedInteger('stock_family_id')->index()->nullable();
            $table->foreign('stock_family_id')->references('id')->on('stock_families');

            $table->unsignedInteger('stock_id')->index()->nullable();
            $table->foreign('stock_id')->references('id')->on('stocks')->nullOnUpdate();

            $table->unsignedInteger('org_stock_family_id')->index()->nullable();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnUpdate();

            $table->unsignedInteger('org_stock_id')->index()->nullable();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnUpdate();

            $table->string('return_state')->default(ReturnDeliveryNoteItemStateEnum::UNASSIGNED->value);
            $table->decimal('total_item_not_returned', 16, 6)->default(0);
            $table->decimal('total_item_damaged', 16, 6)->default(0);
            $table->decimal('total_item_lost', 16, 6)->default(0);
            $table->decimal('total_item_returned', 16, 6)->default(0);

            $table->datetimeTz('handled_at')->nullable();
            $table->datetimeTz('processed_at')->nullable();
            $table->datetimeTz('cancelled_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('return_delivery_note_items');
    }
};
