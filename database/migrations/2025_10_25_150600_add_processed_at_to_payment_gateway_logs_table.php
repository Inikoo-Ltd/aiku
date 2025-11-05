<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 15:07:53 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_gateway_logs', 'gateway_date')) {
                $table->dateTime('gateway_date', 6)->nullable();
            }
            if (Schema::hasColumn('payment_gateway_logs', 'date')) {
                $table->dropColumn('date');
            }
            if (!Schema::hasColumn('payment_gateway_logs', 'gateway_id')) {
                $table->string('gateway_id')->nullable()->index();
            }

            $table->dateTime('processed_at', 6)->nullable()->index();
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_gateway_logs', 'date')) {
                $table->date('date')->nullable();
            }
            if (Schema::hasColumn('payment_gateway_logs', 'gateway_date')) {
                $table->dropColumn('gateway_date');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'gateway_id')) {
                $table->dropColumn('gateway_id');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'processed_at')) {
                $table->dropColumn('processed_at');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'organisation_id')) {
                $table->dropColumn('organisation_id');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'shop_id')) {
                $table->dropColumn('shop_id');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
        });
    }
};
