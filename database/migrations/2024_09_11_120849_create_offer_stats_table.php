<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 20:09:21 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('offer_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('offer_id')->index();
            $table->foreign('offer_id')->references('id')->on('offers');

            $table = $this->usageStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_stats');
    }
};
