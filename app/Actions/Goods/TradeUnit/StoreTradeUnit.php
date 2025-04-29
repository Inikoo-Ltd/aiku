<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnits;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;

class StoreTradeUnit extends OrgAction
{
    use WithNoStrictRules;
    use WithGoodsEditAuthorisation;

    public function handle(Group $group, array $modelData): TradeUnit
    {
        /** @var TradeUnit $tradeUnit */
        $tradeUnit = $group->tradeUnits()->create($modelData);
        GroupHydrateTradeUnits::dispatch($group)->delay($this->hydratorsDelay);

        return $tradeUnit;
    }

    public function rules(): array
    {
        $rules = [
            'code'             => [
                'required',
                'max:64',
                $this->strict ? new AlphaDashDot() : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'trade_units',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),

            ],
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string', 'max:1024'],
            'barcode'          => ['sometimes', 'required'],
            'gross_weight'     => ['sometimes', 'required', 'numeric'],
            'net_weight'       => ['sometimes', 'required', 'numeric'],
            'marketing_weight' => ['sometimes', 'required', 'numeric'],
            'dimensions'       => ['sometimes', 'required'],
            'type'             => ['sometimes', 'required', 'string'],
            'data'             => ['sometimes', 'required', 'array'],

        ];

        if (!$this->strict) {
            $rules['gross_weight'] = ['sometimes', 'nullable', 'numeric'];
            $rules['net_weight']   = ['sometimes', 'nullable', 'numeric'];
            $rules['source_slug']  = ['sometimes', 'nullable', 'string'];
            $rules                 = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): TradeUnit
    {
        if (!$audit) {
            TradeUnit::disableAuditing();
        }
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
