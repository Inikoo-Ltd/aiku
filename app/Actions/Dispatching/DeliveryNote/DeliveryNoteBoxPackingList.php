<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\PreferredShipping\WithPreferredShipperResolver;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Some destinations (Channel Islands customs, HELP-2914) want a packing list saying what is in each
 * box. The organisation lists those destinations as country and postcode prefix rules, matched the
 * same way as the preferred shipping rules. For now only B2B shops use it. A master switch next to
 * the rules turns it on or off, and a dispatch supervisor can let a single note go without it.
 */
class DeliveryNoteBoxPackingList
{
    use AsObject;
    use WithPreferredShipperResolver;

    public function isRequired(DeliveryNote $deliveryNote): bool
    {
        if ($deliveryNote->shop->type != ShopTypeEnum::B2B
            || !Arr::get($deliveryNote->organisation->settings, 'dispatching.box_packing_list')
            || Arr::get($deliveryNote->data, 'box_packing_list_skipped_by')) {
            return false;
        }

        $countryId = $deliveryNote->deliveryAddress?->country_id;
        if (!$countryId) {
            return false;
        }

        $postalCode = $this->normalisePostcode($deliveryNote->deliveryAddress->postal_code);

        return collect(Arr::get($deliveryNote->organisation->settings, 'dispatching.box_packing_list_destinations', []))
            ->contains(
                fn (array $rule) => (!Arr::get($rule, 'country_id') || $rule['country_id'] == $countryId)
                    && $this->postcodeMatchesRule($postalCode, Arr::get($rule, 'postcode'))
            );
    }

    public function missingBoxesMessage(DeliveryNote $deliveryNote): ?string
    {
        if (!$this->isRequired($deliveryNote)) {
            return null;
        }

        $pickedItems = $this->pickedItems($deliveryNote);

        $unboxedCodes = $pickedItems
            ->reject(fn (DeliveryNoteItem $item) => round(collect($item->boxes ?? [])->sum('quantity'), 3) == round((float)$item->quantity_picked, 3))
            ->map(fn (DeliveryNoteItem $item) => $item->orgStock?->code ?? $item->id);

        if ($unboxedCodes->isNotEmpty()) {
            return __('Put every picked item in a box before setting as packed. Not in a box yet: :items', [
                'items' => $unboxedCodes->implode(', '),
            ]);
        }

        $numberParcels = count($deliveryNote->parcels ?? []);
        $highestBox    = $this->highestBox($pickedItems);
        if ($highestBox > $numberParcels) {
            return __('Items are in box :box but the delivery note has :parcels parcels. Add a parcel for every box.', [
                'box'     => $highestBox,
                'parcels' => $numberParcels,
            ]);
        }

        return null;
    }

    public function numberBoxes(DeliveryNote $deliveryNote): int
    {
        return max(1, count($deliveryNote->parcels ?? []), $this->highestBox($this->pickedItems($deliveryNote)));
    }

    /**
     * @return Collection<int, DeliveryNoteItem>
     */
    private function pickedItems(DeliveryNote $deliveryNote): Collection
    {
        return $deliveryNote->deliveryNoteItems()->with('orgStock')->where('quantity_picked', '>', 0)->get();
    }

    private function highestBox(Collection $items): int
    {
        return (int)$items->flatMap(fn (DeliveryNoteItem $item) => $item->boxes ?? [])->max('box');
    }
}
