<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Invoice\CategoriseInvoice;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Net value of orders submitted but not invoiced yet, a snapshot of now repeated for every dashboard interval.
 */
class GetOrderBacklog
{
    use AsObject;

    public const array STATES = [
        OrderStateEnum::SUBMITTED,
        OrderStateEnum::IN_WAREHOUSE,
        OrderStateEnum::HANDLING,
        OrderStateEnum::HANDLING_BLOCKED,
        OrderStateEnum::PICKED,
        OrderStateEnum::PACKING,
        OrderStateEnum::PACKED,
    ];

    private const array AMOUNT_FIELDS = [
        'backlog'                       => 'net_amount',
        'backlog_org_currency_external' => 'org_net_amount',
        'backlog_grp_currency_external' => 'grp_net_amount',
    ];

    /**
     * @return array{organisations: array<int, array<string, float>>, shops: array<int, array<string, float>>, invoiceCategories: array<int, array<string, float>>, platforms: array<int, array<string, float>>, salesChannels: array<int, array<string, float>>, brands: array<int, array<string, float>>}
     */
    public function handle(Group|Organisation $parent, bool $includePartners = false): array
    {
        $totals = [
            'organisations'     => [],
            'shops'             => [],
            'invoiceCategories' => [],
            'platforms'         => [],
            'salesChannels'     => [],
            'brands'            => [],
        ];

        $organisations = $parent instanceof Organisation ? collect([$parent]) : $parent->organisations()->get();

        foreach ($organisations as $organisation) {
            $categoriser       = CategoriseInvoice::make();
            $invoiceCategories = $categoriser->getActiveInvoiceCategories($organisation);

            $this->backlogOrders($organisation, $includePartners)
                ->each(function (Order $order) use ($categoriser, $invoiceCategories, &$totals) {
                    $amounts = array_map(fn ($amountField) => (float) $order->getRawOriginal($amountField), self::AMOUNT_FIELDS);

                    $this->add($totals['organisations'], $order->organisation_id, $amounts);
                    $this->add($totals['shops'], $order->shop_id, $amounts);
                    $this->add($totals['platforms'], $order->platform_id, $amounts);
                    $this->add($totals['salesChannels'], $order->sales_channel_id, $amounts);
                    $this->add($totals['invoiceCategories'], $categoriser->getInvoiceCategory($order, $invoiceCategories)?->id, $amounts);
                });
        }

        $this->brandsQuery($organisations->pluck('id')->all(), $includePartners)->get()->each(function ($row) use (&$totals) {
            $this->add($totals['brands'], $row->brand_id, array_map(fn ($amountField) => (float) $row->{$amountField}, self::AMOUNT_FIELDS));
        });

        return array_map(fn (array $dimension) => array_map($this->toIntervals(...), $dimension), $totals);
    }

    /**
     * @return array<int>
     */
    public function orderIdsInInvoiceCategory(InvoiceCategory $invoiceCategory, bool $includePartners = false): array
    {
        $categoriser       = CategoriseInvoice::make();
        $invoiceCategories = $categoriser->getActiveInvoiceCategories($invoiceCategory->organisation);

        return $this->backlogOrders($invoiceCategory->organisation, $includePartners)
            ->filter(fn (Order $order) => $categoriser->getInvoiceCategory($order, $invoiceCategories)?->id === $invoiceCategory->id)
            ->pluck('id')
            ->all();
    }

    /**
     * @return EloquentCollection<int, Order>
     */
    private function backlogOrders(Organisation $organisation, bool $includePartners): EloquentCollection
    {
        return Order::query()
            ->select(array_merge(['id', 'organisation_id', 'shop_id', 'platform_id', 'billing_country_id', 'as_organisation_id', 'is_vip', 'sales_channel_id'], array_values(self::AMOUNT_FIELDS)))
            ->with(['shop' => fn ($q) => $q->select(['id', 'type'])])
            ->where('organisation_id', $organisation->id)
            ->whereIn('state', self::STATES)
            ->when(!$includePartners, fn ($q) => $q->whereNull('as_organisation_id'))
            ->get();
    }

    /**
     * @param array<int, array> $rows dashboard stats rows keyed by their model id in 'id'
     * @param array<int, array<string, float>> $backlog
     */
    public static function addTo(array $rows, array $backlog): array
    {
        return array_map(fn (array $row) => array_merge($row, $backlog[$row['id'] ?? 0] ?? []), $rows);
    }

    /**
     * @param array<int, array<string, float>> $dimensionTotals
     * @param array<string, float> $amounts
     */
    private function add(array &$dimensionTotals, ?int $id, array $amounts): void
    {
        if (!$id) {
            return;
        }

        foreach ($amounts as $field => $amount) {
            $dimensionTotals[$id][$field] = ($dimensionTotals[$id][$field] ?? 0) + $amount;
        }
    }

    /**
     * @param array<string, float> $fieldTotals
     * @return array<string, float>
     */
    private function toIntervals(array $fieldTotals): array
    {
        $values = [];
        foreach (DateIntervalEnum::cases() as $interval) {
            foreach ($fieldTotals as $field => $total) {
                $values[$field.'_'.$interval->value] = $total;
            }
        }

        return $values;
    }

    /**
     * @param array<int> $organisationIds
     */
    private function brandsQuery(array $organisationIds, bool $includePartners): Builder
    {
        $productBrand = "(select min(model_has_brands.brand_id) from model_has_brands where model_has_brands.model_type = 'Product' and model_has_brands.model_id = transactions.model_id)";

        $query = DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->select(DB::raw("$productBrand as brand_id"))
            ->where('transactions.model_type', 'Product')
            ->where('transactions.state', '!=', TransactionStateEnum::CANCELLED->value)
            ->whereNull('transactions.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.organisation_id', $organisationIds)
            ->whereIn('orders.state', array_map(fn (OrderStateEnum $state) => $state->value, self::STATES))
            ->when(!$includePartners, fn ($q) => $q->whereNull('orders.as_organisation_id'))
            ->groupBy(DB::raw('1'));

        foreach (self::AMOUNT_FIELDS as $amountField) {
            $query->addSelect(DB::raw("sum(transactions.$amountField) as $amountField"));
        }

        return $query;
    }
}
