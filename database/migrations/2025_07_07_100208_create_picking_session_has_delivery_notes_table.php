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
        Schema::create('picking_session_has_delivery_notes', function (Blueprint $table) {
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('picking_session_id')->nullable()->index();
            $table->unsignedInteger('delivery_note_id')->nullable()->index();

            $table->foreign('picking_session_id')
                ->references('id')->on('picking_sessions')
                ->nullOnDelete();

            $table->foreign('delivery_note_id')
                ->references('id')->on('delivery_notes')
                ->nullOnDelete();

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('picking_session_has_delivery_notes');
    }
};
