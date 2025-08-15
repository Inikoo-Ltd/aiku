<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Dec 2024 14:08:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasOrderingStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasOrderingStats;
    public function up(): void
    {
        Schema::create('master_collection_ordering_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_collection_id')->nullable()->index();
            $table->foreign('master_collection_id')->references('id')->on('master_collections');
            $table = $this->orderingStatsFields($table);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_collection_ordering_stats');
    }
};
