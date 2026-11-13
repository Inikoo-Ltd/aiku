<?php

/*
 * Author Andi Ferdiawan
 * Created on 12-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileStockMovementReasons
{
    use AsController;

    /**
     * The reasons the web offers: increase and decrease for an audit (SKO page), transfer for
     * a move (SKO and Location pages), each as value => label.
     */
    public function asController(): JsonResponse
    {
        return response()->json(['data' => [
            'increase' => OrgStockMovementReasonEnum::withLabels(OrgStockMovementReasonEnum::increaseReason()),
            'decrease' => OrgStockMovementReasonEnum::withLabels(OrgStockMovementReasonEnum::decreaseReason()),
            'transfer' => OrgStockMovementReasonEnum::withLabels(OrgStockMovementReasonEnum::transferReason()),
        ]]);
    }
}
