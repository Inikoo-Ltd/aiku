<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 10:00:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasPaymentStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasPaymentStats;

    public function up(): void
    {
        Schema::create('payment_account_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payment_account_id')->index();
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts');
            $table = $this->paymentStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_stats');
    }
};
