<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Helpers\AI\AskToAi;
use App\Actions\Retina\Ecom\Basket\GetRetinaProductBasketRecommendations;
use App\Actions\Retina\UI\Dashboard\GetRetinaB2BDashboardInsights;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Exceptions\AICreditException;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The weekly "Suggested for your shop" list of a wholesale customer who ordered in the last 90 days.
 *
 * Customers are split in two halves to measure the AI against the rule: the bought_together half keeps
 * the products bought together with their top products; the ai half gets 12 products the AI picks, with
 * a one line reason each, from a pool we build: bought together, new launches in the departments they
 * buy from and what sold best in the coming weeks last year. The AI only picks from that pool, so every
 * suggestion is in stock, on the website and allowed for the customer when the list is made.
 */
class GenerateCustomerProductSuggestions
{
    use AsAction;

    public string $commandSignature = 'customers:product-suggestions {--customer= : customer id, run now instead of queueing every active customer}';

    public string $commandDescription = 'Make the weekly suggested products of active wholesale customers (half by AI, half bought together)';

    public string $jobQueue = 'low-priority';

    public int $jobTries = 1;

    public int $jobTimeout = 120;

    public const string ARM_AI = 'ai';
    public const string ARM_BOUGHT_TOGETHER = 'bought_together';

    public const int ACTIVE_DAYS = 90;
    public const int SUGGESTIONS = 12;
    private const int MIN_SUGGESTIONS = 6;

    private const int BOUGHT_TOGETHER_POOL = 40;
    private const int NEW_LAUNCHES_POOL = 15;
    private const int NEW_LAUNCH_DAYS = 60;
    private const int SEASONAL_POOL = 15;
    private const int SEASONAL_WINDOW_DAYS = 45;

    public const string MODEL = 'deepseek/deepseek-v4.1-flash';

    /**
     * ponytail: split by customer id parity, stable across weeks; a seeded hash if parity ever correlates with something.
     */
    public static function arm(int $customerId): string
    {
        return $customerId % 2 === 0 ? self::ARM_AI : self::ARM_BOUGHT_TOGETHER;
    }

    public function handle(Customer $customer): void
    {
        $insights     = GetRetinaB2BDashboardInsights::make();
        $productSales = $insights->getProductSales($customer, now()->subYear());

        if ($productSales->isEmpty()) {
            return;
        }

        $arm         = self::arm($customer->id);
        $boughtIds   = $productSales->pluck('product_id')->all();
        $boughtTogether = GetRetinaProductBasketRecommendations::make()->handle(
            $customer->shop,
            $productSales->take(GetRetinaB2BDashboardInsights::REGULAR_PRODUCTS)->pluck('product_id')->all(),
            [
                'prefer_cheaper'      => false,
                'exclude_product_ids' => $boughtIds,
                'customer_id'         => $customer->id,
                'limit'               => $arm === self::ARM_AI ? self::BOUGHT_TOGETHER_POOL : self::SUGGESTIONS,
            ]
        );

        $suggestions = $boughtTogether->take(self::SUGGESTIONS)->map(fn (Product $product) => ['id' => $product->id, 'reason' => null])->all();
        $model       = null;

        if ($arm === self::ARM_AI) {
            try {
                $aiSuggestions = $this->getAiSuggestions($customer, $productSales, $boughtTogether, $boughtIds);
            } catch (AICreditException) {
                $aiSuggestions = null;
            }
            if ($aiSuggestions) {
                $suggestions = $aiSuggestions;
                $model       = self::MODEL;
            }
        }

        $this->store($customer, $arm, $suggestions, $model);
    }

