<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('inventory_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnDelete();
            $table->float('actual_quantity_in_locations')->comment('Stock at en of day , allow negative values');
            $table->float('quantity_in_locations')->comment('Stock at end of day, min value zero');
            $table->float('unit_value');
            $table->float('value_in_locations')->comment('Stock value at end of day, (unit_value*quantity_in_locations) organisation currency');
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('inventory_daily_snapshots');
    }
};
