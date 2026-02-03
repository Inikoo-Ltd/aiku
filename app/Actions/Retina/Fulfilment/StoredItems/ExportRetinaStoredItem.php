<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\StoredItems;

use App\Actions\Fulfilment\StoredItem\ExportStoredItem;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRetinaStoredItem extends RetinaAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): BinaryFileResponse
    {
        return ExportStoredItem::run($fulfilmentCustomer, $modelData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($request);

        return $this->handle($this->fulfilmentCustomer, $request->all());
    }
}
