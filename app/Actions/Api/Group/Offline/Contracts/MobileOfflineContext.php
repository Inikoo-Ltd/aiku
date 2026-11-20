<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Contracts;

use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

interface MobileOfflineContext
{
    public function user(): User;

    public function warehouse(): Warehouse;

    public function organisation(): Organisation;

    public function performedAt(): CarbonImmutable;

    public function seenAt(): ?CarbonImmutable;

    public function countedSince(LocationOrgStock $locationOrgStock): bool;

    public function ownChainAllows(LocationOrgStock $locationOrgStock, int $baseVersion): bool;

    public function recordCreated(Model $model): void;

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function recordUpdated(Model $model, array $before, array $after): void;

    /**
     * @param array<string, mixed> $before
     */
    public function recordDeleted(Model $model, array $before): void;

    public function requestStockCheck(Model $model, string $description): void;
}
