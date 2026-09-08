<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Actions\OrgAction;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\SysAdmin\Organisation;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

/**
 * The label that makes a stored item scannable: its reference rendered as a CODE 128 barcode, the
 * same way a pallet label carries the pallet reference. Scan-to-pick on stored item returns
 * matches that reference, so a stored item wearing this label is picked by scanner.
 */
class PdfStoredItem extends OrgAction
{
    public function handle(StoredItem $storedItem): Response
    {
        try {
            $config = [
                'title'                  => $storedItem->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
                'orientation'            => 'L',
            ];

            $filename = $storedItem->slug.'-'.now()->format('Y-m-d');
            $pdf      = PDF::loadView('pickings.templates.pdf.stored-item', [
                'shop'       => $storedItem->fulfilment->shop,
                'storedItem' => $storedItem,
                'customer'   => $storedItem->fulfilmentCustomer->customer,
            ], [], $config);

            return response($pdf->stream(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
        } catch (Exception) {
            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }

    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, StoredItem $storedItem, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($storedItem);
    }
}
