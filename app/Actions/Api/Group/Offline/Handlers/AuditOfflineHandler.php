<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Inventory\WithMobileOrgStocks;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class AuditOfflineHandler implements MobileOfflineHandler
{
    use WithMobileOfflineStockHandling;
    use WithMobileOrgStocks;

    /**
     * @return array<string, mixed>
     */
    abstract protected function bodyRules(): array;

    abstract protected function canAudit(User $user, Warehouse $warehouse): bool;

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int> $ids
     * @return Collection<int, LocationOrgStock>|null
     */
    abstract protected function countedLinks(array $payload, Warehouse $warehouse, array $ids): ?Collection;

    /**
     * @param array<int, array<string, mixed>> $counts
     * @param Collection<int, LocationOrgStock> $links
     * @return array<int, array{link: LocationOrgStock, data: array<string, mixed>}>
     *
     * @throws ValidationException
     */
    abstract protected function audits(array $counts, Collection $links): array;

    public function kind(): string
    {
        return self::KIND_VERSION;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, array_merge($this->bodyRules(), [
            'versions'          => ['required', 'array'],
            'versions.*'        => ['required', 'integer', 'min:1'],
            'client_request_id' => ['required', 'uuid'],
        ]));

        $validator->after(function ($validator) use ($payload) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($payload['counts'] as $index => $count) {
                if (self::versionSeen($payload, (int) $count['location_org_stock_id']) === null) {
                    $validator->errors()->add("versions.$index", __('Send the version of every counted location.'));
                }
            }
        });

        return $validator->validate();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function versionSeen(array $payload, int $locationOrgStockId): ?int
    {
        $versions = $payload['versions'] ?? [];
        $version  = $versions[$locationOrgStockId] ?? $versions[(string) $locationOrgStockId] ?? null;

        return $version === null ? null : (int) $version;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{t: string, id: int, v: int}>
     */
    public function touchedRows(array $payload): array
    {
        return array_values(array_map(fn (array $count) => [
            't'  => 'LocationOrgStock',
            'id' => (int) $count['location_org_stock_id'],
            'v'  => (int) self::versionSeen($payload, (int) $count['location_org_stock_id']),
        ], $payload['counts'] ?? []));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \Throwable
     */
    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        if ($alreadyDone = $this->alreadyDoneOnline($context, $payload)) {
            return $alreadyDone;
        }

        $user      = $context->user();
        $warehouse = $context->warehouse();
        $ids       = array_map(fn (array $count) => (int) $count['location_org_stock_id'], $payload['counts']);

        $links = $this->countedLinks($payload, $warehouse, $ids);
        if ($links === null) {
            return MobileOfflineOutcome::refused(self::REASON_NOT_FOUND, MobileOfflineOutcome::INSTRUCTION_NONE, __('A counted location is not in this warehouse any more.'));
        }

        if (!$this->canAudit($user, $warehouse)) {
            return MobileOfflineOutcome::refused(self::REASON_NO_RIGHT, MobileOfflineOutcome::INSTRUCTION_NONE, __('You need stock edit rights to change the stock.'));
        }

        $locked = $this->lockLocationOrgStocks($ids);
        $links  = $links->map(fn (LocationOrgStock $link) => $locked->get($link->id)->setRelation('location', $link->location));

        $changed = $links->filter(function (LocationOrgStock $link) use ($payload, $context) {
            $versionSeen = (int) self::versionSeen($payload, $link->id);

            return (int) $link->version !== $versionSeen && !$context->ownChainAllows($link, $versionSeen);
        });

        if ($changed->isNotEmpty()) {
            return MobileOfflineOutcome::conflict(
                'other_change',
                __('Someone changed the stock of :locations after you downloaded it. Count again.', ['locations' => $changed->map(fn (LocationOrgStock $link) => $link->location?->code)->filter()->unique()->implode(', ')]),
                $links->map(fn (LocationOrgStock $link) => $this->snapshotRow($link, $payload))->values()->all(),
                $this->lastChangedBy($changed, $user)
            );
        }

        try {
            $audits = $this->audits($payload['counts'], $links);
        } catch (ValidationException $exception) {
            return MobileOfflineOutcome::refused(self::REASON_INVALID, MobileOfflineOutcome::INSTRUCTION_RECOUNT, (string) collect($exception->errors())->flatten()->first());
        }

        $lastMovementId = $this->lastMovementId();
        $result         = $this->inSavepoint(function () use ($audits, $user) {
            foreach ($audits as $audit) {
                AuditLocationOrgStock::make()->action($audit['link'], $audit['data'], $user);
            }

            return true;
        }, null);

        if ($result instanceof MobileOfflineOutcome) {
            return $result;
        }

        foreach ($links->groupBy('org_stock_id') as $orgStockId => $orgStockLinks) {
            $this->recordMovementsSince($context, (int) $orgStockId, $orgStockLinks->pluck('location_id')->all(), $lastMovementId);
        }
        $this->rememberClientRequest($context, $payload);

        return MobileOfflineOutcome::applied(null, null, false, __('Stock count saved.'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function snapshotRow(LocationOrgStock $link, array $payload): array
    {
        return [
            'location_org_stock_id' => $link->id,
            'location_id'           => $link->location_id,
            'location_code'         => $link->location?->code,
            'org_stock_id'          => $link->org_stock_id,
            'quantity'              => (float) $link->quantity,
            'version'               => (int) $link->version,
            'version_seen'          => self::versionSeen($payload, $link->id),
            'counted'               => (float) collect($payload['counts'])->firstWhere('location_org_stock_id', $link->id)['quantity'],
            'audited_at'            => $link->audited_at ? Carbon::parse($link->audited_at)->toIso8601String() : null,
        ];
    }

    /**
     * @param Collection<int, LocationOrgStock> $changed
     */
    private function lastChangedBy(Collection $changed, User $user): ?int
    {
        $userId = OrgStockMovement::query()
            ->where(function ($query) use ($changed) {
                foreach ($changed as $link) {
                    $query->orWhere(fn ($row) => $row->where('org_stock_id', $link->org_stock_id)->where('location_id', $link->location_id));
                }
            })
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->orderByDesc('id')
            ->value('user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return false;
    }

    /**
     * @throws ValidationException
     */
    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        $this->refusedRollback(__('A count can not be rolled back. Count the location again.'));
    }
}
