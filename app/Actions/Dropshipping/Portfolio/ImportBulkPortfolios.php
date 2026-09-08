<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\WithImportModel;
use App\Imports\Dropshipping\CustomerSalesChannelPortfoliosImport;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportBulkPortfolios extends OrgAction
{
    use WithImportModel;
    use WithCRMEditAuthorisation;

    public function handle(CustomerSalesChannel $customerSalesChannel, UploadedFile $file): Upload
    {
        Storage::disk('local')->put($this->tmpPath, $file);

        $upload = StoreUpload::make()->fromFile(
            $customerSalesChannel->customer->shop,
            $file,
            [
                'model'       => 'Portfolio',
                'customer_id' => $customerSalesChannel->customer_id,
                'parent_type' => $customerSalesChannel->getMorphClass(),
                'parent_id'   => $customerSalesChannel->id,
            ]
        );

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new CustomerSalesChannelPortfoliosImport($customerSalesChannel, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new CustomerSalesChannelPortfoliosImport($customerSalesChannel, $upload)
            );
        }

        return $upload;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Upload
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel, $request->file('file'));
    }
}
