<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Jul 2026 15:18:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\PastPay;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentAccount;
use App\Models\Ordering\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithPastpayConfiguration
{
    public PaymentAccount $paymentAccount;

    protected function pastpayClient(): PendingRequest
    {
        return Http::baseUrl($this->getBaseUrl())
            ->withHeaders([
                'X-Api-Key' => $this->getApiKey(),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->retry(2, 500);
    }

    protected function getBaseUrl(): string
    {
        return app()->isProduction()
            ? config('services.pastpay.base_url', 'https://api.pastpay.com')
            : config('services.pastpay.sandbox_url', 'https://api.demo.pastpay.com');
    }

    protected function getApiKey(): string
    {
        return config('services.pastpay.demo_api_key', Arr::get($this->paymentAccount->data, 'credentials.api_key'));
    }

    protected function getShopId(): string
    {
        return Arr::get($this->paymentAccount->data, 'tax_number');
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayPost(string $endpoint, array $payload): array
    {

        return $this->pastpayHandleResponse(
            $this->pastpayClient()->post($endpoint, $payload),
            $endpoint
        );
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayGet(string $endpoint, array $query = []): array
    {
        return $this->pastpayHandleResponse(
            $this->pastpayClient()->get($endpoint, $query),
            $endpoint
        );
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayPatch(string $endpoint, array $payload): array
    {
        return $this->pastpayHandleResponse(
            $this->pastpayClient()->patch($endpoint, $payload),
            $endpoint
        );
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayDelete(string $endpoint): array
    {
        return $this->pastpayHandleResponse(
            $this->pastpayClient()->delete($endpoint),
            $endpoint
        );
    }

    private function pastpayHandleResponse(Response $response, string $endpoint): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $body    = $response->json();
        $message = $body['message'] ?? $body['error'] ?? 'PastPay API error';
        $code    = $body['code']    ?? $response->status();

        throw new \RuntimeException(
            "[PastPay] $endpoint → HTTP {$response->status()}: $message (code: $code)"
        );
    }

    protected function pastpayBuildOrderPayload(Order $order, array $extra = []): array
    {
        return array_merge([
            'debtorTaxNumber' => $order->customer->taxNumber->number,
            'orderId'         => (string) $order->reference,
            'totalPrice'       => [
                'amount' => (float) $order->net_amount,
                'currency'        => $order->currency->code
            ],
            'termDays'  => Arr::get($extra, 'termDays', 30),
            'paymentRedirectUrl' => [
                'success'      => route('retina.models.success_order_pay_by_pastpay', $order->slug),
                'failure'         => route('retina.ecom.checkout.show', [
                    'order' => $order->slug
                ]),
            ],
        ], $extra);
    }

    public function pastpayBuildFinalizePayload(Invoice $invoice, array $extra = []): array
    {
        return array_merge([
            'creditorTaxNumber' => $this->getShopId(),
            'invoiceNo' => $invoice->reference,
            'invoicePdf' => route('retina.ecom.invoices.pdf', [
                'invoice' => $invoice->slug
            ]),
            'issueDate'   => $invoice->date->toDateString(),
            'dueDate' => $invoice->date
                ->addDays(Arr::get($extra, 'termDays'))
                ->toDateString(),
            'totalPrice'       => [
                'amount' => (float) $invoice->net_amount,
                'currency' => $invoice->currency->code
            ],
        ], $extra);
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayInitiateOrder(Order $order, array $extra = []): array
    {
        return $this->pastpayPost('store/order', $this->pastpayBuildOrderPayload($order, $extra));
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayFinalizeOrder(Invoice $invoice, array $extra = []): array
    {
        $pastpayOrderId = $this->pastpayResolveOrderId($invoice->order);

        return $this->pastpayPatch(
            "/order/$pastpayOrderId/finalize",
            $this->pastpayBuildFinalizePayload($invoice, $extra)
        );
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayCancelOrder(Order $order): array
    {
        return $this->pastpayDelete('/order/' . $this->pastpayResolveOrderId($order));
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayGetOrder(Order $order): array
    {
        return $this->pastpayGet('/order/' . $this->pastpayResolveOrderId($order));
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function pastpayGetDebtorLimit(string $debtorTaxNumber): array
    {
        return $this->pastpayGet("/debtor/$debtorTaxNumber/limit");
    }

    private function pastpayResolveOrderId(Order $order): string
    {
        $id = $order->reference ?? null;

        abort_if(! $id, 422, 'Order has no PastPay order ID — was it initiated?');

        return $id;
    }
}
