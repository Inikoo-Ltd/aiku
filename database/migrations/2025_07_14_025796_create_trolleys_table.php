<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('trolleys', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('current_delivery_note_id')->index();
            $table->foreign('current_delivery_note_id')->references('id')->on('delivery_notes')->nullOnDelete();
            $table->timestampsTz();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('trolleys');
    }
};
