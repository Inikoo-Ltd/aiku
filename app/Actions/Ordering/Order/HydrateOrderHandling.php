<?php

namespace App\Actions\Ordering\Order;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateCreating;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateCreating;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateCreating;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateSubmitted;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateSubmitted;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateSubmitted;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateInWarehouse;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateHandling;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandling;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandling;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandlingBlocked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStatePacked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStatePacked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStatePacked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateFinalised;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrdersDispatchedToday;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrdersDispatchedToday;

class HydrateOrderHandling
{
    use AsAction;

    public string $commandSignature = 'hydrate:order-handling {--shop=} {--group=} {--organisation=}';
    public string $commandDescription = 'Manually hydrate all order handling states for a given shop, group, or organisation.';


    public function asCommand(Command $command): void
    {
        $shopSlug = $command->option('shop');
        $groupSlug = $command->option('group');
        $organisationSlug = $command->option('organisation');

        $providedOptions = count(array_filter([$shopSlug, $groupSlug, $organisationSlug]));

        if ($providedOptions !== 1) {
            $command->error('Please provide exactly one of --shop, --group, or --organisation.');
            return;
        }

        $models = [];
        $modelType = '';

        if ($shopSlug) {
            $modelType = 'Shop';
            if ($shopSlug === 'all') {
                $models = Shop::all();
            } else {
                $model = Shop::where('slug', $shopSlug)->first();
                if ($model) {
                    $models[] = $model;
                }
            }
        } elseif ($groupSlug) {
            $modelType = 'Group';
            if ($groupSlug === 'all') {
                $models = Group::all();
            } else {
                $model = Group::where('slug', $groupSlug)->first();
                if ($model) {
                    $models[] = $model;
                }
            }
        } elseif ($organisationSlug) {
            $modelType = 'Organisation';
            if ($organisationSlug === 'all') {
                $models = Organisation::all();
            } else {
                $model = Organisation::where('slug', $organisationSlug)->first();
                if ($model) {
                    $models[] = $model;
                }
            }
        }

        if (empty($models)) {
            $command->error("No {$modelType}(s) found.");
            return;
        }

        foreach ($models as $model) {
            $this->handle($model);
            $command->info("All order handling states have been hydrated for {$modelType} {$model->slug}.");
        }
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
        GroupHydrateOrderStateCreating::dispatch($id);
        GroupHydrateOrderStateSubmitted::dispatch($id);
        GroupHydrateOrderStateInWarehouse::dispatch($id);
        GroupHydrateOrderStateHandling::dispatch($id);
        GroupHydrateOrderStateHandlingBlocked::dispatch($id);
        GroupHydrateOrderStatePacked::dispatch($id);
        GroupHydrateOrderStateFinalised::dispatch($id);
        GroupHydrateOrdersDispatchedToday::dispatch($id);
    }

    private function hydrateForOrganisation(int $id): void
    {
        OrganisationHydrateOrderStateCreating::dispatch($id);
        OrganisationHydrateOrderStateSubmitted::dispatch($id);
        OrganisationHydrateOrderStateInWarehouse::dispatch($id);
        OrganisationHydrateOrderStateHandling::dispatch($id);
        OrganisationHydrateOrderStateHandlingBlocked::dispatch($id);
        OrganisationHydrateOrderStatePacked::dispatch($id);
        OrganisationHydrateOrderStateFinalised::dispatch($id);
        OrganisationHydrateOrdersDispatchedToday::dispatch($id);
    }

    private function hydrateForShop(int $id): void
    {
        ShopHydrateOrderStateCreating::dispatch($id);
        ShopHydrateOrderStateSubmitted::dispatch($id);
        ShopHydrateOrderStateInWarehouse::dispatch($id);
        ShopHydrateOrderStateHandling::dispatch($id);
        ShopHydrateOrderStateHandlingBlocked::dispatch($id);
        ShopHydrateOrderStatePacked::dispatch($id);
        ShopHydrateOrderStateFinalised::dispatch($id);
        ShopHydrateOrdersDispatchedToday::dispatch($id);
    }
}
