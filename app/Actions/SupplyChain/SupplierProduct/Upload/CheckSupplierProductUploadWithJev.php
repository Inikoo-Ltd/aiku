<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum as Column;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Upload;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Asks Jev a few yes/no questions about every new or changed row of a supplier product upload preview
 * (one call per row) and adds what it flags to the row's findings. Jev answers with probabilities only,
 * so each question carries its own message; written reasons come from the final review.
 */
class CheckSupplierProductUploadWithJev
{
    use AsAction;

    public const float BLOCK_PROBABILITY   = 0.8;
    public const float WARNING_PROBABILITY = 0.7;

    protected int $groupId;

    /** @var list<string>|null */
    protected ?array $usedLabels = null;

    public function handle(Upload $upload): Upload
    {
        $this->groupId = $upload->group_id;
        $upload->update(['data' => array_merge($upload->data ?? [], ['jev' => 'running'])]);

        $records = $upload->records()->where('status', UploadRecordStatusEnum::PREVIEW)->orderBy('row_number')->get();
        $failed  = 0;

        foreach ($records->values() as $index => $record) {
            $findings    = collect(Arr::get($record->data, 'findings', []))->reject(fn (array $finding) => Arr::get($finding, 'source') === 'jev')->values()->all();
            $jevFindings = $this->checkValues($upload->group_id, $record->values, $findings, $records->get($index - 1)?->values, $records->get($index + 1)?->values);
            if (collect($jevFindings)->contains('code', 'ai_checks_not_run')) {
                $failed++;
            }

            $record->update(['data' => array_merge($record->data ?? [], ['findings' => [...$findings, ...$jevFindings]])]);
        }

        $upload->update(['data' => array_merge($upload->data ?? [], ['jev' => $failed ? 'failed' : 'done'])]);

        return $upload;
    }

