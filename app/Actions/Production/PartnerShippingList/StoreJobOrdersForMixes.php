<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\StoreJobOrdersGroupedByArtisan;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreJobOrdersForMixes extends OrgAction
{
    /**
     * @param  array<int, array{artefact_id: int, quantity: float|int}>  $lines
     * @return array<int, JobOrder>
     */
    public function handle(Production $production, array $lines, ?int $employeeId = null): array
    {
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', collect($lines)->pluck('artefact_id'))
            ->get()
            ->keyBy('id');

        $resolved = [];
        foreach ($lines as $line) {
            if ($artefact = $artefacts->get($line['artefact_id'])) {
                $resolved[] = ['artefact' => $artefact, 'quantity' => $line['quantity']];
            }
        }

        return StoreJobOrdersGroupedByArtisan::run($production, $resolved, $employeeId);
    }

    public function rules(): array
    {
        return [
            'lines'               => ['required', 'array', 'min:1'],
            'lines.*.artefact_id' => ['required', Rule::exists('artefacts', 'id')->where('production_id', $this->production->id)],
            'lines.*.quantity'    => ['required', 'numeric', 'gt:0'],
            'employee_id'         => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
        ]);
    }

    public function action(Production $production, array $lines): array
    {
        $this->asAction   = true;
        $this->production = $production;
        $this->initialisation($production->organisation, ['lines' => $lines]);

        return $this->handle($production, $lines);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['lines'], $this->validatedData['employee_id'] ?? null);
    }

    public function htmlResponse(array $jobOrders): RedirectResponse
    {
        $count = count($jobOrders);

        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => trans_choice(':count job order created|:count job orders created', $count, ['count' => $count]),
        ]);
    }
}
