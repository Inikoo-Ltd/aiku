<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:40:26 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasAssetCodeDescription;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetCodeDescription;
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->unsignedInteger('image_id')->nullable();
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->jsonb('data');
            $table->timestampstz();
            $this->softDeletes($table);
        });
        DB::statement('CREATE INDEX ON product_categories USING gin (name gin_trgm_ops) ');

    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
