<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 12:05
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierChangeRequest\Json;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierChangeRequest\DecideSupplierChangeRequest;
use App\Models\SupplyChain\SupplierChangeRequest;
use Lorisleiva\Actions\ActionRequest;

/**
 * The step an approval in progress has reached, polled by the decision dialog while the approval runs.
 */
class GetSupplierChangeRequestProgress extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return DecideSupplierChangeRequest::canDecide($request->user());
    }

    /**
     * @return array{step: string, done: int, total: int, message: ?string, started_at: string}|null
     */
    public function handle(SupplierChangeRequest $supplierChangeRequest): ?array
    {
        return DecideSupplierChangeRequest::getProgress($supplierChangeRequest);
    }

    public function asController(SupplierChangeRequest $supplierChangeRequest, ActionRequest $request): array
    {
        $this->initialisationFromGroup($supplierChangeRequest->group, $request);

        return ['progress' => $this->handle($supplierChangeRequest)];
    }
}
