<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-13h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\PalletDelivery\ImportPalletsInPalletDelivery;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithImportModel;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Helpers\Upload;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportRetinaPallet extends RetinaAction
{
    use WithImportModel;

    public function handle(PalletDelivery $palletDelivery, $file, array $modelData): Upload
    {
        return ImportPalletsInPalletDelivery::run($palletDelivery, $file, $modelData);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
            'with_stored_item' => ['sometimes', 'required', 'bool'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customer->fulfilmentCustomer->id === $request->route('palletDelivery')->fulfilment_customer_id;
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): Upload
    {
        $this->initialisation($request);

        $file = $request->file('file');
        Storage::disk('local')->put($this->tmpPath, $file);

        return $this->handle($palletDelivery, $file, $this->validatedData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge([
            'with_stored_item' => $request->input('stored_item') == 'true',
        ]);
    }
}
