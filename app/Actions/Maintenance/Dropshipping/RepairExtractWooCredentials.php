<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairExtractWooCredentials
{
    use AsAction;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $errorData = Arr::get($wooCommerceUser, key: 'data', default: []);

        // Normalize error message
        $normalizedError = $wooCommerceUser->normalizeErrorMessage($errorData);

        $wooCommerceUser->update([
            'consumer_key' => Arr::get($wooCommerceUser->settings, 'credentials.consumer_key'),
            'consumer_secret' => Arr::get($wooCommerceUser->settings, 'credentials.consumer_secret'),
            'store_url' => Arr::get($wooCommerceUser->settings, 'credentials.store_url'),
            'error_response' => $normalizedError
        ]);

        echo $wooCommerceUser->name . " ✅\n";
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_extract_credentials {slug?}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('slug')) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('slug'))->first();
            $this->handle($customerSalesChannel->user);
        } else {
            foreach (WooCommerceUser::all() as $wooUser) {
                $this->handle($wooUser);
            }
        }
    }
}
