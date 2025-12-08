<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Feb 2023 23:43:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCreditsStats;
use App\Stubs\Migrations\HasPaymentStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCreditsStats;
    use HasPaymentStats;

    public function up(): void
    {
        Schema::create('shop_accounting_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table = $this->paymentServiceProviderStats($table);
            $table = $this->paymentAccountStats($table);
            $table = $this->paymentStats($table);
            $table = $this->getCreditTransactionsStats($table);
            $table = $this->getTopUpsStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_accounting_stats');
    }
};
