<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->unsignedInteger('delivery_note_id')->nullable()->index()
                ->comment('intercompany: seller delivery note this stock delivery mirrors');
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->nullOnDelete();
            $table->unsignedInteger('invoice_id')->nullable()->index()
                ->comment('intercompany: seller invoice, anchor for refunds/payments');
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_note_id');
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
