<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 17:35:41 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateCreating;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateFinalised;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandling;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandlingBlocked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateInWarehouse;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStatePacked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStatePacking;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStatePicked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateSubmitted;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateCreating;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateHandling;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStatePacked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStatePacking;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStatePicked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateSubmitted;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateCreating;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandling;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStatePacked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStatePacking;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStatePicked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateSubmitted;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrdersDispatchedToday;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateOrderHandling
{
    use AsAction;

    public string $commandSignature = 'hydrate:order-handling {--shop=} {--group=} {--organisation=}';
    public string $commandDescription = 'Hydrate all order handling states for groups, organisations, and shops.';

    public function asCommand(Command $command): void
    {
        $shopSlug = $command->option('shop');
        $groupSlug = $command->option('group');
        $organisationSlug = $command->option('organisation');

        $runAll = !$shopSlug && !$groupSlug && !$organisationSlug;

        if ($runAll) {
            $this->runForAll($command);
            return;
        }

        $providedOptions = count(array_filter([$shopSlug, $groupSlug, $organisationSlug]));

        if ($providedOptions !== 1) {
            $command->error('Please provide exactly one of --shop, --group, or --organisation.');
            return;
        }

        if ($shopSlug) {
            $models = $shopSlug === 'all' ? Shop::all() : Shop::where('slug', $shopSlug)->get();
            $this->runForModels($command, $models, 'Shop');
        } elseif ($groupSlug) {
            $models = $groupSlug === 'all' ? Group::all() : Group::where('slug', $groupSlug)->get();
            $this->runForModels($command, $models, 'Group');
        } elseif ($organisationSlug) {
            $models = $organisationSlug === 'all' ? Organisation::all() : Organisation::where('slug', $organisationSlug)->get();
            $this->runForModels($command, $models, 'Organisation');
        }
    }

    private function runForAll(Command $command): void
    {
        $command->info('Hydrating all Groups...');
        $this->runForModels($command, Group::all(), 'Group');

        $command->info('Hydrating all Organisations (type: shop)...');
        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)->get();
        $this->runForModels($command, $organisations, 'Organisation');

        $command->info('Hydrating all Shops (state: open)...');
        $shops = Shop::where('state', ShopStateEnum::OPEN)->get();
        $this->runForModels($command, $shops, 'Shop');
    }

    private function runForModels(Command $command, $models, string $label): void
    {
        if ($models->isEmpty()) {
            $command->warn("No {$label}s found.");
            return;
        }

        $bar = $command->getOutput()->createProgressBar($models->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($models as $model) {
            $this->handle($model);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
    }

    public function handle(Group|Organisation|Shop $model): void
    {
        $id = $model->id;

        if ($model instanceof Group) {
            $this->hydrateForGroup($id);
        } elseif ($model instanceof Organisation) {
            $this->hydrateForOrganisation($id);
        } elseif ($model instanceof Shop) {
            $this->hydrateForShop($id);
        }
    }

    private function hydrateForGroup(int $id): void
    {
        GroupHydrateOrderStateCreating::run($id);
        GroupHydrateOrderStateSubmitted::run($id);
        GroupHydrateOrderStateInWarehouse::run($id);
        GroupHydrateOrderStateHandling::run($id);
        GroupHydrateOrderStateHandlingBlocked::run($id);
        GroupHydrateOrderStatePicked::run($id);
        GroupHydrateOrderStatePacking::run($id);
        GroupHydrateOrderStatePacked::run($id);
        GroupHydrateOrderStateFinalised::run($id);
        GroupHydrateOrdersDispatchedToday::run($id);
    }

    private function hydrateForOrganisation(int $id): void
    {
        OrganisationHydrateOrderStateCreating::run($id);
        OrganisationHydrateOrderStateSubmitted::run($id);
        OrganisationHydrateOrderStateInWarehouse::run($id);
        OrganisationHydrateOrderStateHandling::run($id);
        OrganisationHydrateOrderStateHandlingBlocked::run($id);
        OrganisationHydrateOrderStatePicked::run($id);
        OrganisationHydrateOrderStatePacking::run($id);
        OrganisationHydrateOrderStatePacked::run($id);
        OrganisationHydrateOrderStateFinalised::run($id);
        OrganisationHydrateOrdersDispatchedToday::run($id);
    }

    private function hydrateForShop(int $id): void
    {
        ShopHydrateOrderStateCreating::run($id);
        ShopHydrateOrderStateSubmitted::run($id);
        ShopHydrateOrderStateInWarehouse::run($id);
        ShopHydrateOrderStateHandling::run($id);
        ShopHydrateOrderStateHandlingBlocked::run($id);
        ShopHydrateOrderStatePicked::run($id);
        ShopHydrateOrderStatePacking::run($id);
        ShopHydrateOrderStatePacked::run($id);
        ShopHydrateOrderStateFinalised::run($id);
        ShopHydrateOrdersDispatchedToday::run($id);
    }
}
