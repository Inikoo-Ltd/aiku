<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Procurement\OrgPartner\GetPartnerOrderCapacity;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Enums\Catalogue\HealthRankEnum;
use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class SuggestPartnerShoppingList extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @return array{currency: string, budget: float, total: float, lines: array<int, array{org_stock_id: int, code: string, name: string, quantity: float, price_per_sko: float, cost: float, reason: string}>}
     */
    public function handle(OrgPartner $orgPartner, float $budget, ?string $instruction = null, ?string $bucket = null, ?string $rank = null): array
    {
        $candidates = $this->candidates($orgPartner);

        if ($bucket) {
            $scopedStockIds = array_flip(GetPartnerStockCoverBuckets::make()->stockIdsInBucket($orgPartner, $bucket, $rank));
            $candidates     = array_values(array_filter(
                $candidates,
                fn (array $candidate) => isset($scopedStockIds[$candidate['stock_id']])
            ));
        }

        $lines = $instruction && config('services.openai.api_key')
            ? $this->aiPick($candidates, $budget, $instruction)
            : [];

        if (empty($lines)) {
            $lines = $this->greedyFill($candidates, $budget);
        }

        $lines = $this->respectPartnerCap($orgPartner, $candidates, $lines);

        return [
            'currency' => $orgPartner->organisation->currency->code,
            'budget'   => $budget,
            'total'    => round(array_sum(array_column($lines, 'cost')), 2),
            'lines'    => $lines,
        ];
    }

    /**
     * Stop suggesting once the projected list value hits what the partner historically
     * delivers to us per month; A-rank and out-of-stock items are exempt from the cap.
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<int, array<string, mixed>> $lines
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rankPriority(?string $rank): int
    {
        return match ($rank) {
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 4,
            'Z' => 5,
            default => 3,
        };
    }

    protected function respectPartnerCap(OrgPartner $orgPartner, array $candidates, array $lines): array
    {
        $capacity = GetPartnerOrderCapacity::run($orgPartner);
        $cap      = $capacity['partner_capacity']['delivers_to_us_per_30d'];

        $candidatesById = collect($candidates)->keyBy('org_stock_id');
        $shareLeft      = $capacity['blocked']['warehouse_full']
            ? 0
            : max(0, $capacity['warehouse']['partner_share_limit'] - $capacity['warehouse']['partner_share_used']);

        $projected = $capacity['list']['value'];
        $kept      = [];
        foreach ($lines as $line) {
            $candidate = $candidatesById->get($line['org_stock_id'], []);

            if ($candidate['never_stocked'] ?? false) {
                if ($shareLeft <= 0) {
                    continue;
                }
                $shareLeft--;
            }

            if ($cap !== null && $projected >= $cap && !($candidate['cap_exempt'] ?? false)) {
                continue;
            }
            $projected += $line['cost'];
            $kept[]     = $line;
        }

        return $kept;
    }

    /**
     * Candidate partner SKOs with price, availability, buyer stock and average quarterly usage.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function candidates(OrgPartner $orgPartner): array
    {
        $rows = DB::table('org_stocks')
            ->leftJoin('org_stocks as buyer_org_stocks', function ($join) use ($orgPartner) {
                $join->on('buyer_org_stocks.stock_id', 'org_stocks.stock_id')
                    ->where('buyer_org_stocks.organisation_id', $orgPartner->organisation_id);
            })
            ->join('product_has_org_stocks', 'product_has_org_stocks.org_stock_id', 'org_stocks.id')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->leftJoin('org_stock_stats as buyer_stats', 'buyer_stats.org_stock_id', 'buyer_org_stocks.id')
            ->leftJoin('partner_shopping_list_items', function ($join) use ($orgPartner) {
                $join->on('partner_shopping_list_items.stock_id', 'org_stocks.stock_id')
                    ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
                    ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
                    ->whereNull('partner_shopping_list_items.deleted_at');
            })
            ->where('org_stocks.organisation_id', $orgPartner->partner_id)
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->whereNull('partner_shopping_list_items.id')
            ->where('org_stocks.quantity_available', '>', 0)
            ->select([
                'org_stocks.id',
                'org_stocks.stock_id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.quantity_available as partner_available',
                'buyer_org_stocks.id as buyer_org_stock_id',
                'buyer_org_stocks.quantity_available as buyer_available',
                'products.price as product_price',
                'product_has_org_stocks.quantity as skos_per_product_unit',
                'buyer_org_stocks.health_rank as buyer_health_rank',
                'buyer_stats.days_of_cover as buyer_days_of_cover',
                'buyer_stats.recommended_order_quantity as buyer_recommended',
            ])
            ->orderBy('org_stocks.id')
            ->get()
            ->unique('id')
            ->values();

        $usage = $this->buyerQuarterlyUsage($rows->pluck('buyer_org_stock_id')->filter()->all());

        $exchange = GetCurrencyExchange::run($orgPartner->partner->currency, $orgPartner->organisation->currency) ?? 1;

        return $rows->map(function ($row) use ($usage, $exchange) {
            $skosPerProductUnit = (float) $row->skos_per_product_unit > 0 ? (float) $row->skos_per_product_unit : 1;

            return [
                'org_stock_id'      => $row->id,
                'stock_id'          => (int) $row->stock_id,
                'code'              => $row->code,
                'name'              => $row->name,
                'partner_available' => (float) $row->partner_available,
                'buyer_available'   => (float) ($row->buyer_available ?? 0),
                'quarterly_usage'   => $usage[$row->buyer_org_stock_id] ?? 0.0,
                'cap_exempt'        => ($row->buyer_org_stock_id && (float) ($row->buyer_available ?? 0) <= 0) || $row->buyer_health_rank === HealthRankEnum::A->value,
                'health_rank'       => $row->buyer_health_rank,
                'never_stocked'     => $row->buyer_org_stock_id === null,
                'price_per_sko'     => round((float) $row->product_price * $exchange / $skosPerProductUnit, 4),
                'days_of_cover'     => $row->buyer_days_of_cover !== null ? (float) $row->buyer_days_of_cover : null,
                'recommended'       => $row->buyer_recommended !== null ? (float) $row->buyer_recommended : null,
            ];
        })->all();
    }

    /**
     * @param array<int, int> $buyerOrgStockIds
     *
     * @return array<int, float> average quarterly SKO usage per buyer org stock
     */
    public function buyerQuarterlyUsage(array $buyerOrgStockIds): array
    {
        if (empty($buyerOrgStockIds)) {
            return [];
        }

        $fromDispatches = DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $buyerOrgStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('org_stock_id, sum(quantity_dispatched) / 4.0 as avg_usage')
            ->groupBy('org_stock_id')
            ->pluck('avg_usage', 'org_stock_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $missing = array_diff($buyerOrgStockIds, array_keys($fromDispatches));
        if (empty($missing)) {
            return $fromDispatches;
        }

        return $fromDispatches + $this->usageFromTimeSeries(array_values($missing));
    }

    /**
     * @param array<int, int> $buyerOrgStockIds
     *
     * @return array<int, float>
     */
    protected function usageFromTimeSeries(array $buyerOrgStockIds): array
    {
        return DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records', 'org_stock_time_series_records.org_stock_time_series_id', 'org_stock_time_series.id')
            ->whereIn('org_stock_time_series.org_stock_id', $buyerOrgStockIds)
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::QUARTERLY->value)
            ->where('org_stock_time_series_records.from', '>=', now()->subMonths(15))
            ->selectRaw('org_stock_time_series.org_stock_id, avg(org_stock_time_series_records.sales_external + org_stock_time_series_records.sales_internal) as avg_usage')
            ->groupBy('org_stock_time_series.org_stock_id')
            ->pluck('avg_usage', 'org_stock_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * Lowest stock cover first, top up to one quarter of usage, until the budget runs out.
     *
     * @param array<int, array<string, mixed>> $candidates
     *
     * @return array<int, array<string, mixed>>
     */
    protected function greedyFill(array $candidates, float $budget): array
    {
        $ranked = collect($candidates)
            ->filter(fn ($candidate) => $candidate['quarterly_usage'] > 0 && $candidate['price_per_sko'] > 0)
            ->sortBy([
                fn ($a, $b) => ($this->rankPriority($a['health_rank'] ?? null) <=> $this->rankPriority($b['health_rank'] ?? null)),
                fn ($a, $b) => (($a['days_of_cover'] ?? PHP_INT_MAX) <=> ($b['days_of_cover'] ?? PHP_INT_MAX)),
            ]);

        $lines     = [];
        $remaining = $budget;

        foreach ($ranked as $candidate) {
            $target = $candidate['recommended'] !== null
                ? ceil($candidate['recommended'])
                : max(0.0, ceil($candidate['quarterly_usage'] - $candidate['buyer_available']));

            $quantity = min($target, $candidate['partner_available'], floor($remaining / $candidate['price_per_sko']));

            if ($quantity < 1) {
                continue;
            }

            $cost       = round($quantity * $candidate['price_per_sko'], 2);
            $remaining -= $cost;

            $lines[] = $this->line($candidate, $quantity, $this->reason($candidate));

            if ($remaining < 1) {
                break;
            }
        }

        return $lines;
    }

    /**
     * Same story the browse cards tell: sales per quarter, what we hold, when we run out.
     *
     * @param array<string, mixed> $candidate
     */
    protected function reason(array $candidate): string
    {
        $reason = sprintf(
            'Our sales/quarter ~%d · our stock %d',
            (int) round($candidate['quarterly_usage']),
            (int) floor($candidate['buyer_available']),
        );

        if ($candidate['days_of_cover'] !== null) {
            $reason .= $candidate['days_of_cover'] <= 0
                ? ' · we run out now'
                : sprintf(' · we run out in ~%d days', (int) round($candidate['days_of_cover']));
        }

        return $reason;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     *
     * @return array<int, array<string, mixed>>
     */
    protected function aiPick(array $candidates, float $budget, string $instruction): array
    {
        $catalogue = collect($candidates)->map(fn ($candidate) => [
            'id'                => $candidate['org_stock_id'],
            'code'              => $candidate['code'],
            'name'              => $candidate['name'],
            'price_per_sko'     => $candidate['price_per_sko'],
            'partner_available' => $candidate['partner_available'],
            'you_hold'          => $candidate['buyer_available'],
            'quarterly_usage'   => $candidate['quarterly_usage'],
            'days_until_out_of_stock' => $candidate['days_of_cover'],
            'recommended_order'       => $candidate['recommended'],
        ])->values()->all();

        $prompt = 'You are a purchasing assistant building an inter-company replenishment shopping list.'
            ."\nBudget: {$budget}. Instruction from the purchaser: {$instruction}"
            ."\nPick lines from this catalogue (quantities are whole SKOs, never exceed partner_available, total cost must stay within budget)."
            ."\nPrefer items with low days_until_out_of_stock and quantities near recommended_order unless the instruction says otherwise."
            ."\nReturn ONLY a JSON array: [{\"id\": <org_stock_id>, \"quantity\": <int>, \"reason\": \"<max 12 words>\"}]"
            ."\n\nCatalogue: ".json_encode($catalogue, JSON_UNESCAPED_UNICODE);

        $byId = collect($candidates)->keyBy('org_stock_id');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $content = Http::withToken(config('services.openai.api_key'))
                    ->timeout(300)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'            => 'gpt-5-nano',
                        'reasoning_effort' => 'low',
                        'messages'         => [['role' => 'user', 'content' => $prompt]],
                    ])
                    ->json('choices.0.message.content') ?? '';

                $start = strpos($content, '[');
                $end   = strrpos($content, ']');
                if ($start === false || $end === false) {
                    continue;
                }

                $items = json_decode(substr($content, $start, $end - $start + 1), true);
                if (!is_array($items)) {
                    continue;
                }

                $lines     = [];
                $remaining = $budget;
                foreach ($items as $item) {
                    $candidate = $byId->get($item['id'] ?? null);
                    if (!$candidate) {
                        continue;
                    }
                    $quantity = min(
                        floor((float) ($item['quantity'] ?? 0)),
                        $candidate['partner_available'],
                        floor($remaining / max($candidate['price_per_sko'], 0.0001))
                    );
                    if ($quantity < 1) {
                        continue;
                    }
                    $cost       = round($quantity * $candidate['price_per_sko'], 2);
                    $remaining -= $cost;
                    $lines[]    = $this->line($candidate, $quantity, (string) ($item['reason'] ?? ''));
                }

                return $lines;
            } catch (Throwable) {
                sleep(3);
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    protected function line(array $candidate, float $quantity, string $reason): array
    {
        return [
            'org_stock_id'  => $candidate['org_stock_id'],
            'code'          => $candidate['code'],
            'name'          => $candidate['name'],
            'quantity'      => $quantity,
            'price_per_sko' => $candidate['price_per_sko'],
            'cost'          => round($quantity * $candidate['price_per_sko'], 2),
            'reason'        => $reason,
        ];
    }

    public function rules(): array
    {
        return [
            'budget'      => ['required', 'numeric', 'min:1'],
            'instruction' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bucket'      => ['sometimes', 'nullable', Rule::in(array_keys(GetPartnerStockCoverBuckets::BUCKETS))],
            'rank'        => ['sometimes', 'nullable', Rule::in(['A', 'B', 'C', 'D', 'Z'])],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle(
            $orgPartner,
            (float) $this->validatedData['budget'],
            $this->validatedData['instruction'] ?? null,
            $this->validatedData['bucket'] ?? null,
            $this->validatedData['rank'] ?? null
        );
    }

    public function action(OrgPartner $orgPartner, float $budget, ?string $instruction = null): array
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, ['budget' => $budget, 'instruction' => $instruction]);

        return $this->handle($orgPartner, $budget, $instruction);
    }
}
