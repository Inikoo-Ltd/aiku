<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryGoodsInChecklist;
use App\Actions\OrgAction;
use App\Actions\Procurement\ProcurementNote\StoreProcurementNote;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteSeverityEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Goods in ticks a check of the delivery's checklist as passed or failed. A failed check opens an incident on
 * the supplier or product it belongs to, linked to the purchase order, for procurement to take up (INI-104).
 */
class RecordStockDeliveryGoodsInCheck extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    private StockDelivery $stockDelivery;
    private ?array $check = null;
    private ?array $section = null;

    public function handle(StockDelivery $stockDelivery, array $modelData, User $user): StockDelivery
    {
        return DB::transaction(function () use ($stockDelivery, $modelData, $user) {
            $stockDelivery = StockDelivery::lockForUpdate()->findOrFail($stockDelivery->id);
            $failed        = $modelData['result'] === 'failed';
            $incident      = null;

            if ($failed) {
                $incident = StoreProcurementNote::make()->action($this->section['subject'], [
                    'title'             => __('Goods in').': '.$this->check['label'],
                    'body'              => $modelData['body'],
                    'category'          => ProcurementNoteCategoryEnum::INCIDENT->value,
                    'severity'          => ProcurementNoteSeverityEnum::WARNING->value,
                    'incident_type'     => Arr::get($modelData, 'incident_type') ?? ProcurementNoteIncidentTypeEnum::PACKING_LABELS->value,
                    'incident_quantity' => Arr::get($modelData, 'incident_quantity'),
                    'purchase_order_id' => $this->section['purchase_order']?->id,
                    'organisation_id'   => $stockDelivery->organisation_id,
                    'author_user_id'    => $user->id,
                ], strict: false);
            }

            $data = $stockDelivery->data ?? [];
            Arr::set($data, 'goods_in_checks.'.$modelData['key'], [
                'result'      => $modelData['result'],
                'user'        => $user->contact_name ?: $user->username,
                'user_id'     => $user->id,
                'at'          => now()->toIso8601String(),
                'incident_id' => $incident?->id,
            ]);
            $stockDelivery->update(['data' => $data]);

            return $stockDelivery;
        });
    }

    public function rules(): array
    {
        return [
            'key'               => ['required', 'string', 'max:100', 'regex:/^[a-z_:0-9]+$/'],
            'result'            => ['required', Rule::in(['passed', 'failed'])],
            'body'              => ['required_if:result,failed', 'nullable', 'string', 'max:10000'],
            'incident_type'     => ['sometimes', 'nullable', Rule::enum(ProcurementNoteIncidentTypeEnum::class)],
            'incident_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        foreach (GetStockDeliveryGoodsInChecklist::run($this->stockDelivery) as $section) {
            foreach ($section['checks'] as $check) {
                if ($check['key'] === $this->get('key')) {
                    $this->section = $section;
                    $this->check   = $check;
                }
            }
        }

        if (!$this->check) {
            $validator->errors()->add('key', __('This check is no longer on the delivery checklist'));
        }
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): RedirectResponse
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);
        $this->handle($stockDelivery, $this->validatedData, $request->user());

        return redirect()->back();
    }

    public function action(StockDelivery $stockDelivery, array $modelData, User $user): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData, $user);
    }
}
