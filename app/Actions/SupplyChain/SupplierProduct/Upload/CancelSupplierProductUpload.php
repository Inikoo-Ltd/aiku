<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Models\Helpers\Upload;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class CancelSupplierProductUpload extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(Upload $upload): Upload
    {
        if ($upload->state === UploadStateEnum::WAITING_CONFIRMATION) {
            $upload->update(['state' => UploadStateEnum::CANCELLED]);
        }

        return $upload;
    }

    public function asController(Upload $upload, ActionRequest $request): Upload
    {
        $this->initialisationFromGroup($upload->group, $request);

        return $this->handle($upload);
    }

    public function htmlResponse(Upload $upload): RedirectResponse
    {
        return redirect()->route('grp.supply-chain.suppliers.supplier_products.index', ['supplier' => $upload->parent->slug]);
    }
}
