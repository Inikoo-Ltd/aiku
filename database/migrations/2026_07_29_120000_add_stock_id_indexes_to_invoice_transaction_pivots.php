<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    protected array $indexes = [
        'invoice_transaction_has_stocks_stock_id_index'          => ['invoice_transaction_has_stocks', 'stock_id'],
        'invoice_transaction_has_org_stocks_org_stock_id_index'  => ['invoice_transaction_has_org_stocks', 'org_stock_id'],
        'invoice_transaction_has_trade_units_trade_unit_id_index' => ['invoice_transaction_has_trade_units', 'trade_unit_id'],
    ];

    public function up(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        foreach ($this->indexes as $name => [$table, $column]) {
            DB::statement("CREATE INDEX {$concurrently}IF NOT EXISTS {$name} ON {$table} ({$column})");
        }
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        foreach (array_keys($this->indexes) as $name) {
            DB::statement("DROP INDEX {$concurrently}IF EXISTS {$name}");
        }
    }
};
