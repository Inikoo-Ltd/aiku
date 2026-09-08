<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryDepositApplication;
use App\Models\SupplyChain\AspoDeposit;
use Illuminate\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ApplyStockDeliveryDeposit extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private StockDelivery $stockDelivery;
    private AspoDeposit $aspoDeposit;

    public function rules(): array
    {
        return [
            'aspo_deposit_id' => ['required', 'exists:aspo_deposits,id'],
            'amount'          => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $this->aspoDeposit = AspoDeposit::find($this->get('aspo_deposit_id'));

        if (!$this->aspoDeposit || $this->aspoDeposit->agent_id !== $this->stockDelivery->agent_id) {
            $validator->errors()->add('aspo_deposit_id', __('This deposit does not belong to the agent of this delivery'));

            return;
        }

        if ($this->aspoDeposit->state->value !== 'paid_to_supplier') {
            $validator->errors()->add('aspo_deposit_id', __('This deposit has not been paid to the supplier yet'));
        }

        if ((float) $this->get('amount') > $this->aspoDeposit->unapplied_amount) {
            $validator->errors()->add('amount', __('Amount exceeds the deposit unapplied balance'));
        }
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDeliveryDepositApplication
    {
        $application = $stockDelivery->depositApplications()->create([
            'group_id'        => $stockDelivery->group_id,
            'organisation_id' => $stockDelivery->organisation_id,
            'aspo_deposit_id' => $modelData['aspo_deposit_id'],
            'amount'          => $modelData['amount'],
            'created_by'      => $this->asAction ? null : request()->user()?->id,
        ]);

        EvaluateStockDeliveryCosting::run($stockDelivery);

        return $application;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDeliveryDepositApplication
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDeliveryDepositApplication
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
