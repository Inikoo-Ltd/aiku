<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveShopDataAllegroChannel
{
    use AsAction;

    public function handle(AllegroUser $allegroUser): AllegroUser
    {
        try {
            // Get user info from Allegro API
            $userInfo = $allegroUser->getUserInfo();

            if ($userInfo) {
                $data = $allegroUser->data ?? [];

                // Save user/seller information
                data_set($data, 'user_id', Arr::get($userInfo, 'id'));
                data_set($data, 'login', Arr::get($userInfo, 'login'));
                data_set($data, 'email', Arr::get($userInfo, 'email'));
                data_set($data, 'company_name', Arr::get($userInfo, 'company.name'));
                data_set($data, 'taxId', Arr::get($userInfo, 'company.taxId'));
                data_set($data, 'marketplace_id', Arr::get($userInfo, 'baseMarketplace.id'));

                $allegroUser->update([
                    'allegro_id' => Arr::get($data, 'user_id'),
                    'marketplace_id' => Arr::get($data, 'marketplace_id'),
                    'data' => $data,
                    'email' => Arr::get($data, 'email') ?? $allegroUser->email,
                    'username' => Arr::get($data, 'login') ?? $allegroUser->username,
                ]);

                UpdateCustomerSalesChannel::run($allegroUser->customerSalesChannel, [
                    'name' => Arr::get($data, 'company_name')
                ]);
            }

            return $allegroUser->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to save Allegro shop data: ' . $e->getMessage());
            return $allegroUser;
        }
    }

    public string $commandSignature = 'allegro:save-shop-data {customerSalesChannel}';

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel->user);
    }
}
