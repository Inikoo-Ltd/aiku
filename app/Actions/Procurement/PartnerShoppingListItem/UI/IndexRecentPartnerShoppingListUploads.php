<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Http\Resources\Helpers\UploadsResource;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class IndexRecentPartnerShoppingListUploads extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(OrgPartner $orgPartner, User $user): Collection
    {
        return Upload::where('user_id', $user->id)
            ->where('parent_type', $orgPartner->getMorphClass())
            ->where('parent_id', $orgPartner->id)
            ->where('model', 'PartnerShoppingListItem')
            ->whereDate('created_at', today())
            ->orderBy('created_at')
            ->get();
    }

    public function jsonResponse(Collection $uploads): AnonymousResourceCollection
    {
        return UploadsResource::collection($uploads);
    }

    public function asController(OrgPartner $orgPartner, ActionRequest $request): Collection
    {
        $this->initialisation($orgPartner->organisation, $request);

        return $this->handle($orgPartner, $request->user());
    }
}
