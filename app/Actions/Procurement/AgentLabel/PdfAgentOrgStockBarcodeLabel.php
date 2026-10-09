<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel;

use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * The SKO and unit barcode label an agent sticks on the goods it buys for us, the same PDF the
 * warehouse prints, reached from the agent's own organisation since the agent is not authorised in ours.
 */
class PdfAgentOrgStockBarcodeLabel extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;
    use WithAgentOrgStockBarcodeLabel;

    public function rules(): array
    {
        return PdfOrgStockLabel::make()->rules();
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, OrgStock $orgStock, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $this->ensureAgentBuysOrgStock($organisation, $orgStock);

        return PdfOrgStockLabel::make()->handle($orgStock, $request->input('level', 'unit'), $request->all());
    }
}
