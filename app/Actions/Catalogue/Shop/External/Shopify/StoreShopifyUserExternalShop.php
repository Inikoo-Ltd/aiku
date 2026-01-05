<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreShopifyUserExternalShop extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, $modelData): ShopifyUser
    {
        $name            = Arr::get($modelData, 'name');
        $myShopifyDomain = config('shopify-app.my_shopify_domain');
        $name            = $name.'.'.$myShopifyDomain;
        data_set($modelData, 'name', Str::lower($name));

        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first();

        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'username', Str::random(4));
        data_set($modelData, 'password', Str::random(8));
        data_set($modelData, 'platform_id', $platform->id);

        return DB::transaction(function () use ($modelData) {
            return ShopifyUser::create($modelData);
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
        $nameInput = $this->get('name');
        $nameInput = trim($nameInput);

        $nameInput = preg_replace('#^https?:?:?//+#i', '', $nameInput);
        $nameInput = preg_replace('/\.myshopify\.com$/i', '', $nameInput);

        $nameInput = trim($nameInput);


        $this->set('name', $nameInput);
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $modelData): ShopifyUser
    {
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }
}
