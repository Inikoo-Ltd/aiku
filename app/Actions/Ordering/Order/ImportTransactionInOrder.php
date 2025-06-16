<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:29:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\Upload\ImportUpload;
use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Traits\WithImportModel;
use App\Imports\Ordering\TransactionImport;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Upload;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportTransactionInOrder extends OrgAction
{
    use WithImportModel;

    private Shop $parent;

    public function handle(Order $order, $file, array $modelData): Upload
    {
        $upload = StoreUpload::make()->fromFile(
            $order->shop,
            $file,
            [
                'model' => 'Transaction',
                'customer_id' => $order->customer_id,
                'parent_type' => $order->getMorphClass(),
                'parent_id' => $order->id,
            ]
        );

        if ($this->isSync) {
            ImportUpload::run(
                $file,
                new TransactionImport($order, $upload)
            );
            $upload->refresh();
        } else {
            ImportUpload::dispatch(
                $this->tmpPath.$upload->filename,
                new TransactionImport($order, $upload)
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

    public function asController(Order $order, ActionRequest $request): Upload
    {
        $this->parent = $order->shop;
        $this->initialisationFromShop($order->shop, $request);

        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($order, $file, $this->validatedData);
    }

    public function action(Order $order, array $modelData): Upload
    {
        $this->parent = $order->shop;
        $this->initialisationFromShop($order->shop, $modelData);


        $file = Arr::get($modelData, 'file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($order, $file, $this->validatedData);
    }

    public function runImportForCommand($file, $command): Upload
    {
        $palletDeliverySlug = $command->argument('order');
        $palletDelivery = Order::where('slug', $palletDeliverySlug)->first();

        return $this->handle($palletDelivery, $file, []);
    }

    public string $commandSignature = 'order-transaction:import {--g|g_drive} {filename} {order}';
}
