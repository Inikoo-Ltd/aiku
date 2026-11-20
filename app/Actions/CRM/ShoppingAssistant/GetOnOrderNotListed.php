<?php

/*
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Catalogue\Search\SearchCatalogueByMeaning;
use App\Actions\Helpers\AI\EmbedTexts;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * HELP-4843, Raul 20 Nov 2026: a customer asked for copper cups and bottles while 21 new stocks were
 * on a submitted order to the supplier with no product in any shop yet, and Merlin said we do not
 * have them. New stock usually becomes a product about 71 days after it is ordered, so stock that is
 * on order and not listed is teased: Merlin may say we have it on order, never its name, a date, a
 * price or a quantity.
 *
 * A candidate is stock of the organisation that was never in our warehouse, has no stock, no product
 * in any shop of the organisation and no customer of its own, is not a raw material or shipment
 * packaging, and is on an open purchase order line submitted in the last RECENT_MONTHS months or on a
 * stock delivery not yet put away. Names suggesting stock made for one customer are left out: such
 * stock has no exclusive product yet to exclude it by (hard rule: no customer sees another's data).
 *
 * The candidates' cleaned names are embedded nightly per shop (HydrateCatalogueSearchEmbeddings), only
 * for shops that carry the organisation's whole range (TEASE_MIN_SHARE of its biggest), and the
 * conditions are checked again on a 10-minute cache of candidate ids, so stock that got a product or
 * arrived stops being teased within 10 minutes.
 *
 * ponytail: the confirming tool trusts the model to pass the query it searched (as sister_shop_products
 * does); the sentence names nothing, and needs a real match. Tie it to the turn's search if it misfires.
 */
class GetOnOrderNotListed
{
    use AsObject;

    public const string MODEL_TYPE = 'OrgStock';

    public const int RECENT_MONTHS = 9;

    public const float MAX_DISTANCE = 0.45;

    public const float TEASE_MIN_SHARE = 0.5;

    public const int MAX_MATCHES = 5;

    public const float NAME_MARGIN = 0.05;

    private const int MAX_PRODUCT_NAMES = 30;

    private const string NOT_FOR_EVERYONE = '/\b(exclusive|private\s*label|bespoke|custom(ised|ized)?|personali[sz]ed|samples?|tests?|testers?|for\s+customer|raw\s+material|not\s+for\s+(re)?sale|polo|uniform|staff)\b/i';

