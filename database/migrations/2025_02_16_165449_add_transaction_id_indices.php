<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 00:54:58 Central Indonesia Time, Sanur, Bali, Indonesia
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
            $table->index('transaction_id');
        });

        Schema::table('transaction_has_offer_components', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable()->change();
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropIndex('invoice_transactions_transaction_id_index');
        });

        Schema::table('transaction_has_offer_components', function (Blueprint $table) {
            $table->dropIndex('transaction_has_offer_components_transaction_id_index');
        });
    }
};
