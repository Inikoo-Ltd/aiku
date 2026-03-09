<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Mar 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait WithAllegroApiServices
{
    use WithAllegroOAuth;

    public string $allegroApiVersion = 'application/vnd.allegro.public.v1+json';

    public function restApi(string $method = 'GET', array $params = []): PendingRequest
    {
        $http = Http::withHeaders([
            'Authorization'  => 'Bearer ' . $this->access_token,
            'Accept'         => $this->allegroApiVersion,
            'Content-Type'   => $this->allegroApiVersion,
        ])->baseUrl(config('services.allegro.base_url'));

        if (! empty($params)) {
            $http = $http->withQueryParameters($params);
        }

        return $http;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function makeApiRequest(
        string $method,
        string $path,
        array $body = [],
        array $params = []
    ): array {
        try {
            $api = $this->restApi($method, $params);

            $response = match (strtoupper($method)) {
                'GET'    => $api->get($path),
                'POST'   => $api->post($path, $body),
                'PATCH'  => $api->patch($path, $body),
                'PUT'    => $api->put($path, $body),
                'DELETE' => $api->delete($path),
                default  => throw new \Exception("Unsupported HTTP method: $method"),
            };

            if ($response->failed()) {
                $errorMessage = Arr::get($response->json(), 'errors.0.userMessage')
                    ?? Arr::get($response->json(), 'error_description')
                    ?? Arr::get($response->json(), 'message')
                    ?? 'Unknown Allegro API error';

                throw new \Exception($errorMessage);
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Allegro API Request failed: ' . $e->getMessage());
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Offers
    // -------------------------------------------------------------------------

    public function getOffers(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/sale/offers', [], $params);
    }

    public function getOffer(string $offerId): array
    {
        return $this->makeApiRequest('GET', "/sale/product-offers/$offerId");
    }

    public function createOffer(array $offerData): array
    {
        return $this->makeApiRequest('POST', '/sale/product-offers', $offerData);
    }

    public function updateOffer(string $offerId, array $offerData): array
    {
        return $this->makeApiRequest('PATCH', "/sale/product-offers/$offerId", $offerData);
    }

    public function deleteOffer(string $offerId): array
    {
        return $this->makeApiRequest('DELETE', "/sale/offers/$offerId");
    }

    public function changeOfferPrice(string $offerId, string $commandId, string $amount, string $currency = 'PLN'): array
    {
        return $this->makeApiRequest('PUT', "/offers/$offerId/change-price-commands/$commandId", [
            'id'    => $commandId,
            'input' => [
                'buyNowPrice' => [
                    'amount'   => $amount,
                    'currency' => $currency,
                ],
            ],
        ]);
    }

    public function publishOffers(string $commandId, array $offerIds, string $action = 'ACTIVATE'): array
    {
        return $this->makeApiRequest('PUT', "/sale/offer-publication-commands/$commandId", [
            'offerCriteria' => [
                [
                    'offers' => array_map(fn ($id) => ['id' => $id], $offerIds),
                    'type'   => 'CONTAINS_OFFERS',
                ],
            ],
            'publication' => ['action' => $action],
        ]);
    }

    public function getOfferEvents(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/sale/offer-events', [], $params);
    }

    // -------------------------------------------------------------------------
    // Products
    // -------------------------------------------------------------------------

    public function searchProducts(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/sale/products', [], $params);
    }

    public function proposeProduct(array $attributes = []): array
    {
        return $this->makeApiRequest('POST', '/sale/product-proposals', $attributes);
    }

    public function getProduct(string $productId): array
    {
        return $this->makeApiRequest('GET', "/sale/products/$productId");
    }

    public function uploadOfferImage(string $imageUrl): array
    {
        return $this->makeApiRequest('POST', '/sale/images', [
            'url' => $imageUrl,
        ]);
    }

    public function uploadOfferAttachment(string $fileName, string $type): array
    {
        return $this->makeApiRequest('POST', '/sale/offer-attachments', [
            'name' => $fileName,
            'type' => $type,
        ]);
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    public function getOrders(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/order/checkout-forms', [], $params);
    }

    public function getOrder(string $orderId): array
    {
        return $this->makeApiRequest('GET', "/order/checkout-forms/$orderId");
    }

    public function getOrderEvents(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/order/events', [], $params);
    }

    public function getOrderEventsStats(): array
    {
        return $this->makeApiRequest('GET', '/order/event-stats');
    }

    public function setOrderFulfilled(string $orderId): array
    {
        return $this->makeApiRequest('PUT', "/order/checkout-forms/$orderId/fulfillment", [
            'status' => 'SENT',
        ]);
    }

    public function addOrderTracking(string $orderId, string $carrierId, string $trackingNumber, array $lineItems): array
    {
        return $this->makeApiRequest('POST', "/order/checkout-forms/$orderId/parcel-tracking-numbers", [
            'carrierId'      => $carrierId,
            'waybill'        => $trackingNumber,
            'lineItems'      => $lineItems,
        ]);
    }

    public function getParcelTrackingNumbers(string $orderId): array
    {
        return $this->makeApiRequest('GET', "/order/checkout-forms/$orderId/parcel-tracking-numbers");
    }

    public function getOrderInvoices(string $orderId): array
    {
        return $this->makeApiRequest('GET', "/order/checkout-forms/$orderId/invoices");
    }

    public function uploadOrderInvoice(string $orderId, array $invoiceData): array
    {
        return $this->makeApiRequest('POST', "/order/checkout-forms/$orderId/invoices", $invoiceData);
    }

    // -------------------------------------------------------------------------
    // Payments & Refunds
    // -------------------------------------------------------------------------

    public function getPaymentOperations(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/payments/payment-operations', [], $params);
    }

    public function initiateRefund(string $paymentId, array $refundData): array
    {
        return $this->makeApiRequest('POST', "/payments/refunds", array_merge(
            ['payment' => ['id' => $paymentId]],
            $refundData
        ));
    }

    // -------------------------------------------------------------------------
    // Shipments (Wysyłam z Allegro)
    // -------------------------------------------------------------------------

    public function getDeliveryServices(): array
    {
        return $this->makeApiRequest('GET', '/shipment-management/delivery-services');
    }

    public function createShipment(array $shipmentData): array
    {
        return $this->makeApiRequest('POST', '/shipment-management/shipments', $shipmentData);
    }

    public function getShipment(string $commandId): array
    {
        return $this->makeApiRequest('GET', "/shipment-management/shipments/creation-commands/$commandId");
    }

    public function getShipmentLabels(array $shipmentIds): array
    {
        return $this->makeApiRequest('POST', '/shipment-management/labels', [
            'shipmentIds' => $shipmentIds,
        ]);
    }

    public function cancelShipment(string $shipmentId): array
    {
        return $this->makeApiRequest('POST', "/shipment-management/shipments/$shipmentId/cancel");
    }

    // -------------------------------------------------------------------------
    // Post-Purchase Issues (Disputes & Claims)
    // -------------------------------------------------------------------------

    public function getPostPurchaseIssues(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/order/post-buy-issues', [], $params);
    }

    public function getPostPurchaseIssue(string $issueId): array
    {
        return $this->makeApiRequest('GET', "/order/post-buy-issues/$issueId");
    }

    public function getIssueMessages(string $issueId): array
    {
        return $this->makeApiRequest('GET', "/order/post-buy-issues/$issueId/messages");
    }

    public function addIssueMessage(string $issueId, string $text, array $attachments = []): array
    {
        return $this->makeApiRequest('POST', "/order/post-buy-issues/$issueId/messages", [
            'text'        => $text,
            'attachments' => $attachments,
        ]);
    }

    // -------------------------------------------------------------------------
    // Delivery / Shipping Rates
    // -------------------------------------------------------------------------

    public function getShippingRates(): array
    {
        return $this->makeApiRequest('GET', '/sale/shipping-rates');
    }

    public function getDeliveryMethods(): array
    {
        return $this->makeApiRequest('GET', '/sale/delivery-methods');
    }

    public function getDeliverySettings(): array
    {
        return $this->makeApiRequest('GET', '/sale/delivery-settings');
    }

    // -------------------------------------------------------------------------
    // After-Sales Services
    // -------------------------------------------------------------------------

    public function getReturnPolicies(): array
    {
        return $this->makeApiRequest('GET', '/after-sales-service-conditions/return-policies');
    }

    public function getWarranties(): array
    {
        return $this->makeApiRequest('GET', '/after-sales-service-conditions/warranties');
    }

    // -------------------------------------------------------------------------
    // User / Account
    // -------------------------------------------------------------------------

    public function getUserInfo(): array
    {
        return $this->makeApiRequest('GET', '/me');
    }

    public function getMarketplaces(): array
    {
        return $this->makeApiRequest('GET', '/marketplaces');
    }

    // -------------------------------------------------------------------------
    // Message Centre
    // -------------------------------------------------------------------------

    public function getMessageThreads(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/messaging/threads', [], $params);
    }

    public function getMessageThread(string $threadId): array
    {
        return $this->makeApiRequest('GET', "/messaging/threads/$threadId");
    }

    public function getThreadMessages(string $threadId, array $params = []): array
    {
        return $this->makeApiRequest('GET', "/messaging/threads/$threadId/messages", [], $params);
    }

    public function sendMessage(string $threadId, string $text, array $attachments = []): array
    {
        return $this->makeApiRequest('POST', "/messaging/threads/$threadId/messages", [
            'text'        => $text,
            'attachments' => $attachments,
        ]);
    }

    public function markThreadAsRead(string $threadId): array
    {
        return $this->makeApiRequest('PUT', "/messaging/threads/$threadId/read", [
            'read' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    public function getCategories(array $params = []): array
    {
        return $this->makeApiRequest('GET', '/sale/categories', [], $params);
    }

    public function getCategory(string $categoryId): array
    {
        return $this->makeApiRequest('GET', "/sale/categories/$categoryId");
    }

    public function getCategoryParameters(string $categoryId): array
    {
        return $this->makeApiRequest('GET', "/sale/categories/$categoryId/parameters");
    }
}
