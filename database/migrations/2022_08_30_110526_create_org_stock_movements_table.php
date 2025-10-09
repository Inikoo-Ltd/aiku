<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 19:05:47 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_stock_movements', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->unsignedSmallInteger('warehouse_area_id')->nullable()->index();
            $table->foreign('warehouse_area_id')->references('id')->on('warehouse_areas');
            $table->dateTimeTz('date');
            $table->string('class')->index();
            $table->string('type')->index();
            $table->string('flow')->index();
            $table->boolean('is_delivered')->index()->default(false);
            $table->boolean('is_received')->index()->default(false);

            $table->unsignedInteger('stock_family_id')->nullable()->index();
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->nullOnDelete();
            $table->unsignedInteger('org_stock_family_id')->nullable()->index();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stocks')->nullOnDelete();

            $table->unsignedInteger('stock_id')->nullable()->index();
            $table->foreign('stock_id')->references('id')->on('stocks')->nullOnDelete();
            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->cascadeOnDelete();

            $table->unsignedInteger('location_id')->nullable()->index();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->nullableMorphs('operation');
            $table->decimal('quantity', 18, 6);
            $table->decimal('org_amount', 16, 3);
            $table->decimal('grp_amount', 16, 3);
            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('org_stock_movements');
    }
};