    /**
     * @return array<int, array{id: int, reason: ?string}>|null null when the AI gave too few usable picks
     */
    private function getAiSuggestions(Customer $customer, Collection $productSales, Collection $boughtTogether, array $boughtIds): ?array
    {
        $departmentIds = DB::table('products')
            ->whereIn('id', array_slice($boughtIds, 0, 100))
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id')
            ->all();

        $sources = [];
        foreach ($boughtTogether as $product) {
            $sources[$product->id] = "bought together with their products in {$product->co_orders} orders";
        }
        foreach ($this->getNewLaunchIds($customer, $departmentIds, $boughtIds) as $productId) {
            $sources[$productId] ??= 'new launch';
        }
        foreach ($this->getSeasonalIds($customer, $departmentIds, $boughtIds) as $productId) {
            $sources[$productId] ??= 'sold well in the coming weeks last year';
        }

        if (count($sources) < self::MIN_SUGGESTIONS) {
            return null;
        }

        $reply = AskToAi::run($this->prompt($customer, $productSales, $sources), self::MODEL);

        $picks = json_decode(preg_replace('/^```(json)?|```$/m', '', (string) $reply), true);

        $suggestions = collect($picks['suggestions'] ?? [])
            ->filter(fn ($pick) => is_array($pick) && isset($sources[(int) ($pick['id'] ?? 0)]))
            ->unique(fn ($pick) => (int) $pick['id'])
            ->take(self::SUGGESTIONS)
            ->map(fn ($pick) => ['id' => (int) $pick['id'], 'reason' => is_string($pick['reason'] ?? null) ? mb_substr(trim($pick['reason']), 0, 160) ?: null : null])
            ->values()
            ->all();

        return count($suggestions) >= self::MIN_SUGGESTIONS ? $suggestions : null;
    }

    private function prompt(Customer $customer, Collection $productSales, array $sources): string
    {
        $shop     = $customer->shop;
        $language = $shop->language?->name ?? 'English';

        $topProducts = $this->describeProducts($productSales->take(GetRetinaB2BDashboardInsights::REGULAR_PRODUCTS)->pluck('product_id')->all());
        $ordersByProduct = $productSales->pluck('orders', 'product_id');

        $bought = $topProducts->map(fn ($product) => "- $product->code | $product->name | family: $product->family | ordered in {$ordersByProduct[$product->id]} orders")->implode("\n");

        $candidates = $this->describeProducts(array_keys($sources))
            ->map(fn ($product) => "- id $product->id | $product->code | $product->name | family: $product->family | ".$sources[$product->id])
            ->implode("\n");

        $profile = $this->getBusinessProfile($customer) ?: 'not given';
        $count   = self::SUGGESTIONS;

        return <<<PROMPT
You choose products for a wholesale customer of {$shop->name} to add to the range of their shop.

About their business (their own answers when they signed up):
$profile

What they order most (last 12 months):
$bought

Candidate products, none of which they have bought before:
$candidates

Pick the $count candidates most likely to sell in their shop: products that fit their type of business and fill a gap in their range, the ones bought together with what they order, new launches that suit their range and what sells in the coming weeks. Prefer variety, at most two from the same family.
For each pick write a short reason for the customer, at most 90 characters, in $language, addressed to them ("your shop"), naming one of their products (by its name, never its code) or their kind of business when it helps. Do not mention prices, discounts or stock.

Reply with JSON only: {"suggestions":[{"id":123,"reason":"..."}]}
PROMPT;
    }

    private function describeProducts(array $productIds): Collection
    {
        return DB::table('products')
            ->leftJoin('product_categories as families', 'families.id', '=', 'products.family_id')
            ->whereIn('products.id', $productIds)
            ->get(['products.id', 'products.code', 'products.name', 'families.name as family'])
            ->sortBy(fn ($product) => array_search($product->id, $productIds))
            ->values();
    }

    private function getBusinessProfile(Customer $customer): string
    {
        return DB::table('poll_replies')
            ->join('polls', 'polls.id', '=', 'poll_replies.poll_id')
            ->leftJoin('poll_options', 'poll_options.id', '=', 'poll_replies.poll_option_id')
            ->where('poll_replies.customer_id', $customer->id)
            ->get(['polls.label as question', 'poll_options.label as option', 'poll_replies.value'])
            ->map(fn ($reply) => '- '.$reply->question.': '.mb_substr(trim((string) ($reply->option ?? $reply->value)), 0, 300))
            ->filter(fn ($line) => !str_ends_with($line, ': '))
            ->implode("\n");
    }

