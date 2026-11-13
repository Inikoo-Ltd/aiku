<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\Catalogue\Product\Json\WithIrisProductsInWebpage;
use App\Actions\CRM\RangeAdvisor\GetCustomerRangeGaps;
use App\Actions\CRM\RangeAdvisor\GetSegmentBestSellers;
use App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales;
use App\Actions\CRM\RangeAdvisor\WithRecommendableProducts;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantTools;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Range Advisor panel shown without chatting: products missing from the customer's range (in the
 * families they already buy from, or best sellers for shops like theirs when they never ordered),
 * forgotten favourites, starter packs of families they never bought from, and new arrivals ranked by
 * what shops like theirs buy. Every product is in stock, at the customer's price, with its reason.
 * What was offered is kept a day per customer so the views and clicks the page reports can be checked.
 */
class GetIrisShoppingAssistantPanel extends IrisAction
{
    use WithIrisProductsInWebpage;
    use WithRecommendableProducts;

    public const array LISTS = ['missing', 'forgotten', 'starter_packs', 'new_arrivals'];

    private const int MAX_ITEMS = 8;

    private const int MAX_PACKS = 3;

    private const int PACK_SIZE = 4;

    private const int MIN_PACK_SIZE = 3;

    private const int GAP_POOL = 80;

    private const int NEW_ARRIVALS_POOL = 60;

    /**
     * @return array{shop_type: ?string, country: ?string, has_orders: bool, remembered: ?string, lists: array<int, array<string, mixed>>}
     */
    public function handle(Customer $customer): array
    {
        $locale          = $this->shop->language?->code ?? 'en';
        $tools           = GetShoppingAssistantTools::make();
        $gaps            = GetCustomerRangeGaps::run($customer, self::GAP_POOL);
        $boughtFamilyIds = DB::table('customer_product_purchases')
            ->join('products', 'products.id', '=', 'customer_product_purchases.product_id')
            ->where('customer_product_purchases.customer_id', $customer->id)
            ->whereNotNull('products.family_id')
            ->distinct()
            ->pluck('products.family_id')
            ->flip();
        $hasOrders = $boughtFamilyIds->isNotEmpty();

        $missing = $gaps['missing']
            ->filter(fn (Product $product) => !$hasOrders || $boughtFamilyIds->has($product->family_id))
            ->take(self::MAX_ITEMS)
            ->values();

        $packs = $gaps['missing']
            ->reject(fn (Product $product) => $product->family_id === null || $boughtFamilyIds->has($product->family_id) || $missing->contains('id', $product->id))
            ->groupBy('family_id')
            ->filter(fn (Collection $products) => $products->count() >= self::MIN_PACK_SIZE)
            ->take(self::MAX_PACKS)
            ->map(fn (Collection $products) => $products->take(self::PACK_SIZE)->values());

        $offered = [
            'missing'      => $missing->map(fn (Product $product) => [$product, $tools->segmentReasonCode($product)]),
            'forgotten'    => $gaps['forgotten']->take(self::MAX_ITEMS)->map(fn (Product $product) => [$product, 'used_to_order']),
            'starter_packs' => $packs->flatten(1)->map(fn (Product $product) => [$product, $tools->segmentReasonCode($product)]),
            'new_arrivals' => $this->newArrivals($customer)->map(fn (Product $product) => [$product, $product->segment_level ? $tools->segmentReasonCode($product) : 'new_arrival']),
        ];

        $productIds = collect($offered)->flatten(1)->map(fn (array $entry) => $entry[0]->id)->unique()->values()->all();
        $cards      = $productIds ? collect(IrisAuthenticatedProductsInWebpageResource::collection(
            $this->getBaseQuery('in_stock')
                ->whereIn('products.id', $productIds)
                ->select($this->getSelect())
                ->get()
        )->resolve())->keyBy('id') : collect();

        $reasons  = [];
        $cardOf   = function (array $entry) use ($cards, $tools, $customer, $locale, &$reasons): ?array {
            [$product, $reasonCode] = $entry;
            $card = $cards->get($product->id);
            if (!$card) {
                return null;
            }
            $reasons[$reasonCode] ??= $tools->reasonText($reasonCode, $customer, $locale);

            return array_merge($card, ['assistant_reason' => (string) $reasons[$reasonCode], 'assistant_reason_code' => $reasonCode]);
        };

        $lists = [];
        foreach (['missing', 'forgotten', 'new_arrivals'] as $key) {
            $items = $offered[$key]->map($cardOf)->filter()->values()->all();
            if ($items) {
                $lists[] = ['key' => $key, 'items' => $items];
            }
        }

        $families = $packs->isEmpty() ? collect() : ProductCategory::whereIn('id', $packs->keys())
            ->with(['webpage' => fn ($query) => $query->where('website_id', $this->website->id)])
            ->get()
            ->keyBy('id');
        $packList = $packs->map(function (Collection $products, int $familyId) use ($families, $cardOf, $tools) {
            $items = $products->map(fn (Product $product) => $cardOf([$product, $tools->segmentReasonCode($product)]))->filter()->values()->all();
            $family = $families->get($familyId);

            return count($items) >= self::MIN_PACK_SIZE && $family ? [
                'family' => ['id' => $family->id, 'name' => $family->name, 'url' => $this->canonicalUrl($family->webpage) ?: null],
                'items'  => $items,
            ] : null;
        })->filter()->values()->all();
        if ($packList) {
            array_splice($lists, min(2, count($lists)), 0, [['key' => 'starter_packs', 'packs' => $packList]]);
        }

        $this->rememberOffered($customer, $lists);

        return [
            'shop_type'  => $customer->shop_type ? CustomerShopTypeEnum::labels()[$customer->shop_type->value] ?? null : null,
            'country'    => DB::table('countries')->where('code', data_get($customer->location, 0))->value('name'),
            'has_orders' => $hasOrders,
            'remembered' => $customer->shop_profile ?: null,
            'lists'      => $lists,
        ];
    }

