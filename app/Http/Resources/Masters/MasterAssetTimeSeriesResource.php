<?php

/*
 * Author: Nickel <nickel@gemini.com>
 * Copyright (c) 2026, Nickel
 */

namespace App\Http\Resources\Masters;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterAssetTimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        $frequency = request()->get('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        return [
            'id' => $this->id,
            'period' => $this->formatPeriod($this->from, $this->to, $frequencyEnum),
            'sales' => (float) $this->sales,
            'invoices' => (int) $this->invoices,
            'refunds' => (int) $this->refunds,
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
