<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

class PdfStockDelivery extends OrgAction
{
    use WithExportData;

    /**
     * @throws MpdfException
     */
    public function handle(StockDelivery $stockDelivery): Response
    {
        $stockDelivery->loadMissing(['items.orgStock', 'organisation', 'currency']);

        $filename = $stockDelivery->slug.'-'.Carbon::now()->format('Y-m-d');

        $pdf = PDF::loadView('goodsIn.templates.pdf.stock-delivery', [
            'stockDelivery' => $stockDelivery,
            'items'         => $stockDelivery->items,
        ]);

        return response($pdf->stream($filename.'.pdf'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
    }

    public function authorize(): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($stockDelivery);
    }
}