    /**
     * New arrivals the customer can buy now, those shops like theirs already buy first, then the newest.
     *
     * @return Collection<int, Product>
     */
    private function newArrivals(Customer $customer): Collection
    {
        $newest = $this->recommendableProducts($this->shop, $customer)
            ->where('products.first_available_at', '>', ProductBadgeEnum::newInSince())
            ->orderByDesc('products.first_available_at')
            ->orderByDesc('products.id')
            ->limit(self::NEW_ARRIVALS_POOL)
            ->get();
        if ($newest->isEmpty()) {
            return collect();
        }

        $ranked = GetSegmentBestSellers::run($this->shop, $customer->shop_type?->value, data_get($customer->location, 0), [
            'customer'    => $customer,
            'area'        => RebuildRangeAdvisorSales::areaOf($customer),
            'limit'       => self::MAX_ITEMS,
            'product_ids' => $newest->pluck('id')->all(),
        ])['items'];

        return $ranked->concat($newest->reject(fn (Product $product) => $ranked->contains('id', $product->id)))
            ->take(self::MAX_ITEMS)
            ->values();
    }

    /**
     * @param array<int, array<string, mixed>> $lists
     */
    private function rememberOffered(Customer $customer, array $lists): void
    {
        $offered = [];
        foreach ($lists as $list) {
            $items = $list['key'] === 'starter_packs' ? collect($list['packs'])->flatMap(fn (array $pack) => $pack['items'])->all() : $list['items'];
            foreach (array_values($items) as $position => $item) {
                $offered[$list['key']][$item['id']] = [
                    'position'    => $position + 1,
                    'reason_code' => $item['assistant_reason_code'],
                    'reason'      => $item['assistant_reason'],
                ];
            }
        }

        Cache::put(self::offeredCacheKey($this->website->id, $customer->id), $offered, now()->addDay());
    }

    public static function offeredCacheKey(int $websiteId, int $customerId): string
    {
        return 'shopping-assistant-panel-offered:'.$websiteId.':'.$customerId;
    }

    /**
     * @param array<string, mixed> $panel
     *
     * @return array<string, mixed>
     */
    public function jsonResponse(array $panel): array
    {
        return $panel;
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::find($customerId) : null;
        abort_unless($customer && GetShoppingAssistantSettings::run($this->website, $customer), 404);

        return $this->handle($customer);
    }
}
