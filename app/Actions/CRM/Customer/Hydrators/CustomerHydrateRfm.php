<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Nov 2025 — Optimized RFM Hydrator (Query-based)
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\CRM\Customer\GetCustomerRfmTagIds;
use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use App\Models\CRM\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateRfm implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:customer-rfm {customer}';

    public function getJobUniqueId(int|null $customerId, bool $hydrateTagModels = true): string
    {
        return $customerId ?? 'empty';
    }

    public function asCommand(Command $command): void
    {
        $customer = Customer::where('slug', $command->argument('customer'))->first();

        if (!$customer) {
            $command->error("Customer not found.");

            return;
        }

        $this->handle($customer->id);
    }

    public function handle(int|null $customerId, bool $hydrateTagModels = true): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $periodEnd   = Carbon::now();
        $periodStart = $periodEnd->copy()->subYear();

        $stats = $customer->invoices()
            ->where('in_process', false)
            ->whereNotNull('date')
            ->where('date', '<=', $periodEnd)
            ->selectRaw(
                '
                MIN(date) as first_invoice_date,
                MAX(date) FILTER (WHERE date >= ?) as last_invoice_date,
                COUNT(*) FILTER (WHERE date >= ?) as frequency_count,
                COALESCE(SUM(net_amount) FILTER (WHERE date >= ?), 0) as monetary_value
            ',
                [$periodStart, $periodStart, $periodStart]
            )
            ->first();

        if (!$stats || !(int) $stats->frequency_count) {
            $this->detachRfmTags($customer, $hydrateTagModels);

            return;
        }

        $segments = [
            $this->recencySegment(
                Carbon::parse($stats->first_invoice_date),
                Carbon::parse($stats->last_invoice_date),
                $periodEnd
            ),
            $this->frequencySegment((int) $stats->frequency_count),
            $this->monetarySegment($customer->shop_id, (float) $stats->monetary_value, $periodStart, $periodEnd),
        ];

        $this->replaceRfmTags($customer, $segments, $hydrateTagModels);
    }

    public function recencyCutoffs(Carbon $periodEnd): array
    {
        $reference = $periodEnd->copy()->startOfDay();

        return [
            CustomerRfmSegmentEnum::RECENT_DAYS   => $reference->copy()->subDays(CustomerRfmSegmentEnum::RECENT_DAYS),
            CustomerRfmSegmentEnum::AT_RISK_DAYS  => $reference->copy()->subDays(CustomerRfmSegmentEnum::AT_RISK_DAYS),
            CustomerRfmSegmentEnum::INACTIVE_DAYS => $reference->copy()->subDays(CustomerRfmSegmentEnum::INACTIVE_DAYS),
        ];
    }

    public function recencySegment(Carbon $firstInvoiceDate, Carbon $lastInvoiceDate, Carbon $periodEnd): CustomerRfmSegmentEnum
    {
        $cutoffs = $this->recencyCutoffs($periodEnd);

        return match (true) {
            $firstInvoiceDate >= $cutoffs[CustomerRfmSegmentEnum::RECENT_DAYS]   => CustomerRfmSegmentEnum::NEW_CUSTOMER,
            $lastInvoiceDate >= $cutoffs[CustomerRfmSegmentEnum::RECENT_DAYS]    => CustomerRfmSegmentEnum::ACTIVE,
            $lastInvoiceDate >= $cutoffs[CustomerRfmSegmentEnum::AT_RISK_DAYS]   => CustomerRfmSegmentEnum::AT_RISK,
            $lastInvoiceDate >= $cutoffs[CustomerRfmSegmentEnum::INACTIVE_DAYS]  => CustomerRfmSegmentEnum::INACTIVE,
            default                                                              => CustomerRfmSegmentEnum::LOST_CUSTOMER,
        };
    }

    public function frequencySegment(int $invoicesCount): CustomerRfmSegmentEnum
    {
        return match (true) {
            $invoicesCount <= 1                                                            => CustomerRfmSegmentEnum::ONE_TIME_BUYER,
            $invoicesCount <= CustomerRfmSegmentEnum::OCCASIONAL_SHOPPER_MAX_INVOICES      => CustomerRfmSegmentEnum::OCCASIONAL_SHOPPER,
            $invoicesCount <= CustomerRfmSegmentEnum::FREQUENT_BUYER_MAX_INVOICES          => CustomerRfmSegmentEnum::FREQUENT_BUYER,
            default                                                                        => CustomerRfmSegmentEnum::BRAND_ADVOCATE,
        };
    }

    protected function monetarySegment(int $shopId, float $spend, Carbon $periodStart, Carbon $periodEnd): CustomerRfmSegmentEnum
    {
        $benchmark = $this->shopSpendBenchmark($shopId, $periodStart, $periodEnd);

        if ($benchmark === null) {
            return CustomerRfmSegmentEnum::LOW_VALUE;
        }

        return match (true) {
            $benchmark['top_spender_floor'] !== null && $spend >= $benchmark['top_spender_floor'] => CustomerRfmSegmentEnum::TOP_10,
            $spend <= $benchmark['p50']                                                           => CustomerRfmSegmentEnum::LOW_VALUE,
            $spend <= $benchmark['p80']                                                           => CustomerRfmSegmentEnum::MEDIUM_VALUE,
            $spend <= $benchmark['p95']                                                           => CustomerRfmSegmentEnum::HIGH_VALUE,
            $spend <= $benchmark['p99']                                                           => CustomerRfmSegmentEnum::GOLD_REWARD,
            default                                                                               => CustomerRfmSegmentEnum::TOP_100,
        };
    }

    protected function shopSpendBenchmark(int $shopId, Carbon $periodStart, Carbon $periodEnd): ?array
    {
        $cacheKey = "rfm_spend_benchmark_shop_{$shopId}_".$periodEnd->toDateString();

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($shopId, $periodStart, $periodEnd) {
            $benchmark = DB::selectOne(
                "
                WITH spend AS (
                    SELECT customer_id, SUM(net_amount) AS total
                    FROM invoices
                    WHERE shop_id = ? AND in_process = false AND deleted_at IS NULL AND date BETWEEN ? AND ?
                    GROUP BY customer_id
                )
                SELECT
                    percentile_cont(0.5)  WITHIN GROUP (ORDER BY total) AS p50,
                    percentile_cont(0.8)  WITHIN GROUP (ORDER BY total) AS p80,
                    percentile_cont(0.95) WITHIN GROUP (ORDER BY total) AS p95,
                    percentile_cont(0.99) WITHIN GROUP (ORDER BY total) AS p99,
                    (SELECT total FROM spend ORDER BY total DESC LIMIT 1 OFFSET ?) AS top_spender_floor
                FROM spend
            ",
                [$shopId, $periodStart, $periodEnd, CustomerRfmSegmentEnum::TOP_SPENDERS_SIZE - 1]
            );

            if (!$benchmark || $benchmark->p50 === null) {
                return null;
            }

            return [
                'p50'               => (float) $benchmark->p50,
                'p80'               => (float) $benchmark->p80,
                'p95'               => (float) $benchmark->p95,
                'p99'               => (float) $benchmark->p99,
                'top_spender_floor' => $benchmark->top_spender_floor === null ? null : (float) $benchmark->top_spender_floor,
            ];
        });
    }

    protected function replaceRfmTags(Customer $customer, array $segments, bool $hydrateTagModels): void
    {
        $rfmTagIds = GetCustomerRfmTagIds::run();

        $newTagIds = array_values(array_filter(array_map(
            fn (CustomerRfmSegmentEnum $segment) => $rfmTagIds[$segment->value] ?? null,
            $segments
        )));

        $customer->tags()->detach(array_diff(array_values($rfmTagIds), $newTagIds));

        if (empty($newTagIds)) {
            return;
        }

        $customer->tags()->syncWithoutDetaching($newTagIds);

        if ($hydrateTagModels) {
            $this->hydrateTagModels($newTagIds);
        }
    }

    protected function detachRfmTags(Customer $customer, bool $hydrateTagModels): void
    {
        $rfmTagIds = array_values(GetCustomerRfmTagIds::run());

        if (empty($rfmTagIds)) {
            return;
        }

        $detached = $customer->tags()->detach($rfmTagIds);

        if ($detached && $hydrateTagModels) {
            $this->hydrateTagModels($rfmTagIds);
        }
    }

    protected function hydrateTagModels(array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            try {
                TagHydrateModels::dispatch($tagId)->delay(300);
            } catch (\Throwable) {
                // Skip errors
            }
        }
    }
}
