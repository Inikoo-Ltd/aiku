<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;

trait WithPalletReturnBucketNavigation
{
    use WithBucketNavigation;

    protected function getPalletReturnBucket(ActionRequest $request): ?string
    {
        $bucket = $request->input('bucket');

        return isset($this->palletReturnBucketStates()[$bucket]) ? $bucket : null;
    }

    /**
     * Mirrors the restriction filters of IndexWarehousePalletReturns.
     *
     * @return array<string, array<PalletReturnStateEnum>>
     */
    private function palletReturnBucketStates(): array
    {
        return [
            'confirmed'  => [PalletReturnStateEnum::CONFIRMED],
            'picking'    => [PalletReturnStateEnum::PICKING],
            'picked'     => [PalletReturnStateEnum::PICKED],
            'dispatched' => [PalletReturnStateEnum::DISPATCHED],
            'cancelled'  => [PalletReturnStateEnum::CANCEL],
            'new'        => [PalletReturnStateEnum::CONFIRMED, PalletReturnStateEnum::SUBMITTED, PalletReturnStateEnum::IN_PROCESS],
            'handling'   => [PalletReturnStateEnum::CONFIRMED, PalletReturnStateEnum::PICKING, PalletReturnStateEnum::PICKED],
        ];
    }

    protected function getPalletReturnBucketNeighbour(PalletReturn $palletReturn, ActionRequest $request, string $bucket, bool $forward): ?PalletReturn
    {
        $query = PalletReturn::query()
            ->where('pallet_returns.warehouse_id', $palletReturn->warehouse_id)
            ->whereIn('pallet_returns.state', $this->palletReturnBucketStates()[$bucket]);

        if ($type = $request->input('filter.type')) {
            $query->where('pallet_returns.type', $type);
        }

        $activityAt = IndexWarehousePalletReturns::RETURN_ACTIVITY_AT;

        $defaultSort = $bucket == 'new'
            ? [$activityAt, true]
            : ['pallet_returns.confirmed_at', true];

        $sortValues = [
            $activityAt => $palletReturn->confirmed_at ?? $palletReturn->submitted_at ?? $palletReturn->created_at,
        ];

        return $this->getBucketNeighbour(
            query: $query,
            model: $palletReturn,
            sort: $request->input('bucket_sort'),
            sortValues: $sortValues,
            sortColumns: [
                'reference'          => 'pallet_returns.reference',
                'customer_reference' => 'pallet_returns.customer_reference',
                'date'               => 'pallet_returns.date',
                'state'              => 'pallet_returns.state',
                'picking_at'         => 'pallet_returns.picking_at',
                'picked_at'          => 'pallet_returns.picked_at',
                'confirmed_at'       => 'pallet_returns.confirmed_at',
                'activity_at'        => $activityAt,
            ],
            defaultSort: $defaultSort,
            forward: $forward
        );
    }
}
