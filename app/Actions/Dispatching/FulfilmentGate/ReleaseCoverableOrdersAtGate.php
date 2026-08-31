<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\FulfilmentGate;

use App\Actions\Ordering\Order\UpdateState\ReleaseOrderFromGate;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class ReleaseCoverableOrdersAtGate
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(int $organisationId): string
    {
        return (string) $organisationId;
    }

    public function handle(int $organisationId): void
    {
        $organisation = Organisation::find($organisationId);
        if (!$organisation || !$organisation->hasFulfilmentGate()) {
            return;
        }

        $orders = Order::where('organisation_id', $organisationId)
            ->whereNotNull('at_gate_at')
            ->where('state', OrderStateEnum::SUBMITTED)
            ->where(function ($query) {
                $query->where('pay_status', OrderPayStatusEnum::PAID)
                    ->orWhere('to_be_paid_by', OrderToBePaidByEnum::CASH_ON_DELIVERY);
            })
            ->orderBy('at_gate_at')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $coverage = GetGateCoverage::make()->handle($orders->pluck('id')->all());

        foreach ($orders as $order) {
            $orderCoverage = $coverage[$order->id] ?? null;
            if (!$orderCoverage || $orderCoverage['total_lines'] == 0 || $orderCoverage['ready_lines'] < $orderCoverage['total_lines']) {
                continue;
            }

            try {
                ReleaseOrderFromGate::make()->action($order);
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }
    }
}
