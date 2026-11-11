<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Catalogue\Search\SearchCatalogueByMeaning;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\CRM\RangeAdvisor\GetCustomerRangeGaps;
use App\Actions\CRM\RangeAdvisor\GetSegmentBestSellers;
use App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales;
use App\Actions\CRM\RangeAdvisor\WithRecommendableProducts;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Actions\Iris\Basket\StoreEcomBasketTransactions;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The tools the shopping assistant model may call, and their executor.
 *
 * What the model sees of a product is only its id, name, family name and a reason code (plus the
 * month it was last ordered, for the customer's own forgotten products). Never codes or names of
 * other customers, buyer counts, revenue, segment sizes, prices, discounts or stock figures: the
 * payload is built field by field, never from the model's attributes.
 *
 * Every product a tool returns is live, in stock, priced and visible to the customer. search_products
 * also names, apart and without ids, the products asked for that are out of stock, so the customer
 * hears "out of stock" rather than "not found"; they can never be presented.
 * present_products is never executed: AskShoppingAssistant reads its arguments as the final answer.
 *
 * add_to_basket puts one of each product in the customer's basket, only products already shown in
 * this conversation and not yet in the basket, through the storefront's own bulk add, so stock,
 * visibility and order limits are checked the same way. clear_conversation hides the conversation
 * from the customer for good; the record stays for staff.
 */
class GetShoppingAssistantTools
{
    use AsAction;
    use WithRecommendableProducts;

    public const string PRESENT_PRODUCTS = 'present_products';

    public const int MAX_LIMIT = 24;

    public const int DEFAULT_LIMIT = 12;

    public const int USUAL_PER_GROUP = 8;

    public const int MAX_PER_GROUP = 12;

    public const int MAX_FOLLOW_UPS = 3;

    public const int MAX_OUT_OF_STOCK = 3;

    private const array SEGMENT_REASON_CODES = [
        RebuildRangeAdvisorSales::LEVEL_TYPE_AREA    => 'similar_shops_area',
        RebuildRangeAdvisorSales::LEVEL_TYPE_COUNTRY => 'similar_shops_country',
        RebuildRangeAdvisorSales::LEVEL_TYPE_GROUP   => 'similar_shops_group',
        RebuildRangeAdvisorSales::LEVEL_TYPE         => 'similar_shops',
        RebuildRangeAdvisorSales::LEVEL_AREA         => 'shops_area',
        RebuildRangeAdvisorSales::LEVEL_COUNTRY      => 'shops_country',
        RebuildRangeAdvisorSales::LEVEL_GROUP        => 'shops_group',
        RebuildRangeAdvisorSales::LEVEL_SHOP         => 'best_seller',
    ];

    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function definitions(): array
    {
        $limit = [
            'type'        => 'integer',
            'minimum'     => 1,
            'maximum'     => self::MAX_LIMIT,
            'description' => 'How many products to return, default '.self::DEFAULT_LIMIT.'.',
        ];

        $productIds = fn (string $description) => [
            'type'        => 'array',
            'items'       => ['type' => 'integer'],
            'maxItems'    => self::MAX_PER_GROUP,
            'description' => $description,
        ];

        return [
            $this->tool(
                'search_products',
                'Search the catalogue for products matching what the customer is looking for. Use it whenever the customer names a product, a product code, a theme, an occasion or a kind of item. Returns products with id, name, family and why; exact_code_match marks the product whose code the customer typed. It may also return out_of_stock: products we sell that match but cannot be ordered right now.',
                [
                    'query' => ['type' => 'string', 'description' => 'What the customer is looking for, in their words.'],
                    'limit' => $limit,
                ],
                ['query']
            ),
            $this->tool(
                'best_sellers_for_similar_shops',
                'Best sellers among the shops closest to this customer: the same kind of shop when we know theirs, otherwise every shop, nearby first. Each product\'s why says exactly which shops it is about. Use it when the customer asks what sells well, wants ideas, or is starting or refreshing their range. Returns products with id, name, family and why.',
                ['limit' => $limit]
            ),
            $this->tool(
                'missing_from_range',
                'Products that sell well in the shops closest to this customer (the same kind of shop when we know theirs, otherwise every shop) but that this customer has never ordered. Each product\'s why says exactly which shops it is about. Use it when the customer asks what they are missing or how to widen their range. Returns products with id, name, family and why.',
                ['limit' => $limit]
            ),
            $this->tool(
                'products_they_stopped_ordering',
                'Products this customer used to order but has not ordered in the last 12 months. Use it when the customer asks what to reorder or mentions products they had before. Returns products with id, name, family, why and last_ordered (year and month).',
                ['limit' => $limit]
            ),
            $this->tool(
                'save_shop_type',
                'Save the kind of shop the customer runs, when they tell you and it is not known yet. Call it at most once. Returns whether it was saved or was already known. Returns no products.',
                [
                    'shop_type' => ['type' => 'string', 'enum' => CustomerShopTypeEnum::values(), 'description' => 'The kind of shop the customer said they run.'],
                ],
                ['shop_type']
            ),
            $this->tool(
                'add_to_basket',
                'Put products in the customer\'s basket, one of each, only when the customer asks you to. Without product_ids it adds every product of your previous answer. Only products already shown in this conversation can be added. Returns the names added, already in the basket and those that could not be added. Returns no products.',
                [
                    'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => self::MAX_PER_GROUP * 2, 'description' => 'Ids of shown products to add, when the customer asked for only some of them.'],
                ]
            ),
            $this->tool(
                'clear_conversation',
                'Clear this conversation when the customer asks to delete, clear or forget it. The page then tells them it was cleared. Returns no products.',
                []
            ),
            $this->tool(
                self::PRESENT_PRODUCTS,
                'Give your final answer with products. Use only ids returned by the other tools. Call it once, as your last step, whenever you show products.',
                [
                    'reply'      => ['type' => 'string', 'description' => 'Two or three short sentences for the customer, with no ids, prices, discounts, stock or numbers.'],
                    'must_haves' => $productIds('Ids of products that fit the request or sell well for similar shops, usually up to '.self::USUAL_PER_GROUP.', at most '.self::MAX_PER_GROUP.' when the customer asks for more.'),
                    'add_ons'    => $productIds('Ids of complementary or previously ordered products, usually up to '.self::USUAL_PER_GROUP.', at most '.self::MAX_PER_GROUP.' when the customer asks for more.'),
                    'follow_ups' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'string'],
                        'maxItems'    => self::MAX_FOLLOW_UPS,
                        'description' => 'At most '.self::MAX_FOLLOW_UPS.' short questions the customer could ask next.',
                    ],
                ],
                ['reply']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    public function execute(ShoppingAssistantConversation $conversation, string $name, array $arguments): array
    {
        $customer = $conversation->customer;

        if (!$customer) {
            return $this->error('This conversation has no customer.');
        }

        try {
            return match ($name) {
                'search_products'                => $this->searchProducts($customer, $arguments, $conversation->language_code),
                'best_sellers_for_similar_shops' => $this->bestSellers($customer, $arguments, $conversation->language_code),
                'missing_from_range'             => $this->missingFromRange($customer, $arguments, $conversation->language_code),
                'products_they_stopped_ordering' => $this->stoppedOrdering($customer, $arguments, $conversation->language_code),
                'save_shop_type'                 => $this->saveShopType($conversation, $customer, $arguments),
                'add_to_basket'                  => $this->addToBasket($conversation, $customer, $arguments),
                'clear_conversation'             => $this->clearConversation($conversation),
                default                          => $this->error("Unknown tool $name."),
            };
        } catch (Throwable $exception) {
            Log::warning('Shopping assistant tool '.$name.' failed: '.$exception->getMessage());

            return $this->error('The tool failed, try another one.');
        }
    }

    public function reasonText(string $reasonCode, Customer $customer, ?string $locale = null): ?string
    {
        $country = fn (): ?string => DB::table('countries')->where('code', data_get($customer->location, 0))->value('name');

        return match ($reasonCode) {
            'similar_shops_area'    => __('Popular with shops like yours in your area', [], $locale),
            'similar_shops_country' => ($name = $country()) ? __('Popular with shops like yours in :country', ['country' => $name], $locale) : __('Popular with shops like yours', [], $locale),
            'similar_shops_group'   => __('Popular with shops like yours in your region', [], $locale),
            'similar_shops'         => __('Popular with shops like yours', [], $locale),
            'shops_area'            => __('Popular with shops in your area', [], $locale),
            'shops_country'         => ($name = $country()) ? __('Popular with shops in :country', ['country' => $name], $locale) : __('A best seller', [], $locale),
            'shops_group'           => __('Popular with shops in your region', [], $locale),
            'best_seller'           => __('A best seller', [], $locale),
            'used_to_order'         => __('You used to order this', [], $locale),
            'matches_request'       => __('Matches what you asked for', [], $locale),
            default                 => null,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function searchProducts(Customer $customer, array $arguments, string $locale): array
    {
        $query = $arguments['query'] ?? null;

        if (!is_string($query) || trim($query) === '') {
            return $this->error('query must be a non-empty string.');
        }

        $limit   = $this->limit($arguments);
        $byCode  = $this->productsByCode($customer, $query);
        $codeIds = $byCode->pluck('id')->flip();
        $nearest = SearchCatalogueByMeaning::run($customer->shop, $query, $customer, $limit + self::MAX_OUT_OF_STOCK, false);
        $inStock = fn (Product $product) => $product->available_quantity > 0;

        $outOfStock = (new EloquentCollection($byCode->concat($nearest->take(self::MAX_OUT_OF_STOCK))->reject($inStock)->unique('id')->take(self::MAX_OUT_OF_STOCK)->all()))->loadMissing('family');

        $result = $this->productsResult($customer, $byCode->concat($nearest)->filter($inStock)->unique('id')->take($limit), fn () => 'matches_request', $locale);

        $result['content']['products'] = array_map(fn (array $item) => isset($codeIds[$item['id']]) ? $item + ['exact_code_match' => true] : $item, $result['content']['products']);
        if ($outOfStock->isNotEmpty()) {
            $result['names']                   = $outOfStock->flatMap(fn (Product $product) => [$product->name, $product->family?->name])->filter()->values()->all();
            $result['content']['out_of_stock'] = $outOfStock->map(fn (Product $product) => array_filter(['name' => $product->name, 'family' => $product->family?->name, 'exact_code_match' => isset($codeIds[$product->id])]))->values()->all();
        }

        return $result;
    }

    /**
     * Customers type product codes loosely ("tiba 04" for TibA-04, "iconic -29" for Iconic-29), and
     * the meaning search knows nothing of codes: codes are compared with everything but letters and
     * digits removed, against the whole query, each word and each pair of neighbouring words.
     *
     * ponytail: no index on the normalised code, one scan of the shop's products per search; add an
     * expression index if searches slow down.
     *
     * @return Collection<int, Product>
     */
    private function productsByCode(Customer $customer, string $query): Collection
    {
        $words      = preg_split('/\s+/', mb_strtolower(trim($query)));
        $candidates = [implode('', $words)];
        foreach ($words as $index => $word) {
            $candidates[] = $word;
            $candidates[] = $word.($words[$index + 1] ?? '');
        }

        $candidates = collect($candidates)
            ->map(fn (string $candidate) => preg_replace('/[^a-z0-9]/', '', $candidate))
            ->filter(fn (string $candidate) => strlen($candidate) >= 3 && preg_match('/\d/', $candidate) && preg_match('/[a-z]/', $candidate))
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        return $this->recommendableProducts($customer->shop, $customer, false)
            ->select('products.*')
            ->whereIn(DB::raw("lower(regexp_replace(products.code, '[^A-Za-z0-9]', '', 'g'))"), $candidates->all())
            ->limit(self::MAX_OUT_OF_STOCK)
            ->get()
            ->toBase();
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function bestSellers(Customer $customer, array $arguments, string $locale): array
    {
        $products = GetSegmentBestSellers::run($customer->shop, $customer->shop_type?->value, data_get($customer->location, 0), [
            'customer' => $customer,
            'area'     => RebuildRangeAdvisorSales::areaOf($customer),
            'limit'    => $this->limit($arguments),
        ])['items'];

        return $this->productsResult($customer, $products, $this->segmentReasonCode(...), $locale);
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function missingFromRange(Customer $customer, array $arguments, string $locale): array
    {
        $products = GetCustomerRangeGaps::run($customer, $this->limit($arguments))['missing'];

        return $this->productsResult($customer, $products, $this->segmentReasonCode(...), $locale);
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function stoppedOrdering(Customer $customer, array $arguments, string $locale): array
    {
        $products = GetCustomerRangeGaps::run($customer, $this->limit($arguments))['forgotten'];

        return $this->productsResult($customer, $products, fn () => 'used_to_order', $locale, true);
    }

    /**
     * Only a shop type the customer has not given, or one we only inferred, is saved; one the
     * customer or staff set is never overwritten.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function saveShopType(ShoppingAssistantConversation $conversation, Customer $customer, array $arguments): array
    {
        $shopType = is_string($arguments['shop_type'] ?? null) ? CustomerShopTypeEnum::tryFrom($arguments['shop_type']) : null;

        if (!$shopType) {
            return $this->error('shop_type must be one of: '.implode(', ', CustomerShopTypeEnum::values()).'.');
        }

        $customer = $customer->fresh();

        if ($customer->shop_type !== null && $customer->shop_type_source !== CustomerShopTypeSourceEnum::INFERRED) {
            return ['content' => ['saved' => false, 'shop_type' => $customer->shop_type->value, 'note' => 'Already known, nothing changed.'], 'products' => [], 'error' => null];
        }

        $customer = UpdateCustomer::make()->action($customer, [
            'shop_type'        => $shopType->value,
            'shop_type_source' => CustomerShopTypeSourceEnum::CUSTOMER->value,
        ]);
        $conversation->setRelation('customer', $customer);

        return ['content' => ['saved' => true, 'shop_type' => $shopType->value], 'products' => [], 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, basket?: array<int, int>}
     */
    private function addToBasket(ShoppingAssistantConversation $conversation, Customer $customer, array $arguments): array
    {
        $shown = $conversation->events()->where('type', ShoppingAssistantEventTypeEnum::SHOWN)->orderBy('id')->get(['product_id', 'shopping_assistant_message_id']);
        $ids   = collect(is_array($arguments['product_ids'] ?? null) ? $arguments['product_ids'] : [])->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            $ids = $shown->where('shopping_assistant_message_id', $shown->max('shopping_assistant_message_id'))->pluck('product_id');
        }

        $ids = $ids->intersect($shown->pluck('product_id'))->unique()->take(self::MAX_PER_GROUP * 2)->values();

        if ($ids->isEmpty()) {
            return $this->error('No product shown in this conversation matches. Show products first, then add them.');
        }

        $orderId  = Order::where('customer_id', $customer->id)->where('state', OrderStateEnum::CREATING)->value('id');
        $inBasket = $orderId
            ? Transaction::where('order_id', $orderId)->where('model_type', 'Product')->whereIn('model_id', $ids)->where('is_gift', false)->pluck('model_id')
            : collect();
        $toAdd    = $ids->diff($inBasket)->values();

        $lines = $toAdd->isEmpty() ? collect() : collect(StoreEcomBasketTransactions::make()->handle($customer, $toAdd->map(fn (int $id) => ['product_id' => $id, 'quantity' => 1])->all())['lines']);
        $added = $lines->where('status', 'added');

        if ($added->isNotEmpty()) {
            RecordShoppingAssistantBasketAdd::make()->record($customer, $added->map(fn (array $line) => [
                'product_id'     => $line['product_id'],
                'transaction_id' => $line['transaction_id'],
                'quantity'       => $line['quantity_ordered'],
            ])->values()->all(), ShoppingAssistantEventTypeEnum::ADDED);
        }

        $names   = Product::whereIn('id', $ids)->pluck('name', 'id');
        $namesOf = fn (Collection $productIds) => $productIds->map(fn ($id) => $names[$id] ?? null)->filter()->values()->all();

        return [
            'content' => [
                'added'             => $namesOf($added->pluck('product_id')),
                'already_in_basket' => $namesOf($inBasket),
                'could_not_add'     => $namesOf($lines->where('status', 'failed')->pluck('product_id')),
                'note'              => 'One of each was added. Tell the customer what happened in one or two sentences, without counts; they set quantities on the product cards or in the basket.',
            ],
            'products' => [],
            'error'    => null,
            'basket'   => $added->pluck('product_id')->values()->all(),
        ];
    }

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, cleared: bool}
     */
    private function clearConversation(ShoppingAssistantConversation $conversation): array
    {
        $conversation->update(['status' => ShoppingAssistantConversationStatusEnum::CLEARED]);

        return ['content' => ['cleared' => true], 'products' => [], 'error' => null, 'cleared' => true];
    }

    /**
     * @param  callable(Product): string  $reasonCodeOf
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function productsResult(Customer $customer, Collection $products, callable $reasonCodeOf, string $locale, bool $withLastOrdered = false): array
    {
        $products = (new EloquentCollection($products->all()))->loadMissing('family');
        $seen    = [];
        $content = [];
        $found   = [];

        foreach ($products as $product) {
            if (isset($seen[$product->id])) {
                continue;
            }
            $seen[$product->id] = true;
            $reasonCode         = $reasonCodeOf($product);

            $item = [
                'id'     => $product->id,
                'name'   => $product->name,
                'family' => $product->family?->name,
                'why'    => $this->reasonText($reasonCode, $customer, 'en'),
            ];
            if ($withLastOrdered) {
                $item['last_ordered'] = $product->customer_last_ordered_at?->format('Y-m');
            }

            $content[] = $item;
            $found[]   = ['id' => $product->id, 'reason_code' => $reasonCode, 'reason' => $this->reasonText($reasonCode, $customer, $locale)];
        }

        return ['content' => ['products' => $content], 'products' => $found, 'error' => null];
    }

    private function segmentReasonCode(Product $product): string
    {
        return self::SEGMENT_REASON_CODES[$product->segment_level] ?? 'best_seller';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function limit(array $arguments): int
    {
        $limit = $arguments['limit'] ?? null;

        return is_numeric($limit) ? max(1, min(self::MAX_LIMIT, (int) $limit)) : self::DEFAULT_LIMIT;
    }

    /**
     * @return array{content: array{error: string}, products: array{}, error: string}
     */
    private function error(string $message): array
    {
        return ['content' => ['error' => $message], 'products' => [], 'error' => $message];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     *
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        $parameters = ['type' => 'object', 'properties' => $properties ?: new \stdClass()];
        if ($required) {
            $parameters['required'] = $required;
        }

        return ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $parameters]];
    }
}
