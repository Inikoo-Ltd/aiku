<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Models\Goods\StockFamily;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * One frontier model call over the whole upload preview: a short summary for the top of the page and a
 * concrete suggestion for each row that looks wrong. Kept cheap: at most MAX_UPLOAD_COST per upload
 * (only flagged rows are sent when the whole sheet would cost more) and off once MONTHLY_BUDGET is spent.
 */
class ReviewSupplierProductUpload
{
    use AsAction;
    use WithAIGateway;

    public const string MODEL = 'anthropic/claude-fable-5.1';

    public const float INPUT_PRICE_PER_TOKEN  = 10 / 1_000_000;
    public const float OUTPUT_PRICE_PER_TOKEN = 50 / 1_000_000;
    public const int MAX_OUTPUT_TOKENS        = 8_000;
    public const float MAX_UPLOAD_COST        = 2.0;
    public const float MONTHLY_BUDGET         = 40.0;

    protected float $lastCost = 0.0;

    public function handle(Upload $upload): Upload
    {
        $rows = $upload->records()->where('status', UploadRecordStatusEnum::PREVIEW)->orderBy('row_number')->get()
            ->reject(fn (UploadRecord $record) => Arr::get($record->data, 'skip'))
            ->map(fn (UploadRecord $record) => ['row' => $record->row_number, 'values' => $record->values, 'findings' => Arr::get($record->data, 'findings', [])])
            ->values();

        /** @var Supplier $supplier */
        $supplier = $upload->parent;

        return $this->saveReview($upload, $this->review($supplier, $rows));
    }

    /**
     * The review of some rows (an upload, or the one product of the New supplier product form).
     *
     * @param Collection<int, array{row: int, values: array<string, mixed>, findings: list<array{level: string, message: string}>}> $rows
     *
     * @return array{status: string, note?: string, partial?: bool, summary?: ?string, rows?: array<string, string>}
     */
    public function review(Supplier $supplier, Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['status' => 'skipped'];
        }

        if (static::spentThisMonth() >= self::MONTHLY_BUDGET) {
            return ['status' => 'off', 'note' => __('AI final review is off this month: the monthly budget is used up.')];
        }

        $prompt  = $this->prompt($supplier, $rows);
        $partial = false;

        if ($this->estimatedCost($prompt) > self::MAX_UPLOAD_COST) {
            $flagged = $rows->filter(fn (array $row) => collect($row['findings'])->whereIn('level', ['error', 'block', 'link', 'warning'])->isNotEmpty())->values();
            $prompt  = $this->prompt($supplier, $flagged);
            $partial = true;
            if ($this->estimatedCost($prompt) > self::MAX_UPLOAD_COST) {
                return ['status' => 'off', 'note' => __('AI final review skipped: this sheet is too big for the per upload limit.')];
            }
        }

        $answer = $this->ask($prompt);
        if ($answer === null) {
            return ['status' => 'failed', 'note' => __('AI final review could not run.'), 'cost' => $this->lastCost];
        }

