<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 May 2025 16:15:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payment_account_stats', function (Blueprint $table) {
            $table->unsignedInteger("number_customers")->default(0)->comment('Number distinct customer with successful payments');
        });

        Schema::table('payment_service_provider_stats', function (Blueprint $table) {
            $table->unsignedInteger("number_customers")->default(0)->comment('Number distinct customer with successful payments');
        });

        Schema::table('payment_account_shop_stats', function (Blueprint $table) {
            $table->unsignedInteger("number_customers")->default(0)->comment('Number distinct customer with successful payments');
        });
    }


    public function down(): void
    {
        Schema::table('payment_account_stats', function (Blueprint $table) {
            $table->dropColumn('number_customers');
        });
        Schema::table('payment_service_provider_stats', function (Blueprint $table) {
            $table->dropColumn('number_customers');
        });
        Schema::table('payment_account_shop_stats', function (Blueprint $table) {
            $table->dropColumn('number_customers');
        });
    }
};
