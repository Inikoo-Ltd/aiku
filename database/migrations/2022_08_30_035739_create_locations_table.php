<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:06:44 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->unsignedSmallInteger('warehouse_area_id')->nullable()->index();
            $table->foreign('warehouse_area_id')->references('id')->on('warehouse_areas');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('status')->index()->default(LocationStatusEnum::OPERATIONAL->value);
            $table->string('code', 64)->index()->collation('und_ns');
            $table->decimal('stock_value', 16, 2)->default(0);
            $table->decimal('stock_commercial_value', 16, 2)->default(0);
            $table->boolean('is_empty')->default(true);
            $table->decimal('max_weight', 16, 3)->nullable()->comment('Max weight in Kg');
            $table->decimal('max_volume', 16, 4)->nullable()->comment('Max volume in m3 (cbm)');
            $table->boolean('allow_stocks')->default(true);
            $table->boolean('allow_dropshipping')->default(true);
            $table->boolean('allow_fulfilment')->default(true);
            $table->boolean('has_stock_slots')->default(false);
            $table->boolean('has_dropshipping_slots')->default(false);
            $table->boolean('has_fulfilment')->default(false);
            $table->string('barcode')->nullable()->index();
            $table->jsonb('data');
            $table->dateTimeTz('audited_at')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
