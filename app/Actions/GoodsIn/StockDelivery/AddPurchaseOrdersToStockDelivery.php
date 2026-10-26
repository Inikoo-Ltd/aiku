<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class AddPurchaseOrdersToStockDelivery extends OrgAction
{
    use AsAction;

    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::userCanAdd($this->stockDelivery, $request->user());
    }

    public static function userCanAdd(StockDelivery $stockDelivery, User $user): bool
    {
        if ($user->authTo("procurement.{$stockDelivery->organisation_id}.edit")) {
            return true;
        }

        $agentOrganisationId = $stockDelivery->parent_type === 'OrgAgent' ? $stockDelivery->agent?->organisation_id : null;

        return $agentOrganisationId && $user->authTo("procurement.$agentOrganisationId.edit");
    }

    public static function acceptsPurchaseOrders(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->parent_type === 'OrgAgent'
            && $stockDelivery->agent_id
            && $stockDelivery->state === StockDeliveryStateEnum::IN_PROCESS;
    }

    /**
     * A purchase order still on a container Aurora brought in stays with that container. One of its lines put there
     * from Aiku does not count: the rest of the order can go in other containers.
     *
     * @return Builder<PurchaseOrder>
     */
    public static function shipsFromAgentWarehouse(StockDelivery $stockDelivery): bool
    {
        return (bool) $stockDelivery->agent?->receivesGoodsInOwnWarehouse();
    }

    public static function addablePurchaseOrders(StockDelivery $stockDelivery): Builder
    {
        $fromAgentWarehouse    = self::shipsFromAgentWarehouse($stockDelivery);
        $itemsAwaitingDelivery = fn (Builder|HasMany $transactions) => StoreStockDeliveryFromPurchaseOrder::whereReadyToShip($transactions, $fromAgentWarehouse);

        return PurchaseOrder::query()
            ->when(!self::acceptsPurchaseOrders($stockDelivery), fn (Builder $query) => $query->whereRaw('false'))
            ->where('organisation_id', $stockDelivery->organisation_id)
            ->where('agent_id', $stockDelivery->agent_id)
            ->where('parent_type', 'OrgSupplier')
            ->where('state', PurchaseOrderStateEnum::CONFIRMED)
            ->where('currency_id', $stockDelivery->currency_id)
            ->whereHas('purchaseOrderTransactions', $itemsAwaitingDelivery)
            ->whereDoesntHave('stockDeliveries', fn (Builder $deliveries) => $deliveries
                ->whereNotNull('source_id')
                ->whereNotIn('state', [
                    StockDeliveryStateEnum::BOOKED_IN,
                    StockDeliveryStateEnum::PLACED,
                    StockDeliveryStateEnum::CANCELLED,
                    StockDeliveryStateEnum::NOT_RECEIVED,
                ])
                ->whereNotExists(fn ($items) => $items->from('stock_delivery_items')
                    ->whereColumn('stock_delivery_items.stock_delivery_id', 'stock_deliveries.id')
                    ->whereIn(
                        DB::raw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint"),
                        fn ($lines) => $lines->from('purchase_order_transactions')->select('purchase_order_transactions.id')->whereColumn('purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
                    )))
            ->withCount(['purchaseOrderTransactions as number_items_awaiting_delivery' => $itemsAwaitingDelivery])
            ->orderBy('reference');
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::acceptsPurchaseOrders($this->stockDelivery)) {
            $validator->errors()->add('purchase_order_ids', __('Purchase orders can only be added to an agent container in process'));
        }
    }

    public function rules(): array
    {
        return [
            'purchase_order_ids'   => ['required', 'array', 'min:1'],
            'purchase_order_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in(self::addablePurchaseOrders($this->stockDelivery)->pluck('purchase_orders.id')->all()),
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'purchase_order_ids.*.in' => __('This purchase order can not be added to this container, it has no items awaiting delivery or it is in another currency'),
        ];
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        DB::transaction(function () use ($stockDelivery, $modelData) {
            PurchaseOrder::whereIn('id', $modelData['purchase_order_ids'])->orderBy('id')->get()
                ->each(fn (PurchaseOrder $purchaseOrder) => StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder, [
                    'stock_delivery_id' => $stockDelivery->id,
                ]));
        });

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
