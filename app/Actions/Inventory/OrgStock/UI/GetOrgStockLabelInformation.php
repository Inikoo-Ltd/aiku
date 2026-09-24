<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The text each piece of label information prints for an org stock, taken from its trade units so a
 * label always reads what the product record says. Batch code and expiry date are stand ins, the
 * real ones are typed in when the labels are printed.
 */
class GetOrgStockLabelInformation
{
    use AsObject;

    /**
     * @return array<string, string>
     */
    public function handle(OrgStock $orgStock): array
    {
        $labelData  = GetOrgStockLabelData::run($orgStock);
        $tradeUnits = $orgStock->tradeUnits;

        $shared = fn (string $field): string => (string) GetOrgStockLabelData::make()->getSharedTradeUnit($tradeUnits, $field)?->{$field};

        return [
            ArtefactLabelInformationEnum::BATCH_CODE->value      => strtoupper($orgStock->code).'-'.now()->format('ymd'),
            ArtefactLabelInformationEnum::EXPIRY_DATE->value     => now()->addYear()->format('d/m/Y'),
            ArtefactLabelInformationEnum::BARCODE->value         => (string) ($orgStock->barcode ?: $orgStock->unit_barcode),
            ArtefactLabelInformationEnum::PRODUCT_NAME->value    => (string) $labelData['name'],
            ArtefactLabelInformationEnum::NET_WEIGHT->value      => (string) $labelData['weight'],
            ArtefactLabelInformationEnum::MADE_IN->value         => (string) $labelData['made_in'],
            ArtefactLabelInformationEnum::MANUFACTURED_BY->value => (string) $labelData['manufactured_by'],
            ArtefactLabelInformationEnum::IMPORTER->value        => (string) $labelData['signature'],
            ArtefactLabelInformationEnum::INGREDIENTS->value     => $shared('marketing_ingredients'),
            ArtefactLabelInformationEnum::CPNP_NUMBER->value     => $shared('cpnp_number'),
            ArtefactLabelInformationEnum::UFI_NUMBER->value      => $shared('ufi_number'),
            ArtefactLabelInformationEnum::SCPN_NUMBER->value     => $shared('scpn_number'),
            ArtefactLabelInformationEnum::WARNINGS->value        => $shared('gpsr_warnings'),
        ];
    }
}
