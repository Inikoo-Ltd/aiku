<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Ordering\PreOrder\CancelPreOrder;
use App\Actions\Ordering\PreOrder\MarkPreOrdersSupplierOrdered;
use App\Actions\OrgAction;
use App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * The buying team either placed the supplier order for a group of pre-orders, or could not
 * reach the supplier's minimum by the order-by date and cancels them with a full refund.
 */
class UpdatePreOrdersForSupplier extends OrgAction
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(array $modelData): int
    {
        $preOrderIds = $modelData['pre_order_ids'];

        if ($modelData['operation'] == 'supplier_ordered') {
            return MarkPreOrdersSupplierOrdered::run($preOrderIds);
        }

        $cancelled = 0;
        foreach (PreOrder::whereIn('id', $preOrderIds)->whereIn('state', PreOrderStateEnum::open())->get() as $preOrder) {
            CancelPreOrder::run($preOrder, PreOrderCancellationReasonEnum::from($modelData['cancellation_reason']));
            $cancelled++;
        }

        return $cancelled;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'operation'           => ['required', Rule::in(['supplier_ordered', 'cancel'])],
            'pre_order_ids'       => ['required', 'array'],
            'pre_order_ids.*'     => ['integer', Rule::exists('pre_orders', 'id')->where('organisation_id', $this->organisation->id)],
            'cancellation_reason' => ['required_if:operation,cancel', 'nullable', Rule::in([
                PreOrderCancellationReasonEnum::SUPPLIER_MINIMUM_NOT_MET->value,
                PreOrderCancellationReasonEnum::SUPPLIER_CANNOT_SUPPLY->value,
            ])],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Organisation $organisation, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
