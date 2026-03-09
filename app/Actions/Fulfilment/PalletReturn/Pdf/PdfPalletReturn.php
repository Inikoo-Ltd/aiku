<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 14:48:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\Pdf;

use App\Actions\Traits\WithExportData;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfPalletReturn
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(PalletReturn $palletReturn): Response
    {
        $filename = 'pallet-return-' . $palletReturn->slug . '.pdf';

        $config = [
            'title'                  => $filename,
            'margin_left'            => 8,
            'margin_right'           => 8,
            'margin_top'             => 2,
            'margin_bottom'          => 2,
            'auto_page_break'        => true,
            'auto_page_break_margin' => 10
        ];

        $data = [
            'filename'     => $filename,
            'return'       => $palletReturn,
            'customer'     => $palletReturn->fulfilmentCustomer->customer,
            'shop'         => $palletReturn->fulfilment->shop,
            'organisation' => $palletReturn->organisation,
        ];

        $pallets = $palletReturn
            ->pallets()
            ->with('location');
        
        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {    
            $pallets = $pallets
                ->with('storedItems')
                ->get()
                ->keyBy('id');  
            data_set($data, 'pallets', $pallets);
        } else {
            $pallets = $pallets
                ->get()
                ->keyBy('id');
            $storedItems = PalletReturnItem::select([
                    'pallet_id',
                    'stored_item_id'
                ])
                ->where('pallet_return_id', $palletReturn->id)
                ->get()
                ->groupBy('stored_item_id')
                ->map(
                    fn ($items) => $items->pluck('pallet_id')->mapWithKeys(fn ($id) => [$id => $pallets->get($id)])
                )
                ->toArray();

            data_set($data, 'stored_items_pallet_data', $storedItems);
        }

        $pdf = PDF::chunkLoadView('<html-separator/>', 'pickings.templates.pdf.return', $data, [], $config);

        return response($pdf->stream($filename . '.pdf'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.pdf"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(PalletReturn $palletReturn, ActionRequest $request): Response
    {
        return $this->handle($palletReturn);
    }
}
