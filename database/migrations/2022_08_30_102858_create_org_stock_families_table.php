<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:20:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_stock_families', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('stock_family_id')->index()->nullable();
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('name', 255)->nullable();
            $table->string('state')->default(OrgStockFamilyStateEnum::IN_PROCESS->value)->index();
            $table->jsonb('data');
            $table->timestampstz();
            $table->dateTimeTz('activated_in_organisation_at')->nullable();
            $table->dateTimeTz('discontinuing_in_organisation_at')->nullable();
            $table->dateTimeTz('discontinued_in_organisation_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_stock_families');
    }
};
