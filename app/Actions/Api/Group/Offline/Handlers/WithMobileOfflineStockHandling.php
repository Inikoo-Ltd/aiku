<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Inventory\WithMobileStockWriteRequests;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\MobileOfflineOperation;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait WithMobileOfflineStockHandling
{
    use WithMobileStockWriteRequests;

    public const string KIND_RULES = 'rules';
    public const string KIND_VERSION = 'version';

    public const string REASON_NOT_FOUND = 'not_found';
    public const string REASON_NO_RIGHT = 'no_right';
    public const string REASON_WRONG_STATE = 'wrong_state';
    public const string REASON_INVALID = 'invalid';

    /**
     * @param array<string, mixed> $payload
     */
    protected function alreadyDoneOnline(MobileOfflineContext $context, array $payload, ?float $requestedQuantity = null): ?MobileOfflineOutcome
    {
        $clientRequestId = Arr::get($payload, 'client_request_id');

        if (!$clientRequestId || !Cache::has(self::mobileWriteKey($context->user()->id, $clientRequestId))) {
            return null;
        }

        return MobileOfflineOutcome::applied($requestedQuantity, $requestedQuantity, false, __('Already done.'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function rememberClientRequest(MobileOfflineContext $context, array $payload): void
    {
        $clientRequestId = Arr::get($payload, 'client_request_id');

        if (!$clientRequestId) {
            return;
        }

        $cacheKey = self::mobileWriteKey($context->user()->id, $clientRequestId);
        DB::afterCommit(fn () => Cache::put($cacheKey, true, now()->addDay()));
    }

    /**
     * @param array<int, int> $ids
     * @return Collection<int, LocationOrgStock>
     */
    protected function lockLocationOrgStocks(array $ids): Collection
    {
        return LocationOrgStock::whereIn('id', array_values(array_unique($ids)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    protected function locationRefusalReason(?Location $location, int $warehouseId): ?string
    {
        return match (true) {
            !$location || $location->warehouse_id !== $warehouseId || $location->trashed() => self::REASON_NOT_FOUND,
            $location->status !== LocationStatusEnum::OPERATIONAL || !$location->allow_stocks => self::REASON_WRONG_STATE,
            default => null,
        };
    }

    protected function locationRefusalMessage(string $reason): string
    {
        return $reason === self::REASON_NOT_FOUND
            ? __('That location is not in this warehouse or does not hold this SKO.')
            : __('That location is not operational or does not take SKOs.');
    }

    /**
     * @param array<int, int> $locationIds
     */
    protected function recordMovementsSince(MobileOfflineContext $context, int $orgStockId, array $locationIds, int $afterId): void
    {
        OrgStockMovement::where('org_stock_id', $orgStockId)
            ->whereIn('location_id', $locationIds)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get()
            ->each(fn (OrgStockMovement $movement) => $context->recordCreated($movement));
    }

    protected function lastMovementId(): int
    {
        return (int) OrgStockMovement::max('id');
    }

    /**
     * @return array<int, int>
     */
    protected function createdEffectIds(MobileOfflineOperation $operation, Model $model): array
    {
        return $operation->effects()
            ->where('model_type', $model->getMorphClass())
            ->where('effect', 'created')
            ->orderBy('id')
            ->pluck('model_id')
            ->map(fn ($modelId) => (int) $modelId)
            ->all();
    }

    /**
     * @template T
     * @param Closure(): T $write
     * @return T|MobileOfflineOutcome
     *
     * @throws \Throwable
     */
    protected function inSavepoint(Closure $write, ?float $requestedQuantity): mixed
    {
        try {
            return DB::transaction($write);
        } catch (ValidationException $exception) {
            return MobileOfflineOutcome::refused(self::REASON_INVALID, MobileOfflineOutcome::INSTRUCTION_NONE, (string) collect($exception->errors())->flatten()->first(), $requestedQuantity);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 403) {
                return MobileOfflineOutcome::refused(self::REASON_NO_RIGHT, MobileOfflineOutcome::INSTRUCTION_NONE, $exception->getMessage() ?: __('You can not do this.'), $requestedQuantity);
            }

            if ($exception->getStatusCode() === 422) {
                return MobileOfflineOutcome::refused(self::REASON_INVALID, MobileOfflineOutcome::INSTRUCTION_NONE, $exception->getMessage(), $requestedQuantity);
            }

            throw $exception;
        }
    }

    protected function refusedRollback(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    protected function quantityLabel(float $quantity): string
    {
        return trimDecimalZeros(round($quantity, 4));
    }
}
