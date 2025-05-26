<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 May 2025 19:57:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name');
            $table->string('scope')->index();
            $table->jsonb('data');
            $table->unsignedInteger('number_models')->default(0);
            $table->timestampsTz();
            $table->unique(['scope', 'name'], 'unique_scope_name');

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
