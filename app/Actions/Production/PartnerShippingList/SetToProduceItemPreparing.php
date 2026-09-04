<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetToProduceItemPreparing extends OrgAction
{
    public function handle(PartnerShoppingListItem $item, bool $preparing, float|int|null $quantityToProduce = null): PartnerShoppingListItem
    {
        if ($item->job_order_id) {
            throw ValidationException::withMessages(['item' => __('Already assigned to a job order')]);
        }

        $item->update([
            'preparing_at'        => $preparing ? now() : null,
            'quantity_to_produce' => $preparing ? ($quantityToProduce ?? ceil((float) $item->quantity)) : null,
        ]);

        return $item;
    }

    public function rules(): array
    {
        return [
            'preparing' => ['required', 'boolean'],
            'quantity'  => ['sometimes', 'nullable', 'numeric', 'min:1'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, PartnerShoppingListItem $item, ActionRequest $request): PartnerShoppingListItem
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($item, $this->validatedData['preparing'], $this->validatedData['quantity'] ?? null);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
