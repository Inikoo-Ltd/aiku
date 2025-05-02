<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider;
use App\Actions\GrpAction;
use App\Actions\Helpers\Colour\GetRandomColour;
use App\Actions\Helpers\Currency\SetCurrencyHistoricFields;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrganisations;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuScopedSections;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationOutboxes;
use App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationPermissions;
use App\Actions\SysAdmin\Organisation\Seeders\SeedOrgPostRooms;
use App\Actions\SysAdmin\User\UserAddRoles;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreOrganisation extends GrpAction
{
    use WithModelAddressActions;

    /**
     * @throws \Throwable
     */
    public function handle(Group $group, array $modelData): Organisation
    {
        /** @var Address $addressData */
        $addressData = Arr::pull($modelData, 'address');


        data_set($modelData, 'ulid', Str::ulid());
        data_set($modelData, 'settings.ui.name', Arr::get($modelData, 'name'));
        data_set($modelData, 'colour', GetRandomColour::run());


        return DB::transaction(function () use ($group, $modelData, $addressData) {
            /** @var Organisation $organisation */
            $organisation = $group->organisations()->create($modelData);
            $organisation->refresh();


            SetOrganisationLogo::run($organisation);
            SeedOrganisationPermissions::run($organisation);
            SeedJobPositions::run($organisation);
            if ($organisation->type == OrganisationTypeEnum::SHOP || $organisation->type == OrganisationTypeEnum::DIGITAL_AGENCY) {
                SeedOrgPostRooms::run($organisation);
                SeedOrganisationOutboxes::run($organisation);
            }

            StoreOrganisationAddress::make()->action(
                $organisation,
                [
                    'address' => $addressData
                ]
            );


            $superAdmins = $group->users()->with('roles')->get()->filter(
                fn ($user) => $user->roles->where('name', 'group-admin')->toArray()
            );

            foreach ($superAdmins as $superAdmin) {
                UserAddRoles::run($superAdmin, [
                    Role::where(
                        'name',
                        RolesEnum::getRoleName(
                            'org-admin',
                            $organisation
                        )
                    )->where('scope_id', $organisation->id)->first()
                ]);
            }


            $organisation->refresh();

            SetCurrencyHistoricFields::run($organisation->currency, $organisation->created_at);

            $organisation->stats()->create();
            $organisation->humanResourcesStats()->create();
            $organisation->procurementStats()->create();
            $organisation->inventoryStats()->create();
            $organisation->accountingStats()->create();
            $organisation->dropshippingStats()->create();
            $organisation->webStats()->create();
            $organisation->commsStats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $organisation->timeSeries()->create(['frequency' => $frequency]);
            }

            if ($organisation->type == OrganisationTypeEnum::SHOP || $organisation->type == OrganisationTypeEnum::DIGITAL_AGENCY) {
                $organisation->outboxNewsletterIntervals()->create();
                $organisation->outboxMarketingIntervals()->create();
                $organisation->outboxMarketingNotificationIntervals()->create();
                $organisation->outboxCustomerNotificationIntervals()->create();
                $organisation->outboxColdEmailsIntervals()->create();
                $organisation->outboxPushIntervals()->create();
            }

            $organisation->outboxUserNotificationIntervals()->create();
            $organisation->outboxTestIntervals()->create();


            if ($organisation->type == OrganisationTypeEnum::SHOP || $organisation->type == OrganisationTypeEnum::DIGITAL_AGENCY) {
                $organisation->crmStats()->create();
                $organisation->catalogueStats()->create();
                $organisation->discountsStats()->create();
                $organisation->mailshotsIntervals()->create();

                $paymentServiceProvider = PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::ACCOUNT)->first();

                StoreOrgPaymentServiceProvider::make()->action(
                    $paymentServiceProvider,
                    organisation: $organisation,
                    modelData: [
                        'code' => 'account-'.$organisation->code,
                    ]
                );

                $otherOrganisations = Organisation::where('type', OrganisationTypeEnum::SHOP)->where('group_id', $group->id)->where('id', '!=', $organisation->id)->get();
                foreach ($otherOrganisations as $otherOrganisation) {
                    StoreOrgPartner::make()->action($otherOrganisation, $organisation);
                    StoreOrgPartner::make()->action($organisation, $otherOrganisation);
                }
            }

            if ($organisation->type == OrganisationTypeEnum::SHOP) {
                $organisation->orderingStats()->create();
                $organisation->salesIntervals()->create();
                $organisation->orderingIntervals()->create();
                $organisation->orderHandlingStats()->create();
                $organisation->fulfilmentStats()->create();
                $organisation->manufactureStats()->create();
            }

            $organisation->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::PURCHASE_ORDER,
                    'organisation_id' => $organisation->id,
                    'format'          => 'PO'.$organisation->slug.'-%04d'
                ]
            );
            $organisation->serialReferences()->create(
                [
                    'model'           => SerialReferenceModelEnum::STOCK_DELIVERY,
                    'organisation_id' => $organisation->id,
                    'format'          => 'SD'.$organisation->slug.'-%04d'
                ]
            );

            SeedAikuScopedSections::make()->seedOrganisationAikuScopedSection($organisation);
            GroupHydrateOrganisations::dispatch($group);

            return $organisation;
        });
    }


    public function rules(): array
    {
        return [
            'code'         => [
                'required',
                new IUnique(
                    table: 'organisations',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],

                    ]
                ),
                'max:16',
                'alpha_dash:ascii'
            ],
            'name'         => ['required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'email'        => [
                'required',
                'nullable',
                'email',
                new IUnique(
                    table: 'organisations',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],

                    ]
                ),
            ],
            'phone'        => ['sometimes', 'nullable', new Phone()],
            'currency_id'  => ['required', 'exists:currencies,id'],
            'country_id'   => ['required', 'exists:countries,id'],
            'language_id'  => ['required', 'exists:languages,id'],
            'timezone_id'  => ['required', 'exists:timezones,id'],
            'source'       => ['sometimes', 'array'],
            'type'         => ['required', Rule::enum(OrganisationTypeEnum::class)],
            'address'      => ['required', new ValidAddress()],
            'created_at'   => ['sometimes', 'date'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Group $group, $modelData): Organisation
    {
        $this->initialisation($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Group $group, ActionRequest $request): Organisation
    {
        $this->initialisation($group, $request);

        return $this->handle($group, $this->validatedData);
    }

    public function htmlResponse(Organisation $organisation): RedirectResponse
    {
        return Redirect::route('grp.org.dashboard.show', $organisation->slug);
    }

    public function getCommandSignature(): string
    {
        return 'org:create {group} {type} {code} {email} {name} {country_code} {currency_code} {--l|language_code= : Language code} {--t|timezone= : Timezone} {--a|address= : Address}
        {--s|source= : source for migration from other system}';
    }

    public function asCommand(Command $command): int
    {
        try {
            /** @var Group $group */
            $group = Group::where('slug', $command->argument('group'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        setPermissionsTeamId($group->id);
        try {
            /** @var Country $country */
            $country = Country::where('code', $command->argument('country_code'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        try {
            /** @var Currency $currency */
            $currency = Currency::where('code', $command->argument('currency_code'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        if ($command->option('language_code')) {
            try {
                /** @var Language $language */
                $language = Language::where('code', $command->option('language_code'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $language = Language::where('code', 'en-gb')->firstOrFail();
        }

        if ($command->option('timezone')) {
            try {
                /** @var Timezone $timezone */
                $timezone = Timezone::where('name', $command->option('timezone'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $timezone = Timezone::where('name', 'UTC')->firstOrFail();
        }


        $source = [];
        if ($command->option('source')) {
            if (Str::isJson($command->option('source'))) {
                $source = json_decode($command->option('source'), true);
            } else {
                $command->error('Source data is not a valid json');

                return 1;
            }
        }


        if ($command->option('address')) {
            if (Str::isJson($command->option('address'))) {
                $address = json_decode($command->option('address'), true);
            } else {
                $command->error('Address data is not a valid json');

                return 1;
            }
        } else {
            $command->error('Address is required');

            return 1;
        }

        $data = [
            'type'        => $command->argument('type'),
            'code'        => $command->argument('code'),
            'name'        => $command->argument('name'),
            'email'       => $command->argument('email'),
            'country_id'  => $country->id,
            'currency_id' => $currency->id,
            'language_id' => $language->id,
            'timezone_id' => $timezone->id,
            'source'      => $source,
        ];

        if ($address) {
            $data['address'] = $address;
        }

        try {
            $organisation = $this->action($group, $data);
            $command->info("Organisation $organisation->slug created successfully 🎉");
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }


}
