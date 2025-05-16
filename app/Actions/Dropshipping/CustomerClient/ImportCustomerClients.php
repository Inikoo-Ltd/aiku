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
use App\Imports\CRM\ProspectImport;
use App\Models\Helpers\Upload;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportCustomerClients extends OrgAction
{
    use WithImportModel;

    public function handle(Customer $customer, Platform $platform, $file): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $customer->shop,
            $file,
            [
                'model' => 'CustomerClient',
                'customer_id' => $customer->id,
                'parent_type' => $customer->getMorphClass(),
                'parent_id' => $customer->id,
            ]
        );


        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new CustomerClientImport($customer, $platform, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new CustomerClientImport($customer, $platform, $upload)
            );
        }


        return $upload;
    }

    public function rules(): array
    {
        return [
            'file'             => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(Customer $customer, Platform $platform, ActionRequest $request): Upload
    {
        $this->initialisationFromShop($customer->shop, $request);
        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);
        return $this->handle($customer, $platform, $file);
    }

    public function action(Customer $customer, Platform $platform, array $modelData): Upload
    {
        $this->initialisationFromShop($customer->shop, $modelData);
        $file = Arr::get($modelData, 'file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($customer, $platform, $file);
    }

    public function jsonResponse(Upload $upload): array
    {
        return UploadsResource::make($upload)->getArray();
    }
}
