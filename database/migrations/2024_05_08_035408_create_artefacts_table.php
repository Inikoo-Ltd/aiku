<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 11:26:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Goods\Stock\StockStateEnum;
use App\Stubs\Migrations\HasAssetCodeDescription;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetCodeDescription;
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('artefacts', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('stock_id')->nullable()->index();
            $table->foreign('stock_id')->references('id')->on('stocks')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->unsignedInteger('stock_family_id')->index()->nullable();

            $table->string('state')->default(StockStateEnum::IN_PROCESS->value)->index();

            $table->jsonb('settings');
            $table->jsonb('data');
            $table->timestampsTz();

            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();

        });
        DB::statement('CREATE INDEX ON stocks USING gin (name gin_trgm_ops) ');

    }

    public function down(): void
    {
        Schema::dropIfExists('artefacts');
    }
};
