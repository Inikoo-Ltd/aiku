<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('match_state')->nullable()->index();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->unsignedInteger('payment_id')->nullable()->index();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->jsonb('match_data')->default('{}');
            $table->timestampTz('matched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['match_state', 'order_id', 'payment_id', 'match_data', 'matched_at']);
        });
    }
};
