<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Production\Artefact\Label\GetArtefactLabelIconSource;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Production\Artefact\ArtefactLabelInformationEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The text each piece of label information prints for an org stock, taken from its trade units so a
 * label always reads what the product record says. Batch code and expiry date are stand ins, the
 * real ones are typed in when the labels are printed. Icons read as the comma separated keys of the
 * pictures they print.
 */
class GetOrgStockLabelInformation
{
    use AsObject;

    private const RESPONSIBLE_PERSON_ORGANISATIONS = [
        'uk_responsible_person' => ['market' => TradeUnitMarketEnum::UK, 'organisation' => 'aw'],
        'eu_responsible_person' => ['market' => TradeUnitMarketEnum::EU, 'organisation' => 'sk'],
    ];

    private const TRANSLATIONS = [
        'product_name'       => 'name_i8n',
        'warnings'           => 'gpsr_warnings_i8n',
        'directions_for_use' => 'gpsr_manual_i8n',
    ];

    /**
     * @return array<string, string>
     */
    public function handle(OrgStock $orgStock): array
    {
        $labelData  = GetOrgStockLabelData::run($orgStock);
        $tradeUnits = $orgStock->tradeUnits;

        $shared = fn (string $field): string => (string) GetOrgStockLabelData::make()->getSharedTradeUnit($tradeUnits, $field)?->{$field};

        return array_merge([
            ArtefactLabelInformationEnum::BATCH_CODE->value            => strtoupper($orgStock->code).'-'.now()->format('ymd'),
            ArtefactLabelInformationEnum::EXPIRY_DATE->value           => now()->addYear()->format('d/m/Y'),
            ArtefactLabelInformationEnum::BARCODE->value               => (string) ($orgStock->barcode ?: $orgStock->unit_barcode),
            ArtefactLabelInformationEnum::PRODUCT_NAME->value          => (string) $labelData['name'],
            ArtefactLabelInformationEnum::NET_WEIGHT->value            => (string) $labelData['weight'],
            ArtefactLabelInformationEnum::MADE_IN->value               => (string) $labelData['made_in'],
            ArtefactLabelInformationEnum::MANUFACTURED_BY->value       => (string) $labelData['manufactured_by'],
            ArtefactLabelInformationEnum::IMPORTER->value              => (string) $labelData['signature'],
            ArtefactLabelInformationEnum::UK_RESPONSIBLE_PERSON->value => $this->getResponsiblePerson($tradeUnits, 'uk_responsible_person'),
            ArtefactLabelInformationEnum::EU_RESPONSIBLE_PERSON->value => $this->getResponsiblePerson($tradeUnits, 'eu_responsible_person'),
            ArtefactLabelInformationEnum::INGREDIENTS->value           => $shared('marketing_ingredients'),
            ArtefactLabelInformationEnum::CPNP_NUMBER->value           => $shared('cpnp_number'),
            ArtefactLabelInformationEnum::UFI_NUMBER->value            => $shared('ufi_number'),
            ArtefactLabelInformationEnum::SCPN_NUMBER->value           => $shared('scpn_number'),
            ArtefactLabelInformationEnum::WARNINGS->value              => $shared('gpsr_warnings'),
            ArtefactLabelInformationEnum::DIRECTIONS_FOR_USE->value    => $shared('gpsr_manual'),
            ArtefactLabelInformationEnum::HAZARD_PICTOGRAMS->value     => implode(',', $this->getHazardPictograms($tradeUnits)),
            ArtefactLabelInformationEnum::PACKAGING_MATERIALS->value   => implode(',', $this->getLabelInfoList($tradeUnits, 'packaging_material_codes.value')),
            ArtefactLabelInformationEnum::CE_MARKING->value            => $this->hasLabelInfo($tradeUnits, 'ce_marking') ? GetArtefactLabelIconSource::CE_MARKING : '',
            ArtefactLabelInformationEnum::UKCA_MARKING->value          => $this->hasLabelInfo($tradeUnits, 'ukca_marking') ? GetArtefactLabelIconSource::UKCA_MARKING : '',
            ArtefactLabelInformationEnum::WEEE_SYMBOL->value           => $this->hasLabelInfo($tradeUnits, 'weee_symbol') ? GetArtefactLabelIconSource::WEEE_SYMBOL : '',
            ArtefactLabelInformationEnum::PERIOD_AFTER_OPENING->value  => implode(',', array_filter(
                $this->getLabelInfoList($tradeUnits, 'best_before'),
                fn (string $bestBefore) => GetArtefactLabelIconSource::isPeriodAfterOpening($bestBefore)
            )),
            ArtefactLabelInformationEnum::SORTING_INFORMATION->value   => $this->hasLabelInfo($tradeUnits, 'sorting_recycling_information')
                ? GetArtefactLabelIconSource::TRIMAN.','.GetArtefactLabelIconSource::INFO_TRI
                : '',
            ArtefactLabelInformationEnum::FREE_TEXT->value             => '',
        ], $this->getTranslations($orgStock, $tradeUnits));
    }