    private function forSaleTo(Customer $customer, array $departmentIds, array $boughtIds): Builder
    {
        return Product::query()
            ->where('products.shop_id', $customer->shop_id)
            ->whereIn('products.department_id', $departmentIds)
            ->whereNotIn('products.id', $boughtIds)
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.has_live_webpage', true)
            ->where('products.available_quantity', '>', 0)
            ->where('products.price', '>', 0)
            ->where(fn ($query) => $query->where(fn ($subQuery) => $subQuery->where('products.is_minion_variant', false)->where('products.is_for_sale', true))->orWhere('products.is_variant_leader', true))
            ->visibleToCustomer($customer->id);
    }

    private function getNewLaunchIds(Customer $customer, array $departmentIds, array $boughtIds): array
    {
        return $this->forSaleTo($customer, $departmentIds, $boughtIds)
            ->where('products.created_at', '>=', now()->subDays(self::NEW_LAUNCH_DAYS))
            ->orderByDesc('products.created_at')
            ->limit(self::NEW_LAUNCHES_POOL)
            ->pluck('products.id')
            ->all();
    }

    private function getSeasonalIds(Customer $customer, array $departmentIds, array $boughtIds): array
    {
        $rankedIds = $this->getShopSeasonalRanking($customer->shop);

        if (!$rankedIds) {
            return [];
        }

        $rank = array_flip($rankedIds);

        return $this->forSaleTo($customer, $departmentIds, $boughtIds)
            ->whereIn('products.id', $rankedIds)
            ->pluck('products.id')
            ->sortBy(fn ($productId) => $rank[$productId])
            ->take(self::SEASONAL_POOL)
            ->values()
            ->all();
    }

    /**
     * The shop's most ordered products from now to six weeks ahead, a year ago.
     */
    public function getShopSeasonalRanking(Shop $shop): array
    {
        return Cache::remember("product_suggestions_seasonal:$shop->id:".now()->toDateString(), now()->addDay(), function () use ($shop) {
            $from = now()->subYear()->startOfDay();

            return DB::table('transactions')
                ->where('shop_id', $shop->id)
                ->where('model_type', 'Product')
                ->whereNull('deleted_at')
                ->whereBetween('submitted_at', [$from, $from->copy()->addDays(self::SEASONAL_WINDOW_DAYS)])
                ->groupBy('model_id')
                ->orderByRaw('count(distinct order_id) desc')
                ->limit(300)
                ->pluck('model_id')
                ->all();
        });
    }

    /**
     * @param array<int, array{id: int, reason: ?string}> $suggestions
     */
    private function store(Customer $customer, string $arm, array $suggestions, ?string $model): void
    {
        if (!$suggestions) {
            return;
        }

        $generatedAt = now();

        DB::table('customer_product_suggestions')->insert(array_map(fn (array $suggestion, int $position) => [
            'shop_id'      => $customer->shop_id,
            'customer_id'  => $customer->id,
            'product_id'   => $suggestion['id'],
            'arm'          => $arm,
            'position'     => $position + 1,
            'reason'       => $suggestion['reason'],
            'model'        => $model,
            'generated_at' => $generatedAt,
        ], $suggestions, array_keys($suggestions)));

        Cache::forget(GetRetinaB2BDashboardInsights::recommendationsCacheKey($customer));
    }

    public function asCommand(Command $command): int
    {
        if ($customerId = $command->option('customer')) {
            $this->handle(Customer::findOrFail($customerId));
            $command->info('Done');

            return 0;
        }

        $shops = Shop::where('type', ShopTypeEnum::B2B)->where('state', ShopStateEnum::OPEN)->get();
        foreach ($shops as $shop) {
            $this->getShopSeasonalRanking($shop);
        }

        $queued = 0;
        Customer::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->whereIn('id', DB::table('orders')
                ->where('date', '>=', now()->subDays(self::ACTIVE_DAYS))
                ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
                ->select('customer_id'))
            ->chunkById(500, function (Collection $customers) use (&$queued) {
                foreach ($customers as $customer) {
                    self::dispatch($customer);
                    $queued++;
                }
            });

        $command->info("Queued $queued customers");

        return 0;
    }
}
