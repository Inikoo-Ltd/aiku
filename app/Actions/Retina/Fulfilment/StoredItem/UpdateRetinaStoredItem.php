<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\StoredItem;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateStoredItems;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateStoredItems;
use App\Actions\Fulfilment\Pallet\Hydrators\PalletHydrateStoredItems;
use App\Actions\Fulfilment\Pallet\Hydrators\PalletHydrateWithStoredItems;
use App\Actions\Fulfilment\StoredItem\Search\StoredItemRecordSearch;
use App\Actions\RetinaAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStoredItems;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateStoredItems;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateRetinaStoredItem extends RetinaAction
{
    use AsAction;
    use WithActionUpdate;

    protected ?FulfilmentCustomer $fulfilmentCustomer;

    protected StoredItem $storedItem;

    public function handle(StoredItem $storedItem, array $modelData): StoredItem
    {
        $storedItem = $this->update($storedItem, $modelData, ['data']);

        if ($storedItem->wasChanged('state')) {
            GroupHydrateStoredItems::dispatch($storedItem->group);
            OrganisationHydrateStoredItems::dispatch($storedItem->organisation);
            FulfilmentHydrateStoredItems::dispatch($storedItem->fulfilment);
            FulfilmentCustomerHydrateStoredItems::dispatch($storedItem->fulfilmentCustomer);

            foreach ($storedItem->pallets as $pallet) {
                PalletHydrateWithStoredItems::run($pallet); // !important this must be ::run
                PalletHydrateStoredItems::dispatch($pallet);
            }
        }

        StoredItemRecordSearch::dispatch($storedItem);

        return $storedItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            // TODO: Raul please do the permission for the web user
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'reference' => [
                'sometimes',
                'required',
                'max:128',
                new AlphaDashDotSpaceSlashParenthesisPlus,
                new IUnique(
                    table: 'stored_items',
                    extraConditions: [
                        [
                            'column' => 'fulfilment_customer_id',
                            'value' => $this->fulfilmentCustomer->id,
                        ],
                        ['column' => 'id', 'value' => $this->storedItem->id, 'operator' => '!='],
                    ]
                ),
            ],
            'name' => ['sometimes', 'nullable',  'max:250', 'string'],
            'state' => ['sometimes', 'required', Rule::enum(StoredItemStateEnum::class)],
        ];
    }

    public function asController(StoredItem $storedItem, ActionRequest $request): StoredItem
    {
        $this->storedItem = $storedItem;
        $this->initialisation($request);

        return $this->handle($storedItem, $this->validatedData);
    }

    public function action(StoredItem $storedItem, array $modelData): StoredItem
    {
        $this->action = true;
        $this->storedItem = $storedItem;
        $this->initialisationFulfilmentActions($storedItem->fulfilmentCustomer, $modelData);

        return $this->handle($storedItem, $this->validatedData);
    }

    public function jsonResponse(StoredItem $storedItem): StoredItemResource
    {
        return new StoredItemResource($storedItem);
    }
}
