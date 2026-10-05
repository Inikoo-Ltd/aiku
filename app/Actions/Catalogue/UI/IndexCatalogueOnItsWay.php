<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\UI;

use App\Actions\Catalogue\Shop\UI\GetCatalogueOnItsWay;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use Closure;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The incoming stock list as a standard table. The rows are worked out in memory (they mix purchase
 * orders, deliveries and partner requests), so the pill filters, search, sorting and paging are
 * applied here to the collection. Filters on a line (company, source, supplier) drop the other
 * lines of a row and recount it; the others keep or drop whole rows. Each filter's counts take
 * every other active filter into account.
 */
class IndexCatalogueOnItsWay
{
    use AsObject;

    private const array SORTS = ['code', 'quantity', 'eta', 'suppliers'];

    private const array LINE_FACETS = ['organisation', 'source', 'supplier'];

    private const array ROW_FACETS = ['catalogue', 'product_state', 'arrival'];

    public function handle(Shop|MasterShop $parent, string $prefix): AnonymousResourceCollection
    {
        $rows   = collect(GetCatalogueOnItsWay::run($parent));
        $active = collect([...self::LINE_FACETS, ...self::ROW_FACETS])
            ->mapWithKeys(fn (string $facet) => [$facet => request()->input("{$prefix}_facets.$facet") ?: null])
            ->all();

        $filtered = $this->filter($rows, $active);

        if ($search = mb_strtolower(trim((string) request()->input("{$prefix}_filter.global")))) {
            $filtered = $filtered->filter(fn (array $row) => collect([$row['code'], $row['name'], $row['suppliers'], ...collect($row['lines'])->pluck('supplier_name')])
                ->merge(collect($row['products'])->flatMap(fn (array $product) => [$product['code'], $product['name']]))
                ->contains(fn (?string $text) => str_contains(mb_strtolower((string) $text), $search)));
        }

        $sort       = (string) request()->input("{$prefix}_sort", 'eta');
        $descending = str_starts_with($sort, '-');
        $sortKey    = in_array(ltrim($sort, '-'), self::SORTS) ? ltrim($sort, '-') : 'eta';
        $filtered   = $filtered->sortBy(fn (array $row) => $sortKey === 'eta' ? [$row['eta'] === null, $row['eta']] : $row[$sortKey], SORT_REGULAR, $descending)->values();

        $perPage = (int) request()->input("{$prefix}_perPage", config('ui.table.records_per_page'));
        $perPage = max(config('ui.table.min_records_per_page'), min(config('ui.table.max_records_per_page'), $perPage));
        $page    = max(1, (int) request()->input("{$prefix}Page", 1));

        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => "{$prefix}Page"]
        );

        return JsonResource::collection($paginator)->additional([
            'facets'        => $this->facets($rows, $active, $parent),
            'active_facets' => $active,
        ]);
    }

    public function tableStructure(Shop|MasterShop $parent, string $prefix): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('Nothing on its way'),
                    'description' => __('No purchase orders, deliveries or partner requests match.'),
                ])
                ->column(key: 'code', label: __('SKO'), sortable: true)
                ->column(key: 'products', label: $parent instanceof MasterShop ? __('Master product') : __('Product'))
                ->column(key: 'suppliers', label: __('Supplier'), sortable: true)
                ->column(key: 'lines', label: __('On order'))
                ->column(key: 'quantity', label: __('SKOs coming'), sortable: true, align: 'right')
                ->column(key: 'eta', label: __('Earliest arrival'), sortable: true, align: 'right')
                ->defaultSort('eta');
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>> $rows
     * @param  array<string, string|null> $active
     * @return Collection<int, array<string, mixed>>
     */
    private function filter(Collection $rows, array $active): Collection
    {
        return $rows
            ->map(function (array $row) use ($active) {
                $lines = collect($row['lines'])->filter(fn (array $line) => collect(self::LINE_FACETS)
                    ->every(fn (string $facet) => $active[$facet] === null || $this->lineValue($facet, $line) === $active[$facet]))->values();

                if ($lines->isEmpty()) {
                    return null;
                }

                return array_merge($row, [
                    'lines'       => $lines->all(),
                    'quantity'    => round($lines->sum('quantity'), 3),
                    'eta'         => $lines->first()['eta'],
                    'is_estimate' => $lines->first()['is_estimate'],
                    'suppliers'   => $lines->pluck('supplier_code')->filter()->unique()->implode(', '),
                    'supplier_list' => $lines->filter(fn (array $line) => $line['supplier_code'])->unique('supplier_code')
                        ->map(fn (array $line) => ['code' => $line['supplier_code'], 'name' => $line['supplier_name'], 'is_agent' => $line['supplier_type'] === 'OrgAgent'])
                        ->values()->all(),
                ]);
            })
            ->filter()
            ->filter(fn (array $row) => collect(self::ROW_FACETS)
                ->every(fn (string $facet) => $active[$facet] === null || in_array($active[$facet], $this->rowValues($facet, $row))))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>> $rows
     * @param  array<string, string|null> $active
     * @return array<string, array<int, array{value: string, label: string, count: int}>>
     */
    private function facets(Collection $rows, array $active, Shop|MasterShop $parent): array
    {
        $labels = [
            'catalogue'     => ['in_catalogue' => __('In catalogue'), 'not_in_catalogue' => __('Not in catalogue yet')],
            'source'        => ['purchase_order' => __('Purchase order'), 'stock_delivery' => __('Stock delivery'), 'partner_request' => __('Partner request')],
            'arrival'       => ['week' => __('Within a week'), 'month' => __('Within a month'), 'later' => __('Later'), 'no_date' => __('No date')],
        ];

        $facets = [];
        foreach ([...self::ROW_FACETS, ...self::LINE_FACETS] as $facet) {
            $others = $this->filter($rows, array_merge($active, [$facet => null]));

            $counts = $others->flatMap(fn (array $row) => in_array($facet, self::LINE_FACETS)
                ? collect($row['lines'])->map(fn (array $line) => $this->lineValue($facet, $line))->filter()->unique()->values()->all()
                : $this->rowValues($facet, $row))->countBy();

            $order = array_keys($labels[$facet] ?? []);

            $facets[$facet] = $counts
                ->map(fn (int $count, string $value) => [
                    'value' => $value,
                    'label' => $labels[$facet][$value] ?? $this->valueLabel($facet, $value, $others),
                    'count' => $count,
                ])
                ->sortBy(fn (array $option) => $order ? array_search($option['value'], $order) : mb_strtolower($option['label']))
                ->values()
                ->all();
        }

        if ($parent instanceof Shop) {
            unset($facets['organisation']);
        }

        return $facets;
    }

    private function lineValue(string $facet, array $line): ?string
    {
        return match ($facet) {
            'organisation' => $line['organisation_slug'],
            'source'       => $line['type'],
            'supplier'     => $line['supplier_code'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function rowValues(string $facet, array $row): array
    {
        return match ($facet) {
            'catalogue'     => [$row['products'] ? 'in_catalogue' : 'not_in_catalogue'],
            'product_state' => collect($row['products'])->pluck('state')->unique()->values()->all(),
            'arrival'       => [match (true) {
                $row['eta'] === null                            => 'no_date',
                $row['eta'] <= now()->addWeek()->toDateString()  => 'week',
                $row['eta'] <= now()->addMonth()->toDateString() => 'month',
                default                                         => 'later',
            }],
        };
    }

    private function valueLabel(string $facet, string $value, Collection $rows): string
    {
        return match ($facet) {
            'organisation'  => mb_strtoupper($value),
            'supplier'      => $value,
            'product_state' => $rows->flatMap(fn (array $row) => $row['products'])->firstWhere('state', $value)['state_label'] ?? $value,
            default         => $value,
        };
    }
}
