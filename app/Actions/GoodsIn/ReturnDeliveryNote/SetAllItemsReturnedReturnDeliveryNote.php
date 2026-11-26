<?php

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteController;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteTransition;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\SetReturnDeliveryNoteItemAsReturned;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Http\Resources\Procurement\ReturnDeliveryNoteItemsResource;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\LocationOrgStock;

class SetAllItemsReturnedReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithReturnDeliveryNoteController;
    use WithReturnDeliveryNoteTransition;

    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        $this->validateReturnDeliveryNoteState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RETURNING);

        $locationOrder = ReturnDeliveryNoteItemsResource::defaultLocationOrder($returnDeliveryNote->shop?->type);

        $items = $returnDeliveryNote->returnDeliveryNoteItem()
            ->where('total_expected_qty', '>', 0)
            ->where('is_handled', false)
            ->get();

        foreach ($items as $item) {
            $remaining = (float) $item->total_expected_qty - (float) $item->total_item_damaged - (float) $item->total_item_not_returned - (float) $item->total_item_returned;
            if ($remaining <= 0.00005) {
                continue;
            }

            $locationOrgStockId = LocationOrgStock::where('org_stock_id', $item->org_stock_id)
                ->where('warehouse_id', $returnDeliveryNote->warehouse_id)
                ->orderByRaw($locationOrder)
                ->orderBy('picking_priority')
                ->value('id');

            if ($locationOrgStockId) {
                SetReturnDeliveryNoteItemAsReturned::make()->handle($item, ['location_org_stock_id' => $locationOrgStockId]);
            }
        }

        return $returnDeliveryNote->refresh();
    }
}
