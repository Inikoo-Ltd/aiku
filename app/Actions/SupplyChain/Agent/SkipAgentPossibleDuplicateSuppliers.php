<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Agent;

use App\Actions\OrgAction;
use App\Models\SupplyChain\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Marks a group of the agent's suppliers flagged as possible duplicates as different suppliers, so the agent
 * suppliers dashboard stops listing that group. A new supplier joining the group lists it again.
 */
class SkipAgentPossibleDuplicateSuppliers extends OrgAction
{
    public const string DATA_KEY = 'skipped_duplicate_suppliers';

    private Agent $agent;

    public static function groupKey(iterable $supplierIds): string
    {
        return collect($supplierIds)->map(fn ($id) => (int) $id)->unique()->sort()->implode(',');
    }

    public function handle(Agent $agent, array $supplierIds): Agent
    {
        $data    = $agent->data ?? [];
        $skipped = $data[self::DATA_KEY] ?? [];

        $skipped[] = self::groupKey($supplierIds);

        $data[self::DATA_KEY] = array_values(array_unique($skipped));
        $agent->update(['data' => $data]);

        return $agent;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user()->authTo('supply-chain.edit')) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit");
    }

    public function rules(): array
    {
        return [
            'supplier_ids'   => ['required', 'array', 'min:2'],
            'supplier_ids.*' => ['integer', 'distinct', Rule::exists('suppliers', 'id')->where('agent_id', $this->agent->id)->whereNull('deleted_at')],
        ];
    }

    public function asController(Agent $agent, ActionRequest $request): RedirectResponse
    {
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);
        $this->handle($agent, $this->validatedData['supplier_ids']);

        return redirect()->back();
    }

    public function action(Agent $agent, array $modelData): Agent
    {
        $this->asAction = true;
        $this->agent    = $agent;
        $this->initialisationFromGroup($agent->group, $modelData);

        return $this->handle($agent, $this->validatedData['supplier_ids']);
    }
}
