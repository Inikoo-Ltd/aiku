<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\Dispatching\DeliveryNote\Search\DeliveryNoteRecordSearch;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Support\Facades\Redirect;

class StoreReplacementDeliveryNote extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithFixedAddressActions;
    use WithModelAddressActions;

    private Order $order;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): DeliveryNote
    {
        if (!Arr::has($modelData, 'delivery_address')) {
            $modelData['delivery_address'] = $order->deliveryAddress;
        }

        $deliveryAddress = Arr::pull($modelData, 'delivery_address');

        data_set($modelData, 'date', now());

        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);
        data_set($modelData, 'type', DeliveryNoteTypeEnum::REPLACEMENT);


        $items   = Arr::pull($modelData, 'delivery_note_items');

        $deliveryNote = DB::transaction(function () use ($order, $modelData, $deliveryAddress, $items) {
            /** @var DeliveryNote $replacement */
            $replacement = $order->deliveryNotes()->create($modelData);

            if ($replacement->delivery_locked) {
                $this->createFixedAddress(
                    $replacement,
                    $deliveryAddress,
                    'Ordering',
                    'delivery',
                    'address_id'
                );
                $replacement->updateQuietly(
                    [
                        'delivery_country_id' => $replacement->address->country_id
                    ]
                );
            } else {
                StoreDeliveryNoteAddress::make()->action($replacement, [
                    'address' => $deliveryAddress
                ]);
            }


            foreach ($items as $itemData) {
                $deliveryNoteItems = DeliveryNoteItem::where('id', $itemData['id'])->first();
                if ($deliveryNoteItems && $itemData['quantity'] > 0) {

                    $deliveryNoteItemData = [
                        'org_stock_id'      => $deliveryNoteItems->org_stock_id,
                        'transaction_id'    => $deliveryNoteItems->transaction_id,
                        'quantity_required' => $itemData['quantity']
                    ];

                    StoreDeliveryNoteItem::make()->action($replacement, $deliveryNoteItemData);
                }


            }



            return $replacement;
        });
        $deliveryNote->refresh();

        DeliveryNoteRecordSearch::dispatch($deliveryNote)->delay($this->hydratorsDelay);
        GroupHydrateDeliveryNotes::dispatch($deliveryNote->group)->delay($this->hydratorsDelay);
        OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop)->delay($this->hydratorsDelay);
        CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer)->delay($this->hydratorsDelay);

        OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
            ->delay($this->hydratorsDelay);

        return $deliveryNote;
    }

    public function htmlResponse(DeliveryNote $deliveryNote): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
            'organisation' => $deliveryNote->organisation->slug,
            'warehouse'    => $deliveryNote->warehouse->slug,
            'deliveryNote' => $deliveryNote->slug
        ]);
    }

    public function rules(): array
    {
        return [
            'delivery_note_items' => ['required', 'array'],
            'warehouse_id'        => ['required', 'integer'],
            'reference'           => ['required', 'max:64', 'string']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): DeliveryNote
    {
        if (!$audit) {
            DeliveryNote::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): DeliveryNote
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->has('warehouse_id')) {
            $warehouse = $this->shop->organisation->warehouses()->first();
            $this->set('warehouse_id', $warehouse->id);
        }

        if (!$this->has('reference')) {
            $baseReference = $this->order->reference.'-r';
            $existingRefs  = $this->order->deliveryNotes
                ->where('type', DeliveryNoteTypeEnum::REPLACEMENT)
                ->pluck('reference')
                ->filter(function ($ref) use ($baseReference) {
                    return str_starts_with($ref, $baseReference);
                })
                ->map(function ($ref) use ($baseReference) {
                    return (int)str_replace($baseReference, '', $ref);
                })
                ->filter(function ($num) {
                    return $num > 0;
                });

            $nextIncrement = $existingRefs->max() + 1 ?? 1;

            $this->set('reference', $baseReference.$nextIncrement);
        }
    }
}
