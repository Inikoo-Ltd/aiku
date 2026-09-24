<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Production\Artefact\Label\DownloadArtefactLabelPdf;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Production\ArtefactLabel;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * An agent prints the published labels of the SKOs it buys, with the batch code and expiry date of
 * the goods in front of it. It can print them, never change them.
 */
class DownloadAgentArtefactLabelPdf extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, OrgStock $orgStock, ArtefactLabel $label, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        $agent = $this->getOrganisationAgent($organisation);

        abort_unless(
            $agent
            && $label->org_stock_id === $orgStock->id
            && GetAgentOrgStocks::run($agent)->where('org_stocks.id', $orgStock->id)->exists(),
            404
        );

        $download = DownloadArtefactLabelPdf::make();

        return $download->handle($orgStock, $label, $download->getRunTexts($request));
    }
}
