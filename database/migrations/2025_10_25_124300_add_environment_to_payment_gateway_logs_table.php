<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 12:45:09 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateway_logs', 'origin')) {
                $table->string('origin')->nullable()->index();
            }

            if (! Schema::hasColumn('payment_gateway_logs', 'operation')) {
                $table->string('operation')->nullable()->index();
            }

            if (! Schema::hasColumn('payment_gateway_logs', 'environment')) {
                $table->string('environment')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_logs', 'origin')) {
                $table->dropColumn('origin');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'operation')) {
                $table->dropColumn('operation');
            }
            if (Schema::hasColumn('payment_gateway_logs', 'environment')) {
                $table->dropColumn('environment');
            }
        });
    }
};
