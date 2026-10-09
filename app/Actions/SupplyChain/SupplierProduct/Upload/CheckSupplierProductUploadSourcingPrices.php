<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use App\Models\Masters\Competitor;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * INI-056: for a supplier in China, the frontier model searches the sourcing websites staff keep as
 * "Factory" competitors for each new product and reports the price range it found, with links, so the
 * buyer sees whether the unit cost is fair. Advice only: Import never waits for it. It spends what
 * the upload review left of the per upload limit and shares the review's monthly budget.
 */
class CheckSupplierProductUploadSourcingPrices
{
    use AsAction;
    use WithAIGateway;

    public const int MAX_ROWS             = 20;
    public const int MAX_SEARCHES_PER_ROW = 3;
    public const int MAX_OUTPUT_TOKENS    = 2_000;
    public const float ROW_COST_CEILING   = 0.25;
    public const int CACHE_DAYS           = 30;
    public const float OVERPAYING_ABOVE   = 1.3;
    public const float CHEAP_BELOW        = 0.5;

    public int $jobTimeout = 3000;

    public int $jobTries = 1;

    public function handle(Upload $upload): Upload
    {
        /** @var Supplier $supplier */
        $supplier = $upload->parent;
        $domains  = $this->sourcingDomains($supplier);

        if ($supplier->address?->country_code !== 'CN' || $domains === [] || !config('services.openrouter.api_key')) {
            $this->setState($upload, 'skipped');

            return $upload;
        }

        $records = $upload->records()->where('status', UploadRecordStatusEnum::PREVIEW)->orderBy('row_number')->get()
            ->filter(fn (UploadRecord $record) => !Arr::get($record->data, 'skip')
                && !Arr::get($record->values, 'supplier_product_id')
                && filled(Arr::get($record->values, 'unit_name'))
                && is_numeric(Arr::get($record->values, 'unit_cost'))
                && Arr::get($record->values, 'unit_cost') > 0
                && !collect(Arr::get($record->data, 'findings', []))->contains('level', 'error'))
            ->take(self::MAX_ROWS);

        if ($records->isEmpty()) {
            $this->setState($upload, 'skipped');

            return $upload;
        }

        $this->setState($upload, 'running');
        $budget   = ReviewSupplierProductUpload::MAX_UPLOAD_COST - (float)Arr::get($upload->data, 'review.cost', 0);
        $currency = $supplier->currency?->code ?? 'USD';

        try {
            foreach ($records as $record) {
                /** @var UploadRecord $record */
                $name     = $record->values['unit_name'];
                $cacheKey = 'supplier-product-sourcing-price:'.md5(mb_strtolower(trim($name)).'|'.$currency);
                $found    = Cache::get($cacheKey);

                if ($found === null) {
                    if ($budget < self::ROW_COST_CEILING || ReviewSupplierProductUpload::spentThisMonth() >= ReviewSupplierProductUpload::MONTHLY_BUDGET) {
                        $this->setState($upload, 'budget');

                        return $upload;
                    }

                    [$found, $cost] = $this->search($record->values, $currency, $domains);
                    $budget         -= $cost;
                    if ($found === null) {
                        continue;
                    }
                    Cache::put($cacheKey, $found, now()->addDays(self::CACHE_DAYS));
                }

                if (!UploadRecord::whereKey($record->id)->update(['data->sourcing' => static::verdict($found, (float)$record->values['unit_cost'], $currency)])) {
                    return $upload;
                }
            }
        } catch (Throwable $e) {
            Log::error('CheckSupplierProductUploadSourcingPrices: '.$e->getMessage());
            $this->setState($upload, 'failed');

            return $upload;
        }

        $this->setState($upload, 'done');

        return $upload;
    }

    public function jobFailed(Throwable $e, Upload $upload): void
    {
        $this->setState($upload, 'failed');
    }

