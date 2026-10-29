<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelData;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithAgentProductsAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class FetchAgentOrgStockBarcodeLabelOptions extends OrgAction
{
    use WithAgentOrganisation;
    use WithAgentProductsAuthorisation;
    use WithAgentOrgStockBarcodeLabel;

    /**
     * @return array<string, mixed>
     */
    public function handle(Organisation $organisation, OrgStock $orgStock, ?int $supplierProductId = null, ?int $commercialisingOrganisationId = null): array
    {
        return [
            'options'     => GetOrgStockLabelOptions::run(
                $orgStock,
                $supplierProductId,
                GetOrgStockLabelData::make()->getCommercialisingOrganisation($orgStock, $commercialisingOrganisationId)
            ),
            'label_route' => [
                'name'       => 'grp.org.procurement.agent_labels.barcode_label',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'orgStock'     => $orgStock->id,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $labelOptions
     * @return array<string, mixed>
     */
    public function jsonResponse(array $labelOptions): array
    {
        return $labelOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, OrgStock $orgStock, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $this->ensureAgentBuysOrgStock($organisation, $orgStock);

        return $this->handle($organisation, $orgStock, $request->integer('supplier_product') ?: null, $request->integer('commercialised_by') ?: null);
    }
}
