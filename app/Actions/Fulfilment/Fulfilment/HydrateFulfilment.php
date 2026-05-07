<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jan 2024 10:06:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateCustomers;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletDeliveries;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateRecurringBills;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateSpaces;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateStoredItemAudits;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateStoredItems;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateWarehouses;
use App\Actions\HydrateModel;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Support\Collection;

class HydrateFulfilment extends HydrateModel
{
    public string $commandSignature = 'hydrate:fulfilments {organisations?*} {--s|slugs=}';

    public function handle(Fulfilment $fulfilment): void
    {
        FulfilmentHydrateWarehouses::run($fulfilment);
        FulfilmentHydrateCustomers::run($fulfilment);

        FulfilmentHydratePallets::run($fulfilment);
        FulfilmentHydratePalletDeliveries::run($fulfilment);
        FulfilmentHydratePalletReturns::run($fulfilment);
        
        FulfilmentHydrateRecurringBills::run($fulfilment);
        FulfilmentHydrateStoredItems::run($fulfilment);
        FulfilmentHydrateStoredItemAudits::run($fulfilment);
        FulfilmentHydrateSpaces::run($fulfilment);
    }

    protected function getModel(string $slug): Fulfilment
    {
        return Fulfilment::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Fulfilment::all();
    }
}
