<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetToProduceItemPreparing extends OrgAction
{
    /**
     * @param  array{quantity?: float|int|null, batch_code?: string|null, expiry_date?: string|null, expiry_applies_to_label?: bool}  $runTexts
     */
    public function handle(PartnerShoppingListItem $item, bool $preparing, float|int|null $quantityToProduce = null, array $runTexts = []): PartnerShoppingListItem
    {
        if ($item->job_order_id) {
            throw ValidationException::withMessages(['item' => __('Already assigned to a job order')]);
        }
        if ($item->pre_picked_at) {
            throw ValidationException::withMessages(['item' => __('Already covered from stock, nothing to make')]);
        }

        $expiryDate = $preparing ? (Arr::get($runTexts, 'expiry_date') ?: null) : null;

        $item->update([
            'preparing_at'        => $preparing ? now() : null,
            'quantity_to_produce' => $preparing ? ($quantityToProduce ?? ceil((float) $item->quantity)) : null,
            'batch_code'          => $preparing ? (Arr::get($runTexts, 'batch_code') ?: null) : null,
            'expiry_date'         => $expiryDate,
        ]);

        if ($expiryDate && Arr::get($runTexts, 'expiry_applies_to_label')) {
            $this->writeExpiryOntoLabels($item, $expiryDate);
        }

        return $item;
    }

    /**
     * Keeping the date for good means writing it onto the label design itself, so the next run
     * starts from it. Only the published labels of the artefact this line is made from are touched,
     * and only the expiry line inside them.
     */
    private function writeExpiryOntoLabels(PartnerShoppingListItem $item, string $expiryDate): void
    {
        $artefactId = Artefact::where('production_id', $this->production->id)
            ->whereHas('orgStock', fn ($query) => $query->where('stock_id', $item->stock_id))
            ->value('id');

        if (!$artefactId) {
            return;
        }

        $printed = Carbon::parse($expiryDate)->format('d/m/Y');

        ArtefactLabel::where('artefact_id', $artefactId)
            ->where('state', ArtefactLabelStateEnum::PUBLISHED)
            ->get()
            ->each(function (ArtefactLabel $label) use ($printed) {
                $layout  = $label->layout;
                $changed = false;

                foreach (Arr::get($layout, 'fields', []) ?? [] as $index => $field) {
                    if (Arr::get($field, 'source') === 'expiry_date') {
                        $layout['fields'][$index]['text'] = $printed;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $label->update(['layout' => $layout]);
                }
            });
    }

    /**
     * @param  array<int, array{id: int, quantity?: float|int|null, batch_code?: string|null, expiry_date?: string|null, expiry_applies_to_label?: bool}>  $lines
     * @return array<int, PartnerShoppingListItem>
     */
    public function handleMany(array $lines, bool $preparing): array
    {
        $items = PartnerShoppingListItem::whereIn('id', collect($lines)->pluck('id'))->get()->keyBy('id');

        return collect($lines)
            ->filter(fn ($line) => $items->has($line['id']))
            ->map(fn ($line) => $this->handle($items->get($line['id']), $preparing, $line['quantity'] ?? null, $line))
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
            'lines.*.batch_code'              => ['sometimes', 'nullable', 'string', 'max:64'],
            'lines.*.expiry_date'             => ['sometimes', 'nullable', 'date'],
            'lines.*.expiry_applies_to_label' => ['sometimes', 'boolean'],
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
