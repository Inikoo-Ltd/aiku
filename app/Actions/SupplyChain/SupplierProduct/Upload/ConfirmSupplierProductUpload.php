<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\Helpers\Upload;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class ConfirmSupplierProductUpload extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function asController(Upload $upload, ActionRequest $request): Upload
    {
        $this->initialisationFromGroup($upload->group, $request);

        return ImportSupplierProductUpload::run($upload);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
