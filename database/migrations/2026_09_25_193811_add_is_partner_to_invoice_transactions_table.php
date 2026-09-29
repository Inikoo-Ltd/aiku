<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 05:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('invoice_transactions', 'is_partner')) {
            Schema::table('invoice_transactions', function (Blueprint $table) {
                $table->boolean('is_partner')->default(false);
            });
        }

        DB::statement('UPDATE invoice_transactions SET is_partner = true FROM invoices WHERE invoices.id = invoice_transactions.invoice_id AND invoices.as_organisation_id IS NOT NULL AND invoice_transactions.is_partner = false');
    }

    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn('is_partner');
        });
    }
};
