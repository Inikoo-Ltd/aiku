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
        Schema::create('picking_sessions', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('picking_sessions');
    }
};