    /**
     * Only offered for a market the product record says it is sold in.
     */
    private function getResponsiblePerson(Collection $tradeUnits, string $information): string
    {
        $responsiblePerson = self::RESPONSIBLE_PERSON_ORGANISATIONS[$information];

        if (!in_array($responsiblePerson['market']->value, $this->getLabelInfoList($tradeUnits, 'markets'), true)) {
            return '';
        }

        $organisation = Organisation::where('slug', $responsiblePerson['organisation'])->first();

        return $organisation ? (string) GetOrgStockLabelData::make()->getSignature($organisation) : '';
    }

    /**
     * Every language the product record asks for, plus any the product texts are already translated
     * into. A language with no translation yet still comes back, blank, for the text to be typed in.
     *
     * @return array<string, string>
     */
    private function getTranslations(OrgStock $orgStock, Collection $tradeUnits): array
    {
        $products = $orgStock->products()->get(['products.id', ...array_map(fn (string $column) => 'products.'.$column, self::TRANSLATIONS)]);

        $translations = [];
        foreach (self::TRANSLATIONS as $information => $column) {
            $translations[$information] = $products->reduce(
                fn (array $carry, Product $product) => $carry + array_filter($product->getTranslations($column), 'filled'),
                []
            );
        }

        $languageCodes = array_unique(array_merge(
            $this->getLabelInfoList($tradeUnits, 'languages'),
            ...array_map('array_keys', array_values($translations))
        ));
        sort($languageCodes);

        $information = [];
        foreach ($languageCodes as $languageCode) {
            foreach (array_keys(self::TRANSLATIONS) as $translated) {
                $information[ArtefactLabelInformationEnum::inLanguage(ArtefactLabelInformationEnum::from($translated), $languageCode)]
                    = trim((string) ($translations[$translated][$languageCode] ?? ''));
            }
        }

        return $information;
    }

    /**
     * A box holding several trade units carries the hazards of all of them.
     *
     * @return array<int, string>
     */
    private function getHazardPictograms(Collection $tradeUnits): array
    {
        return array_values(array_filter(
            array_keys(GetArtefactLabelIconSource::HAZARD_PICTOGRAMS),
            fn (string $pictogram) => $tradeUnits->contains(fn (TradeUnit $tradeUnit) => (bool) $tradeUnit->{'pictogram_'.$pictogram})
        ));
    }

    /**
     * @return array<int, string>
     */
    private function getLabelInfoList(Collection $tradeUnits, string $key): array
    {
        return $tradeUnits
            ->flatMap(fn (TradeUnit $tradeUnit) => (array) data_get($tradeUnit->label_info, $key, []))
            ->filter(fn (mixed $value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function hasLabelInfo(Collection $tradeUnits, string $key): bool
    {
        return $tradeUnits->contains(fn (TradeUnit $tradeUnit) => data_get($tradeUnit->label_info, $key) === true);
    }
}