    /**
     * Jev's findings for one product (an upload row or the New supplier product form), or a block when it could not answer.
     *
     * @param array<string, mixed> $values
     * @param list<array{level: string, code: string, column: ?string, message: string}> $findings the rule findings already on the product
     * @param array<string, mixed>|null $previous values of the row above, on uploads
     * @param array<string, mixed>|null $next values of the row below, on uploads
     *
     * @return list<array{level: string, code: string, column: ?string, message: string, source: string}>
     */
    public function checkValues(int $groupId, array $values, array $findings, ?array $previous = null, ?array $next = null): array
    {
        $this->groupId = $groupId;

        $answers = AskJev::run($this->state($values, $previous, $next), $this->questions($values));
        if ($answers === null) {
            return [$this->finding('block', 'ai_checks_not_run', null, __('The AI checks could not run on this row.'))];
        }

        return $this->findings($values, $answers, collect($findings)->pluck('code')->all());
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(array $values, ?array $previous, ?array $next): array
    {
        return array_filter([
            'unit_name'                 => $values['unit_name'] ?? null,
            'unit_label'                => $values['unit_label'] ?? null,
            'materials'                 => $values['materials'] ?? null,
            'family'                    => $values['family'] ?? null,
            'other_products_in_family'  => implode('; ', $this->familyProductNames($values['family'] ?? null)),
            'unit_weight_grams'         => $values['unit_weight'] ?? null,
            'unit_size_cm'              => isset($values['unit_dimensions']) ? implode(' x ', $values['unit_dimensions']) : null,
            'units_per_sko'             => $values['units_per_sko'] ?? null,
            'skos_per_selling_outer'    => $values['skos_per_outer'] ?? null,
            'skos_per_carton'           => $values['skos_per_carton'] ?? null,
            'wholesale_price_gbp'       => $values['recommended_price'] ?? null,
            'tariff_code'               => $values['tariff_code'] ?? null,
            'row_above'                 => $previous ? trim(($previous['unit_name'] ?? '').' / materials: '.($previous['materials'] ?? '')) : null,
            'row_below'                 => $next ? trim(($next['unit_name'] ?? '').' / materials: '.($next['materials'] ?? '')) : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, array{type: string, instructions: string, criteria: array<string, string>}>
     */
    protected function questions(array $values): array
    {
        $question = fn (string $instructions, string $whenTrue, string $whenFalse) => [
            'type'         => 'noul',
            'instructions' => $instructions,
            'criteria'     => ['true' => $whenTrue, 'false' => $whenFalse],
        ];

        $questions = [
            'unit_name_is_pack' => $question(
                'Does unit_name describe a pack, set, box or several units rather than one single item?',
                'yes, a pack or several units',
                'no, one single item'
            ),
            'spelling'          => $question(
                'Do unit_name, unit_label or materials contain a spelling mistake or typo? Ignore brand names and capital letters.',
                'yes, there is a spelling mistake',
                'no spelling mistakes'
            ),
            'materials_misfit'  => $question(
                'Are the materials clearly wrong for the item named in unit_name?',
                'yes, clearly wrong materials',
                'no, the materials are plausible'
            ),
            'numbers_odd'       => $question(
                'Is any of unit_weight_grams, unit_size_cm, units_per_sko, skos_per_selling_outer, skos_per_carton or wholesale_price_gbp clearly wrong for the item named in unit_name?',
                'yes, a number is clearly wrong',
                'no, the numbers are plausible'
            ),
            'family_misfit'     => $question(
                'Is the item clearly a different kind of product from other_products_in_family?',
                'yes, a different kind of product',
                'no, it fits, or there are no other products'
            ),
            'rows_shifted'      => $question(
                'Do the materials of this row clearly belong to the item in row_above or row_below instead of this item?',
                'yes, they belong to a neighbouring row',
                'no'
            ),
        ];

        if (filled($values['unit_label'] ?? null) && !in_array(mb_strtolower($values['unit_label']), $this->usedLabels(), true)) {
            $questions['unit_label_odd'] = $question(
                'Is unit_label something other than a plain word for one single unit (such as piece, bag, jar, candle, pair)? Numbers, quantities, groups like pack or set, typos and fragments count as yes.',
                'yes, not a plain single unit word',
                'no, a plain single unit word'
            );
        }

        if (filled($values['tariff_code'] ?? null)) {
            $questions['tariff_misfit'] = $question(
                'Is tariff_code (an HS customs code) clearly wrong for the item named in unit_name?',
                'yes, clearly the wrong customs code',
                'no, the customs code is plausible'
            );
        }

        return $questions;
    }

    /**
     * @param array<string, array<string, mixed>> $answers
     * @param list<string> $existingCodes
     *
     * @return list<array{level: string, code: string, column: ?string, message: string, source: string}>
     */
    protected function findings(array $values, array $answers, array $existingCodes): array
    {
        $probability = fn (string $key) => (float)Arr::get($answers, $key.'.noul', 0);
        $blocks      = fn (string $key) => $probability($key) >= self::BLOCK_PROBABILITY;
        $flagged     = fn (string $key) => $probability($key) >= self::WARNING_PROBABILITY;
        $already     = collect($existingCodes);

        $findings = [];
        if ($blocks('unit_name_is_pack') && !$already->contains('unit_name_pack')) {
            $findings[] = $this->finding('block', 'jev_unit_name_pack', Column::UNIT_NAME, __('The unit name reads like a pack or a quantity, not a single unit.'));
        }
        if ($blocks('unit_label_odd') && !$already->contains('unit_label_odd')) {
            $findings[] = $this->finding('block', 'jev_unit_label_odd', Column::UNIT_LABEL, __('Unit label ":label" does not look like a single unit word.', ['label' => $values['unit_label']]));
        }
        if ($flagged('spelling')) {
            $findings[] = $this->finding('warning', 'jev_spelling', Column::UNIT_NAME, __('There may be a spelling mistake in the name, label or materials.'));
        }
        if ($flagged('materials_misfit')) {
            $findings[] = $this->finding('warning', 'jev_materials_misfit', Column::MATERIALS, __('The materials do not seem to fit this product.'));
        }
        if ($flagged('numbers_odd')) {
            $findings[] = $this->finding('warning', 'jev_numbers_odd', null, __('Some weights, sizes, pack sizes or prices look unusual for this product.'));
        }
        if ($flagged('family_misfit')) {
            $findings[] = $this->finding('warning', 'jev_family_misfit', Column::FAMILY, __('This product does not look like the rest of family :family.', ['family' => $values['family']]));
        }
        if ($flagged('rows_shifted')) {
            $findings[] = $this->finding('warning', 'jev_rows_shifted', null, __('The materials or weight may belong to the row above or below.'));
        }
        if ($flagged('tariff_misfit')) {
            $findings[] = $this->finding('warning', 'jev_tariff_misfit', Column::TARIFF_CODE, __('Tariff code :code does not seem to fit this product.', ['code' => $values['tariff_code']]));
        }

        return $findings;
    }

    /**
     * @return array{level: string, code: string, column: ?string, message: string, source: string}
     */
    protected function finding(string $level, string $code, ?Column $column, string $message): array
    {
        return ['level' => $level, 'code' => $code, 'column' => $column?->value, 'message' => $message, 'source' => 'jev'];
    }

    /**
     * @return list<string>
     */
    protected function familyProductNames(?string $family): array
    {
        if ($family === null) {
            return [];
        }

        $stockFamily = StockFamily::where('group_id', $this->groupId)->whereRaw('lower(code) = lower(?)', [$family])->first();

        return $stockFamily ? $stockFamily->stocks()->latest('id')->limit(15)->pluck('name')->filter()->values()->all() : [];
    }

    /**
     * @return list<string>
     */
    protected function usedLabels(): array
    {
        return $this->usedLabels ??= TradeUnit::where('group_id', $this->groupId)
            ->whereNotNull('type')
            ->selectRaw('lower(type) as label, count(*) as uses')
            ->groupByRaw('lower(type)')
            ->havingRaw('count(*) >= 5')
            ->pluck('label')
            ->all();
    }

}
