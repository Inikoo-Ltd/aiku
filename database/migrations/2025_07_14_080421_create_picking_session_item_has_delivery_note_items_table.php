<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
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


    public function down(): void
    {
        Schema::dropIfExists('picking_session_item_has_delivery_note_items');
    }
};
