<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfJobOrder extends OrgAction
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
        $allItems = $jobOrder->jobOrderItems()->with('artefact.orgStock')->orderBy('id')->get();
        $unitsByLine = $allItems->groupBy(fn (JobOrderItem $item) => $item->split_from_id ?? $item->id)
            ->map(fn ($lineItems) => (int)$lineItems->sum('quantity'));

        $items = $allItems->whereNull('split_from_id')
            ->map(fn (JobOrderItem $item) => [
                'code'        => $item->artefact->code,
                'description' => $item->artefact->name,
                'units'       => $unitsByLine[$item->id],
                'skos'        => round($unitsByLine[$item->id] / ($item->artefact->orgStock?->packed_in ?: 1), 2),
            ]);

        $pdf = PDF::loadView('production.job-order', [
            'title'      => trim(__('Job order').' '.$jobOrder->reference.' '.$jobOrder->public_notes),
            'jobOrder'   => $jobOrder,
            'sentAt'     => $jobOrder->confirmed_at,
            'items'      => $items,
            'printedAt'  => now()->format('j M Y H:i'),
        ], [], [
            'format'        => 'A4-L',
            'margin_top'    => 30,
            'margin_header' => 10,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="job-order-'.$jobOrder->slug.'.pdf"');
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
