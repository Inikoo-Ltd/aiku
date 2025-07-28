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
use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreShopifyUser extends RetinaAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, $modelData): ShopifyUser
    {
        $name            = Arr::get($modelData, 'name');
        $myShopifyDomain = config('shopify-app.my_shopify_domain');
        $name            = $name.'.'.$myShopifyDomain;
        data_set($modelData, 'name', $name);

        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'username', Str::random(4));
        data_set($modelData, 'password', Str::random(8));
        data_set($modelData, 'platform_id', $platform->id);


        return DB::transaction(function () use ($customer, $platform, $modelData) {
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
                'platform_user_id'   => $shopifyUser->id,
                'reference'          => Arr::get(explode('.myshopify.com', $shopifyUser->name), '0'),
            ]);

            $shopifyUser->update([
                'customer_sales_channel_id' => $customerSalesChannel->id,
            ]);

            return $shopifyUser;
        });
    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if (str_contains($this->get('name'), ' ')) {
            $validator->errors()->add('name', __('Shop name cannot contain spaces'));

            return;
        }

        if (!preg_match('/^[a-zA-Z0-9-]+$/', $this->get('name'))) {
            $validator->errors()->add('name', __('Shop name can only contain letters, numbers, and hyphens'));

            return;
        }

        $myShopifyDomain = config('shopify-app.my_shopify_domain');
        $shopifyShopUrl  = 'https://'.$this->get('name').'.'.$myShopifyDomain;

        if (ShopifyUser::where('name', $this->get('name').'.'.$myShopifyDomain)
            ->whereNotNull('customer_id')
            ->exists()) {
            $validator->errors()->add('name', __('Shopify shop :shop already exists, please use other name', ['shop' => $this->get('name')]));
        }

        $response = Http::get($shopifyShopUrl);
        if (!$response->ok()) {
            $validator->errors()->add('name', __('Shopify shop :shop not found', ['shop' => $this->get('name')]));
        }
    }

    public function jsonResponse(ShopifyUser $shopifyUser): string
    {
        return route('pupil.authenticate', [
            'shop' => $shopifyUser->name
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shopify_users', 'name')->whereNotNull('customer_id')
            ]
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $nameInput = $request->input('name');
        $nameInput = trim($nameInput);

        $nameInput = preg_replace('#^https?:?:?//+#i', '', $nameInput);
        $nameInput = preg_replace('/\.myshopify\.com$/i', '', $nameInput);

        $nameInput = trim($nameInput);


        $this->set('name', $nameInput);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): ShopifyUser
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
