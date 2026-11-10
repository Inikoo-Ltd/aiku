<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Iris\Basket;

use App\Actions\IrisAction;
use App\Actions\Traits\InteractsWithOrderInBasket;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreEcomBasketTransactions extends IrisAction
{
    use InteractsWithOrderInBasket;
    use WithCustomerPurchasableProduct;

    /**
     * @param array<int, array{product_id: int, quantity: int}> $lines
     * @return array{lines: array<int, array<string, mixed>>, added: int, failed: int}
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $lines): array
    {
        return Cache::lock("ecom-basket:customer:{$customer->id}", 60)->block(5, function () use ($customer, $lines) {
            $results = collect($lines)
                ->map(fn (array $line) => $this->processLine($customer, (int)$line['product_id'], (int)$line['quantity']))
                ->values();

            $results = $this->withServerAmounts($results);

            return [
                'lines'  => $results->all(),
                'added'  => $results->whereIn('status', ['added', 'updated', 'removed'])->count(),
                'failed' => $results->where('status', 'failed')->count(),
            ];
        });
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $results
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function withServerAmounts(Collection $results): Collection
    {
        $transactions = Transaction::whereIn('id', $results->pluck('transaction_id')->filter()->all())->get()->keyBy('id');

        return $results->map(function (array $line) use ($transactions) {
            $transaction = $transactions->get($line['transaction_id']);

            if (!$transaction || !in_array($line['status'], ['added', 'updated'], true)) {
                return $line;
            }

            $offer = Arr::get($transaction->offers_data, 'o');

            return array_merge($line, [
                'gross_amount' => (float)$transaction->gross_amount,
                'net_amount'   => (float)$transaction->net_amount,
                'offer_label'  => Arr::get($offer, 'l') ? trim(Arr::get($offer, 'l').' '.Arr::get($offer, 'p', '')) : null,
            ]);
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function processLine(Customer $customer, int $productId, int $quantity): array
    {
        $product = Product::find($productId);

        try {
            if (!$product || $product->shop_id != $customer->shop_id) {
                throw ValidationException::withMessages(['message' => __('This product is not available for purchase.')]);
            }

            $customer->refresh();
            $order        = $this->getOrderInBasket($customer);
            $existingLine = $order ? $this->findCustomerLine($order, $product) : null;

            if ($quantity === 0 && !$existingLine) {
                return $this->lineResult($productId, $product, 'unchanged', null, null, 0);
            }

            $transaction = DB::transaction(
                fn () => StoreEcomBasketTransaction::make()->addProductToBasket($customer->refresh(), $product, ['quantity' => $quantity])
            );

            $status = match (true) {
                $quantity === 0                => 'removed',
                $existingLine !== null         => 'updated',
                default                        => 'added',
            };

            return $this->lineResult($productId, $product, $status, null, $status === 'removed' ? null : $transaction->id, $status === 'removed' ? 0 : (int)$transaction->quantity_ordered);
        } catch (ValidationException $exception) {
            $message = Arr::first(Arr::flatten($exception->errors())) ?: __('This product is not available for purchase.');

            return $this->lineResult($productId, $product, 'failed', $message, null, 0);
        } catch (Throwable $exception) {
            report($exception);

            return $this->lineResult($productId, $product, 'failed', __('This product is not available for purchase.'), null, 0);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lineResult(int $productId, ?Product $product, string $status, ?string $message, ?int $transactionId, int $quantityOrdered): array
    {
        return [
            'product_id'        => $productId,
            'code'              => $product?->code,
            'status'            => $status,
            'message'           => $message,
            'transaction_id'    => $transactionId,
            'quantity_ordered'  => $quantityOrdered,
            'family_id'         => $product?->family_id,
            'is_golden_product' => (bool)$product?->is_golden_product,
        ];
    }

    public function rules(): array
    {
        return [
            'lines'                => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.product_id'   => ['required', 'integer', 'distinct'],
            'lines.*.quantity'     => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): array
    {
        $customer = $request->user()?->customer;

        if (!$customer) {
            abort(404);
        }

        $this->initialisation($request);

        return $this->handle($customer, $this->validatedData['lines']);
    }

    /**
     * @param array{lines: array<int, array<string, mixed>>, added: int, failed: int} $result
     * @return array{lines: array<int, array<string, mixed>>, added: int, failed: int}
     */
    public function jsonResponse(array $result): array
    {
        return $result;
    }
}
