<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateStoredItems;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStoredItems;
use App\Actions\Fulfilment\Pallet\Hydrators\PalletHydrateStoredItems;
use App\Actions\Fulfilment\Pallet\Hydrators\PalletHydrateWithStoredItems;
use App\Actions\Fulfilment\StoredItem\Search\StoredItemRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStoredItems;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateStoredItems;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreStoredItem extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public FulfilmentCustomer $fulfilmentCustomer;

    public function handle(FulfilmentCustomer|Pallet $parent, array $modelData): StoredItem
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'fulfilment_id', $parent->fulfilment_id);

        if ($parent instanceof Pallet) {
            data_set($modelData, 'fulfilment_customer_id', $parent->fulfilment_customer_id);
        }

        /** @var StoredItem $storedItem */
        $storedItem = $parent->storedItems()->create($modelData);

        if ($parent instanceof Pallet) {
            PalletHydrateWithStoredItems::run($parent); // !important this must be ::run
            PalletHydrateStoredItems::dispatch($parent);
        }

        GroupHydrateStoredItems::dispatch($parent->group);
        OrganisationHydrateStoredItems::dispatch($parent->organisation);
        FulfilmentHydrateStoredItems::dispatch($parent->fulfilment);
        FulfilmentCustomerHydrateStoredItems::dispatch($storedItem->fulfilmentCustomer);



        StoredItemRecordSearch::dispatch($storedItem);

        return $storedItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user() instanceof WebUser) {
            return true;
        }

        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }


    public function rules(): array
    {
        return [
            'reference'    => ['required', 'max:128',  new AlphaDashDotSpaceSlashParenthesisPlus(),
                new IUnique(
                    table: 'stored_items',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->fulfilmentCustomer->id],
                    ]
                )
            ],
            'name'                     => ['sometimes', 'max:250', 'string'],
            'total_quantity'                     => ['sometimes', 'numeric', 'min:1'],
        ];
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): StoredItem
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->fulfilment         = $fulfilmentCustomer->fulfilment;

        $this->initialisation($fulfilmentCustomer->organisation, $request);

        return $this->handle($fulfilmentCustomer, $this->validateAttributes());
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): StoredItem
    {
        $this->asAction           = true;
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->fulfilment         = $fulfilmentCustomer->fulfilment;

        $this->initialisation($fulfilmentCustomer->organisation, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validateAttributes());
    }

    public function fromRetina(ActionRequest $request): StoredItem
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->fulfilment         = $fulfilmentCustomer->fulfilment;

        $this->initialisation($fulfilmentCustomer->organisation, $request);

        return $this->handle($fulfilmentCustomer, $this->validateAttributes());
    }

    public function inPallet(Pallet $pallet, ActionRequest $request): StoredItem
    {
        $this->fulfilmentCustomer = $pallet->fulfilmentCustomer;
        $this->fulfilment         = $pallet->fulfilment;

        $this->initialisation($pallet->organisation, $request);

        return $this->handle($pallet, $this->validateAttributes());
    }

    public function htmlResponse(StoredItem $storedItem, ActionRequest $request): RedirectResponse
    {
        $route = $request->all()['referral_route'] ?? null;
        if ($route) {
            return Redirect::route(
                $route['name'],
                array_merge($route['parameters'], [$storedItem->slug])
            );
        }

        return Redirect::back();
    }
}
