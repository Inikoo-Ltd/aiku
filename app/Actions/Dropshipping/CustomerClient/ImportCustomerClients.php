<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 08:23:57 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\WithImportModel;
use App\Http\Resources\Helpers\UploadsResource;
use App\Imports\CRM\CustomerClientImport;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Upload;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ImportCustomerClients extends OrgAction
{
    use WithImportModel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $customerSalesChannel->shop,
            $file,
            [
                'model' => 'CustomerClient',
                'customer_id' => $customerSalesChannel->customer_id,
                'parent_type' => $customerSalesChannel->getMorphClass(),
                'parent_id' => $customerSalesChannel->id,
            ]
        );


        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new CustomerClientImport($customerSalesChannel, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new CustomerClientImport($customerSalesChannel, $upload)
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


    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData): Upload
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $modelData);
        $file = Arr::get($modelData, 'file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($customerSalesChannel, $file);
    }

    public function jsonResponse(Upload $upload): array
    {
        return UploadsResource::make($upload)->getArray();
    }
}
