<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 21:02:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('type');
            $table->string('state')->default(RawMaterialStateEnum::IN_PROCESS->value);
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedInteger('stock_id')->index()->nullable();
            $table->string('code', 64)->index()->collation('und_ns');
            $table->text('description');
            $table->string('unit')->default(RawMaterialUnitEnum::UNIT->value);
            $table->decimal('unit_cost', 18, 3);
            $table->decimal('quantity_on_location', 18, 3)->default(0)->nullable();
            $table->string('stock_status')->default(RawMaterialStockStatusEnum::OUT_OF_STOCK->value);
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};
