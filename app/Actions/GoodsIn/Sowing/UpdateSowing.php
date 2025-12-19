<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\Dispatching\Sowing;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\Sowing;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateSowing extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private Sowing $sowing;

    public function handle(Sowing $sowing, array $modelData): Sowing|bool
    {
        if (isset($modelData['quantity']) && $modelData['quantity'] == 0) {
            return DeleteSowing::make()->action($sowing);
        }

        $sowing = $this->update($sowing, $modelData);

        return $sowing;
    }

    public function rules(): array
    {
        return [
            'type'     => ['sometimes', Rule::enum(SowingTypeEnum::class)],
            'quantity' => ['sometimes', 'numeric'],
        ];
    }

    public function asController(Sowing $sowing, ActionRequest $request)
    {
        $this->sowing = $sowing;
        $this->initialisationFromShop($sowing->shop, $request);

        $this->handle($sowing, $this->validatedData);
    }

    public function action(Sowing $sowing, array $modelData): Sowing|bool
    {
        $this->sowing = $sowing;
        $this->initialisationFromShop($sowing->shop, $modelData);

        return $this->handle($sowing, $this->validatedData);
    }
}
