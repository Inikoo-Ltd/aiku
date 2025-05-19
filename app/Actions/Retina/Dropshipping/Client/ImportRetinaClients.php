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
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Upload;
use Lorisleiva\Actions\ActionRequest;

class ImportRetinaClients extends RetinaAction
{
    use WithImportModel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $file): Upload
    {
        return ImportCustomerClients::make()->action($customerSalesChannel, $file);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }

}
