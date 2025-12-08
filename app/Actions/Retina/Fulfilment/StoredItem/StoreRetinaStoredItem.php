<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\StoredItem;

use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Rules\AlphaDashDotSpaceSlashParenthesisPlus;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaStoredItem extends RetinaAction
{
    public function handle(FulfilmentCustomer $parent, array $modelData): StoredItem
    {
        return StoreStoredItem::run($parent, $modelData);
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'max:128',  new AlphaDashDotSpaceSlashParenthesisPlus,
                new IUnique(
                    table: 'stored_items',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->fulfilmentCustomer->id],
                    ]
                ),
            ],
        ];
    }

    public function asController(ActionRequest $request): StoredItem
    {

        $this->initialisation($request);

        return $this->handle($this->fulfilmentCustomer, $this->validatedData);
    }

    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): StoredItem
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($fulfilmentCustomer, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function htmlResponse(StoredItem $storedItem, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('retina.fulfilment.itemised_storage.stored_items.show', $storedItem->slug);
    }
}
