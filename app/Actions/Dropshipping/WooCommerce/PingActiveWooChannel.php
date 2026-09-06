<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Maintenance\Dropshipping\RepairWooChannelReconnects;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class PingActiveWooChannel
{
    use asAction;
    use WithActionUpdate;

    public string $commandSignature = 'woo:ping_active_channel';

    public const int PARKED_AFTER_FAILURES = 12;

    public static function hasLiveSiblingForSameStore(CustomerSalesChannel $customerSalesChannel): bool
    {
        $wooCommerceUser = $customerSalesChannel->user;

        if (!$wooCommerceUser instanceof WooCommerceUser || blank($wooCommerceUser->store_url)) {
            return false;
        }

        return WooCommerceUser::query()
            ->join('customer_sales_channels', 'customer_sales_channels.id', '=', 'woo_commerce_users.customer_sales_channel_id')
            ->where('woo_commerce_users.customer_id', $customerSalesChannel->customer_id)
            ->where('woo_commerce_users.id', '!=', $wooCommerceUser->id)
            ->whereRaw("lower(rtrim(woo_commerce_users.store_url, '/')) = ?", [RepairWooChannelReconnects::storeKey($wooCommerceUser->store_url)])
            ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN)
            ->exists();
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command, now()->hour < 6);
    }

    /**
     * A channel that failed twelve pings in a row is parked so a dead store is not hit every six
     * hours forever. The first run of each day pings the parked ones too, so a store that comes
     * back is noticed and its orders flow again instead of staying dark until somebody revives it.
     * A parked channel whose customer has since connected the same store again stays parked: two
     * live channels on one store would both fetch orders and push stock, and which one survives
     * is the repair command's decision, not the ping's.
     */
    public function handle(Command $command, bool $retryParked = false): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->when(!$retryParked, fn ($query) => $query->where('ping_error_count', '<', self::PARKED_AFTER_FAILURES))
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                if ($customerSalesChannel->ping_error_count >= self::PARKED_AFTER_FAILURES && self::hasLiveSiblingForSameStore($customerSalesChannel)) {
                    continue;
                }

                $customerSalesChannel = CheckWooChannel::run($customerSalesChannel->user);

                if (! $customerSalesChannel->platform_status) {
                    $customerSalesChannel->update([
                        'ping_error_count' => $customerSalesChannel->ping_error_count + 1,
                    ]);
                } else {
                    $customerSalesChannel->update([
                        'ping_error_count' => 0
                    ]);
                }

                $errorMsg = '-';

                if (is_string(Arr::get($customerSalesChannel->user->data, '0'))) {
                    $errorMsg = Arr::get($customerSalesChannel->user->data, '0');
                } elseif (is_array(Arr::get($customerSalesChannel->user->data, '0'))) {
                    $errorMsg = Arr::get($customerSalesChannel->user->data, '0.message');
                }

                $statusData = [
                    ['Customer Sales Channel', $customerSalesChannel->slug],
                    ['Platform Status', $customerSalesChannel->platform_status ? 'Yes' : 'No'],
                    ['Can Connect to Platform', $customerSalesChannel->can_connect_to_platform ? 'Yes' : 'No'],
                    ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No'],
                    ['Ban', $customerSalesChannel->ban_stock_update_util ?? '-'],
                    ['Ping Error Count', $customerSalesChannel->ping_error_count],
                    ['Error Message', Str::substr($errorMsg, 0, 120)],
                ];


                $command->info("\nCustomer Sales Channel Status:");
                $command->table(['Field', 'Value'], $statusData);
            }
        }
    }
}
