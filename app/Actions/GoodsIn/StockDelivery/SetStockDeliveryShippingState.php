<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-25 14:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Moves the container itself one shipping step forward (in process → confirmed → ready to ship) or back, before it is
 * dispatched. Its items keep their own states.
 */
class SetStockDeliveryShippingState extends OrgAction
{
    use HasStockDeliveryHydrators;

    public int $hydratorsDelay = 0;

    private const array NEXT_STATE = [
        StockDeliveryStateEnum::IN_PROCESS->value => StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::CONFIRMED->value  => StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    private const array PREVIOUS_STATE = [
        StockDeliveryStateEnum::CONFIRMED->value     => StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::READY_TO_SHIP->value => StockDeliveryStateEnum::CONFIRMED,
    ];

    private StockDelivery $stockDelivery;

    public static function nextState(StockDelivery $stockDelivery): ?StockDeliveryStateEnum
    {
        return self::NEXT_STATE[$stockDelivery->state->value] ?? null;
    }

    public static function previousState(StockDelivery $stockDelivery): ?StockDeliveryStateEnum
    {
        return self::PREVIOUS_STATE[$stockDelivery->state->value] ?? null;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['next', 'previous'])],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && $this->stockDelivery->isManagedByPartner()) {
            $validator->errors()->add('state', __('This delivery is managed by the partner until you receive it'));

            return;
        }

        $targetState = $this->get('direction') === 'next' ? self::nextState($this->stockDelivery) : self::previousState($this->stockDelivery);

        if ($targetState === null) {
            $validator->errors()->add('state', __('You can not change the shipping state of this stock delivery with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery, string $direction): StockDelivery
    {
        $isForward   = $direction === 'next';
        $targetState = $isForward ? self::nextState($stockDelivery) : self::previousState($stockDelivery);

        $timestamps = $isForward
            ? [$targetState->value.'_at' => now()]
            : [$stockDelivery->state->value.'_at' => null];

        $stockDelivery->update([
            'state' => $targetState,
            ...$timestamps,
        ]);

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData['direction']);
    }

    public function action(StockDelivery $stockDelivery, string $direction): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, ['direction' => $direction]);

        return $this->handle($stockDelivery, $this->validatedData['direction']);
    }

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return redirect()->back();
    }
}
