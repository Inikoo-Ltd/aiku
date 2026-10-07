<?php

namespace App\Actions\Catalogue\Shop\Traits;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Actions\Dropshipping\Shopify\ShopifyThrottleRetryMiddleware;
use Gnikyt\BasicShopifyAPI\Contracts\GraphRequester;
use Gnikyt\BasicShopifyAPI\ResponseAccess;
use Gnikyt\BasicShopifyAPI\Session;
use Osiset\ShopifyApp\Contracts\ApiHelper as IApiHelper;
use Illuminate\Support\Arr;
use Throwable;

trait WithShopifyExternalShopApi
{
    public const string SHOPIFY_ORDER_GID_PREFIX = 'gid://shopify/Order/';

    public const string SHOPIFY_ACCESS_TOKEN_PREFIX = 'shpat_';

    protected int $shopifyVariantPageSize = 100;

    protected int $shopifyOrderPageSize = 10;

    protected int $shopifyOrderLineItemPageSize = 50;

    protected int $shopifyMaxPages = 200;

    protected int $shopifyInventoryBatchSize = 100;

    protected string $shopifyAddressFields = '
        firstName
        lastName
        name
        company
        address1
        address2
        city
        province
        provinceCode
        country
        countryCodeV2
        zip
        phone
    ';

    /**
     * Shopify stores have no sandbox and a database copied from production holds their real tokens, so anything
     * that changes a store (stock, fulfilments, requests, settings) is only sent from production. Reading is safe
     * anywhere, which lets a copy fetch the real products and orders.
     */
    public function isShopifyExternalShopWriteAllowed(): bool
    {
        return app()->isProduction();
    }

    public function getShopifyExternalShopWriteBlockedMessage(): string
    {
        return __('Shopify was not changed: outside production (this is :environment) the store is only read', ['environment' => app()->environment()]);
    }

    public function getShopifyExternalShopUser(Shop $shop): ?ShopifyUser
    {
        return ShopifyUser::where('external_shop_id', $shop->id)->first();
    }

    /**
     * A Shopify store can only be served once: while the same store is still an open dropshipping channel,
     * that channel already takes its orders and pushes its stock, so the external shop must not touch either.
     */
    public function getShopifyExternalShopBlockedReason(?ShopifyUser $shopifyUser): ?string
    {
        if (!$shopifyUser) {
            return __('The shop is not connected to a Shopify store');
        }

        if ($shopifyUser->trashed() || !str_starts_with((string) $shopifyUser->password, self::SHOPIFY_ACCESS_TOKEN_PREFIX)) {
            return __('The Shopify store has not finished the app installation');
        }

        if ($shopifyUser->customer_sales_channel_id && CustomerSalesChannel::where('id', $shopifyUser->customer_sales_channel_id)
            ->where('status', '!=', CustomerSalesChannelStatusEnum::CLOSED)
            ->exists()) {
            return __('This Shopify store is still an open dropshipping channel, close that channel before using it as an external shop');
        }

        if ($shopifyUser->customer_id && $shopifyUser->shopify_fulfilment_service_id
            && Arr::get($shopifyUser->externalShop?->settings, 'shopify.fulfilment_service_id') !== $shopifyUser->shopify_fulfilment_service_id) {
            return __('The fulfilment location of this Shopify store still sends its orders to dropshipping; take it over with external_shop:shopify_take_over_fulfilment_service');
        }

        return null;
    }

    public function isShopifyExternalShopFulfilmentService(Shop $shop): bool
    {
        return (bool) Arr::get($shop->settings, 'shopify.fulfilment_service_id');
    }

    public function getShopifyExternalShopFulfilmentServiceCallbackUrl(ShopifyUser $shopifyUser): string
    {
        return 'https://'.config('app.domain').'/webhooks/shopify-external-shop/'.$shopifyUser->id;
    }

