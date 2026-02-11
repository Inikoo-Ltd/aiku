<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 20:26:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Actions\Traits\WithImageColumns;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromTradeUnit extends GrpAction
{
    use WithImageColumns;

    public function handle(TradeUnit $tradeUnit, Media $media, bool $updateDependants = false): TradeUnit
    {
        $tradeUnit->images()->detach($media->id);
        $tradeUnit->refresh();

        $updateData = [];

        foreach ($this->imageColumns() as $column) {
            if ($tradeUnit->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $tradeUnit->update($updateData);
        }

        if ($updateDependants) {
            UpdateTradeUnitImages::make()->updateDependencies($tradeUnit);
        }
        return $tradeUnit;
    }

    public function asController(TradeUnit $tradeUnit, Media $media, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $media, true);
    }
}
