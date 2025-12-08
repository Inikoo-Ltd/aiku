<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-09h-23m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\PurgedOrder;

use App\Actions\Ordering\Purge\Hydrators\PurgeHydratePurgedOrders;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PurgedOrder\PurgedOrderStatusEnum;
use App\Models\Ordering\PurgedOrder;
use Illuminate\Validation\Rule;

class UpdatePurgedOrder extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(PurgedOrder $purgedOrder, $modelData): PurgedOrder
    {

        $purgedOrder = $this->update($purgedOrder, $modelData);
        PurgeHydratePurgedOrders::dispatch($purgedOrder->purge);

        return $purgedOrder;
    }

    public function rules(): array
    {
        $rules = [
            'status' => ['sometimes', Rule::enum(PurgedOrderStatusEnum::class)],
            'error_message' => ['sometimes', 'string'],
        ];

        if (! $this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(PurgedOrder $purgedOrder, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PurgedOrder
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($purgedOrder->purge->shop, $modelData);

        return $this->handle($purgedOrder, $modelData);
    }
}
