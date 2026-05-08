<?php

/*
 * Author: Rifqi Taufiqurrohman <rifqitaufiqurrohman1@gmail.com>
 * Created: Thu, 07 May 2026 Asia/Jakarta
 * Copyright (c) 2026, Inikoo
*/

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnitImageAlt extends GrpAction
{
    public function handle(TradeUnit $tradeUnit, Media $media, array $modelData): TradeUnit
    {
        $tradeUnit->images()->updateExistingPivot($media->id, [
            'caption' => $modelData['alt'] ?? null,
        ]);
    

        return $tradeUnit;
    }

    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function asController(TradeUnit $tradeUnit, Media $media, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $media, $this->validatedData);
    }
}
