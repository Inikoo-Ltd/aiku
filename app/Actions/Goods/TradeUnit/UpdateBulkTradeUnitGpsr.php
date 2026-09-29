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
use Illuminate\Validation\Validator;
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

    /**
     * The whole section is sent by "Replace", a single field by its own save button, both on this route.
     */
    public function rules(): array
    {
        return [
            'trade_units'                => ['required', 'array', 'min:1'],
            'trade_units.*'              => ['integer', Rule::exists('trade_units', 'id')->where('group_id', $this->group->id)],
            'gpsr_manufacturer'          => ['sometimes', 'nullable', 'string'],
            'gpsr_eu_responsible'        => ['sometimes', 'nullable', 'string'],
            'gpsr_warnings'              => ['sometimes', 'nullable', 'string'],
            'gpsr_manual'                => ['sometimes', 'nullable', 'string'],
            'gpsr_class_category_danger' => ['sometimes', 'nullable', 'string'],
            'pictogram_toxic'            => ['sometimes', 'boolean'],
            'pictogram_corrosive'        => ['sometimes', 'boolean'],
            'pictogram_explosive'        => ['sometimes', 'boolean'],
            'pictogram_flammable'        => ['sometimes', 'boolean'],
            'pictogram_gas'              => ['sometimes', 'boolean'],
            'pictogram_environment'      => ['sometimes', 'boolean'],
            'pictogram_health'           => ['sometimes', 'boolean'],
            'pictogram_oxidising'        => ['sometimes', 'boolean'],
            'pictogram_danger'           => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $gpsrFields = array_filter(array_keys($this->rules()), fn (string $field) => $field !== 'trade_units' && !str_contains($field, '.'));

        if (!Arr::hasAny($validator->getData(), $gpsrFields)) {
            $validator->errors()->add('trade_units', __('Choose at least one field to update.'));
        }
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
