<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 01:01:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\SetOrgStockPickingLocation;
use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Dispatching\BatchCode\StoreBatchCode;
use App\Actions\Inventory\OrgStockMovement\AllocateOrgStockMovementBatches;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\BroadcastLowStockAudited;
use App\Events\BroadcastLowStockAuditedStart;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class AuditLocationOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithLocationOrgStockActionAuthorisation;
    use CalculatesOrgStockHistories;

    private LocationOrgStock $locationOrgStock;
    private User|null $user = null;

    /**
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $locationOrgStock, array $modelData): LocationOrgStock
    {
        // Only the other tabs need locking: this one is doing the counting and has the modal open
        broadcast(new BroadcastLowStockAuditedStart($locationOrgStock))->toOthers();

        try {
            $locationOrgStock = DB::transaction(function () use ($locationOrgStock, $modelData) {
                $locationOrgStock = LocationOrgStock::lockForUpdate()->findOrFail($locationOrgStock->id);
                $currentStock     = $locationOrgStock->quantity;
                $newQuantity  = Arr::pull($modelData, 'quantity');
                $countedBatches = Arr::pull($modelData, 'batches');
                $reason       = Arr::pull($modelData, 'reason');
                $note         = Arr::pull($modelData, 'note');
                $stockDiff    = $newQuantity - $currentStock;

                $costPerSku = $this->getLppPerSku($locationOrgStock->orgStock, Carbon::now());

                $exchangeRate = GetCurrencyExchange::run($locationOrgStock->organisation->currency, $locationOrgStock->group->currency);

                $storedData    = [
                    'quantity'         => $stockDiff,
                    'audited_quantity' => $newQuantity,
                    'date'             => now()->format('Y-m-d H:i:s.u'),
                    'type'             => Arr::pull($modelData, 'stock_movement_type', OrgStockMovementTypeEnum::AUDIT),
                    'cost_per_sku'     => $costPerSku,
                    'org_amount'       => $stockDiff * $costPerSku,
                    'grp_amount'       => $stockDiff * $costPerSku * $exchangeRate,
                    'user_id'          => $this->user?->id,
                ];

                if ($reason) {
                    data_set($storedData, 'reason', $reason);
                }

                if ($note) {
                    data_set($storedData, 'note', $note);
                }

                $orgStockMovement = StoreOrgStockMovement::make()->action(
                    $locationOrgStock->orgStock,
                    $locationOrgStock->location,
                    $storedData
                );

                if ($countedBatches !== null) {
                    AllocateOrgStockMovementBatches::make()->count($orgStockMovement, array_map(fn (array $batch) => [
                        'batch_code_id' => $batch['batch_code_id'] ?? StoreBatchCode::make()->inOrganisation($locationOrgStock->organisation, [
                            'code'         => trim($batch['code']),
                            'expiry_date'  => $batch['expiry_date'] ?? null,
                            'org_stock_id' => $locationOrgStock->org_stock_id,
                        ])->id,
                        'quantity'      => $batch['quantity'],
                    ], $countedBatches));
                }
                // Update audited_at
                $locationOrgStock->updateQuietly([
                    'audited_at'        => now(),
                    'is_low_stock_checked' => true
                ]);
                $locationOrgStock->refresh();

                return $locationOrgStock;
            });
        } catch (\Throwable $exception) {
            BroadcastLowStockAudited::dispatch($locationOrgStock->refresh());

            throw $exception;
        }

        SetOrgStockPickingLocation::dispatch($locationOrgStock->org_stock_id)->delay(2);
        OrgStockHydrateQuantityInLocations::dispatch($locationOrgStock->org_stock_id)->delay(2);

        BroadcastLowStockAudited::dispatch($locationOrgStock);

        return $locationOrgStock;
    }

    public function rules(): array
    {
        return [
            'quantity'              => ['required', 'numeric', 'gte:0'],
            'reason'                => ['required', new Enum(OrgStockMovementReasonEnum::class)],
            'note'                  => ['sometimes', 'nullable', 'string'],
            'stock_movement_type'   => ['sometimes', new Enum(OrgStockMovementTypeEnum::class)],
            'batches'                 => ['sometimes', 'array'],
            'batches.*.batch_code_id' => ['required_without:batches.*.code', 'nullable', 'integer', Rule::exists('batch_codes', 'id')->where('org_stock_id', $this->locationOrgStock->org_stock_id)],
            'batches.*.code'          => ['required_without:batches.*.batch_code_id', 'nullable', 'string', 'max:64'],
            'batches.*.expiry_date'   => ['sometimes', 'nullable', 'date'],
            'batches.*.quantity'      => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $counted = collect($this->get('batches', []))->sum(fn ($batch) => (float) ($batch['quantity'] ?? 0));
        if ($counted - (float) $this->get('quantity') > 0.000001) {
            $validator->errors()->add('batches', __('The batches add up to more than the quantity counted'));
        }
    }


    public function prepareForValidation(): void
    {
        if (!$this->has('quantity')) {
            $this->set('quantity', $this->locationOrgStock->quantity);
        }

        if (!$this->has('reason')) {
            $this->set('reason', OrgStockMovementReasonEnum::RECOUNT->value);
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(LocationOrgStock $locationOrgStock, array $modelData, ?User $user = null): LocationOrgStock
    {
        $this->asAction         = true;
        $this->locationOrgStock = $locationOrgStock;
        if ($user) {
            $this->user = $user;
        }

        $this->initialisation($locationOrgStock->organisation, $modelData);


        return $this->handle($locationOrgStock, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(LocationOrgStock $locationOrgStock, ActionRequest $request): LocationOrgStock
    {
        $this->user = request()->user();
        $this->locationOrgStock = $locationOrgStock;
        $this->initialisation($locationOrgStock->organisation, $request);

        return $this->handle($locationOrgStock, $this->validatedData);
    }
}
