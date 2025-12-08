<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:20:49 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasUsageStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasUsageStats;

    public function up(): void
    {
        Schema::create('offer_component_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('offer_component_id')->index();
            $table->foreign('offer_component_id')->references('id')->on('offer_components');
            $table = $this->usageStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_component_stats');
    }
};
