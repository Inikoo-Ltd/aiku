<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 17:18:01 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_gateway_logs', 'order_id')) {
                $table->unsignedInteger('order_id')->nullable()->index();
                $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('payment_gateway_logs', 'gateway_payment_id')) {
                $table->string('gateway_payment_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_logs', 'order_id')) {
                try {
                    $table->dropForeign(['order_id']);
                } catch (\Throwable) {
                    // In case FK name differs or does not exist; proceed to drop the column
                }
                $table->dropColumn('order_id');
            }

            if (Schema::hasColumn('payment_gateway_logs', 'gateway_payment_id')) {
                $table->dropColumn('gateway_payment_id');
            }
        });
    }
};
