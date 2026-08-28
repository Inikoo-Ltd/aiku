<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:21:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\DepositRequestItem;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class MarkDepositRequestItemPaid extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(DepositRequestItem $depositRequestItem): DepositRequestItem
    {
        $depositRequestItem->update(['paid_at' => now()]);

        $depositRequest = $depositRequestItem->depositRequest;
        if ($depositRequest->items()->whereNull('paid_at')->doesntExist()) {
            $depositRequest->update([
                'state'      => DepositRequestStateEnum::SETTLED,
                'settled_at' => now(),
            ]);
        }

        return $depositRequestItem;
    }

    public function asController(DepositRequestItem $depositRequestItem, ActionRequest $request): DepositRequestItem
    {
        $this->initialisation($depositRequestItem->organisation, $request);

        return $this->handle($depositRequestItem);
    }

    public function action(DepositRequestItem $depositRequestItem): DepositRequestItem
    {
        $this->asAction = true;
        $this->initialisation($depositRequestItem->organisation, []);

        return $this->handle($depositRequestItem);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }

}
