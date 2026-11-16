<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\DeliveryNoteBoxPackingList;
use App\Actions\Dispatching\DeliveryNote\PdfPackingList;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\Response;

class DownloadMobilePackingListPdf
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanView($deliveryNote, $user);
        abort_unless(DeliveryNoteBoxPackingList::make()->isRequired($deliveryNote), 404);

        return PdfPackingList::make()->handle($deliveryNote);
    }
}
