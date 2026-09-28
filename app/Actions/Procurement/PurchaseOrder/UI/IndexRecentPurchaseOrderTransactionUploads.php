<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 14:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Http\Resources\Helpers\UploadsResource;
use App\Models\Helpers\Upload;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class IndexRecentPurchaseOrderTransactionUploads extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(PurchaseOrder $purchaseOrder, User $user): Collection
    {
        return Upload::where('user_id', $user->id)
            ->where('parent_type', $purchaseOrder->getMorphClass())
            ->where('parent_id', $purchaseOrder->id)
            ->whereDate('created_at', today())
            ->orderBy('created_at')
            ->get();
    }

    public function jsonResponse(Collection $uploads): AnonymousResourceCollection
    {
        return UploadsResource::collection($uploads);
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): Collection
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $request->user());
    }
}