    /**
     * @return array{id?: string, callbackUrl?: string, location?: array{id: string, name: string}, message?: string}
     */
    public function getShopifyExternalShopFulfilmentService(ShopifyUser $shopifyUser, string $fulfilmentServiceId): array
    {
        $response = $this->shopifyExternalShopQuery($shopifyUser, '
            query ($id: ID!) {
                fulfillmentService(id: $id) {
                    id
                    callbackUrl
                    inventoryManagement
                    location { id name }
                }
            }
        ', ['id' => $fulfilmentServiceId]);

        if (Arr::has($response, 'message')) {
            return $response;
        }

        return Arr::get($response, 'fulfillmentService') ?? ['message' => __('Fulfilment service not found in Shopify')];
    }

    public function updateShopifyExternalShopFulfilmentServiceCallback(ShopifyUser $shopifyUser, string $fulfilmentServiceId, string $callbackUrl): array
    {
        $response = $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($id: ID!, $callbackUrl: URL) {
                fulfillmentServiceUpdate(id: $id, callbackUrl: $callbackUrl) {
                    fulfillmentService { id callbackUrl }
                    userErrors { field message }
                }
            }
        ', [
            'id'          => $fulfilmentServiceId,
            'callbackUrl' => $callbackUrl,
        ]);

        if ($userErrors = Arr::get($response, 'fulfillmentServiceUpdate.userErrors')) {
            return ['message' => $this->getShopifyExternalShopUserErrorMessage($userErrors)];
        }

