<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 15 Oct 2026 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\ResolvePurchaseOrderDeliveryAddress;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * An agent opens an empty shipment (a container, LCL, air freight or a parcel) for one of the organisations it buys for, in its own currency, and fills it
 * afterwards with that organisation's confirmed orders.
 */
class StoreAgentStockDelivery extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementSerialReferences;

    private Agent $agent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(OrgAgent $orgAgent, array $modelData): StockDelivery
    {
        $date     = now();
        $currency = $orgAgent->agent->organisation->currency;

        $deliveryType = StockDeliveryTypeEnum::tryFrom((string) Arr::get($modelData, 'delivery_type')) ?? StockDeliveryTypeEnum::usualFor($orgAgent->agent->organisation);

        return StoreStockDelivery::make()->action(
            $orgAgent,
            [
                'reference'    => Arr::get($modelData, 'reference') ?: $this->newProcurementReference($orgAgent, SerialReferenceModelEnum::STOCK_DELIVERY),
                'state'        => StockDeliveryStateEnum::IN_PROCESS,
                'date'         => $date,
                'currency_id'  => $currency->id,
                'org_exchange' => GetHistoricCurrencyExchange::run($currency, $orgAgent->organisation->currency, $date->copy()->startOfDay()),
                'grp_exchange' => GetHistoricCurrencyExchange::run($currency, $orgAgent->organisation->group->currency, $date->copy()->startOfDay()),
                'data'         => array_filter([
                    'delivery_type'    => $deliveryType->value,
                    'container_size'   => $deliveryType === StockDeliveryTypeEnum::CONTAINER
                        ? (Arr::get($modelData, 'container_size') ?: OrgAgentOrderContainerEnum::FORTY_FOOT->value)
                        : null,
                    'delivery_address' => self::deliveryAddress($orgAgent->organisation),
                ]),
            ],
            strict: false
        );
    }

    /**
     * @return Builder<OrgAgent>
     */
    public static function orgAgentsOfAgent(Agent $agent): Builder
    {
        return OrgAgent::query()
            ->where('agent_id', $agent->id)
            ->where('status', true)
            ->where('organisation_id', '!=', $agent->organisation_id);
    }

    /**
     * Where the organisation takes its goods: its warehouse's address, or else the address its latest purchase order
     * was delivered to.
     */
    public static function deliveryAddress(Organisation $organisation): ?string
    {
        return ResolvePurchaseOrderDeliveryAddress::run($organisation)
            ?? PurchaseOrder::where('organisation_id', $organisation->id)
                ->whereRaw("coalesce(data->>'delivery_address', '') <> ''")
                ->orderByDesc('id')
                ->first(['id', 'data'])
                ?->data['delivery_address'];
    }

    public function rules(): array
    {
        return [
            'org_agent_id'   => [
                'required',
                'integer',
                Rule::in(self::orgAgentsOfAgent($this->agent)->pluck('id')->all()),
            ],
            'reference'      => ['sometimes', 'nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'delivery_type'  => ['sometimes', 'nullable', Rule::enum(StockDeliveryTypeEnum::class)],
            'container_size' => ['sometimes', 'nullable', Rule::enum(OrgAgentOrderContainerEnum::class)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $reference = $this->get('reference');
        $orgAgent  = OrgAgent::find($this->get('org_agent_id'));

        if (!$reference || !$orgAgent) {
            return;
        }

        if (self::referenceIsUsed($orgAgent->organisation_id, $reference)) {
            $validator->errors()->add('reference', __('This reference is already used by another stock delivery of :organisation', ['organisation' => $orgAgent->organisation->name]));
        }
    }

    public static function referenceIsUsed(int $organisationId, string $reference, ?int $exceptStockDeliveryId = null): bool
    {
        return StockDelivery::query()
            ->where('organisation_id', $organisationId)
            ->whereRaw('lower(reference) = lower(?)', [$reference])
            ->when($exceptStockDeliveryId, fn (Builder $query) => $query->where('id', '!=', $exceptStockDeliveryId))
            ->exists();
    }

    public function asController(Organisation $organisation, ActionRequest $request): StockDelivery
    {
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        $this->initialisation($organisation, $request);

        return $this->handle(OrgAgent::findOrFail($this->validatedData['org_agent_id']), $this->validatedData);
    }

    public function action(Agent $agent, OrgAgent $orgAgent, array $modelData = []): StockDelivery
    {
        $this->asAction = true;
        $this->agent    = $agent;
        $this->initialisation($agent->organisation, array_merge($modelData, ['org_agent_id' => $orgAgent->id]));

        return $this->handle($orgAgent, $this->validatedData);
    }

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return Redirect::route('grp.org.agent.stock_deliveries.show', [
            'organisation'  => $this->organisation->slug,
            'stockDelivery' => $stockDelivery->slug,
        ]);
    }
}
