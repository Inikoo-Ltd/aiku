<?php

/*
 * Author Louis Perez
 * Created on 23-11-2026-09h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

trait WithMobileStockFractions
{
    /**
     * @return array<string, float|string|array<int, mixed>>
     */
    protected function quantityFields(string $key, int|float|string|null $quantity, int|float|string|null $packedIn = null): array
    {
        $quantity = (float) ($quantity ?? 0);

        return [
            $key             => $quantity,
            $key.'_label'    => $this->stockFractionLabel($quantity, $packedIn),
            $key.'_fraction' => $this->stockFraction($quantity, $packedIn),
        ];
    }

    /**
     * The website's split of a stock quantity (GetOrgStockShowcase::getFractionalQuantity), for its absolute value.
     *
     * @return array{0: int|float, 1: array{0: int|float, 1: int|float}}
     */
    protected function stockFraction(float $quantity, int|float|string|null $packedIn): array
    {
        $packedIn = (float) ($packedIn ?? 1);

        return riseDivisor(divideWithRemainder(findSmallestFactors(abs($quantity))), $packedIn == floor($packedIn) ? (int) $packedIn : $packedIn);
    }

    /**
     * The text FractionDisplay draws for that split: "921 31/46", "3/4" or "12", signed.
     */
    protected function stockFractionLabel(float $quantity, int|float|string|null $packedIn): string
    {
        [$quotient, [$dividend, $divisor]] = $this->stockFraction($quantity, $packedIn);

        $sign      = $quantity < 0 ? '-' : '';
        $whole     = trimDecimalZeros($quotient);
        $fraction  = trimDecimalZeros(round((float) $dividend, 3)).'/'.trimDecimalZeros($divisor);
        $hasWhole  = (float) $quotient != 0.0;
        $hasRemain = (float) $dividend != 0.0;

        return match (true) {
            $hasWhole && $hasRemain => $sign.$whole.' '.$fraction,
            $hasWhole               => $sign.$whole,
            $hasRemain              => $sign.$fraction,
            default                 => '0',
        };
    }
}
