<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AuditMobileOrgStock
{
    use AsController;
    use WithMobileOrgStocks;
    use WithMobileStockWriteRequests;

    public const float SAME_QUANTITY_TOLERANCE = 0.000001;

    /**
     * @param array<int, array{location_org_stock_id: int, quantity: float|int|string, reason?: string|null, note?: string|null}> $counts
     *
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock, Warehouse $warehouse, array $counts, User $user): bool
    {
        $links  = $this->ownLinks($orgStock, $warehouse, Arr::pluck($counts, 'location_org_stock_id'));
        $audits = $this->auditsWithReasons($counts, $links);

        DB::transaction(function () use ($audits, $user) {
            foreach ($audits as $audit) {
                AuditLocationOrgStock::make()->action($audit['link'], $audit['data'], $user);
            }
        });

        return collect($audits)->contains('changes_stock', true);
    }

    /**
     * @param array<int, array<string, mixed>> $counts
     * @param Collection<int, LocationOrgStock> $links
     * @return array<int, array{link: LocationOrgStock, data: array{quantity: mixed, reason: string, note: string|null}, changes_stock: bool}>
     *
     * @throws ValidationException
     */
    public function auditsWithReasons(array $counts, Collection $links): array
    {
        $audits = [];
        $errors = [];

        foreach ($counts as $index => $count) {
            $link       = $links->get((int) $count['location_org_stock_id']);
            $difference = (float) $count['quantity'] - (float) $link->quantity;
            $reason     = $count['reason'] ?? null;
            $changes    = abs($difference) > self::SAME_QUANTITY_TOLERANCE;

            if (!$changes) {
                $reason ??= OrgStockMovementReasonEnum::RECOUNT->value;
            } elseif ($reason === null) {
                $errors["counts.$index.reason"] = __('A count that differs from the stock needs a reason.');
            } elseif ($difference > 0 && !in_array($reason, OrgStockMovementReasonEnum::increaseReason(), true)) {
                $errors["counts.$index.reason"] = __('This reason can not explain a stock gain.');
            } elseif ($difference < 0 && !in_array($reason, OrgStockMovementReasonEnum::decreaseReason(), true)) {
                $errors["counts.$index.reason"] = __('This reason can not explain a stock loss.');
            }

            $audits[] = [
                'link'          => $link,
                'data'          => [
                    'quantity' => $count['quantity'],
                    'reason'   => $reason,
                    'note'     => $count['note'] ?? null,
                ],
                'changes_stock' => $changes,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $audits;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanChangeOrgStock($user, $warehouse, $orgStock);
        $clientRequestId = $this->optionalClientRequestId($request->all());

        $data = Validator::make($request->all(), [
            'counts'                         => ['required', 'array', 'min:1'],
            'counts.*'                       => ['required', 'array'],
            'counts.*.location_org_stock_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID, 'distinct'],
            'counts.*.quantity'              => ['required', 'numeric', 'gte:0'],
            'counts.*.reason'                => ['sometimes', 'nullable', Rule::enum(OrgStockMovementReasonEnum::class)],
            'counts.*.note'                  => ['sometimes', 'nullable', 'string'],
        ])->validate();

        return $this->oncePerClientRequest(
            $user,
            $clientRequestId,
            function () use ($orgStock, $warehouse, $data, $user) {
                $changed = $this->handle($orgStock, $warehouse, $data['counts'], $user);

                return $this->respondWithOrgStock(
                    $warehouse,
                    $orgStock,
                    $user,
                    $changed ? 'done' : 'nothing_changed',
                    $changed ? __('Stock count saved.') : __('The count matches the stock, nothing changed.')
                );
            },
            fn () => $this->respondWithOrgStock($warehouse, $orgStock, $user, 'nothing_changed', __('Already done.'))
        );
    }
}
