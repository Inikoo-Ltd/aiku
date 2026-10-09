<?php

/*
 * Author Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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

class UpdateBulkTradeUnitWeightDimensions extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(Group $group, array $modelData): void
    {
        $weightDimensionsData = array_filter(Arr::except($modelData, 'trade_units'), fn ($value) => !is_null($value));

        if (!$this->hasDimensions(Arr::get($weightDimensionsData, 'marketing_dimensions'))) {
            unset($weightDimensionsData['marketing_dimensions']);
        }

        $tradeUnits = TradeUnit::where('group_id', $group->id)
            ->whereIn('id', Arr::get($modelData, 'trade_units'))
            ->get();

        foreach ($tradeUnits as $tradeUnit) {
            UpdateTradeUnit::make()->action($tradeUnit, $weightDimensionsData);
        }
    }

    /**
     * A field sent empty is left as each trade unit has it: weights cannot be cleared, and dimensions
     * with no size (only the shape or units picked) would wipe every unit's real dimensions with zeros.
     */
    public function rules(): array
    {
        return [
            'trade_units'          => ['required', 'array', 'min:1'],
            'trade_units.*'        => ['integer', Rule::exists('trade_units', 'id')->where('group_id', $this->group->id)],
            'gross_weight'         => ['sometimes', 'nullable', 'integer', 'min:0'],
            'marketing_weight'     => ['sometimes', 'nullable', 'integer', 'min:0'],
            'marketing_dimensions'       => ['sometimes', 'nullable', 'array'],
            'marketing_dimensions.type'  => ['required_with:marketing_dimensions', 'string', Rule::in(['rectangular', 'sheet', 'cylinder', 'sphere', 'string'])],
            'marketing_dimensions.units' => ['required_with:marketing_dimensions', 'string', Rule::in(['mm', 'cm', 'm', 'inch'])],
            'marketing_dimensions.h'     => ['nullable', 'numeric', 'min:0'],
            'marketing_dimensions.l'     => ['nullable', 'numeric', 'min:0'],
            'marketing_dimensions.w'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $data = $validator->getData();

        if (is_null(Arr::get($data, 'gross_weight')) && is_null(Arr::get($data, 'marketing_weight')) && !$this->hasDimensions(Arr::get($data, 'marketing_dimensions'))) {
            $validator->errors()->add('trade_units', __('Choose at least one field to update.'));
        }
    }

    private function hasDimensions(mixed $dimensions): bool
    {
        return is_array($dimensions) && collect(Arr::only($dimensions, ['h', 'l', 'w']))->contains(fn ($size) => is_numeric($size) && $size > 0);
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
