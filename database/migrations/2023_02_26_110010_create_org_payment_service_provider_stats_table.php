<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 20:48:49 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('org_payment_service_provider_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedInteger('org_payment_service_provider_id')->index();
            $table->foreign('org_payment_service_provider_id')->references('id')->on('org_payment_service_providers');
            $table = $this->paymentAccountStats($table);
            $table = $this->paymentStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_payment_service_provider_stats');
    }
};
