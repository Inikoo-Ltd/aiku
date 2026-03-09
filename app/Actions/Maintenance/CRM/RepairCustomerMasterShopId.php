<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 09 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairCustomerMasterShopId
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:customer_master_shop_id';

    public function asCommand(Command $command): void
    {
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

        $remaining = Customer::whereNull('master_shop_id')->count();
        $command->info("remaining with null master_shop_id: {$remaining}");
    }
}
