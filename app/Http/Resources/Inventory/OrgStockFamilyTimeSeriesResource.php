<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OrgStockFamilyTimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        $frequency = request()->input('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        return [
            'id'                          => $this->id,
            'period'                      => $this->formatPeriod($this->from, $this->to, $frequencyEnum),
            'from'                        => $this->from,
            'to'                          => $this->to,
            'sales_external'              => (float) $this->sales_external,
            'sales_org_currency_external' => (float) $this->sales_org_currency_external,
            'sales_grp_currency_external' => (float) $this->sales_grp_currency_external,
            'invoices'                    => (int) $this->invoices,
            'refunds'                     => (int) $this->refunds,
            'orders'                      => (int) $this->orders,
            'customers_invoiced'          => (int) $this->customers_invoiced,
        ];
    }

    protected function formatPeriod(?Carbon $from, ?Carbon $to, TimeSeriesFrequencyEnum $frequency): string
    {
        if (!$from) {
            return '-';
        }

        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY     => $from->format('d M Y'),
            TimeSeriesFrequencyEnum::WEEKLY    => $from->format('d M') . ' - ' . ($to ? $to->format('d M Y') : ''),
            TimeSeriesFrequencyEnum::MONTHLY   => $from->format('M Y'),
            TimeSeriesFrequencyEnum::QUARTERLY => 'Q' . $from->quarter . ' ' . $from->format('Y'),
            TimeSeriesFrequencyEnum::YEARLY    => $from->format('Y'),
        };
    }
}
