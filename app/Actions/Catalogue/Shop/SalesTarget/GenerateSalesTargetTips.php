<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget;

use App\Actions\Comms\Mailshot\Filters\FilterDueToReorder;
use App\Actions\Helpers\AI\AskToAi;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\SalesTargetTip;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every morning, a short tip for each shop and each of its invoice categories on how to reach the
 * month's target: what is needed today and this week, and which customers to call. The AI only
 * words it; every figure comes from the facts given and is kept with the tip.
 */
class GenerateSalesTargetTips
{
    use AsAction;

    public string $commandSignature = 'sales-targets:daily-tips {shops?* : shop slugs, all open shops when empty}';

    public function handle(Shop $shop, ?Carbon $today = null): int
    {
        $today = ($today ?? now('UTC'))->copy()->startOfDay();
        $month = GetShopMonthSalesTarget::run($shop, null, $today);

        if (!$month['target']['amount']) {
            return 0;
        }

        $shopFacts = $this->shopFacts($shop, $today);
        $tips      = 0;

        foreach ([$month, ...array_filter($month['children'], fn (array $child) => $child['invoice_category_id'] ?? null)] as $block) {
            if (!$block['target']['amount']) {
                continue;
            }

            $invoiceCategoryId = $block['invoice_category_id'] ?? null;
            $facts             = [
                'shop'             => $shop->name,
                'invoice_category' => $invoiceCategoryId ? $block['name'] : null,
                ...$this->targetFacts($block),
                ...$this->lastYearCustomersFacts($shop, $invoiceCategoryId, $today),
                ...($invoiceCategoryId ? [] : $shopFacts),
            ];

            $tip = AskToAi::run($this->prompt($facts), config('marketing.sales_target_tip_model'));

            if (!$tip) {
                continue;
            }

            SalesTargetTip::updateOrCreate(
                ['shop_id' => $shop->id, 'date' => $today->toDateString(), 'invoice_category_id' => $invoiceCategoryId],
                ['group_id' => $shop->group_id, 'organisation_id' => $shop->organisation_id, 'tip' => $tip, 'facts' => $facts]
            );
            $tips++;
        }

        return $tips;
    }

    private function targetFacts(array $block): array
    {
        return [
            'currency'                     => $block['currency_code'],
            'month'                        => $block['month_label'],
            'day_of_month'                 => $block['day_of_month'],
            'days_left_after_today'        => $block['remaining_days'],
            'target'                       => $block['target']['amount'],
            'invoiced_so_far'              => $block['sales_so_far'],
            'orders_in_warehouse_pipeline' => $block['pipeline']['orders'],
            'pipeline_amount'              => $block['pipeline']['amount'],
            'still_needed_after_pipeline'  => $block['gap'],
            'needed_per_day'               => $block['needed_per_day'],
            'needed_this_week'             => $block['needed_this_week'],
            'expected_month_end'           => $block['expected'],
            'invoiced_same_days_last_year' => $block['last_year_so_far'],
            'last_year_whole_month'        => $block['last_year_total'],
        ];
    }

    /**
     * Customers who bought in the same month last year and have not been invoiced this month yet.
     */
    private function lastYearCustomersFacts(Shop $shop, ?int $invoiceCategoryId, Carbon $today): array
    {
        $monthStart    = $today->copy()->startOfMonth();
        $lastYearStart = $monthStart->copy()->subYear();

        $row = DB::table('invoices')
            ->where('shop_id', $shop->id)
            ->when($invoiceCategoryId, fn ($query) => $query->where('invoice_category_id', $invoiceCategoryId))
            ->where('type', 'invoice')
            ->where('in_process', false)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$lastYearStart->toDateString(), $lastYearStart->copy()->endOfMonth()->toDateTimeString()])
            ->whereNotExists(fn ($query) => $query->from('invoices as this_month')
                ->whereColumn('this_month.customer_id', 'invoices.customer_id')
                ->where('this_month.shop_id', $shop->id)
                ->whereNull('this_month.deleted_at')
                ->where('this_month.date', '>=', $monthStart->toDateString()))
            ->selectRaw('count(distinct customer_id) as customers, coalesce(sum(org_net_amount), 0) as amount')
            ->first();

        return [
            'customers_who_bought_same_month_last_year_not_yet_this_month' => (int) $row->customers,
            'what_they_spent_that_month_last_year'                          => round((float) $row->amount, 2),
        ];
    }

    /**
     * Leads that belong to the whole shop, given to the shop's tip only.
     */
    private function shopFacts(Shop $shop, Carbon $today): array
    {
        $dueToReorder = (new FilterDueToReorder())->whereDue(DB::table('customers')->where('customers.shop_id', $shop->id)->whereNull('customers.deleted_at'))->count();

        $baskets = DB::table('orders')
            ->where('shop_id', $shop->id)
            ->where('state', OrderStateEnum::CREATING->value)
            ->whereNull('deleted_at')
            ->where('updated_by_customer_at', '>=', $today->copy()->subDays(14))
            ->where('org_net_amount', '>', 0)
            ->selectRaw('count(*) as baskets, coalesce(sum(org_net_amount), 0) as amount')
            ->first();

        return [
            'customers_due_to_reorder_within_a_week'         => $dueToReorder,
            'open_baskets_customers_touched_in_last_14_days' => (int) $baskets->baskets,
            'open_baskets_amount'                            => round((float) $baskets->amount, 2),
        ];
    }

    private function prompt(array $facts): string
    {
        $scope = $facts['invoice_category'] ? "the {$facts['invoice_category']} sales of {$facts['shop']}" : $facts['shop'];

        return "You coach the team responsible for $scope, a wholesale business, to reach this month's sales target. Amounts are in {$facts['currency']}, net of tax.\n\n"
            ."Facts as of this morning:\n".json_encode($facts, JSON_PRETTY_PRINT)."\n\n"
            ."Write today's tip in plain English: at most three short sentences, under 60 words, no headings, no lists, no greeting. "
            ."Say how much is needed today and this week, then the one or two actions most likely to close the gap, naming the counts from the facts "
            ."(such as customers due to reorder, open baskets to follow up, customers who bought this month last year but not yet), only those present in the facts. "
            ."If the target is already covered by invoices and the pipeline, say so and suggest how to get ahead. "
            ."Use only the figures given, rounded to whole amounts; never invent numbers, products or names.";
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shops = Shop::where('state', ShopStateEnum::OPEN)
            ->when($command->argument('shops'), fn ($query, $slugs) => $query->whereIn('slug', $slugs))
            ->get();

        foreach ($shops as $shop) {
            $command->info("$shop->slug: ".$this->handle($shop).' tips');
        }

        return 0;
    }
}
