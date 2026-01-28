<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:35:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\External\Faire\GetFaireProducts;
use App\Actions\Catalogue\Shop\External\Shopify\StoreShopifyUserExternalShop;
use App\Actions\Catalogue\Shop\Traits\WithFaireShopApiCollection;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithStoreShopRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreExternalShop extends OrgAction
{
    use WithStoreShopRules;
    use WithModelAddressActions;
    use WithFaireShopApiCollection;

    public array $settings;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-admin.{$this->organisation->id}");
    }

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, array $modelData): Shop
    {
        return DB::transaction(function () use ($organisation, $modelData) {
            $modelData['state'] = ShopStateEnum::OPEN;
            $modelData['type'] = ShopTypeEnum::EXTERNAL->value;

            if ($modelData['engine'] === ShopEngineEnum::FAIRE->value) {
                $modelData = $this->handleFaireShop($modelData);
            } elseif ($modelData['engine'] === ShopEngineEnum::SHOPIFY->value) {
                $shopifyUser = $this->handleShopifyShop($organisation, $modelData);
                data_set($modelData, 'settings.shopify.auth_url', route('pupil.authenticate', [
                    'shop' => $shopifyUser->name
                ]));
                data_set($modelData, 'settings.shopify.shop_url', $shopifyUser->name);
            }
            data_set($modelData, 'open_at', now());
            data_set($modelData, 'is_aiku', true);

            $shop = StoreShop::make()->action($organisation, $modelData);

            if (isset($shopifyUser) && $modelData['engine'] === ShopEngineEnum::SHOPIFY->value) {
                $shopifyUser->update([
                    'external_shop_id' => $shop->id
                ]);
            }

            if ($modelData['engine'] === ShopEngineEnum::FAIRE->value) {
                GetFaireProducts::dispatch($shop);
            }

            return $shop;
        });
    }

    public function handleShopifyShop(Organisation $organisation, array $modelData): ShopifyUser
    {
        return StoreShopifyUserExternalShop::make()->action($organisation, $modelData);
    }

    public function handleFaireShop(array $modelData): array
    {
        $this->settings = [
            'faire' => [
                'access_token' => Arr::get($modelData, 'access_token')
            ]
        ];

        $faireBrand = $this->getFaireBrand();

        if (! Arr::has($faireBrand, 'name')) {
            throw ValidationException::withMessages(['access_token' => __('Invalid Faire Access Token')]);
        }

        data_set($modelData, 'name', $faireBrand['name'].' Faire');
        data_set($modelData, 'settings.faire', array_merge($this->settings['faire'], [
            'brand' => $faireBrand['name']
        ]));

        return $modelData;
    }

    public function rules(): array
    {
        return [
            'code'            => [
                'required',
                'max:8',
                'alpha_dash',
                new IUnique(
                    table: 'shops',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                    ]
                ),
            ],
            'access_token'   => ['sometimes', 'string', 'max:255'],
            'name'       => ['sometimes', 'string', 'max:255'],
            'country_id'     => ['sometimes', 'exists:countries,id'],
            'currency_id'    => ['sometimes', 'exists:currencies,id'],
            'language_id'    => ['sometimes', 'exists:languages,id'],
            'timezone_id'    => ['sometimes', 'exists:timezones,id'],
            'engine'         => ['required', Rule::in(ShopEngineEnum::values())]
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('engine', $request->route()->parameter('engine'));
        $this->set('country_id', $this->organisation->country_id);
        $this->set('currency_id', $this->organisation->currency_id);
        $this->set('language_id', $this->organisation->language_id);
        $this->set('timezone_id', $this->organisation->timezone_id);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, string $engine, ActionRequest $request): Shop
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(Shop $shop): RedirectResponse
    {
        $redirect =  Redirect::route('grp.org.shops.show.catalogue.dashboard', [$this->organisation->slug, $shop->slug]);

        if ($redirectUri = Arr::get($shop->settings, 'shopify.auth_url')) {
            $redirect->with('redirect', [
                'url'  => $redirectUri,
                'target'  => '_blank',
            ]);
        }

        return $redirect;
    }
}
