<?php

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Iris\Basket\StoreEcomBasket;
use App\Actions\IrisAction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaEcomBasketTransaction extends IrisAction
{
    public function handle(Customer $customer, Product $product, array $modelData): Transaction
    {
        $order = $customer->orderInBasket;

        if (!$order) {
            $order = StoreEcomBasket::make()->action($customer);
        }

        $historicAsset = $product->currentHistoricProduct;

        return StoreTransaction::make()->action($order, $historicAsset, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);

    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(Product $product, ActionRequest $request): Transaction
    {
        $customer = $request->user()->customer;
        $this->initialisation($request);

        return $this->handle($customer, $product, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(Transaction $transaction): array
    {
        return [
            'transaction_id'    => $transaction->id,
            'quantity_ordered'  => (int) $transaction->quantity_ordered
        ];
    }
}
