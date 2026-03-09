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

class RepairCustomerRegisteredAt
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:customer_registered_at';

    public function asCommand(Command $command): void
    {
        $pending = Customer::whereNull('registered_at')->count();
        $command->info("customers with null registered_at: {$pending}");

        if ($pending === 0) {
            return;
        }

        $sql = <<<SQL
        UPDATE customers
        SET registered_at = created_at
        WHERE registered_at IS NULL
            AND created_at IS NOT NULL
        SQL;

        $updated = DB::affectingStatement($sql);
        $command->info("updated customers: {$updated}");

        $remaining = Customer::whereNull('registered_at')->count();
        $command->info("remaining with null registered_at: {$remaining}");
    }
}
