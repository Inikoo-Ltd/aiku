<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Nov 2025 — Optimized RFM Hydrator (Query-based)
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Models\CRM\Customer;
use App\Models\Helpers\Tag;
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

    public function getJobUniqueId(Customer $customer): string
    {
        return (string) $customer->id;
    }

    public function asCommand(Command $command): void
    {
        $customer = Customer::where('slug', $command->argument('customer'))->first();

        if (!$customer) {
            $command->error("Customer not found.");
            return;
        }

        $this->handle($customer);
    }

    public function handle(Customer $customer): void
    {
        $now = Carbon::now();
        $periodStart = $now->copy()->subYear();

        $stats = $customer->invoices()
            ->where('in_process', false)
            ->whereNotNull('date')
            ->selectRaw('
                MAX(date) as last_invoice_date,
                SUM(CASE WHEN date BETWEEN ? AND ? THEN 1 ELSE 0 END) as frequency_count,
                SUM(CASE WHEN date BETWEEN ? AND ? THEN net_amount ELSE 0 END) as monetary_value
            ', [$periodStart, $now, $periodStart, $now])
            ->first();

        if (!$stats || !$stats->last_invoice_date) {
            return;
        }

        /** 🔹 RECENCY **/
        $lastInvoiceDate = Carbon::parse($stats->last_invoice_date);
        $daysSinceLast = $lastInvoiceDate->diffInDays($now);

        if ($daysSinceLast <= 30) {
            $recencyTag = 'Active';
        } elseif ($daysSinceLast <= 90) {
            $recencyTag = 'At Risk';
        } elseif ($daysSinceLast <= 180) {
            $recencyTag = 'Inactive';
        } else {
            $recencyTag = 'Lost Customer';
        }

        /** 🔹 FREQUENCY **/
        $frequencyCount = (int) $stats->frequency_count;
        if ($frequencyCount == 1) {
            $frequencyTag = 'One-Time Buyer';
        } elseif ($frequencyCount <= 4) {
            $frequencyTag = 'Occasional Shopper';
        } elseif ($frequencyCount <= 9) {
            $frequencyTag = 'Frequent Buyer';
        } else {
            $frequencyTag = 'Brand Advocate';
        }

        /** 🔹 MONETARY **/
        $monetaryValue = (float) $stats->monetary_value;
        $percentile = $this->getMonetaryPercentileByShop($customer->shop_id, $monetaryValue);

        if ($percentile <= 50) {
            $monetaryTag = 'Low Value';
        } elseif ($percentile <= 80) {
            $monetaryTag = 'Medium Value';
        } elseif ($percentile <= 95) {
            $monetaryTag = 'High Value';
        } elseif ($percentile <= 99) {
            $monetaryTag = 'Gold Reward';
        } else {
            $monetaryTag = 'Top 100';
        }

        /** 🔹 Update Tag RFM **/
        $this->replaceRfmTags($customer, [$recencyTag, $frequencyTag, $monetaryTag]);
    }

    protected function replaceRfmTags(Customer $customer, array $newTagNames): void
    {
        $rfmTagIds = Tag::whereIn('data->type', ['recency', 'frequency', 'monetary'])
            ->pluck('id')
            ->toArray();

        if (!empty($rfmTagIds)) {
            $customer->tags()->detach($rfmTagIds);
        }

        $newTagIds = Tag::whereIn('name', $newTagNames)
            ->pluck('id')
            ->toArray();

        if (!empty($newTagIds)) {
            $customer->tags()->syncWithoutDetaching($newTagIds);
        }

        foreach ($newTagIds as $tagId) {
            try {
                $tag = Tag::find($tagId);
                TagHydrateModels::dispatch($tag);
            } catch (\Throwable $e) {
                // Skip errors
            }
        }
    }

    protected function getMonetaryPercentileByShop(int $shopId, float $value): float
    {
        $cacheKey = "rfm_monetary_percentiles_shop_{$shopId}";
        $percentiles = Cache::get($cacheKey);

        if (!$percentiles && !Cache::has("rfm_generating_{$shopId}")) {
            // Avoid race condition
            Cache::put("rfm_generating_{$shopId}", true, now()->addMinutes(5));
            $this->generateMonetaryPercentilesByShop($shopId);
            Cache::forget("rfm_generating_{$shopId}");
            $percentiles = Cache::get($cacheKey);
        }

        if (!$percentiles) {
            return 0;
        }

        if ($value <= $percentiles[50]) {
            return 50;
        }
        if ($value <= $percentiles[80]) {
            return 80;
        }
        if ($value <= $percentiles[95]) {
            return 95;
        }
        if ($value <= $percentiles[99]) {
            return 99;
        }
        return 100;
    }

    protected function generateMonetaryPercentilesByShop(int $shopId): void
    {
        $now = Carbon::now();
        $oneYearAgo = $now->copy()->subYear();

        $spending = DB::table('invoices')
            ->select('customer_id', DB::raw('SUM(net_amount) as total_spend'))
            ->where('shop_id', $shopId)
            ->where('in_process', false)
            ->whereBetween('date', [$oneYearAgo, $now])
            ->groupBy('customer_id')
            ->pluck('total_spend', 'customer_id');

        if ($spending->isEmpty()) {
            return;
        }

        $sorted = $spending->values()->sort()->values();
        $count = $sorted->count();

        $percentiles = [
            50 => $sorted[(int)($count * 0.5)] ?? 0,
            80 => $sorted[(int)($count * 0.8)] ?? 0,
            95 => $sorted[(int)($count * 0.95)] ?? 0,
            99 => $sorted[(int)($count * 0.99)] ?? 0,
        ];

        Cache::put("rfm_monetary_percentiles_shop_{$shopId}", $percentiles, now()->addHours(24));
    }
}
