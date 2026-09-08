<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\FulfilmentGate;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreJobOrderFromShortfall extends OrgAction
{
    use WithDispatchingAuthorisation;

    /**
     * @param array<int, array{org_stock_id: int, quantity: float|int}> $lines
     *
     * @return array{job_order: JobOrder|null, skipped: array<int, array{org_stock_id: int, reason: string}>}
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, array $lines): array
    {
        $production = $organisation->productions()->first();
        if (!$production) {
            throw ValidationException::withMessages(['lines' => __('This organisation has no production facility')]);
        }

        $artefacts = Artefact::where('organisation_id', $organisation->id)
            ->whereIn('org_stock_id', collect($lines)->pluck('org_stock_id'))
            ->get()
            ->keyBy('org_stock_id');

        $jobOrder = null;
        $skipped  = [];

        foreach ($lines as $line) {
            $artefact = $artefacts->get($line['org_stock_id']);
            if (!$artefact) {
                $skipped[] = ['org_stock_id' => $line['org_stock_id'], 'reason' => 'no artefact mapped to this stock, not manufacturable here'];
                continue;
            }

            $jobOrder ??= StoreJobOrder::make()->action($production, []);

            StoreJobOrderItem::make()->action($jobOrder, [
                'artefact_id' => $artefact->id,
                'quantity'    => (int) ceil((float) $line['quantity']),
            ]);
        }

        return ['job_order' => $jobOrder, 'skipped' => $skipped];
    }

    public function rules(): array
    {
        return [
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.org_stock_id' => ['required', 'integer'],
            'lines.*.quantity'     => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @param array{job_order: JobOrder|null, skipped: array<int, array{org_stock_id: int, reason: string}>} $result
     */
    public function htmlResponse(array $result): RedirectResponse
    {
        $skipped = count($result['skipped']);

        if ($result['job_order']) {
            return back()->with('notification', [
                'status'      => 'success',
                'title'       => __('Job order :reference created', ['reference' => $result['job_order']->reference]),
                'description' => $skipped
                    ? __(':count stocks skipped: no manufacturing artefact mapped', ['count' => $skipped])
                    : __(':count items sent to production', ['count' => $result['job_order']->jobOrderItems()->count()]),
            ]);
        }

        return back()->with('notification', [
            'status'      => 'error',
            'title'       => __('No job order created'),
            'description' => __('None of the selected stocks have a manufacturing artefact mapped'),
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $lines): array
    {
        $this->asAction = true;
        $this->initialisation($organisation, ['lines' => $lines]);

        return $this->handle($organisation, $this->validatedData['lines']);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation, $this->validatedData['lines']);
    }
}
