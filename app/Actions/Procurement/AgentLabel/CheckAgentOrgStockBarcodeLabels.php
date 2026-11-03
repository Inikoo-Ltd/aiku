<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-02 10:17
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentLabel;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * Which of the ticked SKOs would be left out of the bulk barcode label file and why, so the agent sees it before
 * printing.
 */
class CheckAgentOrgStockBarcodeLabels extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function rules(): array
    {
        return PdfAgentOrgStockBarcodeLabels::make()->rules();
    }

    /**
     * @return array{printable: int, skipped: list<array{id: int, code: string, reason: string}>}
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        $bulkLabels = PdfAgentOrgStockBarcodeLabels::make();
        $options    = $bulkLabels->labelOptions($request);
        $orgStocks  = $bulkLabels->orgStocks($agent, $bulkLabels->orgStockIds($request));
        $skipped    = $bulkLabels->plan($orgStocks, $request->input('level'), $options['standard'], $options['batch'] ?? null);

        return [
            'printable' => $orgStocks->count() - count($skipped),
            'skipped'   => $skipped,
        ];
    }

    /**
     * @param  array{printable: int, skipped: list<array{id: int, code: string, reason: string}>}  $check
     * @return array{printable: int, skipped: list<array{id: int, code: string, reason: string}>}
     */
    public function jsonResponse(array $check): array
    {
        return $check;
    }
}
