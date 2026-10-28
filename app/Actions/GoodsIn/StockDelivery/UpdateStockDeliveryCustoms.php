<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCustomsLine;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;
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

    /** @var array<string, ?string> */
    private array $changedDates = [];

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        DB::transaction(function () use ($stockDelivery, $modelData) {
            $stockDelivery->update(Arr::only($modelData, ['customs_mrn', 'customs_released_at', 'arrived_at']));
            if (Arr::has($modelData, 'received_at') && $this->changedDate('received_at', $modelData['received_at'])) {
                $stockDelivery->update(['received_at' => $modelData['received_at']]);
            }
            if ($reason = trim((string) Arr::get($modelData, 'reason'))) {
                $stockDelivery->auditEvent     = 'stage_override';
                $stockDelivery->isCustomEvent  = true;
                $stockDelivery->auditCustomOld = $this->changedDates;
                $stockDelivery->auditCustomNew = ['reason' => $reason, ...Arr::only($modelData, array_keys($this->changedDates))];
                Event::dispatch(new AuditCustom($stockDelivery));
                $stockDelivery->isCustomEvent  = false;
                $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];
            }

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
        $code = StockDeliveryCustomsLine::normaliseTariffCode($item->orgStock?->getTariffCode());
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
            'arrived_at'            => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'customs_released_at'   => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'received_at'           => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'reason'                => ['sometimes', 'nullable', 'string', 'max:500'],
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

    /**
     * Port arrival, customs release and the received date are stage dates: backdating or clearing one, releasing
     * customs with required compliance documents missing, or correcting the received date asks for a reason.
     * Only procurement corrects the received date, and only once the delivery is received; the goods stay as the
     * warehouse booked them in.
     */
    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->is_costed && $this->has('lines')) {
            $validator->errors()->add('lines', __('The delivery is costed: reopen the costing before changing the customs lines'));
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $labels  = PurchaseOrderStageEnum::fieldLabels();
        $reasons = [];
        foreach (['arrived_at', 'customs_released_at', 'received_at'] as $field) {
            if (!$this->has($field) || !$this->changedDate($field, $this->get($field))) {
                continue;
            }
            $this->changedDates[$field] = $this->stockDelivery->{$field}?->toDateString();

            if ($field === 'received_at') {
                if (!in_array($this->stockDelivery->state, StockDelivery::STATES_RECEIVED) || !$this->get($field)) {
                    $validator->errors()->add('received_at', __('The received date can only be corrected on a received delivery'));

                    return;
                }
                if (!request()->user()?->authTo("procurement.{$this->organisation->id}.edit")) {
                    $validator->errors()->add('received_at', __('Only procurement corrects the received date'));

                    return;
                }
                $reasons[] = __(':date is corrected', ['date' => $labels[$field]]);
            } elseif (!$this->get($field)) {
                $reasons[] = __(':date is cleared', ['date' => $labels[$field]]);
            } elseif (Carbon::parse($this->get($field))->toDateString() < Carbon::parse(MarkPurchaseOrderStage::today($this->organisation))->subDay()->toDateString()) {
                $reasons[] = __(':date is more than a day in the past', ['date' => $labels[$field]]);
            }

            if ($field === 'customs_released_at' && $this->get($field)) {
                $missing = $this->stockDelivery->purchaseOrders()->get()
                    ->flatMap(fn ($purchaseOrder) => GetPurchaseOrderCompliance::make()->missingRequired($purchaseOrder))
                    ->unique()->values()->all();
                if ($missing) {
                    $reasons[] = __('compliance documents missing: :documents', ['documents' => implode(', ', $missing)]);
                }
            }
        }

        if ($reasons && !$this->asAction && !trim((string) $this->get('reason'))) {
            $validator->errors()->add('reason', __('Give a reason: :reasons', ['reasons' => implode('; ', $reasons)]));
        }
    }

    private function changedDate(string $field, ?string $date): bool
    {
        return ($date ? Carbon::parse($date)->toDateString() : null) !== $this->stockDelivery->{$field}?->toDateString();
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
