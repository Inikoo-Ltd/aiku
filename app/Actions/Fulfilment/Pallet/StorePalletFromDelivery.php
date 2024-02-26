<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\PalletDelivery\Hydrators\HydratePalletDeliveries;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class StorePalletFromDelivery extends OrgAction
{
    use AsCommand;

    public $commandSignature = 'pallet:store-from-delivery {palletDelivery}';

    private PalletDelivery $parent;

    public function handle(PalletDelivery $palletDelivery, array $modelData): Pallet
    {
        data_set($modelData, 'group_id', $palletDelivery->group_id);
        data_set($modelData, 'organisation_id', $palletDelivery->organisation_id);
        data_set($modelData, 'fulfilment_id', $palletDelivery->fulfilment_id);
        data_set($modelData, 'fulfilment_customer_id', $palletDelivery->fulfilment_customer_id);
        data_set($modelData, 'warehouse_id', $palletDelivery->warehouse_id);

        if (Arr::exists($modelData, 'state') and Arr::get($modelData, 'state') != PalletStateEnum::IN_PROCESS) {
            if (!Arr::get($modelData, 'reference')) {
                data_set(
                    $modelData,
                    'reference',
                    GetSerialReference::run(
                        container: $palletDelivery->fulfilmentCustomer,
                        modelType: SerialReferenceModelEnum::PALLET
                    )
                );
            }
        }

        /** @var Pallet $pallet */
        $pallet = $palletDelivery->pallets()->create($modelData);

        HydratePalletDeliveries::run($palletDelivery);

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            // TODO: Raul please do the permission for the web user
            return true;
        }

        return $request->user()->hasPermissionTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if($this->fulfilment->warehouses()->count()==1) {
            $this->fill(['warehouse_id' =>$this->fulfilment->warehouses()->first()->id]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_reference' => ['nullable'],
            'notes'              => ['nullable', 'string','max:1024']
        ];
    }

    public function fromRetina(PalletDelivery $palletDelivery, ActionRequest $request): Pallet
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;

        $this->initialisation($request->get('website')->organisation, $request);
        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): Pallet
    {
        $this->parent = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, array $modelData, int $hydratorsDelay = 0): Pallet
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent         = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $modelData);

        return $this->handle($palletDelivery, $this->validatedData);
    }


    public function asCommand(Command $command): int
    {
        $palletDelivery = PalletDelivery::where('reference', $command->argument('palletDelivery'))->firstOrFail();

        $this->handle($palletDelivery, [
            'group_id'               => $palletDelivery->group_id,
            'organisation_id'        => $palletDelivery->organisation_id,
            'fulfilment_id'          => $palletDelivery->fulfilment_id,
            'fulfilment_customer_id' => $palletDelivery->fulfilment_customer_id,
            'warehouse_id'           => $palletDelivery->warehouse_id,
            'slug'                   => now()->timestamp
        ]);

        echo "Pallet created from delivery: $palletDelivery->reference\n";

        return 0;
    }


    public function htmlResponse(Pallet $pallet, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.pallet-delivery.pallet.store' => Redirect::route('grp.org.fulfilments.show.crm.customers.show.pallet-deliveries.show', [
                'organisation'           => $pallet->organisation->slug,
                'fulfilment'             => $pallet->fulfilment->slug,
                'fulfilmentCustomer'     => $pallet->fulfilmentCustomer->slug,
                'palletDelivery'         => $this->parent->reference
            ]),
            default => Redirect::route('retina.storage.pallet-deliveries.show', [
                'palletDelivery'     => $this->parent->reference
            ])
        };
    }
}
