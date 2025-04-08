<?php
/*
 * author Arya Permana - Kirin
 * created on 08-04-2025-16h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydratePallets;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydrateTransactions;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAutoServices;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\Inventory\WarehouseArea\Hydrators\WarehouseAreaHydratePallets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Pallet;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteStoredPallet extends OrgAction
{
    use WithActionUpdate;

    public function handle(Pallet $pallet, array $modelData): Pallet
    {
        $deleteConfirmation = Arr::pull($modelData, 'delete_confirmation');
        $fulfilmentCustomer = $pallet->fulfilmentCustomer;
        if(strtolower(trim($deleteConfirmation ?? '')) === strtolower($pallet->reference) && $pallet->state == PalletStateEnum::STORING){
            $pallet->delete();

            $fulfilmentCustomer->refresh();
            FulfilmentCustomerHydratePallets::dispatch($fulfilmentCustomer);
            FulfilmentHydratePallets::dispatch($fulfilmentCustomer->fulfilment);
            WarehouseHydratePallets::dispatch($fulfilmentCustomer->warehouse);
            if ($pallet->location && $pallet->location->warehouseArea) {
                WarehouseAreaHydratePallets::dispatch($pallet->location->warehouseArea);
            }
            PalletRecordSearch::dispatch($pallet);
            return $pallet;
        } else {
            abort(419);
        }

        return $invoice;
    }

    public function rules(): array
    {
        return [
            // 'deleted_note' => ['required', 'string', 'max:4000'],
            'delete_confirmation'   => ['required'],
            'deleted_by'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->set('deleted_by', $request->user()->id);
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($pallet, $this->validatedData);
    }
}
