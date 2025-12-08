<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 15:53:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_account_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_pas')->default(0)->comment('Number of Payment Account Shops');
            foreach (PaymentAccountShopStateEnum::cases() as $state) {
                $table->unsignedInteger("number_pas_state_{$state->snake()}")->default(0)->comment("Number of Payment Account Shops in {$state->value}");
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_account_stats', function (Blueprint $table) {
            $table->dropColumn('number_pas');
            foreach (PaymentAccountShopStateEnum::cases() as $state) {
                $table->dropColumn("number_pas_state_{$state->snake()}");
            }
        });
    }
};
