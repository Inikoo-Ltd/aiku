<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestItemKindEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent asks a client for money in one request: advances and balances of its supplier orders, the charges it
 * paid for each container, and what brings its wallet back to zero. Each line is checked against what is still due, so a request never asks
 * twice for the same money.
 */
class StoreAgentMoneyRequest extends OrgAction
{
    private Agent $agent;

    /**
     * @param  array{organisation_id: int, reference?: string|null, notes?: string|null, lines: list<array{kind: string, purchase_order_id?: int|null, stock_delivery_id?: int|null, amount: float|string}>}  $modelData
     */
    public function handle(Agent $agent, array $modelData): DepositRequest
    {
        $client = Organisation::findOrFail($modelData['organisation_id']);

        return DB::transaction(function () use ($agent, $client, $modelData) {
            Agent::whereKey($agent->id)->lockForUpdate()->first();
            if ($error = self::linesError($agent, $client, $modelData['lines'])) {
                throw ValidationException::withMessages(['lines' => $error]);
            }

            $depositRequest = $agent->depositRequests()->create([
                'group_id'        => $agent->group_id,
                'organisation_id' => $client->id,
                'currency_id'     => $agent->organisation->currency_id,
                'reference'       => Arr::get($modelData, 'reference'),
                'notes'           => Arr::get($modelData, 'notes'),
                'state'           => DepositRequestStateEnum::REQUESTED,
                'requested_at'    => now(),
                'created_by'      => $this->asAction ? null : request()->user()?->id,
            ]);

            foreach ($modelData['lines'] as $line) {
                $kind = DepositRequestItemKindEnum::from($line['kind']);
                $depositRequest->items()->create([
                    'organisation_id'   => $client->id,
                    'kind'              => $kind,
                    'purchase_order_id' => in_array($kind, [DepositRequestItemKindEnum::ADVANCE, DepositRequestItemKindEnum::BALANCE], true) ? Arr::get($line, 'purchase_order_id') : null,
                    'stock_delivery_id' => $kind === DepositRequestItemKindEnum::CONTAINER ? Arr::get($line, 'stock_delivery_id') : null,
                    'amount'            => round((float) $line['amount'], 2),
                ]);
            }

            return $depositRequest;
        });
    }

    public function rules(): array
    {
        return [
            'organisation_id'           => ['required', 'integer', Rule::exists('org_agents', 'organisation_id')->where('agent_id', $this->agent->id)],
            'reference'                 => ['nullable', 'string', 'max:255'],
            'notes'                     => ['nullable', 'string', 'max:2000'],
            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.kind'              => ['required', Rule::enum(DepositRequestItemKindEnum::class)],
            'lines.*.purchase_order_id' => ['nullable', 'integer'],
            'lines.*.stock_delivery_id' => ['nullable', 'integer'],
            'lines.*.amount'            => ['required', 'numeric', 'gt:0', 'max:999999999999'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($error = self::linesError($this->agent, Organisation::find($this->get('organisation_id')), $this->get('lines', []))) {
            $validator->errors()->add('lines', $error);
        }
    }

    /**
     * Why the lines can not be asked for: twice the same line, an order that is not an open order of the client
     * through this agent, a container with no charges left to ask for, or more than is still due. Null when they can.
     *
     * @param  list<array{kind: string, purchase_order_id?: int|null, stock_delivery_id?: int|null, amount: float|string}>  $lines
     */
    public static function linesError(Agent $agent, Organisation $client, array $lines): ?string
    {
        $due        = GetAgentMoneyRequestLines::run($agent, $client);
        $orders     = collect($due['orders'])->keyBy('purchase_order_id');
        $containers = collect($due['containers'])->keyBy('stock_delivery_id');
        $seen       = [];

        foreach ($lines as $line) {
            $kind = DepositRequestItemKindEnum::from($line['kind']);
            $key  = $kind->value.':'.match ($kind) {
                DepositRequestItemKindEnum::WALLET    => '',
                DepositRequestItemKindEnum::CONTAINER => Arr::get($line, 'stock_delivery_id'),
                default                               => Arr::get($line, 'purchase_order_id'),
            };

            if (isset($seen[$key])) {
                return __('A line is in the request twice.');
            }
            $seen[$key] = true;

            $limit = match ($kind) {
                DepositRequestItemKindEnum::WALLET    => $due['negative_balance'],
                DepositRequestItemKindEnum::ADVANCE   => $orders->get((int) Arr::get($line, 'purchase_order_id'))['advance_due'] ?? null,
                DepositRequestItemKindEnum::BALANCE   => $orders->get((int) Arr::get($line, 'purchase_order_id'))['balance_due'] ?? null,
                DepositRequestItemKindEnum::CONTAINER => $containers->get((int) Arr::get($line, 'stock_delivery_id'))['due'] ?? null,
            };

            if ($limit === null) {
                return $kind === DepositRequestItemKindEnum::CONTAINER
                    ? __('One of the containers has no charges left to ask this client for.')
                    : __('One of the orders is not an open order of this client through you.');
            }

            if ((float) $line['amount'] > $limit + 0.005) {
                return __('One of the lines asks for more than is still due.');
            }
        }

        return null;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit");
    }

    public function asController(Agent $agent, ActionRequest $request): DepositRequest
    {
        $this->agent = $agent;
        $this->initialisation($agent->organisation, $request);

        $depositRequest = $this->handle($agent, $this->validatedData);

        PurchaseOrder::whereIn('id', $depositRequest->items()->whereNotNull('purchase_order_id')->pluck('purchase_order_id'))
            ->get()
            ->unique('buyer_id')
            ->each(fn (PurchaseOrder $purchaseOrder) => NotifyBuyerOfAgentStageChange::run(
                $purchaseOrder,
                $request->user(),
                fn () => __('the agent asked for money for :reference', ['reference' => $purchaseOrder->reference])
            ));

        NotifyMoneyRequestCreated::run($depositRequest);

        return $depositRequest;
    }

    /**
     * @param  array{organisation_id: int, lines: list<array{kind: string, purchase_order_id?: int|null, stock_delivery_id?: int|null, amount: float|string}>}  $modelData
     */
    public function action(Agent $agent, array $modelData): DepositRequest
    {
        $this->asAction = true;
        $this->agent    = $agent;
        $this->initialisation($agent->organisation, $modelData);

        return $this->handle($agent, $this->validatedData);
    }

    public function htmlResponse(DepositRequest $depositRequest): RedirectResponse
    {
        return redirect()->route('grp.org.agent.accounting.deposit_requests.index', [$this->agent->organisation->slug]);
    }
}
