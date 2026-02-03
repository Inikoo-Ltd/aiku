<?php

/*
 * author Arya Permana - Kirin
 * created on 13-02-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithImportModel;
use App\Imports\Fulfilment\StoredItemsImport;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Upload;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportStoredItems extends OrgAction
{
    use WithImportModel;
    use WithFulfilmentShopAuthorisation;

    private Fulfilment $parent;
    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Upload
    {
        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        $upload = StoreUpload::make()->fromFile(
            $fulfilmentCustomer->fulfilment->shop,
            $file,
            [
                'model' => 'StoredItem',
                'customer_id' => $fulfilmentCustomer->customer_id,
                'parent_type' => $fulfilmentCustomer->getMorphClass(),
                'parent_id' => $fulfilmentCustomer->id,
            ]
        );

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new StoredItemsImport($upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new StoredItemsImport($upload)
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

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Upload
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($fulfilmentCustomer, $file, $this->validatedData);
    }

    public function runImportForCommand($file, $command): Upload
    {
        $fulfilmentCustomer = FulfilmentCustomer::where('slug', $command->argument('fulfilmentCustomer'))->first();

        return $this->handle($fulfilmentCustomer, $file, []);
    }

    public string $commandSignature = 'stored_items:import {--g|g_drive} {filename} {fulfilmentCustomer?} {warehouse?} {palletDelivery?}';
}
