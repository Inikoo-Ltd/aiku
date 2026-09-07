<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * Authorising the same store again must land on the channel it already has, with its
     * portfolios and orders, instead of minting a second channel and platform user.
     */
    public function handle(Customer $customer, array $modelData): WooCommerceUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE->value)->first();

        data_set($modelData, 'store_url', self::normaliseStoreUrl(Arr::get($modelData, 'store_url')));
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'platform_id', $platform->id);

        return DB::transaction(function () use ($customer, $platform, $modelData) {
            $wooCommerceUser = $this->reconnectExistingStore($customer, $modelData);

            if ($wooCommerceUser) {
                return $wooCommerceUser;
            }

            /** @var WooCommerceUser $wooCommerceUser */
            $wooCommerceUser = $customer->wooCommerceUser()->create($modelData);

            $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
                'platform_user_type' => class_basename($wooCommerceUser),
                'platform_user_id'   => $wooCommerceUser->id,
                'reference'          => $wooCommerceUser->name,
                'name'               => $wooCommerceUser->name
            ]);

            $wooCommerceUser->update([
                'customer_sales_channel_id' => $customerSalesChannel->id,
            ]);

            return $wooCommerceUser;
        });
    }

    public static function normaliseStoreUrl(?string $storeUrl): ?string
    {
        if (blank($storeUrl)) {
            return $storeUrl;
        }

        $storeUrl = rtrim(trim($storeUrl), '/');
        $host     = parse_url($storeUrl, PHP_URL_HOST);

        return $host ? Str::replaceFirst($host, Str::lower($host), $storeUrl) : $storeUrl;
    }

    /**
     * The webhook ids are forgotten so the next channel check registers fresh ones: the store
     * disables webhooks that kept failing while the channel was closed.
     */
    private function reconnectExistingStore(Customer $customer, array $modelData): ?WooCommerceUser
    {
        $storeUrl = Arr::get($modelData, 'store_url');

        if (blank($storeUrl)) {
            return null;
        }

        /** @var WooCommerceUser|null $wooCommerceUser */
        $wooCommerceUser = WooCommerceUser::withTrashed()
            ->where('customer_id', $customer->id)
            ->whereNotNull('customer_sales_channel_id')
            ->whereRaw("lower(rtrim(store_url, '/')) = ?", [Str::lower($storeUrl)])
            ->orderByRaw('deleted_at is null desc')
            ->orderByDesc('id')
            ->first();

        $customerSalesChannel = $wooCommerceUser?->customerSalesChannel;

        if (!$customerSalesChannel) {
            return null;
        }

        if ($wooCommerceUser->trashed()) {
            $wooCommerceUser->restore();
        }

        $wooCommerceUser = $this->update($wooCommerceUser, array_merge(
            Arr::only($modelData, ['name', 'consumer_key', 'consumer_secret', 'store_url']),
            ['settings' => Arr::except($wooCommerceUser->settings ?? [], 'webhooks')]
        ));

        $wasClosed = $customerSalesChannel->status == CustomerSalesChannelStatusEnum::CLOSED;

        $this->update($customerSalesChannel, [
            'platform_user_type' => class_basename($wooCommerceUser),
            'platform_user_id'   => $wooCommerceUser->id,
            'status'             => CustomerSalesChannelStatusEnum::OPEN,
            'state'              => CustomerSalesChannelStateEnum::CREATED,
            'name'               => preg_replace('/ - deleted - \d+$/', '', (string) $customerSalesChannel->name) ?: null,
            'closed_at'          => null,
        ]);

        if ($wasClosed) {
            foreach ($customerSalesChannel->portfolios as $portfolio) {
                UpdatePortfolio::run($portfolio, ['status' => true]);
            }
        }

        return $wooCommerceUser;
    }
}
