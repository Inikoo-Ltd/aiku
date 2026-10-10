<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgStockFamilyGbPallet extends OrgAction
{
    /**
     * Whether the family's GB-origin SKOs travel on the separate GB pallet to partners that split
     * GB-origin goods off. Off keeps them on the partner's normal pallet.
     */
    public function handle(OrgStockFamily $orgStockFamily, bool $gbSeparatePallet): OrgStockFamily
    {
        $orgStockFamily->update(['gb_separate_pallet' => $gbSeparatePallet]);

        return $orgStockFamily;
    }

    public static function canEdit(User $user, Organisation $organisation): bool
    {
        return $user->authTo([
            "org-supervisor.{$organisation->id}",
            ...$organisation->warehouses()->pluck('id')->map(fn ($warehouseId) => "supervisor-stocks.$warehouseId"),
            ...$organisation->productions()->pluck('id')->map(fn ($productionId) => "productions_operations.$productionId"),
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canEdit($request->user(), $this->organisation);
    }

    public function rules(): array
    {
        return [
            'gb_separate_pallet' => ['required', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): OrgStockFamily
    {
        abort_unless($orgStockFamily->organisation_id === $warehouse->organisation_id && $warehouse->organisation_id === $organisation->id, 404);
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStockFamily, (bool) $this->validatedData['gb_separate_pallet']);
    }

    public function action(OrgStockFamily $orgStockFamily, bool $gbSeparatePallet): OrgStockFamily
    {
        $this->asAction = true;
        $this->initialisation($orgStockFamily->organisation, ['gb_separate_pallet' => $gbSeparatePallet]);

        return $this->handle($orgStockFamily, (bool) $this->validatedData['gb_separate_pallet']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
