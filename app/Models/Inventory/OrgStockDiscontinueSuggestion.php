<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockDiscontinueSuggestionReasonEnum;
use App\Enums\Inventory\OrgStock\OrgStockDiscontinueSuggestionSourceEnum;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One member of staff saying that a product should be discontinued and why: a buyer while ordering,
 * customer services while handling a claim, goods in while booking in a delivery. Nothing
 * changes when it is stored: the suggestions pile up per stock across the group, and the count of
 * suggestions and of different people behind them is the evidence a Supply Chain Manager acts on.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_stock_id
 * @property int|null $stock_id
 * @property int|null $user_id
 * @property OrgStockDiscontinueSuggestionSourceEnum $source
 * @property array<int, string> $reasons
 * @property string $note
 */
class OrgStockDiscontinueSuggestion extends Model
{
    use InGroup;

    public const int NOTES_SHOWN = 20;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'source'  => OrgStockDiscontinueSuggestionSourceEnum::class,
        ];
    }

    /**
     * @return Collection<int, array{times: int, people: int}> keyed by org stock id, counting the whole group
     */
    public static function tallyForOrgStocks(Collection|array $orgStockIds): Collection
    {
        $orgStockIds = collect($orgStockIds)->filter()->unique()->values();
        if ($orgStockIds->isEmpty()) {
            return collect();
        }

        return DB::table('org_stocks')
            ->join('org_stock_discontinue_suggestions as s', fn ($join) => $join
                ->on('s.org_stock_id', 'org_stocks.id')
                ->orOn('s.stock_id', 'org_stocks.stock_id'))
            ->whereIn('org_stocks.id', $orgStockIds)
            ->groupBy('org_stocks.id')
            ->selectRaw('org_stocks.id, count(distinct s.id) as times, count(distinct s.user_id) as people')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => ['times' => (int) $row->times, 'people' => (int) $row->people]]);
    }

    /**
     * Stocks already being discontinued are left out: the decision the suggestions asked for has been taken.
     *
     * @return array<int, array{times: int, people: int}> keyed by stock id
     */
    public static function tallyByStock(int $groupId): array
    {
        return DB::table('org_stock_discontinue_suggestions')
            ->join('stocks', 'stocks.id', 'org_stock_discontinue_suggestions.stock_id')
            ->where('org_stock_discontinue_suggestions.group_id', $groupId)
            ->whereNotIn('stocks.state', [StockStateEnum::DISCONTINUING->value, StockStateEnum::DISCONTINUED->value])
            ->groupBy('org_stock_discontinue_suggestions.stock_id')
            ->selectRaw('org_stock_discontinue_suggestions.stock_id, count(*) as times, count(distinct org_stock_discontinue_suggestions.user_id) as people')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->stock_id => ['times' => (int) $row->times, 'people' => (int) $row->people]])
            ->all();
    }

    /**
     * @return array<int, array{sources: array<int, array{label: string, times: int}>, reasons: array<int, array{label: string, times: int}>, notes: array<int, array{user: ?string, organisation: string, source: string, reasons: array<int, string>, note: string, date: string}>}> keyed by stock id
     */
    public static function evidenceForStocks(array $stockIds): array
    {
        $labels  = OrgStockDiscontinueSuggestionReasonEnum::labels();
        $sources = OrgStockDiscontinueSuggestionSourceEnum::labels();

        return self::whereIn('stock_id', $stockIds)
            ->leftJoin('users', 'users.id', 'org_stock_discontinue_suggestions.user_id')
            ->join('organisations', 'organisations.id', 'org_stock_discontinue_suggestions.organisation_id')
            ->orderByDesc('org_stock_discontinue_suggestions.id')
            ->get(['org_stock_discontinue_suggestions.*', 'users.contact_name as user_name', 'organisations.code as organisation_code'])
            ->groupBy('stock_id')
            ->map(fn (Collection $suggestions) => [
                'sources' => $suggestions->countBy(fn (self $suggestion) => $suggestion->source->value)
                    ->map(fn (int $times, string $source) => ['label' => $sources[$source], 'times' => $times])
                    ->values()
                    ->all(),
                'reasons' => $suggestions->flatMap(fn (self $suggestion) => $suggestion->reasons)
                    ->countBy()
                    ->sortDesc()
                    ->map(fn (int $times, string $reason) => ['label' => $labels[$reason] ?? $reason, 'times' => $times])
                    ->values()
                    ->all(),
                'notes'   => $suggestions->take(self::NOTES_SHOWN)->map(fn (self $suggestion) => [
                    'user'         => $suggestion->user_name,
                    'organisation' => $suggestion->organisation_code,
                    'source'       => $sources[$suggestion->source->value],
                    'reasons'      => array_map(fn (string $reason) => $labels[$reason] ?? $reason, $suggestion->reasons),
                    'note'         => $suggestion->note,
                    'date'         => $suggestion->created_at->toIso8601String(),
                ])->values()->all(),
            ])
            ->all();
    }
}
