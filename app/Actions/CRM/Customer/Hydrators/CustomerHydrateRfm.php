<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Mon, 10 Nov 2025 07:32:45 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Models\CRM\Customer;
use App\Models\Helpers\Tag;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateRfm implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:customer-rfm {customer}';

    public function getJobUniqueId(int $customerId): string
    {
        return $customerId;
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
        /** 🔹 Get all valid invoices **/
        $invoices = $customer->invoices()
            ->where('in_process', false)
            ->whereNotNull('date')
            ->get();

        if ($invoices->isEmpty()) {
            return;
        }

        $now = Carbon::now();

        /** 🔹 RECENCY **/
        $lastInvoiceDate = $invoices->max('date');
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

        /** 🔹 FREQUENCY (last month only) **/
        $frequencyCount = $customer->invoices()
            ->whereBetween('date', [$now->copy()->subMonth(), $now])
            ->count();

        if ($frequencyCount == 1) {
            $frequencyTag = 'One-Time Buyer';
        } elseif ($frequencyCount <= 4) {
            $frequencyTag = 'Occasional Shopper';
        } elseif ($frequencyCount <= 9) {
            $frequencyTag = 'Frequent Buyer';
        } else {
            $frequencyTag = 'Brand Advocate';
        }

        /** 🔹 MONETARY (last month only) **/
        $monetaryValue = $customer->invoices()
            ->whereBetween('date', [$now->copy()->subMonth(), $now])
            ->sum('net_amount');

        $percentile = $this->getMonetaryPercentileGlobal($monetaryValue);

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

        /** 🔹 Attach or replace RFM tags safely **/
        $newTagNames = [$recencyTag, $frequencyTag, $monetaryTag];
        $this->replaceRfmTags($customer, $newTagNames);
    }

    /**
     * 🔹 Helper: Replace old RFM tags (recency/frequency/monetary) with new ones
     */
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
                TagHydrateModels::dispatch($tagId);
            } catch (\Throwable $e) {
                // Skip errors in CLI or queue context
            }
        }
    }

    /**
     * 🔹 Get global percentile from cache or regenerate if missing
     */
    protected function getMonetaryPercentileGlobal(float $value): float
    {
        $percentiles = Cache::get('rfm_monetary_percentiles');

        if (!$percentiles) {
            $this->generateGlobalMonetaryPercentiles();
            $percentiles = Cache::get('rfm_monetary_percentiles');
        }

        // Find the closest percentile based on spend value
        return collect($percentiles)
            ->sortKeys()
            ->reduce(function ($carry, $percent, $spend) use ($value) {
                return ($spend <= $value) ? $percent : $carry;
            }, 0);
    }

    /**
     * 🔹 Generate percentile distribution for all customers (last month only)
     */
    public static function generateGlobalMonetaryPercentiles(): void
    {
        $now = Carbon::now();
        $oneMonthAgo = $now->copy()->subMonth();

        $allSpend = Customer::with(['invoices' => function ($q) use ($oneMonthAgo, $now) {
            $q->whereBetween('date', [$oneMonthAgo, $now])
                ->where('in_process', false);
        }])->get()->mapWithKeys(function ($c) {
            $sum = $c->invoices->sum('net_amount');
            return [$c->id => $sum];
        });

        if ($allSpend->isEmpty()) {
            return;
        }

        $sorted = $allSpend->sort()->values();
        $count = $sorted->count();

        $percentiles = [];
        foreach ($sorted as $index => $value) {
            $percentiles[$value] = round(($index / max($count - 1, 1)) * 100, 2);
        }

        Cache::put('rfm_monetary_percentiles', $percentiles, 3600);
    }
}