    /**
     * @return list<string>
     */
    public function sourcingDomains(Supplier $supplier): array
    {
        return Competitor::where('group_id', $supplier->group_id)
            ->where('sells_to', CompetitorSellsToEnum::FACTORY)
            ->pluck('website')
            ->map(fn (string $website) => static::domain($website))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function domain(string $url): ?string
    {
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', mb_strtolower($host)) : null;
    }

    /**
     * @param array{low: ?float, high: ?float, links: list<array{url: string, title: string, price: ?float}>, note: ?string} $found
     *
     * @return array{status: string, low: ?float, high: ?float, currency: string, links: list<array{url: string, title: string, price: ?float}>, note: ?string}
     */
    public static function verdict(array $found, float $unitCost, string $currency): array
    {
        $status = match (true) {
            $found['low'] === null || $found['high'] === null  => 'unknown',
            $unitCost > $found['high'] * self::OVERPAYING_ABOVE => 'overpaying',
            $unitCost < $found['low'] * self::CHEAP_BELOW       => 'cheap',
            default                                             => 'ok',
        };

        return ['status' => $status, 'currency' => $currency] + Arr::only($found, ['low', 'high', 'links', 'note']);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $domains
     *
     * @return array{0: array{low: ?float, high: ?float, links: list<array{url: string, title: string, price: ?float}>, note: ?string}|null, 1: float}
     */
    protected function search(array $values, string $currency, array $domains): array
    {
        $product = json_encode(Arr::only($values, ['unit_name', 'unit_label', 'materials', 'unit_weight', 'unit_dimensions', 'tariff_code']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
A buyer is about to order this product from a supplier in China. Search the allowed wholesale websites for the same item, or the closest similar items, and report what one single unit costs there.

Product: {$product}

Wholesale prices depend on the order quantity: give the range from the largest quantity tier to the smallest, per single unit, in {$currency} (convert approximately if the site shows another currency). Ignore items that are clearly a different size, material or pack.

Answer with JSON only, no prose around it:
{"low": number or null, "high": number or null, "links": [{"url": "the page link exactly as found", "title": "its name", "price": price per unit in {$currency} or null}], "note": "one short sentence: how close the matches are and the order quantities behind the range"}
Use null for low and high when nothing comparable was found. At most 5 links.
PROMPT;

        try {
            $response = $this->aiRequest()
                ->connectTimeout(10)
                ->timeout(120)
                ->post('chat/completions', [
                    'model'      => ReviewSupplierProductUpload::MODEL,
                    'max_tokens' => self::MAX_OUTPUT_TOKENS,
                    'reasoning'  => ['effort' => 'low'],
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                    'tools'      => [[
                        'type'       => 'openrouter:web_search',
                        'parameters' => [
                            'max_uses'        => self::MAX_SEARCHES_PER_ROW,
                            'max_results'     => 5,
                            'allowed_domains' => $domains,
                        ],
                    ]],
                ]);
        } catch (Throwable $e) {
            Log::error('CheckSupplierProductUploadSourcingPrices: '.$e->getMessage());

            return [null, self::ROW_COST_CEILING];
        }

        if (!$response->successful()) {
            Log::error('CheckSupplierProductUploadSourcingPrices: '.$response->body());

            return [null, self::ROW_COST_CEILING];
        }

        $cost      = is_array($response->json('usage')) ? (float)$this->aiUsageCost($response->json('usage')) : self::ROW_COST_CEILING;
        $citations = collect($response->json('choices.0.message.annotations', []))->pluck('url_citation.url')->filter()->map(fn (string $url) => rtrim($url, '/'))->all();
        $answer    = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim((string)$response->json('choices.0.message.content')))), true);

        if (!is_array($answer)) {
            return [null, $cost];
        }

        return [static::parse($answer, $domains, $citations), $cost];
    }

    /**
     * Only links on a sourcing website, and among the search results when the answer cites any, are
     * kept, so a made-up link is never shown.
     *
     * @param array<string, mixed> $answer
     * @param list<string>         $domains
     * @param list<string>         $citations
     *
     * @return array{low: ?float, high: ?float, links: list<array{url: string, title: string, price: ?float}>, note: ?string}
     */
    public static function parse(array $answer, array $domains, array $citations): array
    {
        $number = fn ($value) => is_numeric($value) && $value > 0 ? round((float)$value, 4) : null;
        $low    = $number($answer['low'] ?? null);
        $high   = $number($answer['high'] ?? null);

        $links = collect(is_array($answer['links'] ?? null) ? $answer['links'] : [])
            ->filter(function ($link) use ($domains, $citations) {
                $url    = is_array($link) ? ($link['url'] ?? null) : null;
                $domain = is_string($url) ? static::domain($url) : null;

                return $domain
                    && collect($domains)->contains(fn (string $allowed) => $domain === $allowed || str_ends_with($domain, '.'.$allowed))
                    && ($citations === [] || in_array(rtrim($url, '/'), $citations, true));
            })
            ->map(fn (array $link) => ['url' => $link['url'], 'title' => mb_substr((string)($link['title'] ?? ''), 0, 200), 'price' => $number($link['price'] ?? null)])
            ->take(5)
            ->values()
            ->all();

        return [
            'low'   => $low !== null && $high !== null ? min($low, $high) : null,
            'high'  => $low !== null && $high !== null ? max($low, $high) : null,
            'links' => $links,
            'note'  => is_string($answer['note'] ?? null) ? mb_substr($answer['note'], 0, 300) : null,
        ];
    }

    protected function setState(Upload $upload, string $state): void
    {
        Upload::whereKey($upload->id)->update(['data->sourcing' => $state]);
    }
}
