<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Address;
use Illuminate\Console\Command;

class RepairCustomerCountryCodeMismatch
{
    use WithActionUpdate;


    /**
     * @var array|\ArrayAccess|mixed
     */
    private mixed $count = 0;

    protected function handle(Address $address, Command $command): void
    {
        if (!$address->country) {
            return;
        }

        $countryCode0 = $address->country_code;
        $countryCode1 = $address->country->code;
        if ($countryCode0 !== $countryCode1) {
            $this->count++;

            if ($address->is_fixed) {
                $command->line(" $this->count FIXED Address: $address->id  $address->created_at  country_code: $countryCode0 ||>>>><<<<|| country_id->code: $countryCode1");
            } else {
                $command->line(" $this->count LIVE Address: $address->id  $address->created_at  country_code: $countryCode0 ||>>>><<<<|| country_id->code: $countryCode1");
            }
            UpdateAddress::run($address, [
                'country_id' => $address->country_id,
            ]);




        }
    }

    public string $commandSignature = 'repair:country_code_mismatch';

    public function asCommand(Command $command): void
    {
        Address::orderBy('created_at', 'desc')->chunk(1000, function ($addresses) use ($command) {
            foreach ($addresses as $address) {
                $this->handle($address, $command);
            }
        });
    }
}