    /**
     * Cleaned names of the candidates closest to the query that beat, by NAME_MARGIN, the names of the
     * products the search found ($productNames). Names are compared with names: a short stock name
     * always sits closer to a short query than a product's long indexed text (20 Nov 2026: "candle
     * holder" was 0.28 from an on-order Buddha candle holder and 0.38 from the candle holders we sell,
     * but 0.29 from their names), so comparing with the product distance teased ranges we already sell.
     *
     * @param  array<int, string>  $productNames
     *
     * @return array<int, string>
     */
    public function handle(Shop $shop, string $query, array $productNames): array
    {
        $vector = SearchCatalogueByMeaning::queryVector($query);

        if ($vector === null || !self::teasesIn($shop)) {
            return [];
        }

        $candidateIds = $this->candidateIds($shop->organisation_id);
        if (!$candidateIds) {
            return [];
        }

        $matches = DB::table('catalogue_search_embeddings')
            ->join('org_stocks', 'org_stocks.id', '=', 'catalogue_search_embeddings.model_id')
            ->where('catalogue_search_embeddings.shop_id', $shop->id)
            ->where('catalogue_search_embeddings.model_type', self::MODEL_TYPE)
            ->whereIn('org_stocks.id', $candidateIds)
            ->select('org_stocks.name')
            ->selectVectorDistance('catalogue_search_embeddings.embedding', $vector, 'distance')
            ->orderByVectorDistance('catalogue_search_embeddings.embedding', $vector)
            ->limit(self::MAX_MATCHES)
            ->get()
            ->filter(fn (object $row) => (float) $row->distance < self::MAX_DISTANCE && $row->name && !preg_match(self::NOT_FOR_EVERYONE, $row->name));

        if ($matches->isEmpty()) {
            return [];
        }

        $closestName = $this->closestNameDistance($vector, $productNames);
        if ($closestName === false) {
            return [];
        }

        return $matches
            ->filter(fn (object $row) => $closestName === null || (float) $row->distance < $closestName - self::NAME_MARGIN)
            ->map(fn (object $row) => self::cleanName($row->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function candidateIds(int $organisationId): array
    {
        return Cache::remember('shopping-assistant:on-order-candidates:'.$organisationId, now()->addMinutes(10), fn () => $this->candidates($organisationId)->pluck('org_stocks.id')->all());
    }

    /**
     * Null when no product was found, false when their names could not be embedded (nothing is
     * teased then rather than guessing). Name vectors are cached a day.
     *
     * @param  array<int, float>  $vector
     * @param  array<int, string>  $productNames
     */
    private function closestNameDistance(array $vector, array $productNames): float|false|null
    {
        $names = collect($productNames)->map(fn (string $name) => self::cleanName($name))->filter()->unique()->take(self::MAX_PRODUCT_NAMES)->values();
        if ($names->isEmpty()) {
            return null;
        }

        $key     = fn (string $name) => 'catalogue-search:name-vector:'.sha1($name);
        $vectors = $names->mapWithKeys(fn (string $name) => [$name => Cache::get($key($name))])->filter(fn ($cached) => is_array($cached));
        $missing = $names->reject(fn (string $name) => $vectors->has($name))->values();

        if ($missing->isNotEmpty()) {
            $embedded = EmbedTexts::run($missing->all());
            if ($embedded === null || count($embedded) !== $missing->count()) {
                return false;
            }
            foreach ($missing as $i => $name) {
                Cache::put($key($name), $embedded[$i], now()->addDay());
                $vectors[$name] = $embedded[$i];
            }
        }

        return $vectors->map(fn (array $nameVector) => self::cosineDistance($vector, $nameVector))->min();
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private static function cosineDistance(array $a, array $b): float
    {
        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $i => $value) {
            $dot   += $value * ($b[$i] ?? 0.0);
            $normA += $value * $value;
        }
        foreach ($b as $value) {
            $normB += $value * $value;
        }

        return $normA > 0 && $normB > 0 ? 1 - $dot / sqrt($normA * $normB) : 1.0;
    }

    public function candidates(int $organisationId): Builder
    {
        $finishedLines = [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value, PurchaseOrderTransactionStateEnum::SETTLED->value];
        $finishedItems = [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value, StockDeliveryItemStateEnum::PLACED->value];
        $deliveries    = array_map(fn ($state) => $state->value, GetComingSoon::ON_THE_WAY_DELIVERY_STATES);

        return DB::table('org_stocks')
            ->where('org_stocks.organisation_id', $organisationId)
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
            ->whereNull('org_stocks.deleted_at')
            ->whereNull('org_stocks.customer_id')
            ->where('org_stocks.has_been_in_warehouse', false)
            ->where('org_stocks.is_raw_material_in_organisation', false)
            ->where('org_stocks.is_shipment_packaging', false)
            ->whereRaw('coalesce(org_stocks.quantity_in_locations, 0) <= 0')
            ->whereNotExists(fn ($query) => $query->from('product_has_org_stocks')
                ->join('products', 'products.id', '=', 'product_has_org_stocks.product_id')
                ->whereColumn('product_has_org_stocks.org_stock_id', 'org_stocks.id')
                ->whereNull('products.deleted_at'))
            ->where(fn ($query) => $query
                ->whereExists(fn ($query) => $query->from('purchase_order_transactions')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_transactions.purchase_order_id')
                    ->whereColumn('purchase_order_transactions.org_stock_id', 'org_stocks.id')
                    ->whereNull('purchase_order_transactions.deleted_at')
                    ->whereNotIn('purchase_order_transactions.state', $finishedLines)
                    ->whereNull('purchase_orders.deleted_at')
                    ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::openValues())
                    ->where('purchase_orders.submitted_at', '>=', now()->subMonths(self::RECENT_MONTHS)))
                ->orWhereExists(fn ($query) => $query->from('stock_delivery_items')
                    ->join('stock_deliveries', 'stock_deliveries.id', '=', 'stock_delivery_items.stock_delivery_id')
                    ->whereColumn('stock_delivery_items.org_stock_id', 'org_stocks.id')
                    ->whereNull('stock_delivery_items.deleted_at')
                    ->whereNull('stock_deliveries.deleted_at')
                    ->whereNotIn('stock_delivery_items.state', $finishedItems)
                    ->whereIn('stock_deliveries.state', $deliveries)));
    }

    /**
     * @return array<int, string> cleaned names keyed by org stock id, for the nightly embedding
     */
    public function namesToEmbed(Shop $shop): array
    {
        if (!self::teasesIn($shop)) {
            return [];
        }

        return $this->candidates($shop->organisation_id)
            ->pluck('org_stocks.name', 'org_stocks.id')
            ->reject(fn (?string $name) => !$name || preg_match(self::NOT_FOR_EVERYONE, $name))
            ->map(fn (string $name) => self::cleanName($name))
            ->filter()
            ->all();
    }

    /**
     * Wholesale and dropshipping shops that carry the organisation's whole range: on prod (Nov 2026)
     * the country shops hold about the same 10k products, while a narrow shop (awad 357, acar 278)
     * would tease almost everything on order.
     */
    public static function teasesIn(Shop $shop): bool
    {
        if (!in_array($shop->type, [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING], true)) {
            return false;
        }

        $counts = Cache::remember('shopping-assistant:on-order-shops:'.$shop->organisation_id, now()->addDay(), fn () => DB::table('products')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('shops.organisation_id', $shop->organisation_id)
            ->whereIn('shops.type', [ShopTypeEnum::B2B->value, ShopTypeEnum::DROPSHIPPING->value])
            ->where('products.is_in_website', true)
            ->whereNull('products.deleted_at')
            ->groupBy('products.shop_id')
            ->selectRaw('products.shop_id, count(*) as website_products')
            ->pluck('website_products', 'shop_id')
            ->all());

        $biggest = (int) max([0, ...array_values($counts)]);

        return $biggest > 0 && (int) ($counts[$shop->id] ?? 0) >= $biggest * self::TEASE_MIN_SHARE;
    }

    /**
     * Internal stock names carry sizes and weights ("Copper Bottles - 27.5cm -950ml - Tall shape -
     * 270gm"): words with a digit and the units they leave behind go, so neither the embedding nor
     * the model sees a figure.
     */
    public static function cleanName(string $name): string
    {
        $words = preg_split('/\s+/u', str_replace(['-', '–', '/', '(', ')', ','], ' ', $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept  = array_filter($words, fn (string $word) => !preg_match('/\d/u', $word) && !preg_match('/^(x|cm|mm|ml|l|g|gm|gr|kg|oz|m|cl|pcs?)$/iu', $word));

        return trim(implode(' ', $kept));
    }
}
