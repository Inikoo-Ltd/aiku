<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 14:30:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->renameColumn('invoice_transaction_id', 'original_invoice_transaction_id');
        });
    }


    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->renameColumn('original_invoice_transaction_id', 'invoice_transaction_id');
        });
    }
};
