<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnitFamilies;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\Group;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnitFamily extends GrpAction
{
    use WithActionUpdate;

    public function handle(TradeUnitFamily $tradeUnitFamily, array $modelData): TradeUnitFamily
    {
        /** @var TradeUnitFamily $tradeUnitFamily */
        $tradeUnitFamily = $this->update($tradeUnitFamily, $modelData);

        return $tradeUnitFamily;
    }

    public function rules(): array
    {
        $rules = [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];

        return $rules;
    }
    public function asController(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): TradeUnitFamily
    {
        $this->initialisation($tradeUnitFamily->group, $request);

        return $this->handle($tradeUnitFamily, $this->validatedData);
    }
}
