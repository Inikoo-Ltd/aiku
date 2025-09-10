<?php

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromTradeUnit extends GrpAction
{
    public function handle(TradeUnit $tradeUnit, Media $media): TradeUnit
    {
        $tradeUnit->images()->detach($media->id);

        return $tradeUnit;
    }

    public function asController(TradeUnit $tradeUnit, Media $media, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $media);
    }
}
