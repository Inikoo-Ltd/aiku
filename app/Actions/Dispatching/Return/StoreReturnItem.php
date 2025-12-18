<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Action to store a new ReturnItem within a Return
 */

namespace App\Actions\Dispatching\Return;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\OrderReturn;
use App\Models\Dispatching\ReturnItem;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreReturnItem extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(OrderReturn $return, array $modelData): ReturnItem
    {
        // If delivery_note_item_id is provided, copy data from the delivery note item
        $deliveryNoteItemId = Arr::pull($modelData, 'delivery_note_item_id');
        if ($deliveryNoteItemId) {
            $deliveryNoteItem = DeliveryNoteItem::find($deliveryNoteItemId);
            if ($deliveryNoteItem) {
                data_set($modelData, 'org_stock_id', $deliveryNoteItem->org_stock_id);
                data_set($modelData, 'stock_id', $deliveryNoteItem->stock_id);
                data_set($modelData, 'org_stock_family_id', $deliveryNoteItem->org_stock_family_id);
                data_set($modelData, 'stock_family_id', $deliveryNoteItem->stock_family_id);
                data_set($modelData, 'transaction_id', $deliveryNoteItem->transaction_id);
                data_set($modelData, 'invoice_transaction_id', $deliveryNoteItem->invoice_transaction_id);
                data_set($modelData, 'customer_id', $deliveryNoteItem->customer_id);
                data_set($modelData, 'order_id', $deliveryNoteItem->order_id);
                data_set($modelData, 'invoice_id', $deliveryNoteItem->invoice_id);
                data_set($modelData, 'estimated_weight', $deliveryNoteItem->estimated_required_weight ?? 0);

                // Use dispatched quantity as the expected return quantity if not provided
                if (! Arr::has($modelData, 'quantity_expected')) {
                    data_set($modelData, 'quantity_expected', $deliveryNoteItem->quantity_dispatched ?? 0);
                }
            }
        }

        data_set($modelData, 'return_id', $return->id);
        data_set($modelData, 'group_id', $return->group_id);
        data_set($modelData, 'organisation_id', $return->organisation_id);
        data_set($modelData, 'shop_id', $return->shop_id);
        data_set($modelData, 'state', ReturnItemStateEnum::WAITING_TO_RECEIVE);
        data_set($modelData, 'date', now());

        $returnItem = ReturnItem::create($modelData);

        // Update return stats
        $this->updateReturnStats($return);

        return $returnItem;
    }

    protected function updateReturnStats(OrderReturn $return): void
    {
        $stats = $return->stats;
        $stats?->update([
            'number_items'               => $return->returnItems()->count(),
            'number_items_state_pending' => $return->returnItems()->where('state', ReturnItemStateEnum::WAITING_TO_RECEIVE)->count(),
            'total_quantity_expected'    => $return->returnItems()->sum('quantity_expected'),
        ]);

        $return->updateQuietly([
            'number_items' => $stats->number_items ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'delivery_note_item_id' => ['nullable', 'integer', 'exists:delivery_note_items,id'],
            'org_stock_id'          => ['nullable', 'integer', 'exists:org_stocks,id'],
            'transaction_id'        => ['nullable', 'integer', 'exists:transactions,id'],
            'quantity_expected'     => ['required', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string'],
            'condition'             => ['nullable', 'string'],
        ];
    }

    public function action(OrderReturn $return, array $modelData, int $hydratorsDelay = 0): ReturnItem
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($return->shop, $modelData);

        return $this->handle($return, $this->validatedData);
    }
}
