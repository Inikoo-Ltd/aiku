<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductTradeUnitCompositionEnum;
use App\Stubs\Migrations\HasAssetCodeDescription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetCodeDescription;

    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->string('trade_unit_composition')->default(SupplierProductTradeUnitCompositionEnum::MATCH->value)->nullable();
            $table->unsignedInteger('current_historic_supplier_product_id')->index()->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media');
            $table->unsignedInteger('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedSmallInteger('agent_id')->nullable()->index();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->string('state')->index()->default(SupplierProductStateEnum::IN_PROCESS->value);
            $table->boolean('is_available')->index()->default(true);
            $table->decimal('cost', 18, 4)->comment('unit cost');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedInteger('units_per_pack')->nullable()->comment('units per pack');
            $table->unsignedInteger('units_per_carton')->nullable()->comment('units per carton');
            $table->decimal('cbm', 18, 4)->nullable()->comment('carton cubic meters');
            $table->jsonb('settings');
            $table->jsonb('data');
            $table->dateTimeTz('activated_at')->nullable();
            $table->dateTimeTz('discontinuing_at')->nullable();
            $table->dateTimeTz('discontinued_at')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_slug')->index()->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->jsonb('sources');
            $table->unique(['supplier_id', 'code']);
        });
        DB::statement('CREATE INDEX ON supplier_products USING gin (name gin_trgm_ops) ');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
