<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-20 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestItemKindEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Some money reaches the agent outside a money request, e.g. the client hands the deposit of an order straight to the
 * agent. The agent then skips that line so it is no longer offered: it is kept under data.money_request_skips.<kind>
 * of the order, the shipment or the ledger entry, with who skipped it, when and why, and can be put back.
 */
class SkipAgentMoneyRequestLine extends OrgAction
{
    public const string DATA_KEY = 'money_request_skips';

    private Agent $agent;

    /**
     * @param  array{organisation_id: int, kind: string, purchase_order_id?: int|null, stock_delivery_id?: int|null, agent_payment_id?: int|null, skipped: bool, reason?: string|null}  $modelData
     */
    public function handle(Agent $agent, array $modelData, ?User $user = null): Model
    {
        $kind  = DepositRequestItemKindEnum::from($modelData['kind']);
        $model = self::target($agent, (int) $modelData['organisation_id'], $kind, $modelData);
        $data  = $model->data ?? [];

        if ($modelData['skipped']) {
            Arr::set($data, self::DATA_KEY.'.'.$kind->value, [
                'at'      => now()->toIso8601String(),
                'user_id' => $user?->id,
                'by'      => $user?->contact_name ?: $user?->username,
                'reason'  => trim((string) Arr::get($modelData, 'reason')),
            ]);
        } else {
            Arr::forget($data, self::DATA_KEY.'.'.$kind->value);
            if (empty($data[self::DATA_KEY])) {
                unset($data[self::DATA_KEY]);
            }
        }

        $model->update(['data' => $data]);

        return $model;
    }

    /**
     * @param  array{purchase_order_id?: int|null, stock_delivery_id?: int|null, agent_payment_id?: int|null}  $modelData
     */
    public static function target(Agent $agent, int $clientId, DepositRequestItemKindEnum $kind, array $modelData): ?Model
    {
        return match ($kind) {
            DepositRequestItemKindEnum::ADVANCE, DepositRequestItemKindEnum::BALANCE => PurchaseOrder::where('organisation_id', $clientId)
                ->where('agent_id', $agent->id)
                ->find((int) Arr::get($modelData, 'purchase_order_id')),
            DepositRequestItemKindEnum::SHIPMENT => DB::table('agent_payments')
                ->where('agent_id', $agent->id)
                ->where('organisation_id', $clientId)
                ->where('stock_delivery_id', (int) Arr::get($modelData, 'stock_delivery_id'))
                ->whereNull('deleted_at')
                ->exists()
                ? StockDelivery::find((int) Arr::get($modelData, 'stock_delivery_id'))
                : null,
            DepositRequestItemKindEnum::EXPENSE => AgentPayment::where('agent_id', $agent->id)
                ->where('organisation_id', $clientId)
                ->find((int) Arr::get($modelData, 'agent_payment_id')),
            default => null,
        };
    }

    public function rules(): array
    {
        $kinds = [DepositRequestItemKindEnum::ADVANCE, DepositRequestItemKindEnum::BALANCE, DepositRequestItemKindEnum::SHIPMENT, DepositRequestItemKindEnum::EXPENSE];

        return [
            'organisation_id'   => ['required', 'integer', Rule::exists('org_agents', 'organisation_id')->where('agent_id', $this->agent->id)],
            'kind'              => ['required', Rule::in(array_map(fn (DepositRequestItemKindEnum $kind) => $kind->value, $kinds))],
            'purchase_order_id' => ['nullable', 'integer'],
            'stock_delivery_id' => ['nullable', 'integer'],
            'agent_payment_id'  => ['nullable', 'integer'],
            'skipped'           => ['required', 'boolean'],
            'reason'            => ['required_if:skipped,true', 'nullable', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (!self::target($this->agent, (int) $this->get('organisation_id'), DepositRequestItemKindEnum::from($this->get('kind')), $this->all())) {
            $validator->errors()->add('kind', __('This line does not belong to this client.'));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit");
    }

    public function asController(Agent $agent, ActionRequest $request): Model
    {
        $this->agent = $agent;
        $this->initialisation($agent->organisation, $request);

        return $this->handle($agent, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
