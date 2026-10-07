<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode;

use App\Actions\OrgAction;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreBarcode extends OrgAction
{
    public function handle(Group $group, $modelData): Barcode
    {
        $tradeUnitId = Arr::pull($modelData, 'trade_unit');

        /** @var Barcode $barcode */
        $barcode = $group->barcodes()->create($modelData);

        if ($tradeUnitId) {
            SyncBarcodeToTradeUnit::make()->action($barcode, TradeUnit::find($tradeUnitId));
        }

        return $barcode;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction) {
            $this->set('status', BarcodeStatusEnum::USED->value);
            $this->set('type', BarcodeTypeEnum::EAN->value);
            $this->set('data', ['external' => true]);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("goods.edit");
    }

    public function rules(): array
    {
        $rules = [
            'number'      => ['required', 'numeric'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:1000'],
            'data'        => ['sometimes', 'nullable', 'array'],
            'status'      => ['required', Rule::enum(BarcodeStatusEnum::class)],
            'type'        => ['required', Rule::enum(BarcodeTypeEnum::class)],
            'assigned_at' => ['sometimes', 'nullable', 'date'],
            'trade_unit'  => ['sometimes', 'nullable', Rule::exists('trade_units', 'id')->whereNull('barcode_id')],
        ];
        if (!$this->asAction) {
            $rules['number'] = [
                'required',
                'digits_between:8,14',
                new IUnique(
                    table: 'barcodes',
                    extraConditions: [
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (!$this->hasValidCheckDigit((string)$value)) {
                        $fail(__('This barcode is not valid, please check the number for typos.'));
                    }
                },
            ];
        }
        if (!$this->strict) {
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    public function hasValidCheckDigit(string $number): bool
    {
        $digits     = array_map('intval', str_split($number));
        $checkDigit = array_pop($digits);
        $sum        = 0;
        foreach (array_reverse($digits) as $position => $digit) {
            $sum += $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === $checkDigit;
    }

    public function asController(ActionRequest $request): Barcode
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $this->validatedData);
    }

    public function htmlResponse(Barcode $barcode): RedirectResponse
    {
        return redirect()->route('grp.trade_units.barcodes.show', $barcode->slug);
    }

    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Barcode
    {
        if (!$audit) {
            Barcode::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
