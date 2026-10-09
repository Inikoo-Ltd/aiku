<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

class PdfAgentInvoice extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * @throws MpdfException
     */
    public function handle(AgentInvoice $agentInvoice): Response
    {
        $agentInvoice->loadMissing(['agent.organisation.address', 'organisation.address', 'stockDelivery', 'currency']);

        $pdf = PDF::loadView('supplyChain.templates.pdf.agent-invoice', [
            'invoice' => $agentInvoice,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$agentInvoice->reference.'.pdf"');
    }

    public function asController(Organisation $organisation, AgentInvoice $agentInvoice, ActionRequest $request): Response
    {
        abort_unless($organisation->agent?->id === $agentInvoice->agent_id || $organisation->id === $agentInvoice->organisation_id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentInvoice);
    }
}
