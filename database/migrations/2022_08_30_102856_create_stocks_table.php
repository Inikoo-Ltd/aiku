<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:03:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\Stock\StockTradeUnitCompositionEnum;
use App\Stubs\Migrations\HasAssetCodeDescription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetCodeDescription;

    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->unsignedInteger('stock_family_id')->index()->nullable();
            $table->foreign('stock_family_id')->references('id')->on('stock_families');
            $table->string('trade_unit_composition')->default(StockTradeUnitCompositionEnum::MATCH->value)->nullable();
            $table->string('state')->default(StockStateEnum::IN_PROCESS->value)->index();

            $table->boolean('sellable')->default(1)->index();
            $table->boolean('raw_material')->default(0)->index();
            $table->unsignedInteger('units_per_pack')->nullable()->comment('units per pack');
            $table->unsignedInteger('units_per_carton')->nullable()->comment('units per carton');
            $table->decimal('unit_value', 16)->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->unsignedInteger('gross_weight')->nullable()->comment('package weight grams');

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

        });
        DB::statement('CREATE INDEX ON stocks USING gin (name gin_trgm_ops) ');

    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
