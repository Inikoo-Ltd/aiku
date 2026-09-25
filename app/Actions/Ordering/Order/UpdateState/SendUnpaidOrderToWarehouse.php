<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The accounting supervisor's credit decision: release a submitted order to the warehouse before it is
 * fully paid (e.g. a customer on payment terms), leaving who decided it and why in the internal notes.
 */
class SendUnpaidOrderToWarehouse extends OrgAction
{
    use WithActionUpdate;

    private Order $order;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, User $user, string $reason): ?DeliveryNote
    {
        return DB::transaction(function () use ($order, $user, $reason) {
            $this->update($order, [
                'internal_notes' => collect([
                    $order->internal_notes,
                    __('Sent to the warehouse unpaid by :name on :date: :reason', [
                        'name'   => $user->contact_name ?: $user->username,
                        'date'   => now()->format('Y-m-d H:i'),
                        'reason' => $reason,
                    ]),
                ])->filter()->implode("\n"),
            ]);

            return SendOrderToWarehouse::make()->action($order->refresh(), []);
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-supervisor.{$this->organisation->id}.accounting");
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state != OrderStateEnum::SUBMITTED) {
            $validator->errors()->add('state', __('Only submitted orders can be sent to the warehouse'));
        } elseif ($this->order->pay_status == OrderPayStatusEnum::PAID) {
            $validator->errors()->add('pay_status', __('This order is paid, use the ordinary send to warehouse'));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, User $user, array $modelData): ?DeliveryNote
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $user, $this->validatedData['reason']);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): ?DeliveryNote
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $request->user(), $this->validatedData['reason']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
