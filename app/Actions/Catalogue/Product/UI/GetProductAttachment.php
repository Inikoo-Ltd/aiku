<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitDocuments;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductAttachment
{
    use AsObject;

    public function handle(Product $product): array
    {
        return [
            'documents' => GetTradeUnitDocuments::make()->forProduct($product->attachments()->get(), $product->tradeUnits),
        ];
    }
}
