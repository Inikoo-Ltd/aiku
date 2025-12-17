<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 21:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasOrderingStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasOrderingStats;
    public function up(): void
    {
        Schema::create('master_variant_ordering_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_variant_id')->index();
            $table->foreign('master_variant_id')->references('id')->on('master_variants')->onDelete('cascade')->onUpdate('cascade');
            $table = $this->orderingStatsFields($table);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_variant_ordering_stats');
    }
};
