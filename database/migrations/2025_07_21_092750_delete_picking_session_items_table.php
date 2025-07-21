<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    
    public function up(): void
    {
        Schema::dropIfExists('picking_session_item_has_delivery_note_items');
        Schema::dropIfExists('picking_session_items');
    }


    public function down(): void
    {
        Schema::create('picking_session_items', function (Blueprint $table) {
              $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();

            $table->unsignedInteger('location_id')->index();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();

            $table->unsignedInteger('picking_session_id')->nullable()->index();
            $table->foreign('picking_session_id')->references('id')->on('picking_sessions')->nullOnDelete();

            $table->unsignedInteger('stock_family_id')->index()->nullable();
            $table->foreign('stock_family_id')->references('id')->on('stock_families');

            $table->unsignedInteger('stock_id')->index()->nullable();
            $table->foreign('stock_id')->references('id')->on('stocks')->nullOnUpdate();

            $table->unsignedInteger('org_stock_family_id')->index()->nullable();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families')->nullOnUpdate();

            $table->unsignedInteger('org_stock_id')->index()->nullable();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnUpdate();

            $table->string('notes')->nullable();
            $table->decimal('quantity_required', 16, 3)->default(0);
            $table->decimal('quantity_picked', 16, 3)->nullable();
            $table->timestampsTz();
        });

        Schema::create('picking_session_item_has_delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('picking_session_item_id')->nullable()->index();
            $table->unsignedInteger('delivery_note_item_id')->nullable()->index();

            $table->foreign('picking_session_item_id')
                ->references('id')->on('picking_session_items')
                ->nullOnDelete();

            $table->foreign('delivery_note_item_id')
                ->references('id')->on('delivery_note_items')
                ->nullOnDelete();

            $table->timestampsTz();
        });
    }
};
