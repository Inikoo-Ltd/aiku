<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 23:51:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('shop_accounting_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_current_payment_accounts')->default(0)->after('number_payment_accounts');
        });

        Schema::table('organisation_accounting_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_current_payment_accounts')->default(0)->after('number_payment_accounts');
        });

        Schema::table('group_accounting_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_current_payment_accounts')->default(0)->after('number_payment_accounts');
        });
    }


    public function down(): void
    {
        Schema::table('shop_accounting_stats', function (Blueprint $table) {
            $table->dropColumn('number_current_payment_accounts');
        });

        Schema::table('organisation_accounting_stats', function (Blueprint $table) {
            $table->dropColumn('number_current_payment_accounts');
        });

        Schema::table('group_accounting_stats', function (Blueprint $table) {
            $table->dropColumn('number_current_payment_accounts');
        });
    }
};
