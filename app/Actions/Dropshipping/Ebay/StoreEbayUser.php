<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): EbayUser
    {
        if ($unfinished = self::unfinishedRow($customer)) {
            UpdateEbayUser::run($unfinished, ['name' => Arr::get($modelData, 'name'), 'step' => EbayUserStepEnum::MARKETPLACE]);
            UpdateCustomerSalesChannel::run($unfinished->customerSalesChannel, ['name' => Arr::get($modelData, 'name')]);

            return $unfinished->refresh();
        }

        $platform = Platform::where('type', PlatformTypeEnum::EBAY->value)->first();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'platform_id', $platform->id);
        data_set($modelData, 'marketplace', Arr::get($customer->shop->settings, 'ebay.marketplace_id'));
        data_set($modelData, 'step', EbayUserStepEnum::MARKETPLACE);

        /** @var EbayUser $ebayUser */
        $ebayUser = $customer->ebayUser()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($ebayUser),
            'platform_user_id' => $ebayUser->id,
            'reference' => 'ebay-'. $customer->reference,
            'name' => Arr::get($modelData, 'name')
        ]);

        $ebayUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        return $ebayUser;
    }

    public static function unfinishedRow(Customer $customer): ?EbayUser
    {
        return EbayUser::where('customer_id', $customer->id)
            ->whereNot('step', EbayUserStepEnum::COMPLETED)
            ->whereHas('customerSalesChannel', fn ($query) => $query->where('status', CustomerSalesChannelStatusEnum::OPEN))
            ->orderByDesc('updated_at')
            ->first();
    }
}
