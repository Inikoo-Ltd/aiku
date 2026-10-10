<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCustomsLine;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Records the import declaration the customs agent filed: its MRN, release date and tariff lines. Aiku never
 * changes the declaration, it only uses its lines to give each item the duty its own tariff line paid.
 * Items still on no line are matched to one by their trade unit's tariff code.
 */
class UpdateStockDeliveryCustoms extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;

    private StockDelivery $stockDelivery;

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        DB::transaction(function () use ($stockDelivery, $modelData) {
            $stockDelivery->update(Arr::only($modelData, ['customs_mrn', 'customs_released_at']));

            if (!Arr::has($modelData, 'lines')) {
                return;
            }

            $keptIds = [];
            foreach ($modelData['lines'] as $line) {
                $attributes = [
                    'tariff_code'   => StockDeliveryCustomsLine::normaliseTariffCode($line['tariff_code']),
                    'description'   => Arr::get($line, 'description'),
                    'duty_rate'     => (float) Arr::get($line, 'duty_rate', 0),
                    'customs_value' => (float) Arr::get($line, 'customs_value', 0),
                    'duty_amount'   => Arr::get($line, 'duty_amount') ?? round((float) Arr::get($line, 'duty_rate', 0) * (float) Arr::get($line, 'customs_value', 0) / 100, 2),
                    'import_vat'    => Arr::get($line, 'import_vat'),
                ];

                $customsLine = isset($line['id']) ? $stockDelivery->customsLines()->find($line['id']) : null;
                if ($customsLine) {
                    $customsLine->update($attributes);
                } else {
                    $customsLine = $stockDelivery->customsLines()->create([
                        'group_id'        => $stockDelivery->group_id,
                        'organisation_id' => $stockDelivery->organisation_id,
                        ...$attributes,
                    ]);
                }
                $keptIds[] = $customsLine->id;
            }

            $stockDelivery->customsLines()->whereNotIn('id', $keptIds)->delete();

            self::matchItemsToLines($stockDelivery);
        });

        if (Arr::has($modelData, 'lines') && $stockDelivery->costs()->exists()) {
            EvaluateStockDeliveryCosting::run($stockDelivery);
        }

        return $stockDelivery->refresh();
    }

    /**
     * Longest shared prefix of the tariff code wins, at least the 4-digit heading; a tie keeps the first line.
     */
    public static function matchItemsToLines(StockDelivery $stockDelivery): void
    {
        $lines = $stockDelivery->customsLines()->orderBy('id')->get();
        if ($lines->isEmpty()) {
            return;
        }

        $items = $stockDelivery->items()
            ->whereNull('stock_delivery_customs_line_id')
            ->with('orgStock.tradeUnits.tariffCodeOverrides')
            ->get();

        foreach ($items as $item) {
            if ($line = self::bestLine($item, $lines)) {
                $item->update(['stock_delivery_customs_line_id' => $line->id]);
            }
        }
    }

    private static function bestLine(StockDeliveryItem $item, Collection $lines): ?StockDeliveryCustomsLine
    {
        $tradeUnit = $item->orgStock?->tradeUnits->first();
        $code      = StockDeliveryCustomsLine::normaliseTariffCode($tradeUnit?->getTariffCodeForOrganisation($item->organisation_id));
        if (strlen($code) < 4) {
            return null;
        }

        $best       = null;
        $bestLength = 3;
        foreach ($lines as $line) {
            $length = strlen(self::commonPrefix($code, $line->tariff_code));
            if ($length > $bestLength) {
                $best       = $line;
                $bestLength = $length;
            }
        }

        return $best;
    }

    private static function commonPrefix(string $a, string $b): string
    {
        $length = 0;
        $max    = min(strlen($a), strlen($b));
        while ($length < $max && $a[$length] === $b[$length]) {
            $length++;
        }

        return substr($a, 0, $length);
    }

    public function rules(): array
    {
        return [
            'customs_mrn'           => ['sometimes', 'nullable', 'string', 'max:64'],
            'customs_released_at'   => ['sometimes', 'nullable', 'date'],
            'lines'                 => ['sometimes', 'array'],
            'lines.*.id'            => ['sometimes', 'nullable', 'integer'],
            'lines.*.tariff_code'   => ['required', 'string', 'max:32', 'regex:/\d{4}/'],
            'lines.*.description'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.duty_rate'     => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.customs_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.duty_amount'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.import_vat'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->is_costed && $this->has('lines')) {
            $validator->errors()->add('lines', __('The delivery is costed: reopen the costing before changing the customs lines'));
        }
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
