<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\DepositRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class StoreDepositRequest extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'reference'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'currency_id'            => ['required', 'exists:currencies,id'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.aspo_deposit_id' => ['required', 'exists:aspo_deposits,id'],
            'items.*.organisation_id' => ['required', 'exists:organisations,id'],
            'items.*.amount'         => ['required', 'numeric', 'gt:0'],
            'items.*.exchange'       => ['sometimes', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        foreach ($this->get('items', []) as $item) {
            if (!\App\Models\SupplyChain\AspoDeposit::where('id', $item['aspo_deposit_id'] ?? null)
                ->where('agent_id', $this->agent->id)
                ->exists()) {
                $validator->errors()->add('items', __('One of the deposits does not belong to this agent'));
            }
        }
    }

    public function handle(Agent $agent, array $modelData): DepositRequest
    {
        $items = $modelData['items'];
        unset($modelData['items']);

        return DB::transaction(function () use ($agent, $modelData, $items) {
            $depositRequest = $agent->depositRequests()->create(
                array_merge($modelData, [
                    'group_id'     => $agent->group_id,
                    'state'        => DepositRequestStateEnum::REQUESTED,
                    'requested_at' => now(),
                ])
            );

            foreach ($items as $item) {
                $depositRequest->items()->create([
                    'aspo_deposit_id' => $item['aspo_deposit_id'],
                    'organisation_id' => $item['organisation_id'],
                    'amount'          => $item['amount'],
                    'exchange'        => $item['exchange'] ?? 1,
                ]);
            }

            return $depositRequest;
        });
    }

    public function asController(Agent $agent, ActionRequest $request): DepositRequest
    {
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent, $this->validatedData);
    }

    public function action(Agent $agent, array $modelData): DepositRequest
    {
        $this->asAction = true;
        $this->agent     = $agent;
        $this->initialisationFromGroup($agent->group, $modelData);

        return $this->handle($agent, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }


    private Agent $agent;
}
