<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Goods\TradeUnitFamily\Hydrators\TradeUnitFamilyHydrateTradeUnits;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Support\Arr;

class AttachTradeUnitsToTradeUnitFamily extends GrpAction
{
    use WithGoodsEditAuthorisation;

    public function handle(TradeUnitFamily $tradeUnitFamily, array $modelData): void
    {
        TradeUnit::whereIn('id', Arr::get($modelData, 'trade_units'))
                ->update(['trade_unit_family_id' => $tradeUnitFamily->id]);

        $tradeUnitFamily->refresh();

        TradeUnitFamilyHydrateTradeUnits::dispatch($tradeUnitFamily);
    }

    public function rules(): array
    {
        $rules = [
            'trade_units' => ['sometimes'],
        ];

        return $rules;
    }

    public function asController(TradeUnitFamily $tradeUnitFamily, array $modelData): void
    {
        $this->initialisation($tradeUnitFamily->group, $modelData);

        $this->handle($tradeUnitFamily, $this->validatedData);
    }
}
