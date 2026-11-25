<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 22:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS invoices_owed_index ON invoices (organisation_id) INCLUDE (id, shop_id, customer_id, currency_id, order_id, date, total_amount, payment_amount, grp_exchange, org_exchange, source_id, as_organisation_id, is_cash_on_delivery, is_pastpay) WHERE pay_status = 'unpaid' AND type = 'invoice' AND in_process = false AND deleted_at IS NULL");
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS invoices_refunds_of_index ON invoices (original_invoice_id) INCLUDE (total_amount, payment_amount) WHERE type = 'refund' AND in_process = false AND deleted_at IS NULL");
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS customers_with_balance_index ON customers (organisation_id) INCLUDE (shop_id, balance) WHERE balance <> 0 AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS customers_with_balance_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS invoices_refunds_of_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS invoices_owed_index');
    }
};
