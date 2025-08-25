<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Aug 2025 08:25:38 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairInvoicesMasterShopId
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:invoices_master_shop';

    public function asCommand(Command $command): void
    {
        $pending = Invoice::whereNull('master_shop_id')->count();
        $command->info("invoices with null master_shop_id: {$pending}");

        if ($pending === 0) {
            return;
        }

        // Prefer a single efficient SQL update using a JOIN (PostgreSQL syntax)
        $sql = <<<SQL
UPDATE invoices
SET master_shop_id = shops.master_shop_id
FROM shops
WHERE invoices.shop_id = shops.id
  AND invoices.master_shop_id IS NULL
  AND shops.master_shop_id IS NOT NULL
SQL;

        $updated = DB::affectingStatement($sql);
        $command->info("updated invoices: {$updated}");

        $remaining = Invoice::whereNull('master_shop_id')->count();
        $command->info("remaining with null master_shop_id: {$remaining}");

        // ============= orders
        $pending = Order::whereNull('master_shop_id')->count();
        $command->info("orders with null master_shop_id: {$pending}");

        if ($pending === 0) {
            return;
        }

        // Prefer a single efficient SQL update using a JOIN (PostgreSQL syntax)
        $sql = <<<SQL
UPDATE orders
SET master_shop_id = shops.master_shop_id
FROM shops
WHERE orders.shop_id = shops.id
  AND orders.master_shop_id IS NULL
  AND shops.master_shop_id IS NOT NULL
SQL;

        $updated = DB::affectingStatement($sql);
        $command->info("updated orders: {$updated}");

        $remaining = Order::whereNull('master_shop_id')->count();
        $command->info("remaining with null master_shop_id: {$remaining}");


        // ============= customers
        $pending = Customer::whereNull('master_shop_id')->count();
        $command->info("customers with null master_shop_id: {$pending}");

        if ($pending === 0) {
            return;
        }

        $sql = <<<SQL
UPDATE customers
SET master_shop_id = shops.master_shop_id
FROM shops
WHERE customers.shop_id = shops.id
  AND customers.master_shop_id IS NULL
  AND shops.master_shop_id IS NOT NULL
SQL;

        $updated = DB::affectingStatement($sql);
        $command->info("updated customers: {$updated}");

        $remaining = Order::whereNull('master_shop_id')->count();
        $command->info("remaining with null master_shop_id: {$remaining}");
    }



}
