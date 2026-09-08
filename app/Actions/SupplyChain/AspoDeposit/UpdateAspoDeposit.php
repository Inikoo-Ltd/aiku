<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:11:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AspoDeposit;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SupplyChain\AspoDeposit;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateAspoDeposit extends OrgAction
{
    use WithActionUpdate;

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
            'reference'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount'      => ['sometimes', 'numeric', 'gt:0'],
            'currency_id' => ['sometimes', 'exists:currencies,id'],
            'notes'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function handle(AspoDeposit $aspoDeposit, array $modelData): AspoDeposit
    {
        return $this->update($aspoDeposit, $modelData);
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
