<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Market\Outer\OuterStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('outers', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('is_main')->index();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index();
            $table->string('name', 255)->nullable();
            $table->string('state')->default(OuterStateEnum::IN_PROCESS)->index();

            $table->unsignedDecimal('units', 12, 3)->nullable()->comment('units per outer');
            $table->unsignedDecimal('price', 18)->comment('outer price');
            $table->unsignedInteger('available')->default(0)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('outers');
    }
};