        return $response;
    }

    /**
     * Fulfilment orders Shopify assigned to our fulfilment service location, with the whole order they belong to.
     *
     * @return array{fulfillment_orders: array<int, array<string, mixed>>, complete: bool, message?: string}
     */
    public function getShopifyExternalShopAssignedFulfillmentOrders(ShopifyUser $shopifyUser, string $assignmentStatus): array
    {
        $fulfillmentOrders = [];
        $cursor            = null;

        for ($page = 0; $page < $this->shopifyMaxPages; $page++) {
            $response = $this->shopifyExternalShopQuery($shopifyUser, '
                query ($first: Int!, $after: String, $assignmentStatus: FulfillmentOrderAssignmentStatus!, $lineItems: Int!) {
                    shop {
                        assignedFulfillmentOrders(first: $first, after: $after, assignmentStatus: $assignmentStatus) {
                            nodes {
                                id
                                status
                                requestStatus
                                lineItems(first: $lineItems) {
                                    nodes {
                                        id
                                        remainingQuantity
                                        lineItem { '.$this->getShopifyExternalShopLineItemFields().' }
                                    }
                                    pageInfo { hasNextPage }
                                }
                                order {
                                    id
                                    name
                                    createdAt
                                    processedAt
                                    cancelledAt
                                    displayFinancialStatus
                                    displayFulfillmentStatus
                                    email
                                    phone
                                    note
                                    taxesIncluded
                                    currencyCode
                                    totalPriceSet { shopMoney { amount currencyCode } }
                                    subtotalPriceSet { shopMoney { amount currencyCode } }
                                    totalTaxSet { shopMoney { amount currencyCode } }
                                    totalShippingPriceSet { shopMoney { amount currencyCode } }
                                    totalDiscountsSet { shopMoney { amount currencyCode } }
                                    customer {
                                        id
                                        email
                                        firstName
                                        lastName
                                        phone
                                        defaultAddress { '.$this->shopifyAddressFields.' }
                                    }
                                    shippingAddress { '.$this->shopifyAddressFields.' }
                                    billingAddress { '.$this->shopifyAddressFields.' }
                                }
                            }
                            pageInfo { hasNextPage endCursor }
                        }
                    }
                }
            ', [
                'first'            => $this->shopifyOrderPageSize,
                'after'            => $cursor,
                'assignmentStatus' => $assignmentStatus,
                'lineItems'        => $this->shopifyOrderLineItemPageSize,
            ]);

            if (Arr::has($response, 'message')) {
                return ['fulfillment_orders' => $fulfillmentOrders, 'complete' => false, 'message' => Arr::get($response, 'message')];
            }

            array_push($fulfillmentOrders, ...Arr::get($response, 'shop.assignedFulfillmentOrders.nodes', []));

            $cursor = Arr::get($response, 'shop.assignedFulfillmentOrders.pageInfo.endCursor');

            if (!Arr::get($response, 'shop.assignedFulfillmentOrders.pageInfo.hasNextPage') || !$cursor) {
                return ['fulfillment_orders' => $fulfillmentOrders, 'complete' => true];
            }
        }

        return ['fulfillment_orders' => $fulfillmentOrders, 'complete' => false, 'message' => __('Too many Shopify fulfilment requests to read in one go')];
    }

    public function acceptShopifyExternalShopFulfillmentRequest(ShopifyUser $shopifyUser, string $fulfillmentOrderId): array
    {
        return $this->answerShopifyExternalShopRequest($shopifyUser, 'fulfillmentOrderAcceptFulfillmentRequest', $fulfillmentOrderId, __('Accepted, the order is being prepared in our warehouse.'));
    }

    public function acceptShopifyExternalShopCancellationRequest(ShopifyUser $shopifyUser, string $fulfillmentOrderId): array
    {
        return $this->answerShopifyExternalShopRequest($shopifyUser, 'fulfillmentOrderAcceptCancellationRequest', $fulfillmentOrderId, __('Cancellation accepted.'));
    }

    public function rejectShopifyExternalShopCancellationRequest(ShopifyUser $shopifyUser, string $fulfillmentOrderId, string $message): array
    {
        return $this->answerShopifyExternalShopRequest($shopifyUser, 'fulfillmentOrderRejectCancellationRequest', $fulfillmentOrderId, $message);
    }

    protected function answerShopifyExternalShopRequest(ShopifyUser $shopifyUser, string $mutation, string $fulfillmentOrderId, string $message): array
    {
        $response = $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($id: ID!, $message: String) {
                '.$mutation.'(id: $id, message: $message) {
                    fulfillmentOrder { id status requestStatus }
                    userErrors { field message }
                }
            }
        ', [
            'id'      => $fulfillmentOrderId,
            'message' => $message,
        ]);

        if ($userErrors = Arr::get($response, $mutation.'.userErrors')) {
            return ['message' => $this->getShopifyExternalShopUserErrorMessage($userErrors)];
        }

        return $response;
    }

    /**
     * @return array<string, mixed> the "data" of the answer, or ['message' => string] when Shopify could not be asked or refused
     */
    public function shopifyExternalShopQuery(ShopifyUser $shopifyUser, string $query, array $variables = []): array
    {
        if ($this->isShopifyExternalShopMutationText($query)) {
            return ['message' => __('A Shopify mutation can not be sent as a query')];
        }

        return $this->sendShopifyExternalShopGraphQL($shopifyUser, $query, $variables);
    }

    /**
     * @return array<string, mixed> the "data" of the answer, or ['message' => string] when it was not sent or Shopify refused
     */
    public function shopifyExternalShopMutation(ShopifyUser $shopifyUser, string $mutation, array $variables = []): array
    {
        if (!$this->isShopifyExternalShopWriteAllowed()) {
            return ['message' => $this->getShopifyExternalShopWriteBlockedMessage(), 'write_blocked' => true];
        }

        if (!$this->isShopifyExternalShopMutationText($mutation)) {
            return ['message' => __('Only a Shopify mutation can be sent as a mutation')];
        }

        return $this->sendShopifyExternalShopGraphQL($shopifyUser, $mutation, $variables);
    }

    protected function isShopifyExternalShopMutationText(string $graphQL): bool
    {
        return (bool) preg_match('/(^|})\s*mutation\b/i', $graphQL);
    }

    private function sendShopifyExternalShopGraphQL(ShopifyUser $shopifyUser, string $graphQL, array $variables): array
    {
        if (app()->runningUnitTests()) {
            return ['message' => __('Shopify stores are never called from tests')];
        }

        $client = $this->getShopifyExternalShopGraphClient($shopifyUser);

        if (!$client) {
            return ['message' => __('Could not connect to the Shopify store')];
        }

        try {
            $response = $client->request($graphQL, $variables);
        } catch (Throwable $e) {
            return ['message' => $e->getMessage()];
        }

        if (!empty($response['errors']) || !($response['body'] ?? null) instanceof ResponseAccess) {
            return ['message' => $this->getShopifyExternalShopErrorMessage($response)];
        }

        $body = $response['body']->toArray();

        if (!empty($body['errors'])) {
            return ['message' => $this->getShopifyExternalShopErrorMessage(['errors' => $body['errors']])];
        }

        return Arr::get($body, 'data') ?? [];
    }

    /**
     * In production the client may refresh or migrate the store's token, as it must to keep it valid. A copy of
     * the database uses the stored token as it is: refreshing it there would rotate the token at Shopify and leave
     * production with one that no longer works.
     */
    protected function getShopifyExternalShopGraphClient(ShopifyUser $shopifyUser): ?GraphRequester
    {
        try {
            $api = $this->isShopifyExternalShopWriteAllowed()
                ? $shopifyUser->api()
                : resolve(IApiHelper::class)->make(new Session($shopifyUser->getDomain()->toNative(), $shopifyUser->getAccessToken()->toNative()))->getApi();
            $api->getOptions()->setGuzzleOptions([
                'timeout'                  => 90.0,
                'max_retry_attempts'       => 0,
                'default_retry_multiplier' => 0.0,
            ]);
            $api->removeMiddleware(ShopifyThrottleRetryMiddleware::NAME)
                ->addMiddleware(new ShopifyThrottleRetryMiddleware(), ShopifyThrottleRetryMiddleware::NAME);

            return $api->getGraphClient();
        } catch (Throwable) {
            return null;
        }
    }

    protected function getShopifyExternalShopErrorMessage(array $response): string
    {
        if (($exception = Arr::get($response, 'exception')) instanceof Throwable) {
            return $exception->getMessage();
        }

        $errors = Arr::get($response, 'errors');

        if ($errors instanceof ResponseAccess) {
            $errors = $errors->toArray();
        }

        if (is_array($errors)) {
            $messages = collect($errors)->map(fn ($error) => is_array($error) ? Arr::get($error, 'message', json_encode($error)) : (string) $error)->filter();

            if ($messages->isNotEmpty()) {
                return $messages->join('; ');
            }
        }

        if (is_string($errors) && $errors !== '') {
            return $errors;
        }

        return __('Unknown Shopify API error (HTTP :status)', ['status' => Arr::get($response, 'status') ?? '-']);
    }

    /**
     * @param array<int, array{field?: mixed, message?: string}> $userErrors
     */
    protected function getShopifyExternalShopUserErrorMessage(array $userErrors): string
    {
        return collect($userErrors)->pluck('message')->filter()->join('; ');
    }

    public function getShopifyExternalShopStoreData(ShopifyUser $shopifyUser): array
    {
        return $this->shopifyExternalShopQuery($shopifyUser, '
            query {
                shop {
                    name
                    myshopifyDomain
                    currencyCode
                    primaryDomain { url }
                    billingAddress { countryCodeV2 }
                }
                location { id name }
            }
        ');
    }

    /**
     * Every variant of the store, flattened to what a product in Aiku needs.
     *
     * @return array{variants: array<int, array{product_id: string, variant_id: string, inventory_item_id: ?string, inventory_tracked: bool, sku: ?string, name: string, description: ?string, price: float, status: string}>, complete: bool, message?: string}
     */
    public function getAllShopifyExternalShopVariants(ShopifyUser $shopifyUser): array
    {
        $variants = [];
        $cursor   = null;

        for ($page = 0; $page < $this->shopifyMaxPages; $page++) {
            $response = $this->shopifyExternalShopQuery($shopifyUser, '
                query ($first: Int!, $after: String) {
                    productVariants(first: $first, after: $after) {
                        nodes {
                            id
                            sku
                            title
                            price
                            selectedOptions { name value }
                            inventoryItem { id tracked }
                            product { id title status description(truncateAt: 2000) }
                        }
                        pageInfo { hasNextPage endCursor }
                    }
                }
            ', ['first' => $this->shopifyVariantPageSize, 'after' => $cursor]);

            if (Arr::has($response, 'message')) {
                return ['variants' => $variants, 'complete' => false, 'message' => Arr::get($response, 'message')];
            }

            foreach (Arr::get($response, 'productVariants.nodes', []) as $variant) {
                $variants[] = [
                    'product_id'        => (string) Arr::get($variant, 'product.id'),
                    'variant_id'        => (string) Arr::get($variant, 'id'),
                    'inventory_item_id' => Arr::get($variant, 'inventoryItem.id'),
                    'inventory_tracked' => (bool) Arr::get($variant, 'inventoryItem.tracked', false),
                    'sku'               => trim((string) Arr::get($variant, 'sku')) ?: null,
                    'name'              => $this->getShopifyExternalShopVariantName($variant),
                    'description'       => trim((string) Arr::get($variant, 'product.description')) ?: null,
                    'price'             => (float) Arr::get($variant, 'price', 0),
                    'status'            => (string) Arr::get($variant, 'product.status'),
                ];
            }

            $cursor = Arr::get($response, 'productVariants.pageInfo.endCursor');

            if (!Arr::get($response, 'productVariants.pageInfo.hasNextPage') || !$cursor) {
                return ['variants' => $variants, 'complete' => true];
            }
        }

        return ['variants' => $variants, 'complete' => false, 'message' => __('Too many Shopify variants to read in one go')];
    }

    protected function getShopifyExternalShopVariantName(array $variant): string
    {
        $productName = (string) Arr::get($variant, 'product.title', '');

        $choices = collect(Arr::get($variant, 'selectedOptions', []))
            ->reject(fn (array $option) => Arr::get($option, 'name') === 'Title' && Arr::get($option, 'value') === 'Default Title')
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        return $choices ? $productName.' - '.implode(' / ', $choices) : $productName;
    }

    /**
     * @return array{orders: array<int, array<string, mixed>>, complete: bool, message?: string}
     */
    public function getAllShopifyExternalShopOrders(ShopifyUser $shopifyUser, string $search): array
    {
        $orders = [];
        $cursor = null;

        for ($page = 0; $page < $this->shopifyMaxPages; $page++) {
            $response = $this->shopifyExternalShopQuery($shopifyUser, '
                query ($first: Int!, $after: String, $search: String, $lineItems: Int!) {
                    orders(first: $first, after: $after, query: $search, sortKey: CREATED_AT) {
                        nodes {
                            id
                            name
                            createdAt
                            processedAt
                            cancelledAt
                            displayFinancialStatus
                            displayFulfillmentStatus
                            email
                            phone
                            note
                            taxesIncluded
                            currencyCode
                            totalPriceSet { shopMoney { amount currencyCode } }
                            subtotalPriceSet { shopMoney { amount currencyCode } }
                            totalTaxSet { shopMoney { amount currencyCode } }
                            totalShippingPriceSet { shopMoney { amount currencyCode } }
                            totalDiscountsSet { shopMoney { amount currencyCode } }
                            customer {
                                id
                                email
                                firstName
                                lastName
                                phone
                                defaultAddress { '.$this->shopifyAddressFields.' }
                            }
                            shippingAddress { '.$this->shopifyAddressFields.' }
                            billingAddress { '.$this->shopifyAddressFields.' }
                            fulfillmentOrders(first: 5) { nodes { id } }
                            lineItems(first: $lineItems) {
                                nodes { '.$this->getShopifyExternalShopLineItemFields().' }
                                pageInfo { hasNextPage endCursor }
                            }
                        }
                        pageInfo { hasNextPage endCursor }
                    }
                }
            ', [
                'first'     => $this->shopifyOrderPageSize,
                'after'     => $cursor,
                'search'    => $search,
                'lineItems' => $this->shopifyOrderLineItemPageSize,
            ]);

            if (Arr::has($response, 'message')) {
                return ['orders' => $orders, 'complete' => false, 'message' => Arr::get($response, 'message')];
            }

            foreach (Arr::get($response, 'orders.nodes', []) as $order) {
                $orders[] = $this->completeShopifyExternalShopOrderLineItems($shopifyUser, $order);
            }

            $cursor = Arr::get($response, 'orders.pageInfo.endCursor');

            if (!Arr::get($response, 'orders.pageInfo.hasNextPage') || !$cursor) {
                return ['orders' => $orders, 'complete' => true];
            }
        }

        return ['orders' => $orders, 'complete' => false, 'message' => __('Too many Shopify orders to read in one go')];
    }

    protected function getShopifyExternalShopLineItemFields(): string
    {
        return '
            id
            sku
            name
            title
            quantity
            currentQuantity
            unfulfilledQuantity
            requiresShipping
            variant { id }
            product { id }
            originalUnitPriceSet { shopMoney { amount } }
            discountedUnitPriceAfterAllDiscountsSet { shopMoney { amount } }
            taxLines { priceSet { shopMoney { amount } } }
        ';
    }

    /**
     * The order list reads the first page of lines only; an order with more lines gets the rest here,
     * so a long order is never imported short.
     */
    protected function completeShopifyExternalShopOrderLineItems(ShopifyUser $shopifyUser, array $order): array
    {
        $lineItems = Arr::get($order, 'lineItems.nodes', []);
        $hasMore   = Arr::get($order, 'lineItems.pageInfo.hasNextPage', false);
        $cursor    = Arr::get($order, 'lineItems.pageInfo.endCursor');

        for ($page = 0; $hasMore && $cursor && $page < $this->shopifyMaxPages; $page++) {
            $response = $this->shopifyExternalShopQuery($shopifyUser, '
                query ($id: ID!, $first: Int!, $after: String) {
                    order(id: $id) {
                        lineItems(first: $first, after: $after) {
                            nodes { '.$this->getShopifyExternalShopLineItemFields().' }
                            pageInfo { hasNextPage endCursor }
                        }
                    }
                }
            ', ['id' => Arr::get($order, 'id'), 'first' => $this->shopifyOrderLineItemPageSize, 'after' => $cursor]);

            if (Arr::has($response, 'message')) {
                data_set($order, 'line_items_incomplete', true);

                break;
            }

            array_push($lineItems, ...Arr::get($response, 'order.lineItems.nodes', []));

            $hasMore = Arr::get($response, 'order.lineItems.pageInfo.hasNextPage', false);
            $cursor  = Arr::get($response, 'order.lineItems.pageInfo.endCursor');
        }

        data_set($order, 'lineItems.nodes', $lineItems);

        return $order;
    }

    public function getShopifyExternalShopOrderFulfillmentOrders(ShopifyUser $shopifyUser, string $orderGid): array
    {
        return $this->shopifyExternalShopQuery($shopifyUser, '
            query ($id: ID!) {
                order(id: $id) {
                    id
                    displayFulfillmentStatus
                    fulfillmentOrders(first: 10) {
                        nodes {
                            id
                            status
                            requestStatus
                            supportedActions { action }
                            assignedLocation { location { id } }
                            lineItems(first: 50) {
                                nodes {
                                    id
                                    remainingQuantity
                                    lineItem { id }
                                }
                            }
                        }
                    }
                }
            }
        ', ['id' => $orderGid]);
    }

    public function createShopifyExternalShopFulfillment(ShopifyUser $shopifyUser, array $fulfillment): array
    {
        return $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($fulfillment: FulfillmentInput!) {
                fulfillmentCreate(fulfillment: $fulfillment) {
                    fulfillment { id status }
                    userErrors { field message }
                }
            }
        ', ['fulfillment' => $fulfillment]);
    }

    public function updateShopifyExternalShopFulfillmentTracking(ShopifyUser $shopifyUser, string $fulfillmentId, array $trackingInfo): array
    {
        return $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($fulfillmentId: ID!, $trackingInfoInput: FulfillmentTrackingInput!, $notifyCustomer: Boolean) {
                fulfillmentTrackingInfoUpdate(fulfillmentId: $fulfillmentId, trackingInfoInput: $trackingInfoInput, notifyCustomer: $notifyCustomer) {
                    fulfillment { id }
                    userErrors { field message }
                }
            }
        ', [
            'fulfillmentId'     => $fulfillmentId,
            'trackingInfoInput' => $trackingInfo,
            'notifyCustomer'    => false,
        ]);
    }

    /**
     * @param array<int, array{inventoryItemId: string, locationId: string, quantity: int}> $quantities
     * @return array{failed: array<int, string>, message?: string} failed messages keyed by the index in $quantities
     */
    public function setShopifyExternalShopInventoryQuantities(ShopifyUser $shopifyUser, array $quantities): array
    {
        $response = $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($input: InventorySetQuantitiesInput!) {
                inventorySetQuantities(input: $input) {
                    inventoryAdjustmentGroup { id }
                    userErrors { code field message }
                }
            }
        ', [
            'input' => [
                'name'                  => 'available',
                'reason'                => 'correction',
                'ignoreCompareQuantity' => true,
                'quantities'            => array_values($quantities),
            ],
        ]);

        if (Arr::has($response, 'message')) {
            return ['failed' => [], 'message' => Arr::get($response, 'message')];
        }

        $failed = [];

        foreach (Arr::get($response, 'inventorySetQuantities.userErrors', []) as $userError) {
            $index = collect((array) Arr::get($userError, 'field', []))->first(fn ($part) => is_numeric($part));

            if ($index === null) {
                return ['failed' => [], 'message' => (string) Arr::get($userError, 'message')];
            }

            $failed[(int) $index] = (string) Arr::get($userError, 'message');
        }

        return ['failed' => $failed];
    }

    public function activateShopifyExternalShopInventoryItem(ShopifyUser $shopifyUser, string $inventoryItemId, string $locationId, int $quantity): array
    {
        $response = $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($inventoryItemId: ID!, $locationId: ID!, $available: Int) {
                inventoryActivate(inventoryItemId: $inventoryItemId, locationId: $locationId, available: $available) {
                    inventoryLevel { id }
                    userErrors { field message }
                }
            }
        ', [
            'inventoryItemId' => $inventoryItemId,
            'locationId'      => $locationId,
            'available'       => $quantity,
        ]);

        if ($userErrors = Arr::get($response, 'inventoryActivate.userErrors')) {
            return ['message' => $this->getShopifyExternalShopUserErrorMessage($userErrors)];
        }

        return $response;
    }

    public function trackShopifyExternalShopInventoryItem(ShopifyUser $shopifyUser, string $inventoryItemId): array
    {
        $response = $this->shopifyExternalShopMutation($shopifyUser, '
            mutation ($id: ID!, $input: InventoryItemInput!) {
                inventoryItemUpdate(id: $id, input: $input) {
                    inventoryItem { id tracked }
                    userErrors { field message }
                }
            }
        ', [
            'id'    => $inventoryItemId,
            'input' => ['tracked' => true],
        ]);

        if ($userErrors = Arr::get($response, 'inventoryItemUpdate.userErrors')) {
            return ['message' => $this->getShopifyExternalShopUserErrorMessage($userErrors)];
        }

        return $response;
    }

    /**
     * Stock is set at the location chosen in the shop settings, or at the store's primary location, which is
     * then remembered so every push goes to the same place.
     */
    public function getShopifyExternalShopLocationId(Shop $shop, ShopifyUser $shopifyUser): ?string
    {
        if ($locationId = Arr::get($shop->settings, 'shopify.location_id')) {
            return $locationId;
        }

        $locationId = Arr::get($this->getShopifyExternalShopStoreData($shopifyUser), 'location.id');

        if ($locationId) {
            $settings = $shop->settings ?? [];
            data_set($settings, 'shopify.location_id', $locationId);
            $shop->updateQuietly(['settings' => $settings]);
        }

        return $locationId;
    }

    public function getShopifyExternalShopOrderGid(string $externalId): string
    {
        return str_starts_with($externalId, 'gid://') ? $externalId : self::SHOPIFY_ORDER_GID_PREFIX.$externalId;
    }
}
