<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-11h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace Tests\Support;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class MobileOfflineTestContext implements MobileOfflineContext
{
    /** @var array<int, Model> */
    public array $created = [];

    /** @var array<int, array{model: Model, before: array<string, mixed>, after: array<string, mixed>}> */
    public array $updated = [];

    /** @var array<int, array{model: Model, before: array<string, mixed>}> */
    public array $deleted = [];

    /** @var array<int, array{model: Model, description: string}> */
    public array $stockChecks = [];

    /** @var array<int, array<int, array{0: int, 1: int}>> */
    private array $ownVersionSteps = [];

    private CarbonImmutable $performedAt;

    public function __construct(
        private readonly User $user,
        private readonly Warehouse $warehouse,
        ?CarbonImmutable $performedAt = null,
        private readonly ?CarbonImmutable $seenAt = null,
    ) {
        $this->performedAt = $performedAt ?? CarbonImmutable::now();
    }

    public function user(): User
    {
        return $this->user;
    }

    public function warehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function organisation(): Organisation
    {
        return $this->warehouse->organisation;
    }

    public function performedAt(): CarbonImmutable
    {
        return $this->performedAt;
    }

    public function seenAt(): ?CarbonImmutable
    {
        return $this->seenAt;
    }

    public function countedSince(LocationOrgStock $locationOrgStock): bool
    {
        $auditedAt = LocationOrgStock::whereKey($locationOrgStock->id)->value('audited_at');

        return $auditedAt && CarbonImmutable::parse($auditedAt)->gt($this->performedAt);
    }

    public function withOwnChange(LocationOrgStock $locationOrgStock, int $fromVersion, int $toVersion): self
    {
        $this->ownVersionSteps[$locationOrgStock->id][] = [$fromVersion, $toVersion];

        return $this;
    }

    public function ownChainAllows(LocationOrgStock $locationOrgStock, int $baseVersion): bool
    {
        $point = $baseVersion;
        foreach ($this->ownVersionSteps[$locationOrgStock->id] ?? [] as [$fromVersion, $toVersion]) {
            if ($fromVersion !== $point) {
                return false;
            }
            $point = $toVersion;
        }

        return $point === (int) LocationOrgStock::whereKey($locationOrgStock->id)->value('version');
    }

    public function recordCreated(Model $model): void
    {
        $this->created[] = $model;
    }

    public function recordUpdated(Model $model, array $before, array $after): void
    {
        $this->updated[] = ['model' => $model, 'before' => $before, 'after' => $after];
    }

    public function recordDeleted(Model $model, array $before): void
    {
        $this->deleted[] = ['model' => $model, 'before' => $before];
    }

    public function requestStockCheck(Model $model, string $description): void
    {
        $this->stockChecks[] = ['model' => $model, 'description' => $description];
    }

    /**
     * @return array<int, Model>
     */
    public function createdOf(string $modelClass): array
    {
        return array_values(array_filter($this->created, fn (Model $model) => $model instanceof $modelClass));
    }
}
