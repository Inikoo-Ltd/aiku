<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\StoreJobOrdersGroupedByArtisan;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Actions\Production\JobOrder\FillToOrder;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use Illuminate\Support\Facades\Redirect;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreJobOrdersFromToProduceItems extends OrgAction
{
    /**
     * @param  array<int, int>  $ids
     * @return array{job_orders: array<int, JobOrder>, skipped: array<int, array{id: int, reason: string}>}
     */
    /**
     * @param  array<int, array<int, array{quantity: float|int, batch_code?: string|null, expiry_date?: string|null, period_after_opening?: string|null}>>  $runs  per line id, one job line per batch of raw material, each with its own batch code and expiry date
     */
    public function handle(Production $production, array $ids, ?int $employeeId = null, float|int|null $quantity = null, ?string $plannedFor = null, array $runs = []): array
    {
        $seller = $production->organisation;

        $items = PartnerShoppingListItem::query()
            ->whereIn('id', $ids)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('job_order_id')
            ->whereNull('pre_picked_at')
            ->where(function ($query) use ($seller) {
                $query->where('partner_organisation_id', $seller->id)
                    ->orWhere(function ($query) use ($seller) {
                        $query->whereNull('partner_organisation_id')->where('organisation_id', $seller->id);
                    });
            })
            ->get();

        $skipped = [];
        $lines   = [];

        foreach ($items as $item) {
            $artefact = $this->resolveArtefact($production, $item);
            if (!$artefact) {
                $skipped[] = ['id' => $item->id, 'reason' => 'no artefact in this factory for this stock'];
                continue;
            }

            $line = [
                'artefact'    => $artefact,
                'quantity'    => $quantity && count($ids) === 1 ? $quantity : ($item->quantity_to_produce ?? $item->quantity),
                'batch_code'  => $item->batch_code,
                'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                'period_after_opening' => $item->period_after_opening,
                'after'       => fn (JobOrder $jobOrder) => $item->update(['job_order_id' => $jobOrder->id]),
                'order'       => $item->transaction?->order?->reference,
                'fill'        => $artefact->org_stock_id ? null : [
                    'to_produce_id' => $item->id,
                    'oil'           => $item->orgStock->code,
                    'oil_name'      => $item->orgStock->name,
                    'order'         => $item->transaction?->order?->reference,
                ],
            ];

            $itemRuns = $line['fill'] ? [] : ($runs[$item->id] ?? []);

            if ($production->hasSimpleBoard() && !$line['fill']) {
                $this->leaveShortfallOnBoard($item, $itemRuns ? array_sum(array_column($itemRuns, 'quantity')) : (float) $line['quantity']);
            }
            if (!$itemRuns) {
                $lines[] = $line;
                continue;
            }

            foreach (array_values($itemRuns) as $index => $run) {
                $lines[] = [
                    ...$line,
                    'quantity'    => $run['quantity'],
                    'batch_code'  => ($run['batch_code'] ?? null) ?: null,
                    'expiry_date' => ($run['expiry_date'] ?? null) ?: null,
                    'period_after_opening' => ($run['period_after_opening'] ?? null) ?: null,
                    'after'       => $index === 0 ? $line['after'] : null,
                ];
            }
        }

        $jobOrders = StoreJobOrdersGroupedByArtisan::run($production, $lines, $employeeId, $plannedFor);

        return ['job_orders' => $jobOrders, 'skipped' => $skipped];
    }

    /**
     * On a simple board the run is made as typed: when fewer SKOs are made than the line asks for,
     * the line keeps what is made and the rest stays on the board as its own line, as at pre-pick.
     */
    private function leaveShortfallOnBoard(PartnerShoppingListItem $item, float $made): void
    {
        $shortfall = round((float) $item->quantity - $made, 3);
        if ($shortfall <= 0) {
            return;
        }

        PartnerShoppingListItem::create([
            ...$item->only([
                'group_id',
                'organisation_id',
                'org_partner_id',
                'partner_organisation_id',
                'stock_id',
                'org_stock_id',
                'priority',
                'needed_by',
                'notes',
                'transaction_id',
                'added_by_user_id',
            ]),
            'parent_id'  => $item->id,
            'quantity'   => $shortfall,
            'state'      => ShoppingListItemStateEnum::OPEN,
            'created_at' => $item->created_at,
        ]);

        $item->update(['quantity' => $made]);
    }

    private function resolveArtefact(Production $production, PartnerShoppingListItem $item): ?Artefact
    {
        if ($item->transaction_id && ($fillArtefact = FillToOrder::make()->artefactFor($item->transaction?->model))) {
            return $fillArtefact->production_id == $production->id ? $fillArtefact : null;
        }

        $orgStockId = OrgStock::where('organisation_id', $production->organisation_id)
            ->where('stock_id', $item->stock_id)
            ->value('id');
        if (!$orgStockId) {
            return null;
        }

        return Artefact::where('production_id', $production->id)
            ->where('org_stock_id', $orgStockId)
            ->whereIn('state', ArtefactStateEnum::launched())
            ->first();
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'list', 'min:1'],
            'ids.*' => ['integer', 'min:1', 'max:2147483647'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'quantity'    => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'planned_for' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'assign_to_me' => ['sometimes', 'boolean'],
            'runs'                 => ['sometimes', 'array'],
            'runs.*'               => ['array', 'min:1'],
            'runs.*.*.quantity'    => ['required', 'numeric', 'min:1'],
            'runs.*.*.batch_code'  => ['sometimes', 'nullable', 'string', 'max:64'],
            'runs.*.*.expiry_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'runs.*.*.period_after_opening' => ['sometimes', 'nullable', Rule::in(TradeUnitBestBeforeEnum::periodsAfterOpening())],
        ];
    }

    /**
     * On a simple board (Production::hasSimpleBoard) the job goes to whoever moved the card.
     */
    private function moverEmployeeId(ActionRequest $request): int
    {
        $employeeId = $request->user()->employees()
            ->where('employees.organisation_id', $this->organisation->id)
            ->where('employees.state', EmployeeStateEnum::WORKING->value)
            ->value('employees.id');

        if (!$employeeId) {
            throw ValidationException::withMessages(['assign_to_me' => __('You are not a worker of :organisation, so the job cannot be given to you.', ['organisation' => $this->organisation->name])]);
        }

        return $employeeId;
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

    public function action(Production $production, array $ids): array
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, ['ids' => $ids]);

        return $this->handle($production, $ids);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        $employeeId = ($this->validatedData['assign_to_me'] ?? false) ? $this->moverEmployeeId($request) : ($this->validatedData['employee_id'] ?? null);

        return $this->handle($production, $this->validatedData['ids'], $employeeId, $this->validatedData['quantity'] ?? null, $this->validatedData['planned_for'] ?? null, $this->validatedData['runs'] ?? []);
    }

    public function htmlResponse(array $result): RedirectResponse
    {
        $count = count($result['job_orders']);
        $skipped = count($result['skipped']);

        return Redirect::back()->with('notification', [
            'status' => $skipped ? 'warning' : 'success',
            'title'  => trans_choice(':count job order created|:count job orders created', $count, ['count' => $count])
                .($skipped ? ', '.trans_choice(':count line skipped, no artefact|:count lines skipped, no artefact', $skipped, ['count' => $skipped]) : ''),
        ]);
    }
}
