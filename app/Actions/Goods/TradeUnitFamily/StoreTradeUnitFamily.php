<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnitFamilies;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\Group;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreTradeUnitFamily extends GrpAction
{

    public function handle(Group $group, array $modelData): TradeUnitFamily
    {
        /** @var TradeUnitFamily $tradeUnitFamily */
        $tradeUnitFamily = $group->tradeUnitFamilies()->create($modelData);
        $tradeUnitFamily->stats()->create();
        GroupHydrateTradeUnitFamilies::dispatch($group);

        return $tradeUnitFamily;
    }

    public function htmlResponse(TradeUnitFamily $tradeUnitFamily)
    {
        return Redirect::route('grp.masters.trade-unit-families.show', [
            $tradeUnitFamily->slug
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'code'                  => [
                'required',
                'max:64',
                $this->strict ? new AlphaDashDot() : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'trade_unit_families',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),

            ],
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];

        return $rules;
    }

    public function asController(ActionRequest $request): TradeUnitFamily
    {
        $group = group();
        $this->initialisation($group, $request);

        return $this->handle($group, $this->validatedData);
    }
}
