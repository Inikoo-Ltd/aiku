<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Actions\Traits\WithExportData;
use App\Exports\StoredItem\PalletReturnPalletStoredItemExport;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRetinaPalletReturnStoredItem extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new PalletReturnPalletStoredItemExport($fulfilmentCustomer), 'pallet-return-stored-items', $type);
    }

    /**
     * @throws \Throwable
     */
    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($request);

        return $this->handle($fulfilmentCustomer, $request->all());
    }
}
