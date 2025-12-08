<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Sept 2024 11:49:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCreditsStats;
use App\Stubs\Migrations\HasOrderingStats;
use App\Stubs\Migrations\HasSalesStats;
use App\Stubs\Migrations\HasWebStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCreditsStats;
    use HasOrderingStats;
    use HasSalesStats;
    use HasWebStats;

    public function up(): void
    {
        Schema::create('customer_client_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_client_id')->index();
            $table->foreign('customer_client_id')->references('id')->on('customer_clients');
            $table = $this->orderingStatsFields($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_client_stats');
    }
};
