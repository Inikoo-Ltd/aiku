<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-11h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Transaction;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateOrders;
use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateOrders;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

use function Pest\Laravel\json;

class StoreApiOrderTransaction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, Product $product, array $modelData): Transaction
    {
        $transaction = StoreTransaction::make()->action($order, $product->historicAsset, $modelData);

        return $transaction;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered'    => ['required', 'numeric', 'min:0'],
        ];

        return $rules;
    }

    public function asController(Order $order, Product $product, ActionRequest $request): Transaction
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($order, $product, $validatedData);
    }

    public function jsonResponse(Transaction $transaction)
    {
        return [
            'transaction' => $transaction, //todo: transaction resource
        ];
    }
}
