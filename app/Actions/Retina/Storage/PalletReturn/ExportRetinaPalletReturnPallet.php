<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Storage\PalletReturn;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Pallets\PalletReturnPalletExport;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRetinaPalletReturnPallet extends RetinaAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new PalletReturnPalletExport($fulfilmentCustomer), 'pallet-return-pallets', $type);
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): BinaryFileResponse
    {
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($fulfilmentCustomer, $request->all());
    }
}
