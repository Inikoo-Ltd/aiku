<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\Inventory\OrgStockMovement\UpdateOrgStockMovement;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\Sowing;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateSowing extends OrgAction
{
    use WithActionUpdate;

    private Sowing $sowing;

    public function handle(Sowing $sowing, array $modelData): Sowing|bool
    {
        $oldQuantity = $sowing->quantity;

        if (isset($modelData['quantity']) && $modelData['quantity'] == 0) {
            return DeleteSowing::make()->action($sowing);
        }

        if ($sowing->orgStockMovement) {
            if ($oldQuantity != $sowing->quantity) {
                UpdateOrgStockMovement::make()->action($sowing->orgStockMovement, [
                    'quantity' => $sowing->quantity,
                ]);
            }
        }

        return $this->update($sowing, $modelData);
    }

    public function rules(): array
    {
        return [
            'type'     => ['sometimes', Rule::enum(SowingTypeEnum::class)],
            'quantity' => ['sometimes', 'numeric'],
        ];
    }

    public function asController(Sowing $sowing, ActionRequest $request): void
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
