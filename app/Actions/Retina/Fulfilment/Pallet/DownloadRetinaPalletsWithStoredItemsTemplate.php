<?php
/*
 * author Arya Permana - Kirin
 * created on 25-03-2025-16h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\DownloadPalletsTemplate;
use App\Actions\Fulfilment\Pallet\DownloadPalletStoredItemTemplate;
use App\Actions\RetinaAction;
use App\Exports\Pallets\PalletTemplateExport;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadRetinaPalletsWithStoredItemsTemplate extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public function handle(): BinaryFileResponse
    {
        return DownloadPalletStoredItemTemplate::run();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->customer->id == $request->route()->parameter('palletDelivery')->fulfilmentCustomer->customer_id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [];
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletDelivery $palletDelivery, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($request);

        return $this->handle();
    }

    public function inReturn(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn): BinaryFileResponse
    {
        return $this->handle();
    }
}
