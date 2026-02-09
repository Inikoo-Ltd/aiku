<?php

/*
 * author Louis Perez
 * created on 06-02-2026-16h-02m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class GetExternalProductTradeUnits extends OrgAction
{
    public function handle(Product $product): array
    {
        $packedIn = DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->whereIn('trade_unit_id', $product->tradeUnits->pluck('id'))
            ->pluck('quantity', 'trade_unit_id')
            ->toArray();

        return $product->tradeUnits->map(function ($t) use ($packedIn) {
            return array_merge(
                ['quantity' => (int)$t->pivot->quantity],
                ['fraction' => $t->pivot->quantity / $packedIn[$t->id]],
                ['packed_in' => $packedIn[$t->id]],
                ['pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($t->pivot->quantity / $packedIn[$t->id])), $packedIn[$t->id])],
                $t->toArray()
            );
        })->toArray();
    }

    public function asController(Product $product, ActionRequest $request): array
    {
        $this->initialisation($product->organisation, $request);

        return $this->handle($product);
    }

    public function jsonResponse(array $tradeUnits): array|JsonResource
    {
        return $tradeUnits;
    }
}
