<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-09h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Client;

use App\Actions\Dropshipping\CustomerClient\ImportCustomerClients;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithImportModel;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Upload;
use Lorisleiva\Actions\ActionRequest;

class ImportRetinaClients extends RetinaAction
{
    use WithImportModel;

    public function handle(Customer $customer, Platform $platform, $file): Upload
    {
        return ImportCustomerClients::make()->action($customer, $platform, $file);
    }

    public function rules(): array
    {
        return [
            'file'             => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(Platform $platform, ActionRequest $request)
    {
        $this->initialisationFromPlatform($platform, $request);

        $this->handle($this->customer, $platform, $this->validatedData);
    }

}
