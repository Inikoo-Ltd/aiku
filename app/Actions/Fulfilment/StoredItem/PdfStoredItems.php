<?php

/*
 * author Louis Perez
 * created on 07-05-2026-15h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class PdfStoredItems extends RetinaAction
{
    use WithExportData;
    use WithStoredItemsExport;

    public function handle(FulfilmentCustomer $fulfilmentCustomer): Response
    {
        return $this->processDataExportPdf($fulfilmentCustomer);
    }

    public function inFulfilmentCustomer(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        $this->initialisation($request);
        return $this->handle($fulfilmentCustomer);
    }
}
