<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 15:32:22 Central Indonesia Time, Sanur, Bali, Indonesia
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
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->string('state')->default(PaymentAccountShopStateEnum::IN_PROCESS);
        });
    }

    public function down(): void
    {
        Schema::table('payment_account_shop', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
