<?php

namespace App\Http\Resources\Masters;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class MasterProductCategoryTimeSeriesResource extends JsonResource
{
    public function toArray($request): array
    {
        $frequency = request()->input('frequency', TimeSeriesFrequencyEnum::MONTHLY->value);
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::MONTHLY;

        return [
            'id' => $this->id,
            'period' => $this->formatPeriod($this->from, $this->to, $frequencyEnum),
            'filter_date' => $this->formatFilterDate($this->from, $this->to),
            'currency_code' => $this->currency_code,
            'from' => $this->from,
            'to' => $this->to,
            'sales_external' => (float) $this->sales_external,
            'sales_org_currency_external' => (float) $this->sales_org_currency_external,
            'sales_grp_currency_external' => (float) $this->sales_grp_currency_external,
            'invoices' => (int) $this->invoices,
            'refunds' => (int) $this->refunds,
            'orders' => (int) $this->orders,
            'customers_invoiced' => (int) $this->customers_invoiced,
            'total_customers' => (int) $this->total_customers,
            'sales_grp_currency_external_ly' => (float) ($this->sales_grp_currency_external_ly ?? 0),
            'sales_grp_currency_external_delta' => $this->calculateDelta((float) $this->sales_grp_currency_external, (float) ($this->sales_grp_currency_external_ly ?? 0)),
            'organisations_route' => $this->organisationsRoute(),
        ];
    }

    protected function organisationsRoute(): ?array
    {
        if (!$this->master_product_category_id) {
            return null;
        }

        return [
            'name' => 'grp.json.master_product_category.time_series_organisations',
            'parameters' => [
                'masterProductCategory' => $this->master_product_category_id,
                'record' => $this->id,
            ],
        ];
    }

    protected function calculateDelta(float $current, float $previous): ?array
    {
        if (!$previous) {
            return null;
        }

        $delta = (($current - $previous) / $previous) * 100;

        return [
            'value' => $delta,
            'formatted' => number_format($delta, 1) . '%',
            'is_positive' => $delta > 0,
            'is_negative' => $delta < 0,
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
