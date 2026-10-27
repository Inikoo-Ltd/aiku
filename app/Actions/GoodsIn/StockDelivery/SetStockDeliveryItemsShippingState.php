<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToConfirmedStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToReadyToShipStockDeliveryItem;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Confirms, or sets as ready to ship, several items of a container at once: each item goes through the same action
 * as its own row button, all in one transaction so a selection is moved whole or not at all.
 */
class SetStockDeliveryItemsShippingState extends OrgAction
{
    private StockDelivery $stockDelivery;

    private const array FROM_STATE = [
        'confirmed'     => StockDeliveryItemStateEnum::IN_PROCESS,
        'ready_to_ship' => StockDeliveryItemStateEnum::CONFIRMED,
    ];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    public function rules(): array
    {
        $fromState = self::FROM_STATE[$this->get('state')] ?? null;

        return [
            'state'   => ['required', Rule::in(array_keys(self::FROM_STATE))],
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in($fromState ? $this->stockDelivery->items()->where('stock_delivery_items.state', $fromState)->pluck('stock_delivery_items.id')->all() : []),
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->isManagedByPartner()) {
            $validator->errors()->add('items', __('This delivery is managed by the partner until you receive it'));
        }
    }

    /**
     * @param  int[]  $stockDeliveryItemIds
     */
    public function handle(StockDelivery $stockDelivery, string $state, array $stockDeliveryItemIds): StockDelivery
    {
        $setState = $state === 'confirmed'
            ? fn (StockDeliveryItem $item) => UpdateStateToConfirmedStockDeliveryItem::make()->action($item)
            : fn (StockDeliveryItem $item) => UpdateStateToReadyToShipStockDeliveryItem::make()->action($item);

        DB::transaction(function () use ($stockDelivery, $stockDeliveryItemIds, $setState) {
            StockDelivery::whereKey($stockDelivery->id)->lockForUpdate()->first();

            $stockDelivery->items()->whereIn('stock_delivery_items.id', $stockDeliveryItemIds)->get()->each($setState);
        });

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData['state'], $this->validatedData['items']);
    }

    /**
     * @param  int[]  $stockDeliveryItemIds
     */
    public function action(StockDelivery $stockDelivery, string $state, array $stockDeliveryItemIds): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, ['state' => $state, 'items' => $stockDeliveryItemIds]);

        return $this->handle($stockDelivery, $this->validatedData['state'], $this->validatedData['items']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
