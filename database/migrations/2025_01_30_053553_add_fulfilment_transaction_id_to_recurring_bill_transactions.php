<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jan 2025 13:36:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->unsignedInteger('fulfilment_transaction_id')->nullable()->index();
            $table->foreign('fulfilment_transaction_id')->references('id')->on('fulfilment_transactions')->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->dropForeign(['fulfilment_transaction_id']);
        });
    }
};
