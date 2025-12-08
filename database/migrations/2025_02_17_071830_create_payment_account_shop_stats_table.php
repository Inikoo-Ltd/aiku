<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 15:32:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
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
        Schema::create('payment_account_shop_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payment_account_shop_id')->index();
            $table->foreign('payment_account_shop_id')->references('id')->on('payment_account_shop')->cascadeOnDelete();
            $table = $this->paymentStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_shop_stats');
    }
};
