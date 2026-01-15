<?php

namespace App\Http\Resources\Masters;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class MasterProductCategoryTimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        $frequency = request()->get('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        return [
            'id' => $this->id,
            'period' => $this->formatPeriod($this->from, $this->to, $frequencyEnum),
            'from' => $this->from,
            'to' => $this->to,
            'sales' => (float) $this->sales,
            'sales_org_currency' => (float) $this->sales_org_currency,
            'sales_grp_currency' => (float) $this->sales_grp_currency,
            'invoices' => (int) $this->invoices,
            'refunds' => (int) $this->refunds,
            'orders' => (int) $this->orders,
            'customers_invoiced' => (int) $this->customers_invoiced,
        ];
    }

    protected function formatPeriod(?Carbon $from, ?Carbon $to, TimeSeriesFrequencyEnum $frequency): string
    {
        if (!$from) {
            return '-';
        }

        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => $from->format('d M Y'),
            TimeSeriesFrequencyEnum::WEEKLY => $from->format('d M') . ' - ' . ($to ? $to->format('d M Y') : ''),
            TimeSeriesFrequencyEnum::MONTHLY => $from->format('M Y'),
            TimeSeriesFrequencyEnum::QUARTERLY => 'Q' . $from->quarter . ' ' . $from->format('Y'),
            TimeSeriesFrequencyEnum::YEARLY => $from->format('Y'),
        };
    }
}
