<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePurchaseOrderJourneyStage extends OrgAction
{
    private PurchaseOrder $order;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        if ($this->order->isAgentOrder()) {
            $column = PurchaseOrderJourneyStageEnum::tryFrom((string) $request->input('stage'))?->markColumn();
            if (in_array($column, UpdatePurchaseOrder::MANAGEMENT_ONLY_FIELDS) && $user->authorisedShopOrganisations()->doesntExist()) {
                return false;
            }
        }

        return $user->authTo('supply-chain.edit') || $user->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(PurchaseOrder $order, array $modelData): PurchaseOrder
    {
        $column = PurchaseOrderJourneyStageEnum::from($modelData['stage'])->markColumn();

        return UpdatePurchaseOrder::make()->action($order, [$column => $modelData['date']]);
    }

    public function rules(): array
    {
        return [
            'stage' => [
                'required',
                Rule::in(collect(PurchaseOrderJourneyStageEnum::cases())
                    ->filter(fn (PurchaseOrderJourneyStageEnum $stage) => $stage->markColumn() !== null)
                    ->map(fn (PurchaseOrderJourneyStageEnum $stage) => $stage->value)
                    ->values()
                    ->all()),
            ],
            'date'  => ['present', 'nullable', 'date', 'before_or_equal:tomorrow'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->order = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
