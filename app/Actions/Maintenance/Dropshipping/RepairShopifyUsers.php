<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\ShopifyUser\DeleteShopifyUser;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairShopifyUsers
{
    use AsAction;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser): array
    {
        $state = '';

        $deleted = $shopifyUser->trashed();

        $hasDeletedSlug = ' ';
        if (preg_match('/deleted-\d+/', $shopifyUser->name)) {
            $hasDeletedSlug = '✅';
        }


        // Get name and stripped name for all users
        $name         = $shopifyUser->name;
        $strippedName = preg_replace('/deleted-\d+.*/', '', $name);
        $strippedName = trim($strippedName);
        // Remove .myshopify.com from the end of the name
        $strippedName = preg_replace('/\.myshopify\.com$/', '', $strippedName);

        $channel = '';

        $channelHasThis = CustomerSalesChannel::where('platform_user_type', 'ShopifyUser')->where('platform_user_id', $shopifyUser->id)->first();

        if ($deleted) {
            $state = '🗑️ ';
            if ($hasDeletedSlug) {
                $state .= ' ✅';
            } else {
                $state .= ' ❌';
            }

            if (!$shopifyUser->customerSalesChannel) {
                $state .= ' ✅';
            } else {
                $state .= ' ❌';
            }


            if (!$channelHasThis) {
                $state .= ' ✅ NO CHANNEL';
            } else {
                $state .= ' ❌';
                $state .= ' '.$channelHasThis->status->value;

                $numberPortfolios      = $channelHasThis->portfolios()->count();
                $numberOrders          = $channelHasThis->orders()->count();
                $numberCustomerClients = $channelHasThis->clients()->count();
                $state                 .= " P:$numberPortfolios O:$numberOrders C:$numberCustomerClients";
            }


            $otherShopifyUsers = ShopifyUser::where('name', $strippedName)->exists();


            $state .= " ($strippedName) ".$otherShopifyUsers ? ' 🐦‍🔥' : '';

            if ($shopifyUser->customerSalesChannel) {
                //CloseCustomerSalesChannel::run($shopifyUser->customerSalesChannel);
            }
        } else {
            $name = preg_replace('/\.myshopify\.com$/', '', $strippedName);
            $name .= ' '.$shopifyUser->id;
            if (!$shopifyUser->customerSalesChannel) {
                $channel = '🧟 Zombie';

                if ($channelHasThis) {
                    $channel .= ' InDirect';
                }

                $channel .= ' '.$shopifyUser->created_at;
            } else {
                $channel = '📺 ';

                if ($shopifyUser->customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
                    $channel .= ' ⚠️ '.$shopifyUser->customerSalesChannel->status->value;

                    // delete the shopifyUser
                    //DeleteShopifyUser::run($shopifyUser);
                }
            }
        }


        return [
            'name'    => $name,
            'channel' => $channel,
            'state'   => $state,
        ];
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_users';
    }

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $tableData = [];
        $counter   = 1;

        foreach (ShopifyUser::withTrashed()->orderBy('id')->get() as $shopifyUser) {
            $tableData[] = array_merge(['counter' => $counter++], $this->handle($shopifyUser, $command));
        }

        $command->table(
            ['#', 'ShopifyUser Name', 'Channel', 'State'],
            array_map(function ($item) {
                return [$item['counter'], $item['name'], $item['channel'], $item['state']];
            }, $tableData)
        );
    }

}
