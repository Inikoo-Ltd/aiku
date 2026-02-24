<?php

/*
 * author Arya Permana - Kirin
 * created on 13-02-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithImportModel;
use App\Imports\Dropshipping\CustomerSalesChannelPortfoliosImport;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Helpers\Upload;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportBulkCustomerSalesChannelPortfolios extends RetinaAction
{
    use WithImportModel;

    private Fulfilment $parent;
    public function handle(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Upload
    {
        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        $upload = StoreUpload::make()->fromFile(
            $customerSalesChannel->customer->shop,
            $file,
            [
                'model' => 'Portfolio',
                'customer_id' => $customerSalesChannel->customer_id,
                'parent_type' => $customerSalesChannel->getMorphClass(),
                'parent_id' => $customerSalesChannel->id,
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
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request);
    }

    public function runImportForCommand($file, $command): Upload
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        return $this->handle($customerSalesChannel, $file, []);
    }

    public string $commandSignature = 'portfolios:import {--g|g_drive} {filename} {customerSalesChannel}';
}
