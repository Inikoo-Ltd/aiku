<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateJobPositions;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuScopedSections;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuSections;
use App\Actions\SysAdmin\Group\Seeders\SeedEmailTemplates;
use App\Actions\SysAdmin\Group\Seeders\SeedGroupPaymentServiceProviders;
use App\Actions\SysAdmin\Group\Seeders\SeedGroupPermissions;
use App\Actions\SysAdmin\Group\Seeders\SeedJobPositionCategories;
use App\Actions\SysAdmin\Group\Seeders\SeedJobPositionsScopeGroup;
use App\Actions\SysAdmin\Group\Seeders\SeedPlatforms;
use App\Actions\SysAdmin\Group\Seeders\SeedPostRooms;
use App\Actions\SysAdmin\Group\Seeders\SeedSalesChannels;
use App\Actions\SysAdmin\Group\Seeders\SeedStockImages;
use App\Actions\SysAdmin\Group\Seeders\SeedWebBlockTypes;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreGroup
{
    use AsAction;
    use WithAttributes;

    public function handle(array $modelData): Group
    {
        data_set($modelData, 'ulid', Str::ulid());

        /** @var Group $group */
        $group = Group::create($modelData);

        app()->instance('group', $group);

        $group->stats()->create();
        $group->supplyChainStats()->create();
        $group->procurementStats()->create();
        $group->sysadminStats()->create();
        $group->humanResourcesStats()->create();
        $group->inventoryStats()->create();
        $group->goodsStats()->create();
        $group->crmStats()->create();
        $group->accountingStats()->create();
        $group->catalogueStats()->create();
        $group->fulfilmentStats()->create();
        $group->orderingStats()->create();
        $group->salesIntervals()->create();
        $group->orderHandlingStats()->create();
        $group->mailshotsIntervals()->create();
        $group->manufactureStats()->create();
        $group->webStats()->create();
        $group->dropshippingStats()->create();
        $group->commsStats()->create();
        $group->discountsStats()->create();
        $group->orderingIntervals()->create();
        $group->sysadminIntervals()->create();

        $group->outboxNewsletterIntervals()->create();
        $group->outboxMarketingIntervals()->create();
        $group->outboxMarketingNotificationIntervals()->create();
        $group->outboxCustomerNotificationIntervals()->create();
        $group->outboxColdEmailsIntervals()->create();
        $group->outboxUserNotificationIntervals()->create();
        $group->outboxPushIntervals()->create();
        $group->outboxTestIntervals()->create();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $group->timeSeries()->create(['frequency' => $frequency]);
        }

        SeedGroupPermissions::run($group);
        SeedGroupPaymentServiceProviders::run($group);
        SeedJobPositionCategories::run($group);
        SeedJobPositionsScopeGroup::run($group);
        SeedStockImages::run($group);
        SeedWebBlockTypes::run($group);
        SeedPlatforms::run($group);
        SeedEmailTemplates::run($group);
        SeedSalesChannels::run($group);
        SeedAikuSections::run($group);
        SeedPostRooms::run($group);
        SeedAikuScopedSections::make()->seedGroupAikuScopedSection($group);

        SetGroupLogo::run($group);

        GroupHydrateJobPositions::run($group);

        return $group;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'required', 'unique:groups', 'between:2,6'],
            'name' => ['required', 'required', 'max:64'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'language_id' => ['required', 'exists:languages,id'],
            'timezone_id' => ['required', 'exists:timezones,id'],
            'subdomain' => ['sometimes', 'nullable', 'unique:groups', 'between:2,64'],
            'limits' => ['sometimes', 'array'],
        ];
    }

    public function action($modelData): Group
    {
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }

    public string $commandSignature = 'group:create {code} {name} {country_code} {currency_code} {--s|subdomain=} {--l|language_code=} {--tz|timezone= : Timezone} {--O|organisations=2} {--S|shops=4} {--W|warehouses=2} {--M|manufactures=1} {--A|agents=3}';

    public function asCommand(Command $command): int
    {
        if ($command->option('language_code')) {
            try {
                /** @var Language $language */
                $language = Language::where('code', $command->option('language_code'))->firstOrFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }
        } else {
            $language = Language::where('code', 'en')->firstOrFail();
        }

        try {
            /** @var Currency $currency */
            $currency = Currency::where('code', $command->argument('currency_code'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        try {
            /** @var Country $country */
            $country = Country::where('code', $command->argument('country_code'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
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

        $this->setRawAttributes([
            'code' => $command->argument('code'),
            'name' => $command->argument('name'),
            'country_id' => $country->id,
            'currency_id' => $currency->id,
            'language_id' => $language->id,
            'timezone_id' => $timezone->id,
            'subdomain' => $command->option('subdomain') ?? null,
            'limits' => [
                'organisations' => $command->option('organisations'),
                'shops' => $command->option('shops'),
                'warehouses' => $command->option('warehouses'),
                'manufactures' => $command->option('manufactures'),
                'agents' => $command->option('agents'),
            ],
        ]);

        try {
            $validatedData = $this->validateAttributes();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($validatedData);

        $command->info('Done!');

        return 0;
    }
}
