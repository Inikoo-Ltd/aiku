<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Mon, 30 Dec 2025 20:00:00 Western Indonesia Time, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Goods;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetTimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        $frequency = request()->input('frequency', TimeSeriesFrequencyEnum::DAILY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        return [
            'id' => $this->id,
            'period' => $this->formatPeriod($this->from, $this->to, $frequencyEnum),
            'filter_date' => $this->formatFilterDate($this->from, $this->to),
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

    protected function formatFilterDate(?Carbon $from, ?Carbon $to): string
    {
        if (!$from || !$to) {
            return '-';
        }

        return $from->format('Ymd') . '-' . $to->format('Ymd');
    }
}
