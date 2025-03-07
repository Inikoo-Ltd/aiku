<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCreditsStats;
use App\Stubs\Migrations\HasPaymentStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasPaymentStats;
    use HasCreditsStats;

    public function up(): void
    {
        Schema::create('organisation_accounting_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

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
        Schema::dropIfExists('organisation_accounting_stats');
    }
};
