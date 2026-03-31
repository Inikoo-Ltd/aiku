<?php

namespace App\Actions\CRM\Customer\UI;

use App\Models\CRM\CustomerRfmSnapshot;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerRfmComparison
{
    use AsObject;

    public function handle(int $shopId): ?array
    {
        $current = CustomerRfmSnapshot::where('shop_id', $shopId)
            ->orderBy('snapshot_date', 'desc')
            ->first();

        if (!$current) {
            return null;
        }

        $previousTargetDate = $current->snapshot_date->copy()->subMonth();

        $previous = CustomerRfmSnapshot::where('shop_id', $shopId)
            ->whereDate('snapshot_date', $previousTargetDate->format('Y-m-d'))
            ->first();

        if (!$previous) {
            $previous = CustomerRfmSnapshot::where('shop_id', $shopId)
                ->orderBy('snapshot_date', 'asc')
                ->first();

            if ($previous && $previous->id === $current->id) {
                $previous = $this->createEmptyPreviousData($current->rfm_data());
            }
        }

        if (!$previous) {
            $previous = $this->createEmptyPreviousData($current->rfm_data());
        }

        return [
            'comparison' => [
                'current' => [
                    'date' => $current->snapshot_date,
                    'data' => $current->rfm_data(),
                    'total' => array_sum($current->tags_summary ?? [])
                ],
                'previous' => [
                    'date' => $previous->snapshot_date ?? $current->snapshot_date->copy()->subDay(),
                    'data' => $previous instanceof CustomerRfmSnapshot ? $previous->rfm_data() : $previous['data'],
                    'total' => $previous instanceof CustomerRfmSnapshot ?
                        array_sum($previous->tags_summary ?? []) : $previous['total']
                ],
                'comparison' => $this->calculateChanges($current, $previous)
            ],
            'segments' => $this->getRfmSegmentsStructure()
        ];
    }

    protected function createEmptyPreviousData($currentRfmData): array
    {
        $emptyData = [];

        foreach ($currentRfmData as $type => $segments) {
            $emptyData[$type] = [];
            foreach ($segments as $segment => $value) {
                $emptyData[$type][$segment] = 0;
            }
        }

        return [
            'date' => null,
            'data' => $emptyData,
            'total' => 0
        ];
    }

    protected function calculateChanges($current, $previous): array
    {
        $currentData = $current->rfm_data();

        if ($previous instanceof CustomerRfmSnapshot) {
            $previousData = $previous->rfm_data();
        } else {
            $previousData = $previous['data'];
        }

        $changes = [];

        foreach ($currentData as $type => $segments) {
            $changes[$type] = [];
            foreach ($segments as $segment => $currentCount) {
                $previousCount = $previousData[$type][$segment] ?? 0;
                $change = $currentCount - $previousCount;

                $changes[$type][$segment] = [
                    'current' => $currentCount,
                    'previous' => $previousCount,
                    'change' => $change,
                    'percent_change' => $previousCount > 0 ?
                        round(($change / $previousCount) * 100, 2) : 0
                ];
            }
        }

        return $changes;
    }

    public function getRfmSegmentsStructure(): array
    {
        return [
            'recency' => [
                'title' => 'Recency Segments',
                'description' => 'Based on when the last transaction was made',
                'segments' => [
                    'New Customer',
                    'Active',
                    'At Risk',
                    'Inactive',
                    'Lost Customer'
                ],
                'tooltips' => [
                    'New Customer'   => 'Customer has never placed an order yet.',
                    'Active'         => 'Last purchase within the last 30 days. Highly engaged customer.',
                    'At Risk'        => 'Last purchase was 31–90 days ago. May need re-engagement.',
                    'Inactive'       => 'Last purchase was 91–180 days ago. High churn risk.',
                    'Lost Customer'  => 'Last purchase was more than 180 days ago. Considered churned.',
                ]
            ],
            'frequency' => [
                'title' => 'Frequency Segments',
                'description' => 'Based on number of orders placed in the last 12 months',
                'segments' => [
                    'One-Time Buyer',
                    'Occasional Shopper',
                    'Frequent Buyer',
                    'Brand Advocate'
                ],
                'tooltips' => [
                    'One-Time Buyer'     => 'Placed exactly 1 order in the last 12 months.',
                    'Occasional Shopper' => 'Placed 2–4 orders in the last 12 months.',
                    'Frequent Buyer'     => 'Placed 5–9 orders in the last 12 months.',
                    'Brand Advocate'     => 'Placed 10 or more orders in the last 12 months. Highly loyal customer.',
                ]
            ],
            'monetary' => [
                'title' => 'Monetary Segments',
                'description' => 'Based on total spend in the last 12 months, ranked against all customers in this shop',
                'segments' => [
                    'Low Value',
                    'Medium Value',
                    'High Value',
                    'Gold Reward',
                    'Top 100',
                    'Top 10'
                ],
                'tooltips' => [
                    'Low Value'    => 'Total spend is in the bottom 50% of all customers in this shop (≤ 50th percentile).',
                    'Medium Value' => 'Total spend is between the 50th and 80th percentile of all customers.',
                    'High Value'   => 'Total spend is between the 80th and 95th percentile of all customers.',
                    'Gold Reward'  => 'Total spend is between the 95th and 99th percentile of all customers.',
                    'Top 100'      => 'Total spend is in the top 1% of all customers in this shop (> 99th percentile).',
                    'Top 10'       => 'Absolute top spenders — elite tier customers in this shop.',
                ]
            ]
        ];
    }
}
