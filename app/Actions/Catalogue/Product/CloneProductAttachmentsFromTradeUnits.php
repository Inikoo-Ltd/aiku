<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductAttachmentsFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        if (!$product->is_single_trade_unit) {
            return;
        }

        $attachments   = [];

        $tradeUnit = $product->tradeUnits->first();
        $publicAttachments = $tradeUnit->attachments()
            ->wherePivotIn('scope', [TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS, TradeAttachmentScopeEnum::CPSR, TradeAttachmentScopeEnum::DOC, TradeAttachmentScopeEnum::IFRA, TradeAttachmentScopeEnum::SDS])
            ->get();

        foreach ($publicAttachments as $publicAttachment) {
            $attachments[$publicAttachment->id] = [
                'scope'           => $publicAttachment->pivot->scope,
                'caption'         => $publicAttachment->pivot->caption,
                'group_id'        => $product->group_id,
                'created_at'      => now(),
                'updated_at'      => now(),
                'data'            => '{}'

            ];
        }

        $product->attachments()->sync($attachments);
    }
}
