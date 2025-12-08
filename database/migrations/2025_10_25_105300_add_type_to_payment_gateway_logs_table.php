<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 10:55:45 Central Indonesia Time, Kuta, Bali, Indonesia
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
            $table->string('type')->nullable()->index();
            $table->dateTime('date')->nullable()->index();
            $table->string('outcome')->nullable()->index();
            $table->string('api_point_model_type')->nullable()->index();
            $table->unsignedInteger('api_point_model_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('outcome');
            $table->dropColumn('date');
            $table->dropColumn('api_point_model_type');
            $table->dropColumn('api_point_model_id');

        });
    }
};
