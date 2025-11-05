<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 13:29:25 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('offer_components') && !Schema::hasTable('offer_allowances')) {
            DB::statement('ALTER TABLE IF EXISTS offer_components RENAME TO offer_allowances');
        }

        if (Schema::hasTable('offer_component_stats') && !Schema::hasTable('offer_allowances_stats')) {
            DB::statement('ALTER TABLE IF EXISTS offer_component_stats RENAME TO offer_allowances_stats');
        }

        if (Schema::hasTable('invoice_transaction_has_offer_components') && !Schema::hasTable('invoice_transaction_has_offer_allowances')) {
            DB::statement('ALTER TABLE IF EXISTS invoice_transaction_has_offer_components RENAME TO invoice_transaction_has_offer_allowances');
        }

        if (Schema::hasTable('invoice_has_no_invoice_transaction_offer_components') && !Schema::hasTable('invoice_has_no_invoice_transaction_offer_allowances')) {
            DB::statement('ALTER TABLE IF EXISTS invoice_has_no_invoice_transaction_offer_components RENAME TO invoice_has_no_invoice_transaction_offer_allowances');
        }

        if (Schema::hasTable('order_has_no_transaction_offer_components') && !Schema::hasTable('order_has_no_transaction_offer_allowances')) {
            DB::statement('ALTER TABLE IF EXISTS order_has_no_transaction_offer_components RENAME TO order_has_no_transaction_offer_allowances');
        }

        if (Schema::hasTable('transaction_has_offer_components') && !Schema::hasTable('transaction_has_offer_allowances')) {
            DB::statement('ALTER TABLE IF EXISTS transaction_has_offer_components RENAME TO transaction_has_offer_allowances');
        }





    }

    public function down(): void
    {
        if (Schema::hasTable('offer_allowances') && !Schema::hasTable('offer_components')) {
            DB::statement('ALTER TABLE IF EXISTS offer_allowances RENAME TO offer_components');
        }

        if (Schema::hasTable('offer_allowances_stats') && !Schema::hasTable('offer_component_stats')) {
            DB::statement('ALTER TABLE IF EXISTS offer_allowances_stats RENAME TO offer_component_stats');
        }

        if (Schema::hasTable('invoice_transaction_has_offer_allowances') && !Schema::hasTable('invoice_transaction_has_offer_components')) {
            DB::statement('ALTER TABLE IF EXISTS invoice_transaction_has_offer_allowances RENAME TO invoice_transaction_has_offer_components');
        }

        if (Schema::hasTable('invoice_has_no_transaction_has_offer_allowances') && !Schema::hasTable('invoice_has_no_invoice_transaction_offer_components')) {
            DB::statement('ALTER TABLE IF EXISTS invoice_has_no_transaction_has_offer_allowances RENAME TO invoice_has_no_invoice_transaction_offer_components');
        }

        if (Schema::hasTable('order_has_no_transaction_offer_allowances') && !Schema::hasTable('order_has_no_transaction_offer_components')) {
            DB::statement('ALTER TABLE IF EXISTS order_has_no_transaction_offer_allowances RENAME TO order_has_no_transaction_offer_components');
        }

        if (Schema::hasTable('transaction_has_offer_allowances') && !Schema::hasTable('transaction_has_offer_components')) {
            DB::statement('ALTER TABLE IF EXISTS transaction_has_offer_allowances RENAME TO transaction_has_offer_components');
        }
    }
};
