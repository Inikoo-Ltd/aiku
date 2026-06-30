<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode;

use App\Actions\GrpAction;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Group;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreBarcode extends GrpAction
{
    public function handle(Group $group, $modelData): Barcode
    {
        /** @var Barcode $barcode */
        $barcode = $group->barcodes()->create($modelData);

        return $barcode;
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
        ];
        if (!$this->strict) {
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Barcode
    {
        if (!$audit) {
            Barcode::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
