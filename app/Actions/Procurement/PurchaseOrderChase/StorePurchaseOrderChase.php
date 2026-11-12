<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderChase;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderChase\PurchaseOrderChaseChannelEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderChase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Logs a chase of an open purchase order. A promised date later than the expected date becomes the new
 * expected date, so an order past its date leaves the chase list once the supplier gives a new one.
 */
class StorePurchaseOrderChase extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrderChase
    {
        $chase = $purchaseOrder->chases()->create([
            'group_id'        => $purchaseOrder->group_id,
            'organisation_id' => $purchaseOrder->organisation_id,
            'user_id'         => Arr::get($modelData, 'user_id'),
            'chased_at'       => Arr::get($modelData, 'chased_at', now()),
            'channel'         => $modelData['channel'],
            'contact'         => Arr::get($modelData, 'contact'),
            'promised_at'     => Arr::get($modelData, 'promised_at'),
            'note'            => Arr::get($modelData, 'note'),
        ]);

        if ($chase->promised_at && (!$purchaseOrder->estimated_received_at || $chase->promised_at->gt($purchaseOrder->estimated_received_at))) {
            UpdatePurchaseOrder::make()->action($purchaseOrder, ['estimated_receiving_date' => $chase->promised_at->toDateString()]);
        }

        return $chase;
    }

    public function rules(): array
    {
        return [
            'channel'     => ['required', Rule::enum(PurchaseOrderChaseChannelEnum::class)],
            'contact'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'promised_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'note'        => ['sometimes', 'nullable', 'string', 'max:2000'],
            'chased_at'   => ['sometimes', 'date', 'before_or_equal:'.Carbon::now()->addDay()->toDateString()],
            'user_id'     => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->purchaseOrder->state, PurchaseOrderStateEnum::open())) {
            $validator->errors()->add('channel', __('Only an open purchase order is chased'));
        }
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrderChase
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, [...Arr::except($this->validatedData, 'user_id'), 'user_id' => $request->user()->id]);
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrderChase
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
