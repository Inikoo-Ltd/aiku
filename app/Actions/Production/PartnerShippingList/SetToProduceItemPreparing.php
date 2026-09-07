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

    /**
     * @param  array<int, array{id: int, quantity?: float|int|null}>  $lines
     * @return array<int, PartnerShoppingListItem>
     */
    public function handleMany(array $lines, bool $preparing): array
    {
        $items = PartnerShoppingListItem::whereIn('id', collect($lines)->pluck('id'))->get()->keyBy('id');

        return collect($lines)
            ->filter(fn ($line) => $items->has($line['id']))
            ->map(fn ($line) => $this->handle($items->get($line['id']), $preparing, $line['quantity'] ?? null))
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'preparing'        => ['required', 'boolean'],
            'lines'            => ['required', 'array', 'min:1'],
            'lines.*.id'       => ['required', 'integer'],
            'lines.*.quantity' => ['sometimes', 'nullable', 'numeric', 'min:1'],
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

    /** @return array<int, PartnerShoppingListItem> */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handleMany($this->validatedData['lines'], $this->validatedData['preparing']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
