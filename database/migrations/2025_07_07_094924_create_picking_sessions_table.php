<?php

use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('picking_sessions', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference');
            $table->unsignedInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('state')->index()->default(PickingSessionStateEnum::IN_PROCESS->value);
            $table->unsignedInteger('number_trolleys')->default(0);
            $table->unsignedInteger('number_delivery_notes')->default(0);
            $table->unsignedInteger('numbe_trolleys_picked')->default(0);
            $table->unsignedInteger('number_delivery_notes_picked')->default(0);
            $table->unsignedInteger('number_locations')->default(0);
            $table->unsignedInteger('number_locations_picked')->default(0);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('picking_sessions');
    }
};
