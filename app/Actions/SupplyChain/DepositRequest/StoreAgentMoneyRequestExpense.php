<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 13:15
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\AgentPayment\StoreAgentLedgerEntry;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * While building a money request the agent writes an expense it already paid for a client, such as a bank charge,
 * straight into that client's ledger as an other expense, so it shows up as a line to ask for. The description and
 * the receipt are required, as the client approves the line on them alone.
 */
class StoreAgentMoneyRequestExpense extends OrgAction
{
    private Agent $agent;

    /**
     * @param  array{organisation_id: int, date: string, amount: float|string, notes: string, reference?: string|null, receipt: UploadedFile}  $modelData
     */
    public function handle(Agent $agent, array $modelData): AgentPayment
    {
        return StoreAgentLedgerEntry::make()->action($agent, [
            'organisation_id' => $modelData['organisation_id'],
            'type'            => AgentPaymentTypeEnum::OTHER_EXPENSE->value,
            'date'            => $modelData['date'],
            'amount'          => $modelData['amount'],
            'notes'           => $modelData['notes'],
            'reference'       => Arr::get($modelData, 'reference'),
            'receipt'         => $modelData['receipt'],
        ]);
    }

    public function rules(): array
    {
        return [
            'organisation_id' => ['required', 'integer', Rule::exists('org_agents', 'organisation_id')->where('agent_id', $this->agent->id)],
            'date'            => ['required', 'date', 'before_or_equal:today'],
            'amount'          => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'notes'           => ['required', 'string', 'max:2000'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'receipt'         => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit");
    }

    public function asController(Agent $agent, ActionRequest $request): AgentPayment
    {
        $this->agent = $agent;
        $this->initialisation($agent->organisation, $request);

        return $this->handle($agent, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
