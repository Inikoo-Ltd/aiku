<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Pallets\PalletDeliveryPalletExport;
use App\Exports\Pallets\PalletReturnPalletExport;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPalletDeliveryPallet extends OrgAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(PalletDelivery $palletDelivery, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new PalletDeliveryPalletExport($palletDelivery), 'pallet-delivery-pallets', $type);
    }

    public function prepareForValidation()
    {
        $this->set('type', 'xlsx');
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Fulfilment $fulfilment, PalletDelivery $palletDelivery, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromFulfilment($fulfilment, $request);
        
        return $this->handle($palletDelivery, $this->validatedData);
    }
}
