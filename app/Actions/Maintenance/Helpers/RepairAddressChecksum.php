<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:56:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Helpers;

use App\Models\Helpers\Address;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairAddressChecksum
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(Address $address,Command $command): void
    {
        $checksum = $address->getChecksum();
        $address->update(['checksum' => $checksum]);
        $command->info('Checksum updated for address '.$address->id.' '.$checksum);

    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_address_checksum {address_id}';
    }

    public function asCommand(Command $command): int
    {


        $address = Address::find($command->argument('address_id'));
        try {
            $this->handle($address,$command);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
