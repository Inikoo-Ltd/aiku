<?php

namespace App\Actions\CRM\Customer\UI;

use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\CustomerRfmSnapshot;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerRfmComparison
{
    use AsObject;

    public function handle(Shop $shop, ?string $from = null, ?string $to = null): array
    {
        $periodEnd   = $this->parseDate($to) ?? now();
        $periodStart = $this->parseDate($from) ?? $periodEnd->copy()->subMonth();

        $current  = $this->snapshotOn($shop->id, $periodEnd);
        $previous = $this->snapshotOn($shop->id, $periodStart);

        if (!$current) {
            $current = CustomerRfmSnapshot::where('shop_id', $shop->id)
                ->orderBy('snapshot_date')
                ->first();
        }

        if ($previous && $current && $previous->id === $current->id) {
            $previous = null;
        }

        $currentData  = $current ? $this->rfmData($current) : $this->emptyRfmData();
        $previousData = $previous ? $this->rfmData($previous) : $this->emptyRfmData();

        return [
            'comparison' => [
                'current'    => [
                    'date'  => $current?->snapshot_date,
                    'data'  => $currentData,
                    'total' => array_sum($currentData[CustomerRfmSegmentEnum::TYPE_RECENCY]),
                ],
                'previous'   => [
                    'date'  => $previous?->snapshot_date,
                    'data'  => $previousData,
                    'total' => array_sum($previousData[CustomerRfmSegmentEnum::TYPE_RECENCY]),
                ],
                'comparison' => $this->calculateChanges($currentData, $previousData),
                'period'     => [
                    'from' => $periodStart->toDateString(),
                    'to'   => $periodEnd->toDateString(),
                ],
            ],
            'segments'          => $this->getRfmSegmentsStructure($shop),
            'newsletterRevenue' => [
                'currency' => $shop->currency->code,
                'data'     => GetCustomerRfmNewsletterRevenue::run($shop, $this->parseDate($from), $this->parseDate($to)),
            ],
        ];
    }

    protected function parseDate(?string $date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        $parsed = is_numeric($date) ? Carbon::createFromFormat('Ymd', $date) : Carbon::parse($date);

        return $parsed->endOfDay();
    }

    protected function snapshotOn(int $shopId, Carbon $date): ?CustomerRfmSnapshot
    {
        return CustomerRfmSnapshot::where('shop_id', $shopId)
            ->where('snapshot_date', '<=', $date->copy()->endOfDay())
            ->orderByDesc('snapshot_date')
            ->first();
    }

    protected function rfmData(CustomerRfmSnapshot $snapshot): array
    {
        return $snapshot->rfm_data();
    }

    protected function emptyRfmData(): array
    {
        $data = [];

        foreach (CustomerRfmSegmentEnum::types() as $type) {
            foreach (CustomerRfmSegmentEnum::tagNamesOfType($type) as $tagName) {
                $data[$type][$tagName] = 0;
            }
        }

        return $data;
    }

    protected function calculateChanges(array $currentData, array $previousData): array
    {
        $changes = [];

        foreach ($currentData as $type => $segments) {
            foreach ($segments as $segment => $currentCount) {
                $previousCount = $previousData[$type][$segment] ?? 0;
                $change        = $currentCount - $previousCount;

                $changes[$type][$segment] = [
                    'current'        => $currentCount,
                    'previous'       => $previousCount,
                    'change'         => $change,
                    'percent_change' => $previousCount > 0 ? round(($change / $previousCount) * 100, 2) : 0,
                ];
            }
        }

        return $changes;
    }

    public function getRfmSegmentsStructure(Shop $shop): array
    {
        $structure = [];

        foreach (CustomerRfmSegmentEnum::types() as $type) {
            $segments = CustomerRfmSegmentEnum::ofType($type);

            $structure[$type] = [
                'title'       => CustomerRfmSegmentEnum::typeTitles()[$type],
                'description' => CustomerRfmSegmentEnum::typeDescriptions()[$type],
                'segments'    => array_map(fn (CustomerRfmSegmentEnum $segment) => $segment->tagName(), $segments),
                'tooltips'    => $this->keyByTagName($segments, fn (CustomerRfmSegmentEnum $segment) => CustomerRfmSegmentEnum::tooltips()[$segment->value]),
                'routes'      => $this->keyByTagName($segments, fn (CustomerRfmSegmentEnum $segment) => $this->segmentRoute($shop, $segment)),
            ];
        }

        return $structure;
    }

    protected function keyByTagName(array $segments, callable $value): array
    {
        $keyed = [];

        foreach ($segments as $segment) {
            $keyed[$segment->tagName()] = $value($segment);
        }

        return $keyed;
    }

    protected function segmentRoute(Shop $shop, CustomerRfmSegmentEnum $segment): array
    {
        return [
            'name'       => 'grp.org.shops.show.crm.customers.index',
            'parameters' => [
                'organisation' => $shop->organisation->slug,
                'shop'         => $shop->slug,
                'filter[tag]'  => $segment->value,
            ],
        ];
    }
}
