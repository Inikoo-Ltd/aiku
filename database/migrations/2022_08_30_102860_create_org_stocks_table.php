<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 08:52:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Stubs\Migrations\HasAssetCodeDescription;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetCodeDescription;
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_stocks', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('stock_id')->index()->nullable();
            $table->foreign('stock_id')->references('id')->on('stocks')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('org_stock_family_id')->index()->nullable();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families');

            $table->unsignedInteger('customer_id')->index()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onUpdate('cascade')->onDelete('cascade');

            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('name', 255)->nullable();
            $table->decimal('unit_cost', 16)->nullable();
            $table->decimal('unit_value', 16)->nullable();
            $table->decimal('unit_commercial_value', 16)->default(0);

            $table->boolean('is_sellable_in_organisation')->default(1)->index();
            $table->boolean('is_raw_material_in_organisation')->default(0)->index();
            $table->string('state')->default(OrgStockStateEnum::ACTIVE->value)->index();
            $table->string('quantity_status')->nullable()->index();

            $table->decimal('quantity_in_locations', 16, 3)->nullable()->default(0)->comment('stock quantity in units');
            $table->decimal('value_in_locations', 16)->default(0);
            $table->float('available_forecast')->nullable()->comment('days');

            $table->jsonb('data');
            $table->timestampsTz();
            $table->dateTimeTz('activated_in_organisation_at')->nullable();
            $table->dateTimeTz('discontinuing_in_organisation_at')->nullable();
            $table->dateTimeTz('discontinued_in_organisation_at')->nullable();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_stocks');
    }
};
