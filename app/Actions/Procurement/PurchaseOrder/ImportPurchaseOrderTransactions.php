<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 14:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Imports\Procurement\PurchaseOrderTransactionImport;
use App\Models\Helpers\Upload;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class ImportPurchaseOrderTransactions extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder, UploadedFile $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $purchaseOrder->organisation,
            $file,
            [
                'model'       => 'PurchaseOrderTransaction',
                'parent_type' => $purchaseOrder->getMorphClass(),
                'parent_id'   => $purchaseOrder->id,
            ]
        );

        ImportUpload::run($file, new PurchaseOrderTransactionImport($purchaseOrder, $upload));

        return $upload->refresh();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
            $validator->errors()->add('file', __('Products can only be uploaded while the purchase order is in process'));
        }
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): Upload
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData['file']);
    }

    public function action(PurchaseOrder $purchaseOrder, UploadedFile $file): Upload
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, ['file' => $file]);

        return $this->handle($purchaseOrder, $this->validatedData['file']);
    }
}
