<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShopifyUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, $modelData): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'username', Str::random(4));
        data_set($modelData, 'password', Str::random(8));
        data_set($modelData, 'platform_id', $platform->id);


        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = ShopifyUser::whereNull('customer_id')->where('name', Arr::get($modelData, 'name'))->first();

        if ($shopifyUser) {
            data_set($modelData, 'customer_id', $customer->id);
            data_set($modelData, 'organisation_id', $customer->organisation_id);
            data_set($modelData, 'group_id', $customer->group_id);

            $shopifyUser = $this->update($shopifyUser, $modelData);
        } else {
            /** @var ShopifyUser $shopifyUser */
            $shopifyUser = $customer->shopifyUser()->create($modelData);
        }

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($shopifyUser),
            'platform_user_id' => $shopifyUser->id,
            'reference' => Arr::get(explode('myshopify.com', $shopifyUser->name), '0'),
        ]);

        $shopifyUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);


    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'ends_with:.'.config('shopify-app.myshopify_domain'), Rule::unique('shopify_users', 'name')->whereNotNull('customer_id')]
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $shopifyFullName = $request->input('name').'.'.config('shopify-app.my_shopify_domain');

        $this->set('name', $shopifyFullName);
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $this->validatedData);
    }
}
