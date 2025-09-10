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


        $imageColumns = [
            'image_id',
            'front_image_id',
            '34_image_id',
            'right_image_id',
            'back_image_id',
            'bottom_image_id',
            'size_comparison_image_id',
            'lifestyle_image_id',
            'top_image_id'
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($tradeUnit->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $tradeUnit->update($updateData);
        }

        return $tradeUnit;
    }

    public function asController(TradeUnit $tradeUnit, Media $media, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $media);
    }
}
