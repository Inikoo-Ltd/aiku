<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 02 Sept 2022 14:44:17 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasCreditsStats;
use App\Stubs\Migrations\HasHelpersStats;
use App\Stubs\Migrations\HasOrderingStats;
use App\Stubs\Migrations\HasQueriesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCatalogueStats;
    use HasCreditsStats;
    use HasHelpersStats;
    use HasOrderingStats;
    use HasQueriesStats;

    public function up(): void
    {
        Schema::create('shop_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table = $this->catalogueStats($table);
            $table = $this->billableFields($table);
            $table = $this->uploadStats($table);
            $table = $this->getQueriesStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stats');
    }
};
