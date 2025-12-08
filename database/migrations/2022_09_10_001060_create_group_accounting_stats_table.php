<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 11:39:16 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Stubs\Migrations\HasCreditsStats;
use App\Stubs\Migrations\HasOrderingStats;
use App\Stubs\Migrations\HasPaymentStats;
use App\Stubs\Migrations\HasSalesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCreditsStats;
    use HasOrderingStats;
    use HasPaymentStats;
    use HasSalesStats;

    public function up(): void
    {
        if (! Schema::hasTable('group_accounting_stats')) {
            Schema::create('group_accounting_stats', function (Blueprint $table) {
                $table->smallIncrements('id');
                $table->unsignedSmallInteger('group_id');
                $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');

                $table->unsignedSmallInteger('number_payment_service_providers')->default(0);
                foreach (PaymentServiceProviderTypeEnum::cases() as $case) {
                    $table->unsignedInteger("number_payment_service_providers_type_{$case->snake()}")->default(0);
                }

                $table = $this->paymentServiceProviderStats($table);
                $table = $this->paymentAccountStats($table);
                $table = $this->paymentStats($table);
                $table = $this->invoicesStatsFields($table);
                $table = $this->getCreditTransactionsStats($table);
                $table = $this->getTopUpsStats($table);

                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_accounting_stats');
    }
};
