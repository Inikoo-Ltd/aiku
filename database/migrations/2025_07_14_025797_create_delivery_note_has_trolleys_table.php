<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('delivery_note_has_trolleys', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('delivery_note_id')->nullable()->index();
            $table->unsignedInteger('trolley_id')->nullable()->index();

            $table->foreign('delivery_note_id')
                ->references('id')->on('delivery_notes')
                ->nullOnDelete();

            $table->foreign('trolley_id')
                ->references('id')->on('trolleys')
                ->nullOnDelete();

            $table->string('state')->index()->nullable();

            $table->timestampsTz();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_has_trolleys');
    }
};
