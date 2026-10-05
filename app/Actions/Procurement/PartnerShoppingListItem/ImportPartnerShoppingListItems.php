<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Imports\Procurement\PartnerShoppingListItemImport;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;

class ImportPartnerShoppingListItems extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(OrgPartner $orgPartner, UploadedFile $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $orgPartner->organisation,
            $file,
            [
                'model'       => 'PartnerShoppingListItem',
                'parent_type' => $orgPartner->getMorphClass(),
                'parent_id'   => $orgPartner->id,
            ]
        );

        ImportUpload::run($file, new PartnerShoppingListItemImport($orgPartner, $upload));

        return $upload->refresh();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): Upload
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->validatedData['file']);
    }

    public function action(OrgPartner $orgPartner, UploadedFile $file): Upload
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, ['file' => $file]);

        return $this->handle($orgPartner, $this->validatedData['file']);
    }
}
