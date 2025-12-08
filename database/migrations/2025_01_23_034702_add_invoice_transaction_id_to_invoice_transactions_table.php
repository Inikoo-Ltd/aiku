<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Jan 2025 11:51:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->unsignedInteger('invoice_transaction_id')->nullable()->index()->comment('For refunds link to original invoice transaction');
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn('invoice_transaction_id');
        });
    }
};
