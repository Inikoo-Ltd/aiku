<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-16h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\TradeUnit;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateBulkTradeUnitGpsr extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(Group $group, array $modelData): void
    {
        $gpsrData = Arr::except($modelData, 'trade_units');

        $tradeUnits = TradeUnit::where('group_id', $group->id)
            ->whereIn('id', Arr::get($modelData, 'trade_units'))
            ->get();

        foreach ($tradeUnits as $tradeUnit) {
            UpdateTradeUnit::make()->action($tradeUnit, $gpsrData);
        }
    }

    public function rules(): array
    {
        return [
            'trade_units'                => ['required', 'array', 'min:1'],
            'trade_units.*'              => ['integer', Rule::exists('trade_units', 'id')->where('group_id', $this->group->id)],
            'gpsr_manufacturer'          => ['present', 'nullable', 'string'],
            'gpsr_eu_responsible'        => ['present', 'nullable', 'string'],
            'gpsr_warnings'              => ['present', 'nullable', 'string'],
            'gpsr_manual'                => ['present', 'nullable', 'string'],
            'gpsr_class_category_danger' => ['present', 'nullable', 'string'],
            'pictogram_toxic'            => ['required', 'boolean'],
            'pictogram_corrosive'        => ['required', 'boolean'],
            'pictogram_explosive'        => ['required', 'boolean'],
            'pictogram_flammable'        => ['required', 'boolean'],
            'pictogram_gas'              => ['required', 'boolean'],
            'pictogram_environment'      => ['required', 'boolean'],
            'pictogram_health'           => ['required', 'boolean'],
            'pictogram_oxidising'        => ['required', 'boolean'],
            'pictogram_danger'           => ['required', 'boolean'],
        ];
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
