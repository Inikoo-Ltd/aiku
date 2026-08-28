<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:12:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AspoDeposit;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\AspoDepositStateEnum;
use App\Models\SupplyChain\AspoDeposit;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateAspoDepositState extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(AspoDepositStateEnum::class)],
        ];
    }

    public function handle(AspoDeposit $aspoDeposit, array $modelData): AspoDeposit
    {
        $state = AspoDepositStateEnum::from($modelData['state']);

        $timestampField = match ($state) {
            AspoDepositStateEnum::PAID_TO_SUPPLIER => 'paid_to_supplier_at',
            AspoDepositStateEnum::REFUNDED         => 'refunded_at',
            AspoDepositStateEnum::CANCELLED        => 'cancelled_at',
            default                                 => null,
        };

        $data = ['state' => $state];
        if ($timestampField) {
            $data[$timestampField] = now();
        }

        $aspoDeposit->update($data);

        return $aspoDeposit;
    }

    public function asController(AspoDeposit $aspoDeposit, ActionRequest $request): AspoDeposit
    {
        $this->initialisationFromGroup($aspoDeposit->group, $request);

        return $this->handle($aspoDeposit, $this->validatedData);
    }

    public function action(AspoDeposit $aspoDeposit, array $modelData): AspoDeposit
    {
        $this->asAction = true;
        $this->initialisationFromGroup($aspoDeposit->group, $modelData);

        return $this->handle($aspoDeposit, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }

}
