<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A job order the planner writes by hand, like the job sheets of the old system (HELP-3528). Its
 * lines go on the To produce board already inside the job order, so it lands in Assigned: planned
 * and waiting for the floor. It is one job order whatever the products, never split per artisan,
 * and a product already on the board does not stop it; the planner is warned before, not blocked.
 */
class StoreManualJobOrder extends OrgAction
{
    public const array REASONS = ['stock', 'partner', 'sample', 'rework', 'other'];

    /**
     * @param array{reason: string, notes?: string|null, needed_by?: string|null, employee_id?: int|null, lines: array<int, array{artefact_id: int, quantity: float}>} $modelData quantities in SKOs
     */
    public function handle(Production $production, array $modelData): JobOrder
    {
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', collect($modelData['lines'])->pluck('artefact_id'))
            ->whereNotNull('org_stock_id')
            ->where('state', '!=', ArtefactStateEnum::DORMANT)
            ->with('orgStock')
            ->get()
            ->keyBy('id');

        $missing = collect($modelData['lines'])->pluck('artefact_id')->diff($artefacts->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => __('Some products are not made in this factory or have no stock to go to'),
            ]);
        }

        $employeeId = Arr::get($modelData, 'employee_id');
        foreach ($modelData['lines'] as $line) {
            $artefact   = $artefacts->get($line['artefact_id']);
            $employeeId ??= $artefact->artisans()->first()?->id ?? $artefact->artefactDepartment?->artisans()->first()?->id;
        }

        $label = trim(ucfirst($modelData['reason']).(filled(Arr::get($modelData, 'notes')) ? ': '.$modelData['notes'] : ''));

        return DB::transaction(function () use ($production, $modelData, $artefacts, $employeeId, $label) {
            $seller = $production->organisation;
            $lines  = [];

            foreach ($modelData['lines'] as $line) {
                $artefact = $artefacts->get($line['artefact_id']);

                $boardLine = PartnerShoppingListItem::create([
                    'group_id'         => $seller->group_id,
                    'organisation_id'  => $seller->id,
                    'stock_id'         => $artefact->orgStock->stock_id,
                    'org_stock_id'     => $artefact->org_stock_id,
                    'quantity'         => round((float) $line['quantity'], 3),
                    'priority'         => ShoppingListItemPriorityEnum::NORMAL,
                    'state'            => ShoppingListItemStateEnum::OPEN,
                    'needed_by'        => Arr::get($modelData, 'needed_by'),
                    'notes'            => $label,
                    'added_by_user_id' => request()->user()?->id,
                ]);

                $lines[] = [
                    'artefact' => $artefact,
                    'quantity' => (float) $line['quantity'],
                    'after'    => fn (JobOrder $jobOrder) => $boardLine->update(['job_order_id' => $jobOrder->id]),
                ];
            }

            $jobOrders = StoreJobOrdersGroupedByArtisan::run($production, $lines, $employeeId);

            /** @var JobOrder $jobOrder */
            $jobOrder = $jobOrders[0];
            $jobOrder->update([
                'internal_notes' => $label,
                'data'           => array_merge($jobOrder->data ?? [], [
                    'manual'    => true,
                    'reason'    => $modelData['reason'],
                    'needed_by' => Arr::get($modelData, 'needed_by'),
                ]),
            ]);

            return $jobOrder;
        });
    }

    public function rules(): array
    {
        return [
            'reason'              => ['required', Rule::in(self::REASONS)],
            'notes'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'needed_by'           => ['sometimes', 'nullable', 'date'],
            'employee_id'         => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'lines'               => ['required', 'array', 'min:1'],
            'lines.*.artefact_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity'    => ['required', 'numeric', 'min:0.001'],
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
        ]);
    }

    public function action(Production $production, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): JobOrder
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(JobOrder $jobOrder): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __('Job order :reference created', ['reference' => $jobOrder->reference]),
        ]);
    }
}
