<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Oct 2025 11:36:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\TopUp;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Models\Accounting\TopUp;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;
use Symfony\Component\HttpFoundation\Response;

class SinglePdfTopupPdf extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithInvoicesExport;


    public function handle(TopUp $topUp, array $options): Response
    {
        $customer = $topUp->customer;

        $locale = $customer->shop->language->code;
        app()->setLocale($locale);

        try {
            $config = [
                'title'                  => $topUp->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];


            $filename = $config['title'].'-'.now()->format('Y-m-d');
            $pdf      = PDF::loadView('invoices.templates.pdf.topup', [
                'shop'                 => $customer->shop,
                'topups'                => $customer->topUps()->where('id', $topUp->id)->get(),
                'reference'                => $config['title'],
                'customer' => $customer
            ], [], $config);

            return response($pdf->stream($filename.'.pdf'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
        } catch (Exception $e) {
            Sentry::captureException($e);

            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }

    public function asController(TopUp $topUp, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($topUp, $this->validatedData);
    }
}
