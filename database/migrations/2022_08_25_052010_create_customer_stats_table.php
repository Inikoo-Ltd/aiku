<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 29 Aug 2022 12:29:04 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Stubs\Migrations\HasBackInStockReminderStats;
use App\Stubs\Migrations\HasCreditsStats;
use App\Stubs\Migrations\HasCRMStats;
use App\Stubs\Migrations\HasDropshippingStats;
use App\Stubs\Migrations\HasFavouritesStats;
use App\Stubs\Migrations\HasOrderingStats;
use App\Stubs\Migrations\HasSalesStats;
use App\Stubs\Migrations\HasWebStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasBackInStockReminderStats;
    use HasCreditsStats;
    use HasCRMStats;
    use HasDropshippingStats;
    use HasFavouritesStats;
    use HasOrderingStats;
    use HasSalesStats;
    use HasWebStats;

    public function up(): void
    {
        Schema::create('customer_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $salesFields = [
                'sales',
                'sales_org_currency',
                'sales_grp_currency',
            ];

            foreach ($salesFields as $salesField) {
                $col = '_all';
                $table->decimal($salesField.$col, 16)->default(0);
            }

            $table = $this->orderingStatsFields($table);
            $table = $this->getWebUsersStatsFields($table);
            $table = $this->dropshippingStatsFields($table);
            $table = $this->getCreditTransactionsStats($table);
            $table = $this->getTopUpsStats($table);
            $table = $this->getFavouritesStatsFields($table);
            $table = $this->getRemindersStatsFields($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_stats');
    }
};
