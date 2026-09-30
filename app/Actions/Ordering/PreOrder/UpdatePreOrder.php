<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\PreOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * What staff do to a pre-order from its panel on the order page (HELP-3432).
 */
class UpdatePreOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(PreOrder $preOrder, array $modelData, ?User $user = null): PreOrder
    {
        return match ($modelData['operation']) {
            'unlock' => UnlockPreOrder::run($preOrder, $user),
            'lock' => UnlockPreOrder::run($preOrder, $user, unlock: false),
            'supplier_ordered' => tap($preOrder, fn () => MarkPreOrdersSupplierOrdered::run([$preOrder->id]))->refresh(),
            'goods_arrived' => ArrivePreOrder::run($preOrder),
            'pallet_quote' => SetPreOrderPalletQuote::run($preOrder, (float) $modelData['amount']),
            'dispatch_dates' => UpdatePreOrderDispatchDates::run($preOrder, $modelData['from'], $modelData['to'], Arr::get($modelData, 'reason')),
            'release' => ReleasePreOrder::run($preOrder),
            'cancel' => CancelPreOrder::run($preOrder, PreOrderCancellationReasonEnum::from($modelData['cancellation_reason']), Arr::get($modelData, 'notes')),
        };
    }

    public function rules(): array
    {
        return [
            'operation'           => ['required', Rule::in(['unlock', 'lock', 'supplier_ordered', 'goods_arrived', 'pallet_quote', 'dispatch_dates', 'release', 'cancel'])],
            'amount'              => ['required_if:operation,pallet_quote', 'nullable', 'numeric', 'min:0'],
            'from'                => ['required_if:operation,dispatch_dates', 'nullable', 'date'],
            'to'                  => ['required_if:operation,dispatch_dates', 'nullable', 'date', 'after_or_equal:from'],
            'reason'              => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cancellation_reason' => ['required_if:operation,cancel', 'nullable', Rule::enum(PreOrderCancellationReasonEnum::class)],
            'notes'               => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, ActionRequest $request): PreOrder
    {
        abort_unless($order->preOrder, 404);
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order->preOrder, $this->validatedData, $request->user());
    }
}
