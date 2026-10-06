<?php

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Iris\Basket\StoreEcomOrder;
use App\Actions\Traits\InteractsWithOrderInBasket;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Actions\IrisAction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Retina\Ecom\Basket\RetinaEcomUpdateTransaction;
use App\Actions\Retina\UI\Dashboard\StoreRetinaDashboardBasketAdd;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaEcomBasketTransaction extends IrisAction
{
    use InteractsWithOrderInBasket;
    use WithCustomerPurchasableProduct;

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function handle(Customer $customer, Product $product, array $modelData): Transaction
    {
        $order = $this->getOrderInBasket($customer);

        $transaction = $order ? $this->findCustomerLine($order, $product) : null;
        if ($transaction) {
            return RetinaEcomUpdateTransaction::make()->action(
                $transaction,
                $customer,
                [
                    'quantity_ordered' => data_get($modelData, 'quantity')
                ]
            );
        }

        $this->ensureProductIsPurchasableByCustomer($product, $customer, data_get($modelData, 'quantity'));

        if (!$order) {
            $order = StoreEcomOrder::make()->action($customer);
        }

        $historicAsset = $product->currentHistoricProduct;

        $order->update([
            'updated_by_customer_at' => now()
        ]);

        return StoreTransaction::make()->action($order, $historicAsset, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);
    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'integer', 'min:0'],
            'dashboard_section' => ['sometimes', 'nullable', Rule::in(StoreRetinaDashboardBasketAdd::SECTIONS)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Product $product, ActionRequest $request): Transaction
    {
        $user = $request->user();
        if (!$user) {
            abort('422');
        }
        $customer = $user->customer;
        $this->initialisation($request);

        $transaction = $this->handle($customer, $product, $this->validatedData);

        if ($section = Arr::get($this->validatedData, 'dashboard_section')) {
            StoreRetinaDashboardBasketAdd::run($customer, $section, $transaction->order_id, [$product->id => $transaction->quantity_ordered]);
        }

        return $transaction;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(Transaction $transaction): array
    {
        $product = $transaction->model;

        return [
            'transaction_id'    => $transaction->id,
            'quantity_ordered'  => (int)$transaction->quantity_ordered,
            'department_id'     => $product?->department_id,
            'sub_department_id' => $product?->sub_department_id,
            'family_id'         => $product?->family_id,
            'is_golden_product' => (bool)$product?->is_golden_product,
        ];
    }
}
