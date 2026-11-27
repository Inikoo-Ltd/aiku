<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\Production\Artisan\PickDepartmentArtisans;
use App\Actions\OrgAction;
use App\Actions\Production\Artefact\Label\DownloadArtefactLabelPdf;
use App\Actions\Production\PartnerShippingList\SetToProduceItemPreparing;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
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
     * @param array{reason: string, notes?: string|null, needed_by?: string|null, employee_id?: int|null, lines: array<int, array{artefact_id: int, quantity: int, batch_code?: string|null, expiry_date?: string|null, period_after_opening?: string|null}>} $modelData quantities in whole SKOs
     */
    public function handle(Production $production, array $modelData): JobOrder
    {
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn('id', collect($modelData['lines'])->pluck('artefact_id'))
            ->whereNotNull('org_stock_id')
            ->with('orgStock')
            ->get()
            ->keyBy('id');

        $missing = collect($modelData['lines'])->pluck('artefact_id')->diff($artefacts->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => __('Some products are not made in this factory or have no stock to go to'),
            ]);
        }

        $missing = [];
        foreach ($modelData['lines'] as $index => $line) {
            $missing += SetToProduceItemPreparing::missingRunTexts(
                DownloadArtefactLabelPdf::getRunSourcesOfArtefact($line['artefact_id']),
                $line,
                "lines.$index",
                $artefacts->get($line['artefact_id'])->code
            );
        }
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        $repacks = [];
        foreach ($modelData['lines'] as $index => $line) {
            $repacks[$index] = $this->repack($artefacts->get($line['artefact_id']), $line, "lines.$index");
        }

        $employeeId      = Arr::get($modelData, 'employee_id');
        $departmentPicks = $employeeId ? [] : PickDepartmentArtisans::run($production);
        foreach ($modelData['lines'] as $line) {
            $artefact   = $artefacts->get($line['artefact_id']);
            $employeeId ??= $artefact->artisans()->first()?->id ?? $departmentPicks[$artefact->artefact_department_id] ?? null;
        }

        $label = trim(ucfirst($modelData['reason']).(filled(Arr::get($modelData, 'notes')) ? ': '.$modelData['notes'] : ''));

        return DB::transaction(function () use ($production, $modelData, $artefacts, $employeeId, $label, $repacks) {
            $seller = $production->organisation;
            $lines  = [];

            foreach ($modelData['lines'] as $index => $line) {
                $artefact           = $artefacts->get($line['artefact_id']);
                $batchCode          = trim((string) Arr::get($line, 'batch_code')) ?: null;
                $periodAfterOpening = Arr::get($line, 'period_after_opening') ?: null;
                $expiryDate         = $periodAfterOpening ? null : (Arr::get($line, 'expiry_date') ?: null);

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
                    'batch_code'           => $batchCode,
                    'expiry_date'          => $expiryDate,
                    'period_after_opening' => $periodAfterOpening,
                ]);

                $lines[] = [
                    'artefact'             => $artefact,
                    'quantity'             => (float) $line['quantity'],
                    'batch_code'           => $batchCode,
                    'expiry_date'          => $expiryDate,
                    'period_after_opening' => $periodAfterOpening,
                    ...$repacks[$index],
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

    /**
     * A line can run only some of its recipe steps, e.g. Packing only to repack stock already made, and take
     * the pieces from another SKO, one piece for each piece made (HELP-4991). The last step must stay, it is
     * what finishes the line and puts it in stock.
     *
     * @param  array{steps?: array<int, int>|null, repack_from?: int|null}  $line
     * @return array{steps?: array<int, string>, repack_from?: int}
     */
    private function repack(Artefact $artefact, array $line, string $field): array
    {
        $chosen = collect(Arr::get($line, 'steps') ?? [])->map(fn ($id) => (int) $id);
        $source = Arr::get($line, 'repack_from');
        if ($chosen->isEmpty() && !$source) {
            return [];
        }

        $recipe = $artefact->recipeSteps()->get();
        $steps  = $chosen->isEmpty() ? $recipe : $recipe->whereIn('id', $chosen->all());

        if ($chosen->isNotEmpty() && $steps->count() != $chosen->count()) {
            throw ValidationException::withMessages([$field.'.steps' => __('Some steps are not in the recipe of :code', ['code' => $artefact->code])]);
        }

        if ($recipe->isNotEmpty() && !$steps->contains('id', $recipe->sortBy([['position', 'asc'], ['id', 'asc']])->last()->id)) {
            throw ValidationException::withMessages([$field.'.steps' => __('The last step of :code must be in the job, it is what puts it in stock', ['code' => $artefact->code])]);
        }

        if ($source == $artefact->org_stock_id) {
            throw ValidationException::withMessages([$field.'.repack_from' => __(':code can not be repacked from itself', ['code' => $artefact->code])]);
        }

        return array_filter([
            'steps'       => $steps->count() < $recipe->count() ? $steps->map(fn (ArtefactManufactureTask $step) => $step->stepKey())->values()->all() : null,
            'repack_from' => $source ? (int) $source : null,
        ]);
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
            'lines.*.quantity'    => ['required', 'integer', 'min:1'],
            'lines.*.batch_code'  => ['sometimes', 'nullable', 'string', 'max:64'],
            'lines.*.expiry_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'lines.*.period_after_opening' => ['sometimes', 'nullable', Rule::in(TradeUnitBestBeforeEnum::periodsAfterOpening())],
            'lines.*.steps'       => ['sometimes', 'nullable', 'array'],
            'lines.*.steps.*'     => ['integer', 'distinct'],
            'lines.*.repack_from' => ['sometimes', 'nullable', 'integer', Rule::exists('org_stocks', 'id')->where('organisation_id', $this->organisation->id)],
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
