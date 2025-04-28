<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:35:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccountShop\StorePaymentAccountShop;
use App\Actions\Catalogue\Shop\Seeders\SeedShopOfferCampaigns;
use App\Actions\Catalogue\Shop\Seeders\SeedShopOutboxes;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\Fulfilment\Fulfilment\StoreFulfilment;
use App\Actions\Helpers\Currency\SetCurrencyHistoricFields;
use App\Actions\Helpers\Query\Seeders\ProspectQuerySeeder;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateShops;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShops;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuScopedSections;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShops;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\SysAdmin\Organisation\SetIconAsShopLogo;
use App\Actions\SysAdmin\User\UserAddRoles;
use App\Actions\Traits\Rules\WithStoreShopRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreShop extends OrgAction
{
    use WithStoreShopRules;
    use WithModelAddressActions;

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
        $warehouses = Arr::get($modelData, 'warehouses', []);
        Arr::forget($modelData, 'warehouses');

        if (Arr::has($modelData, 'address')) {
            /** @var Address $addressData */
            $addressData = Arr::pull($modelData, 'address');
        } else {
            $addressData = $organisation->address;
        }


        data_set($modelData, 'group_id', $organisation->group_id);


        $shop = DB::transaction(function () use ($organisation, $modelData, $addressData, $warehouses) {
            /** @var Shop $shop */
            $shop = $organisation->shops()->create($modelData);
            $shop->refresh();


            StoreShopAddress::make()->action(
                $shop,
                [
                    'address' => $addressData,
                    'type'    => 'legal'
                ]
            );

            StoreShopAddress::make()->action(
                $shop,
                [
                    'address' => $addressData,
                    'type'    => 'collection'
                ]
            );


            $shop->stats()->create();
            $shop->accountingStats()->create();
            $shop->commsStats()->create();
            $shop->crmStats()->create();
            $shop->orderingStats()->create();
            $shop->salesIntervals()->create();
            $shop->orderHandlingStats()->create();
            $shop->mailshotsIntervals()->create();
            $shop->discountsStats()->create();
            $shop->orderingIntervals()->create();

            $shop->outboxNewsletterIntervals()->create();
            $shop->outboxMarketingIntervals()->create();
            $shop->outboxMarketingNotificationIntervals()->create();
            $shop->outboxCustomerNotificationIntervals()->create();
            $shop->outboxColdEmailsIntervals()->create();
            $shop->outboxPushIntervals()->create();


            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $shop->timeSeries()->create(['frequency' => $frequency]);
            }


            if ($shop->type === ShopTypeEnum::DROPSHIPPING || $shop->type === ShopTypeEnum::FULFILMENT) {
                $shop->dropshippingStats()->create();
            }

            $shop->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::CUSTOMER,
                    'organisation_id' => $organisation->id,
                ]
            );
            $shop->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::ORDER,
                    'organisation_id' => $organisation->id,
                ]
            );
            $shop->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::TOP_UP,
                    'organisation_id' => $organisation->id,
                    'format'          => $shop->slug.'-%04d'
                ]
            );

            $shop->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::PURGE,
                    'organisation_id' => $organisation->id,
                    'format'          => 'purge-'.$shop->slug.'-%04d'
                ]
            );

            $shop->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::INVOICE,
                    'organisation_id' => $organisation->id,
                    'format'          => 'inv-'.$shop->slug.'-%04d'
                ]
            );


            if ($shop->type == ShopTypeEnum::FULFILMENT) {
                $fulfilment = StoreFulfilment::make()->make()->action(
                    $shop,
                    [
                        'warehouses' => $warehouses,
                    ]
                );
                SeedAikuScopedSections::make()->seedFulfilmentAikuScopedSection($fulfilment);
            } else {
                SeedShopPermissions::run($shop);
                SeedAikuScopedSections::make()->seedShopAikuScopedSection($shop);


                $orgAdmins = $organisation->group->users()->with('roles')->get()->filter(
                    fn ($user) => $user->roles->where('name', "org-admin-$organisation->id")->toArray()
                );

                foreach ($orgAdmins as $orgAdmin) {
                    UserAddRoles::run($orgAdmin, [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $shop))->first()
                    ]);
                }
            }


            SetCurrencyHistoricFields::run($shop->currency, $shop->created_at);

            $paymentAccount       = StorePaymentAccount::make()->action(
                $organisation->getAccountsServiceProvider(),
                [
                    'code'        => 'accounts-'.$shop->slug,
                    'name'        => 'Accounts '.$shop->code,
                    'type'        => PaymentAccountTypeEnum::ACCOUNT->value,
                    'is_accounts' => true
                ]
            );
            $paymentAccount->slug = 'accounts-'.$shop->slug;
            $paymentAccount->save();

            StorePaymentAccountShop::make()->action(
                $paymentAccount,
                $shop,
                [
                   'currency_id' => $shop->currency_id,
                   'state'       => PaymentAccountShopStateEnum::ACTIVE
                ]
            );
            $shop->refresh();
            return $shop;

        });



        GroupHydrateShops::dispatch($organisation->group)->delay($this->hydratorsDelay);
        OrganisationHydrateShops::dispatch($organisation)->delay($this->hydratorsDelay);
        ProspectQuerySeeder::run($shop);
        SeedShopOutboxes::run($shop);
        SeedJobPositions::run($organisation);
        SetIconAsShopLogo::dispatch($shop)->delay($this->hydratorsDelay);

        SeedShopOfferCampaigns::run($shop);

        if ($shop->master_shop_id) {
            MasterShopHydrateShops::dispatch($shop->masterShop)->delay($this->hydratorsDelay);
        }


        return $shop;
    }


    public function rules(): array
    {
        return $this->getStoreShopRules();
    }


    public function afterValidator(Validator $validator): void
    {
        if ($this->get('type') == ShopTypeEnum::FULFILMENT->value && !$this->get('warehouses')) {
            $validator->errors()->add('warehouses', 'warehouse required');
        }
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
    public function asController(Organisation $organisation, ActionRequest $request): Shop
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }


    public string $commandSignature = 'shop:create {organisation : organisation slug} {code} {name} {type}
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
