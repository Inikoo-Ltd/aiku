<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_supplier_products', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('supplier_product_id');
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products');

            $table->unsignedInteger('org_agent_id')->nullable();
            $table->foreign('org_agent_id')->references('id')->on('org_agents');

            $table->unsignedInteger('org_supplier_id')->nullable();
            $table->foreign('org_supplier_id')->references('id')->on('org_suppliers');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('state')->index()->default(OrgSupplierProductStateEnum::ACTIVE->value);
            $table->boolean('is_available')->index()->default(true);
            $table->timestampsTz();
            $table->string('source_id')->index()->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_supplier_products');
    }
};
