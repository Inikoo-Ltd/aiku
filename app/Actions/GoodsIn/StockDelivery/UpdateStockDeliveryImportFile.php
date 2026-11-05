<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryImportFile;
use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Marks one step of a shipment's import file done, pending or N/A with its date, or clears the mark so aiku's own
 * reading of the step shows again. Each change is audited as import_file_mark.
 */
class UpdateStockDeliveryImportFile extends OrgAction
{
    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        return DB::transaction(function () use ($stockDelivery, $modelData) {
            $stockDelivery = StockDelivery::lockForUpdate()->findOrFail($stockDelivery->id);

            return $this->mark($stockDelivery, $modelData);
        });
    }

    private function mark(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $step = $modelData['step'];
        $data = $stockDelivery->data ?? [];
        $old  = Arr::get($data, "import_file.$step");

        if (Arr::get($modelData, 'status')) {
            $new = [
                'status'  => $modelData['status'],
                'date'    => Arr::get($modelData, 'date'),
                'user_id' => request()->user()?->id,
                'at'      => now()->toIso8601String(),
            ];
            Arr::set($data, "import_file.$step", $new);
        } else {
            $new = null;
            Arr::forget($data, "import_file.$step");
        }

        $stockDelivery->update(['data' => $data]);

        $stockDelivery->auditEvent     = 'import_file_mark';
        $stockDelivery->isCustomEvent  = true;
        $stockDelivery->auditCustomOld = [$step => $old];
        $stockDelivery->auditCustomNew = [$step => $new];
        Event::dispatch(new AuditCustom($stockDelivery));
        $stockDelivery->isCustomEvent  = false;
        $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];

        return $stockDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "procurement.{$this->organisation->id}.edit",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'step'   => ['required', Rule::in(GetStockDeliveryImportFile::STEPS)],
            'status' => ['nullable', Rule::in(GetStockDeliveryImportFile::STATUSES)],
            'date'   => ['nullable', 'date'],
        ];
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $this->asAction = true;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
