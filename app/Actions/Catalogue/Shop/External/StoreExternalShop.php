<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:35:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop\External;

use App\Actions\Catalogue\Shop\External\Shopify\StoreShopifyUserExternalShop;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\Traits\WithFaireShopApiCollection;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithStoreShopRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Exception;
use Illuminate\Console\Command;
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

            if($modelData['engine'] === ShopEngineEnum::FAIRE->value) {
                $modelData = $this->handleFaireShop($modelData);
            } else if($modelData['engine'] === ShopEngineEnum::SHOPIFY->value) {
                data_set($modelData, 'name', Arr::get($modelData, 'shop_url'));

                $shopifyUser = $this->handleShopifyShop($organisation, $modelData);
                data_set($modelData, 'settings.shopify.auth_url', route('pupil.authenticate', [
                    'shop' => $shopifyUser->name
                ]));
            }

            $shop = StoreShop::make()->action($organisation, $modelData);

            if(isset($shopifyUser) && $modelData['engine'] === ShopEngineEnum::SHOPIFY->value) {
                $shopifyUser->update([
                    'external_shop_id' => $shop->id
                ]);
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

        if(! Arr::has($faireBrand, 'name')) {
            throw ValidationException::withMessages(['message' => 'Invalid Faire Access Token']);
        }

        data_set($modelData, 'name', $faireBrand['name']);
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
            'shop_url'       => ['sometimes', 'string', 'max:255'],
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
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Shop
    {
        if (!$audit) {
            Shop::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, string $engine, ActionRequest $request): Shop
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }


    public string $commandSignature = 'shop-external:create {organisation : organisation slug} {code} {name} {type}
    {--warehouses=*} {--contact_name=} {--company_name=} {--email=} {--phone=} {--identity_document_number=} {--identity_document_type=} {--country=} {--currency=} {--language=} {--timezone=}';


    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            /** @var Organisation $organisation */
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }
        $this->organisation = $organisation;
        setPermissionsTeamId($organisation->group->id);

        if ($command->option('country')) {
            try {
                $country = Country::where('code', $command->option('country'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $country = $organisation->country;
        }

        if ($command->option('currency')) {
            try {
                $currency = Currency::where('code', $command->option('currency'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $currency = $organisation->currency;
        }

        if ($command->option('language')) {
            try {
                $language = Language::where('code', $command->option('language'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $language = $organisation->language;
        }

        if ($command->option('timezone')) {
            try {
                $timezone = Timezone::where('name', $command->option('timezone'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $timezone = $organisation->timezone;
        }


        $this->setRawAttributes([
            'code'        => $command->argument('code'),
            'name'        => $command->argument('name'),
            'type'        => $command->argument('type'),
            'timezone_id' => $timezone->id,
            'country_id'  => $country->id,
            'currency_id' => $currency->id,
            'language_id' => $language->id,
        ]);

        if ($command->option('warehouses')) {
            $this->fill([
                'warehouses' => $command->option('warehouses')
            ]);
        }


        try {
            $validatedData = $this->validateAttributes();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $shop = $this->handle($organisation, $validatedData);

        $command->info("Shop $shop->code created successfully 🎉");

        return 0;
    }

    public function htmlResponse(Shop $shop): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.catalogue.dashboard', [$this->organisation->slug, $shop->slug]);
    }
}
