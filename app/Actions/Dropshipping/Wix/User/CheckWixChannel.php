<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WixUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class CheckWixChannel
{
    use AsAction;

    public function handle(WixUser $wixUser): ?CustomerSalesChannel
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;

        $customerSalesChannel = $wixUser->customerSalesChannel;

        if (!$customerSalesChannel) {
            return null;
        }

        $reason = null;

        try {
            if ($wixUser->getUserInfo()) {
                $canConnectToPlatform = $existInPlatform = true;

                if ($wixUser->hasWixStores()) {
                    $platformStatus = true;
                } else {
                    $reason = __('Wix Stores is not installed on this site. Add the Wix Stores app to the site, then reconnect the channel.');
                }
            }
        } catch (\Exception $e) {
            Sentry::captureException($e);

            $reason = $e->getMessage();
        }

        $data = [
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform
        ];

        $settings = $customerSalesChannel->settings ?? [];
        data_set($settings, 'wix.not_ready_reason', $reason);
        $data['settings'] = $settings;

        if ($platformStatus) {
            $data['state']                 = CustomerSalesChannelStateEnum::AUTHENTICATED;
            $data['ban_stock_update_util'] = null;
        } else {
            $data['state'] = CustomerSalesChannelStateEnum::NOT_READY;
        }

        return UpdateCustomerSalesChannel::run($customerSalesChannel, $data);
    }

    public string $commandSignature = 'wix:check {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        /** @var WixUser $wixUser */
        $wixUser = $customerSalesChannel->user;

        $customerSalesChannel = $this->handle($wixUser);

        if (!$customerSalesChannel) {
            $command->error('This Wix user has no customer sales channel.');

            return;
        }

        $this->displayChannelInfo($command, $customerSalesChannel, $wixUser->refresh());
    }

    private function displayChannelInfo(Command $command, CustomerSalesChannel $customerSalesChannel, WixUser $wixUser): void
    {
        $command->info("\nChannel Status:");
        $command->table(['Field', 'Value'], [
            ['Customer Sales Channel', $customerSalesChannel->slug],
            ['State', $customerSalesChannel->state->value],
            ['Platform Status', $customerSalesChannel->platform_status ? 'Yes' : 'No'],
            ['Can Connect to Platform', $customerSalesChannel->can_connect_to_platform ? 'Yes' : 'No'],
            ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No'],
            ['Not Ready Reason', Arr::get($customerSalesChannel->settings, 'wix.not_ready_reason') ?? 'N/A'],
        ]);

        $siteData = $wixUser->data ?? [];

        $command->info("\nWix Site:");
        $command->table(['Field', 'Value'], [
            ['Name', $wixUser->name ?? 'N/A'],
            ['Email', $wixUser->email ?? 'N/A'],
            ['Site ID', $wixUser->wix_site_id ?? 'N/A'],
            ['Site URL', $wixUser->site_url ?? 'N/A'],
            ['Instance ID', Arr::get($siteData, 'instance_id') ?? 'N/A'],
            ['Catalog Version', Arr::get($siteData, 'catalog_version') ?? 'N/A'],
            ['Currency', Arr::get($siteData, 'currency') ?? 'N/A'],
            ['Locale', Arr::get($siteData, 'locale') ?? 'N/A'],
            ['Access Token Expires', $wixUser->access_token_expire_in ? Carbon::createFromTimestamp($wixUser->access_token_expire_in)->toDateTimeString() : 'N/A'],
        ]);
    }
}
