<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Changes the state of an organisation stock and, by default, of every sibling that carries the
 * same stock in the other organisations. Named organisations may be given a different target state.
 *
 * The state itself goes through UpdateOrgStock so the existing cascade (stock, trade units,
 * products, warehouses) keeps running. On top of it every org stock gets one custom audit entry
 * with the scope, the reason, the effective date and where the request came from, so the history
 * says who asked, why and how, not only that a column moved.
 *
 * An effective date in the future stores the request on the org stock and changes nothing yet;
 * ApplyScheduledOrgStockStateChanges runs it when the day comes.
 */
class DiscontinueOrgStocks extends OrgAction
{
    public const string SCHEDULED_KEY = 'scheduled_state_change';

    /** @var Collection<int, OrgStock> */
    private Collection $orgStocks;

    private ?User $user = null;

    /**
     * @return array{changed: int, unchanged: int, scheduled: int}
     */
    public function handle(Collection $orgStocks, array $modelData): array
    {
        $targetState = OrgStockStateEnum::from($modelData['state']);
        $overrides   = Arr::get($modelData, 'organisation_states', []);
        $effectiveAt = Arr::get($modelData, 'effective_at') ? Carbon::parse($modelData['effective_at']) : now();
        $scheduled   = $effectiveAt->isFuture();

        $stats = ['changed' => 0, 'unchanged' => 0, 'scheduled' => 0];

        $set = Arr::get($modelData, 'scope', 'group') === 'group' ? $this->groupSet($orgStocks) : $orgStocks->load('organisation');

        foreach ($set as $orgStock) {
            $organisationCode = $orgStock->organisation->code;
            $state            = OrgStockStateEnum::from(Arr::get($overrides, $organisationCode, $targetState->value));

            $record = [
                'from_state'    => $orgStock->state->value,
                'to_state'      => $state->value,
                'scope'         => Arr::get($modelData, 'scope', 'group'),
                'group_state'   => $targetState->value,
                'overrides'     => (object) $overrides,
                'reason'        => Arr::get($modelData, 'reason'),
                'effective_at'  => $effectiveAt->toIso8601String(),
                'source'        => Arr::get($modelData, 'source', 'ui'),
                'request_text'  => Arr::get($modelData, 'request_text'),
                'requested_by'  => $this->user?->username,
            ];

            if ($scheduled) {
                $orgStock->update(['data' => array_merge($orgStock->data, [self::SCHEDULED_KEY => $record])]);
                $this->audit($orgStock, 'schedule_state_change', $record);
                $stats['scheduled']++;
                continue;
            }

            if (Arr::has($orgStock->data, self::SCHEDULED_KEY)) {
                $orgStock->update(['data' => Arr::except($orgStock->data, [self::SCHEDULED_KEY])]);
            }

            if ($orgStock->state === $state) {
                $stats['unchanged']++;
                continue;
            }

            UpdateOrgStock::make()->action($orgStock, ['state' => $state->value]);
            $this->audit($orgStock, 'state_change', $record);
            $stats['changed']++;
        }

        return $stats;
    }

    /**
     * @return Collection<int, OrgStock>
     */
    private function groupSet(Collection $orgStocks): Collection
    {
        $stockIds = $orgStocks->pluck('stock_id')->filter()->unique();

        return OrgStock::whereIn('stock_id', $stockIds)
            ->orWhereIn('id', $orgStocks->pluck('id'))
            ->with('organisation')
            ->get()
            ->unique('id')
            ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
            ->values();
    }

    private function audit(OrgStock $orgStock, string $event, array $record): void
    {
        $orgStock->auditEvent     = $event;
        $orgStock->isCustomEvent  = true;
        $orgStock->auditCustomOld = ['state' => $record['from_state']];
        $orgStock->auditCustomNew = Arr::except($record, ['from_state']);

        Event::dispatch(new AuditCustom($orgStock));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation));
    }

    public function rules(): array
    {
        $states = [
            OrgStockStateEnum::ACTIVE->value,
            OrgStockStateEnum::DISCONTINUING->value,
            OrgStockStateEnum::DISCONTINUED->value,
            OrgStockStateEnum::SUSPENDED->value,
        ];

        return [
            'org_stock_ids'         => ['required', 'array', 'max:200'],
            'org_stock_ids.*'       => ['integer'],
            'state'                 => ['required', Rule::in($states)],
            'scope'                 => ['sometimes', Rule::in(['group', 'organisation'])],
            'organisation_states'   => ['sometimes', 'array'],
            'organisation_states.*' => [Rule::in($states)],
            'reason'                => ['required_unless:state,'.OrgStockStateEnum::ACTIVE->value, 'nullable', 'string', 'max:1000'],
            'effective_at'          => ['sometimes', 'nullable', 'date'],
            'expected_updated_at'   => ['sometimes', 'array'],
            'expected_updated_at.*' => ['date'],
            'source'                => ['sometimes', Rule::in(['ui', 'mcp', 'schedule'])],
            'request_text'          => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * The stale guard compares the updated_at the caller saw at preview time with what the row
     * carries now; any drift means someone else touched the stock and the caller must look again.
     * Overrides may only name organisations the user can edit stock in.
     */
    public function afterValidator(Validator $validator): void
    {
        $input = $validator->getData();

        $this->orgStocks = OrgStock::where('organisation_id', $this->organisation->id)
            ->whereIn('id', Arr::wrap(Arr::get($input, 'org_stock_ids', [])))
            ->get();

        if ($this->orgStocks->isEmpty()) {
            $validator->errors()->add('org_stock_ids', __('No SKO found in this organisation'));

            return;
        }

        foreach (Arr::get($input, 'expected_updated_at', []) as $orgStockId => $expected) {
            $orgStock = $this->orgStocks->firstWhere('id', (int) $orgStockId);
            if ($orgStock && !$orgStock->updated_at->equalTo(Carbon::parse($expected))) {
                $validator->errors()->add('expected_updated_at', __('SKO :code changed since the preview, look again before confirming', ['code' => $orgStock->code]));
            }
        }

        if ($this->user && !$this->asAction) {
            foreach (array_keys(Arr::get($input, 'organisation_states', [])) as $organisationCode) {
                $organisation = Organisation::where('code', $organisationCode)->first();
                if (!$organisation || !$this->user->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($organisation))) {
                    $validator->errors()->add('organisation_states', __('You cannot set stock state in :organisation', ['organisation' => $organisationCode]));
                }
            }
        }
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): RedirectResponse
    {
        $this->user = $request->user();
        $this->initialisationFromWarehouse($warehouse, $request);
        $this->handle($this->orgStocks, $this->validatedData);

        return back();
    }

    public function action(Organisation $organisation, array $modelData, ?User $user = null): array
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->initialisation($organisation, $modelData);

        return $this->handle($this->orgStocks, $this->validatedData);
    }
}
