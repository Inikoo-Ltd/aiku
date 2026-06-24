<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Http\Resources\Helpers\BarcodeResource;
use App\Models\Helpers\Barcode;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateBarcode extends GrpAction
{
    use WithActionUpdate;

    private Barcode $barcode;

    public function handle(Barcode $barcode, array $modelData): Barcode
    {
        return $this->update($barcode, $modelData, ['data']);
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
            'number' => ['sometimes', 'required', 'numeric'],
            'note'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'data'   => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'required', Rule::enum(BarcodeStatusEnum::class)],

        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function asController(Barcode $barcode, ActionRequest $request): Barcode
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($barcode, $request->validated());
    }

    public function action(Barcode $barcode, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Barcode
    {
        $this->strict = $strict;
        if (!$audit) {
            Barcode::disableAuditing();
        }
        $this->asAction       = true;
        $this->barcode        = $barcode;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($barcode->group, $modelData);

        return $this->handle($barcode, $this->validatedData);
    }

    public function jsonResponse(Barcode $barcode): BarcodeResource
    {
        return new BarcodeResource($barcode);
    }
}
