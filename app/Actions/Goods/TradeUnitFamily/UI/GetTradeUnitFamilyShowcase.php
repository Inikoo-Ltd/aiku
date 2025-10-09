<?php

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Traits\HasBucketAttachment;
use App\Http\Resources\Goods\TradeUnitFamilyResource;
use App\Models\Goods\TradeUnitFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitFamilyShowcase
{
    use AsObject;
    use HasBucketAttachment;

    public function handle(TradeUnitFamily $tradeUnitFamily): array
    {
        return [
           'tradeUnitFamily' => TradeUnitFamilyResource::make($tradeUnitFamily)->resolve(),
           'attachment_box'=>  $this->getAttachmentData($tradeUnitFamily),
        ];
    }


}