        return [
            'status'  => 'done',
            'partial' => $partial,
            'cost'    => $this->lastCost,
            'summary' => is_array(Arr::get($answer, 'summary')) ? implode("\n", Arr::get($answer, 'summary')) : Arr::get($answer, 'summary'),
            'rows'    => collect(Arr::get($answer, 'rows', []))->filter(fn ($suggestion) => is_string($suggestion) && $suggestion !== '')->all(),
        ];
    }

    /**
     * @param Collection<int, array{row: int, values: array<string, mixed>, findings: list<array{level: string, message: string}>}> $rows
     */
    protected function prompt(Supplier $supplier, Collection $rows): string
    {
        $rows = $rows->map(fn (array $row) => [
            'row'      => $row['row'],
            'values'   => Arr::except($row['values'], ['trade_unit_id', 'stock_id', 'supplier_product_id']),
            'findings' => collect($row['findings'])->map(fn (array $finding) => $finding['level'].': '.$finding['message'])->values()->all(),
        ])->values();

        $families = $rows->pluck('values.family')->filter()->unique()->mapWithKeys(fn (string $family) => [
            $family => StockFamily::where('group_id', $supplier->group_id)->whereRaw('lower(code) = lower(?)', [$family])->first()?->stocks()->latest('id')->limit(12)->pluck('name')->filter()->values()->all() ?? [],
        ])->all();

        $supplierProducts = $supplier->supplierProducts()->latest('id')->limit(30)->get(['code', 'name', 'cost', 'units_per_pack', 'units_per_carton', 'data'])
            ->map(fn ($supplierProduct) => [
                'code'              => $supplierProduct->code,
                'name'              => $supplierProduct->name,
                'cost'              => (float)$supplierProduct->cost,
                'units_per_sko'     => $supplierProduct->units_per_pack,
                'units_per_carton'  => $supplierProduct->units_per_carton,
                'recommended_price' => Arr::get($supplierProduct->data, 'seed.recommended_price'),
            ])->all();

        $context = json_encode([
            'supplier'                        => ['name' => $supplier->name, 'currency' => $supplier->currency?->code],
            'units'                           => 'unit_cost and unit_expense in the supplier currency; recommended_price/rrp in GBP, *_eur in EUR; weights in grams; dimensions in cm; carton_cbm in m3; extra_costs is a fraction (0.4 = 40%)',
            'targets'                         => 'our margin on the wholesale price at least 60% of the price after landed cost; retailer margin (RRP vs price) usually about 58%, at least 50%',
            'other_products_in_the_families'  => $families,
            'this_suppliers_existing_products' => $supplierProducts,
            'rows'                            => $rows->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You review a supplier's new product sheet before a buyer imports it into our wholesale catalogue. Each row is one product: a trade unit (one single item sold to shoppers), packed into an SKO (units_per_sko units) that the warehouse picks, sold in outers of skos_per_outer SKOs, shipped in cartons of skos_per_carton SKOs.

Automatic checks already ran; their findings are listed per row. Look for what they could not see: names that are not one single item, spelling mistakes, materials or numbers that do not fit the item, prices that look off against similar products, pack sizes that are awkward to pick and sell, rows that look shifted or copy-pasted, and anything that contradicts the supplier's existing products.

Answer with JSON only, no prose around it:
{"summary": "3 to 6 short lines for the buyer, most important first", "rows": {"<row number>": "one or two sentences: what is wrong and the exact fix (e.g. the corrected name or number)"}}
Only include rows that need a change. Be concrete and brief. Do not repeat findings that are already listed unless you can give the fix.

Data:
{$context}
PROMPT;
    }

    protected function estimatedCost(string $prompt): float
    {
        return (mb_strlen($prompt) / 3.5) * self::INPUT_PRICE_PER_TOKEN + self::MAX_OUTPUT_TOKENS * self::OUTPUT_PRICE_PER_TOKEN;
    }

    /**
     * The upload review and the sourcing price check share one monthly budget.
     */
    public static function spentThisMonth(): float
    {
        return (float)DB::table('ai_usages')
            ->whereIn('feature', [class_basename(self::class), class_basename(CheckSupplierProductUploadSourcingPrices::class)])
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function ask(string $prompt): ?array
    {
        $apiKey = config('services.openrouter.api_key');
        if (!$apiKey) {
            return null;
        }

        try {
            $response = $this->aiRequest($apiKey)
                ->connectTimeout(10)
                ->timeout(600)
                ->post('chat/completions', [
                    'model'      => self::MODEL,
                    'max_tokens' => self::MAX_OUTPUT_TOKENS,
                    'reasoning'  => ['effort' => 'medium'],
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]);
        } catch (Throwable $e) {
            Log::error('ReviewSupplierProductUpload: '.$e->getMessage());

            return null;
        }

        if (!$response->successful()) {
            Log::error('ReviewSupplierProductUpload: '.$response->body());

            return null;
        }

        $this->lastCost = (float)$this->aiUsageCost($response->json('usage') ?? []);

        $content = $response->json('choices.0.message.content');
        if (!is_string($content)) {
            return null;
        }

        $decoded = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim($content))), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $review
     */
    protected function saveReview(Upload $upload, array $review): Upload
    {
        $upload->refresh();
        $upload->update(['data' => array_merge($upload->data ?? [], ['review' => $review])]);

        return $upload;
    }
}
