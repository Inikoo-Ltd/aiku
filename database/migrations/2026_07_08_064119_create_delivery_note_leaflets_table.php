<?php

use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('delivery_note_leaflets', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('delivery_note_id')->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes');
            $table->unsignedInteger('model_has_leaflet_id')->nullable()->index();
            $table->foreign('model_has_leaflet_id')->references('id')->on('model_has_leaflets');
            $table->string('type', 64)->index()->comment('Values: '.implode(', ', LeafletTypeEnum::values()));
            $table->string('name');
            $table->unsignedInteger('media_id')->nullable();
            $table->foreign('media_id')->references('id')->on('media');
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->string('state', 32)->index()->default(DeliveryNoteLeafletStateEnum::PENDING_PRINT->value)->comment('Values: '.implode(', ', DeliveryNoteLeafletStateEnum::values()));
            $table->dateTimeTz('printed_at')->nullable();
            $table->unsignedSmallInteger('printed_by_user_id')->nullable();
            $table->foreign('printed_by_user_id')->references('id')->on('users');
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_leaflets');
    }
};
