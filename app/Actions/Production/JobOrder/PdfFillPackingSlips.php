<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

/** HELP-4638: one page per order a filling job fills, so the bottles wait with their order until it goes to the warehouse */
class PdfFillPackingSlips extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
        ]);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(JobOrder $jobOrder): Response
    {
        $slips = FillToOrder::make()->slips($jobOrder);
        abort_if($slips->isEmpty(), 404);

        $pdf = PDF::loadView('production.fill-packing-slips', [
            'jobOrder'  => $jobOrder,
            'slips'     => $slips,
            'printedAt' => now($jobOrder->organisation->timezone?->name ?? config('app.timezone'))->format('j M Y H:i'),
        ], [], ['format' => 'A5']);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="packing-slips-'.$jobOrder->slug.'.pdf"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, Production $production, JobOrder $jobOrder, ActionRequest $request): Response
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($jobOrder);
    }
}
