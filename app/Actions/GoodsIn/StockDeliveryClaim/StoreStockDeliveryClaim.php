<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryClaim;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDeliveryClaim;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Photos go on the delivery's attachments, tagged as claim photos and pointing at the claim, so they sit
 * with the invoice and packing list and can be sent to the supplier from there.
 */
class StoreStockDeliveryClaim
{
    use AsAction;

    public function handle(StockDeliveryItem $stockDeliveryItem, User $user, array $modelData): StockDeliveryClaim
    {
        $stockDelivery = $stockDeliveryItem->stockDelivery;

        /** @var StockDeliveryClaim $claim */
        $claim = $stockDelivery->claims()->create([
            'group_id'               => $stockDelivery->group_id,
            'organisation_id'        => $stockDelivery->organisation_id,
            'stock_delivery_item_id' => $stockDeliveryItem->id,
            'org_stock_id'           => $stockDeliveryItem->org_stock_id,
            'state'                  => StockDeliveryClaimStateEnum::OPEN,
            'quantity'               => $modelData['quantity'],
            'amount'                 => $modelData['amount'],
            'currency_id'            => $stockDelivery->currency_id,
            'notes'                  => Arr::get($modelData, 'notes'),
            'created_by_id'          => $user->id,
        ]);

        self::savePhotos($claim, Arr::get($modelData, 'photos', []));

        return $claim;
    }

    /**
     * @param array<int, UploadedFile> $photos
     */
    public static function savePhotos(StockDeliveryClaim $claim, array $photos): void
    {
        foreach ($photos as $photo) {
            SaveModelAttachment::make()->action($claim->stockDelivery, [
                'path'         => $photo->getPathName(),
                'originalName' => $photo->getClientOriginalName(),
                'extension'    => $photo->getClientOriginalExtension(),
                'scope'        => PurchaseOrderAttachmentScopeEnum::CLAIM->value,
                'sub_scope'    => (string) $claim->id,
                'caption'      => __('Claim :code', ['code' => $claim->orgStock?->code]),
            ]);
        }
    }
}
