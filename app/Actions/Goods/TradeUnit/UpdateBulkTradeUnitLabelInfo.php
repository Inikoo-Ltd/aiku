<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-15h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\TradeUnit;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Models\SysAdmin\Group;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateBulkTradeUnitLabelInfo extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(Group $group, array $modelData): void
    {
        $labelInfoData = Arr::except($modelData, 'trade_units');

        $tradeUnits = TradeUnit::where('group_id', $group->id)
            ->whereIn('id', Arr::get($modelData, 'trade_units'))
            ->get();

        foreach ($tradeUnits as $tradeUnit) {
            UpdateTradeUnit::make()->action($tradeUnit, $labelInfoData);
        }
    }

    public function rules(): array
    {
        $rules = [
            'trade_units'                   => ['required', 'array', 'min:1'],
            'trade_units.*'                 => ['integer', Rule::exists('trade_units', 'id')->where('group_id', $this->group->id)],
            'label_info_approved'           => ['required', 'boolean'],
            'show_net_quantity'             => ['required', 'boolean'],
            'markets'                       => ['present', 'array'],
            'markets.*'                     => ['string', Rule::enum(TradeUnitMarketEnum::class)],
            'languages'                     => ['present', 'array'],
            'languages.*'                   => ['string', Rule::exists('languages', 'code')],
            'best_before'                   => ['present', 'nullable', Rule::enum(TradeUnitBestBeforeEnum::class)],
            'packaging_material_codes'      => ['present', 'array'],
            'packaging_material_codes.*'    => ['string', Rule::enum(TradeUnitPackagingMaterialEnum::class)],
            'packaging_material_codes_show' => ['required', 'boolean'],
        ];

        foreach (TradeUnitLabelPresenceEnum::values() as $labelPresenceField) {
            $rules[$labelPresenceField] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(group(), $request);

        $this->handle($this->group, $this->validatedData);
    }

    public function action(Group $group, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        $this->handle($group, $this->validatedData);
    }
}
