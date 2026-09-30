<?php

/*
 * Author Louis Perez
 * Created on 30-09-2026-11h-04m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

trait WithIndivisibleSet
{
    /**
     * The parts a complete set is made of, for the heading icon popover. Null when the
     * product can be sent with parts missing, so the icon is not shown.
     *
     * @return array{parts: array<int, array{code: string, name: string, quantity: float}>, route: array|null}|null
     */
    public function getIndivisibleSet(Product|MasterAsset $model, ?array $compositionRoute): ?array
    {
        if (!$model->is_indivisible) {
            return null;
        }

        return [
            'parts' => $model->tradeUnits->unique('id')->map(fn ($tradeUnit) => [
                'code'     => $tradeUnit->code,
                'name'     => $tradeUnit->name,
                'quantity' => (float) $tradeUnit->pivot->quantity,
            ])->values()->all(),
            'route' => $compositionRoute,
        ];
    }
}
