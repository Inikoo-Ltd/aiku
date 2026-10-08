<?php

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductUploadWithJev;
use App\Actions\SupplyChain\SupplierProduct\Upload\ReviewSupplierProductUpload;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The AI step of the New supplier product form, run once when the buyer presses Save: Jev's questions and the
 * frontier model review, the same ones an upload row gets. It runs in the background (it can take longer than
 * a web request may) and keeps its result in the cache; the form polls for it and the final save reuses it.
 */
class ReviewSupplierProductForm
{
    use AsAction;

    public const string CACHE_PREFIX = 'supplier-product-form-review:';

    public int $jobTimeout = 900;

    public int $jobTries = 1;

    /**
     * @param array{values: array<string, mixed>, findings: list<array{level: string, code: string, column: ?string, message: string}>} $row
     */
    public function start(Supplier $supplier, array $row): string
    {
        $reviewId = (string)Str::uuid();
        Cache::put(self::CACHE_PREFIX.$reviewId, ['status' => 'running', 'supplier_id' => $supplier->id, 'values' => $row['values']], now()->addHours(4));

        self::dispatch($supplier, $reviewId, $row);

        return $reviewId;
    }

    /**
     * @param array{values: array<string, mixed>, findings: list<array{level: string, code: string, column: ?string, message: string}>} $row
     */
    public function handle(Supplier $supplier, string $reviewId, array $row): void
    {
        try {
            $jevFindings = CheckSupplierProductUploadWithJev::make()->checkValues($supplier->group_id, $row['values'], $row['findings']);
        } catch (Throwable $e) {
            Log::error('ReviewSupplierProductForm: '.$e->getMessage());
            $jevFindings = [$this->notRunFinding()];
        }

        $review = ReviewSupplierProductUpload::make()->review($supplier, collect([[
            'row'      => 1,
            'values'   => $row['values'],
            'findings' => [...$row['findings'], ...$jevFindings],
        ]]));

        $this->finish($reviewId, [
            'findings'   => $jevFindings,
            'summary'    => Arr::get($review, 'summary'),
            'suggestion' => Arr::get($review, 'rows.1'),
            'note'       => Arr::get($review, 'note'),
        ]);
    }

    public function jobFailed(Throwable $e, Supplier $supplier, string $reviewId, array $row): void
    {
        $this->finish($reviewId, ['findings' => [$this->notRunFinding()]]);
    }

    /**
     * Adds the review's findings to a fresh rule check. A review only counts for the Part reference it checked.
     * When the buyer changes a field after the review, a warning about it is left out (the AI only saw the old
     * value) but a block stays, marked as about the earlier value, so it still needs "I accept responsibility".
     *
     * @param array{values: array<string, mixed>, findings: list<array<string, mixed>>} $row
     *
     * @return array{0: array{values: array<string, mixed>, findings: list<array<string, mixed>>}, 1: ?array<string, mixed>}
     */
    public function applyReview(Supplier $supplier, array $row, ?string $reviewId): array
    {
        $review = $reviewId ? Cache::get(self::CACHE_PREFIX.$reviewId) : null;
        if (($review['supplier_id'] ?? null) !== $supplier->id
            || mb_strtolower((string)Arr::get($review, 'values.part_reference')) !== mb_strtolower((string)Arr::get($row['values'], 'part_reference'))) {
            return [$row, null];
        }

        $isStale = fn (array $finding) => $finding['column'] !== null
            && Arr::get($review['values'], $finding['column']) !== Arr::get($row['values'], $finding['column']);

        $aiFindings = collect(Arr::get($review, 'findings', []))
            ->reject(fn (array $finding) => $isStale($finding) && $finding['level'] === 'warning')
            ->map(fn (array $finding) => $isStale($finding) ? array_merge($finding, ['message' => __('About the earlier value: :message', ['message' => $finding['message']])]) : $finding)
            ->values()
            ->all();

        $row['findings'] = [...$row['findings'], ...$aiFindings];

        return [$row, $review];
    }

    public function forget(string $reviewId): void
    {
        Cache::forget(self::CACHE_PREFIX.$reviewId);
    }

    protected function finish(string $reviewId, array $result): void
    {
        $key = self::CACHE_PREFIX.$reviewId;
        Cache::put($key, array_merge(Cache::get($key, []), $result, ['status' => 'done']), now()->addHours(4));
    }

    /**
     * @return array{level: string, code: string, column: ?string, message: string, source: string}
     */
    protected function notRunFinding(): array
    {
        return ['level' => 'block', 'code' => 'ai_checks_not_run', 'column' => null, 'message' => __('The AI checks could not run on this product.'), 'source' => 'jev'];
    }
}
