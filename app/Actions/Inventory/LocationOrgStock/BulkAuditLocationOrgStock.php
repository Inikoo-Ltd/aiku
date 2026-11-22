<?php

/*
 * Author Louis Perez
 * Created on 17-07-2026-15h-09m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class BulkAuditLocationOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;

    public function handle(OrgStock $orgStock, array $modelData)
    {
        $auditedLocationOrgStocks = data_get($modelData, 'audited_locations');
        $locationOrgStocks = LocationOrgStock::whereIn('id', data_get($auditedLocationOrgStocks, '*.id'))
            ->get()
            ->keyBy('id');

        $isCountWarningConfirmed = (bool)data_get($modelData, 'confirm_count_warning', false);
        if (!$isCountWarningConfirmed) {
            $countWarnings = [];
            foreach ($auditedLocationOrgStocks as $auditedLocationOrgStock) {
                $locationOrgStock = $locationOrgStocks->get($auditedLocationOrgStock['id']);
                if ($locationOrgStock && $countWarning = AuditLocationOrgStock::make()->countWarningFor($locationOrgStock, (float)$auditedLocationOrgStock['quantity'])) {
                    $countWarnings[] = $countWarning + ['location_code' => $locationOrgStock->location->code];
                }
            }
            if ($countWarnings) {
                throw ValidationException::withMessages(['count_warning' => json_encode($countWarnings)]);
            }
        }

        foreach ($auditedLocationOrgStocks as $auditedLocationOrgStock) {
            $locationOrgStock = $locationOrgStocks->get($auditedLocationOrgStock['id']);
            if ($locationOrgStock) {
                AuditLocationOrgStock::make()->action(
                    $locationOrgStock,
                    array_merge(Arr::only($auditedLocationOrgStock, [
                        'quantity',
                        'reason',
                        'note'
                    ]), ['confirm_count_warning' => $isCountWarningConfirmed]),
                    $this->user
                );
            }
        }

        $orgStock->refresh();

        return $orgStock;
    }

    public function rules(): array
    {
        return [
            'confirm_count_warning'         => ['sometimes', 'boolean'],
            'audited_locations'             => ['required', 'array'],
            'audited_locations.*.id'        => ['required', Rule::exists('location_org_stocks', 'id')],
            'audited_locations.*.quantity'  => ['required', 'numeric', 'gte:0'],
            'audited_locations.*.reason'    => ['required', Rule::enum(OrgStockMovementReasonEnum::class)],
            'audited_locations.*.note'      => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->user = request()->user();
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $this->validatedData);
    }
}
