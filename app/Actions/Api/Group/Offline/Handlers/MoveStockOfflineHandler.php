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
use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MoveStockOfflineHandler implements MobileOfflineHandler
{
    use WithMobileOfflineStockHandling;
    use WithMobileOrgStocks;

    public const float TOLERANCE = 0.000001;

    public function operation(): string
    {
        return 'move_stock';
    }

    public function kind(): string
    {
        return self::KIND_RULES;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'location_org_stock_id'           => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'targets'                         => ['sometimes', 'array', 'min:1'],
            'targets.*'                       => ['required', 'array'],
            'targets.*.location_org_stock_id' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'required_without:targets.*.location_id', 'prohibits:targets.*.location_id'],
            'targets.*.location_id'           => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'targets.*.quantity'              => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'target_location_org_stock_id'    => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'prohibits:location_id'],
            'location_id'                     => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'quantity'                        => ['required_without:targets', 'nullable', 'numeric', 'gt:0', 'max:1000000'],
            'reason'                          => ['sometimes', 'nullable', Rule::in(OrgStockMovementReasonEnum::transferReason())],
            'note'                            => ['sometimes', 'nullable', 'string', 'max:1024'],
            'client_request_id'               => ['required', 'uuid'],
        ]);

        $validator->after(function ($validator) use ($payload) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (!isset($payload['targets']) && empty($payload['target_location_org_stock_id']) && empty($payload['location_id'])) {
                $validator->errors()->add('targets', __('Say where the stock was moved to.'));

                return;
            }

            $targets = self::targetsOf($payload);
            $keys    = array_map(fn (array $target) => $target['location_org_stock_id'] ? 'los'.$target['location_org_stock_id'] : 'loc'.$target['location_id'], $targets);

            if (count($keys) !== count(array_unique($keys))) {
                $validator->errors()->add('targets', __('Each destination location can only be used once.'));
            }

            if (in_array((int) $payload['location_org_stock_id'], array_column($targets, 'location_org_stock_id'), true)) {
                $validator->errors()->add('targets', __('The source and the destination location must be different.'));
            }

            if (isset($payload['targets'], $payload['quantity']) && abs((float) $payload['quantity'] - self::totalOf($targets)) > self::TOLERANCE) {
                $validator->errors()->add('quantity', __('The quantity must be the total moved to the destinations.'));
            }
        });

        $validator->validate();

        return array_merge($payload, [
            'targets'  => self::targetsOf($payload),
            'quantity' => self::totalOf(self::targetsOf($payload)),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{location_org_stock_id: int|null, location_id: int|null, quantity: float}>
     */
    public static function targetsOf(array $payload): array
    {
        $targets = $payload['targets'] ?? [[
            'location_org_stock_id' => $payload['target_location_org_stock_id'] ?? null,
            'location_id'           => $payload['location_id'] ?? null,
            'quantity'              => $payload['quantity'] ?? 0,
        ]];

        return array_values(array_map(fn (array $target) => [
            'location_org_stock_id' => empty($target['location_org_stock_id']) ? null : (int) $target['location_org_stock_id'],
            'location_id'           => empty($target['location_org_stock_id']) && !empty($target['location_id']) ? (int) $target['location_id'] : null,
            'quantity'              => (float) ($target['quantity'] ?? 0),
        ], $targets));
    }

    /**
     * @param array<int, array{quantity: float}> $targets
     */
    public static function totalOf(array $targets): float
    {
        return (float) array_sum(array_column($targets, 'quantity'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{subject_type: string, subject_id: int, parent_type: ?string, parent_id: ?int}
     */
    public function subject(array $payload): array
    {
        $sourceId = (int) $payload['location_org_stock_id'];

        return [
            'subject_type' => 'LocationOrgStock',
            'subject_id'   => $sourceId,
            'parent_type'  => 'Location',
            'parent_id'    => LocationOrgStock::whereKey($sourceId)->value('location_id'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{t: string, id: int}>
     */
    public function touchedRows(array $payload): array
    {
        $sourceId = (int) $payload['location_org_stock_id'];
        $targets  = self::targetsOf($payload);
        $byLocation = $this->existingRowsAtLocations($sourceId, array_filter(array_column($targets, 'location_id')));

        $rows = [['t' => 'LocationOrgStock', 'id' => $sourceId]];
        foreach ($targets as $target) {
            $rowId = $target['location_org_stock_id'] ?? $byLocation[$target['location_id']] ?? null;
            $rows[] = $rowId ? ['t' => 'LocationOrgStock', 'id' => (int) $rowId] : ['t' => 'Location', 'id' => (int) $target['location_id']];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \Throwable
     */
    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        $targets   = self::targetsOf($payload);
        $requested = self::totalOf($targets);

        if ($alreadyDone = $this->alreadyDoneOnline($context, $payload, $requested)) {
            return $alreadyDone;
        }

        $user      = $context->user();
        $warehouse = $context->warehouse();

        /** @var LocationOrgStock|null $source */
        $source = LocationOrgStock::with(['location', 'orgStock'])->find((int) $payload['location_org_stock_id']);
        if (!$source || $source->warehouse_id !== $warehouse->id || $this->locationRefusalReason($source->location, $warehouse->id) === self::REASON_NOT_FOUND) {
            return MobileOfflineOutcome::refused(self::REASON_NOT_FOUND, MobileOfflineOutcome::INSTRUCTION_NONE, __('That stock location is not in this warehouse any more.'), $requested);
        }

        if (!(self::canViewInventory($user, $warehouse) || self::canViewLocations($user, $warehouse)) || !(self::canEditStocks($user, $warehouse) || self::canEditLocations($user, $warehouse))) {
            return MobileOfflineOutcome::refused(self::REASON_NO_RIGHT, MobileOfflineOutcome::INSTRUCTION_PUT_BACK, __('You can not move stock. Put the goods back in :location.', ['location' => $source->location->code]), $requested);
        }

        $resolved = [];
        foreach ($targets as $target) {
            $row = null;
            if ($target['location_org_stock_id']) {
                $row      = LocationOrgStock::with('location')->find($target['location_org_stock_id']);
                $location = $row && $row->org_stock_id === $source->org_stock_id && $row->warehouse_id === $warehouse->id ? $row->location : null;
            } else {
                $location = Location::withTrashed()->find($target['location_id']);
                $row      = $location ? LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $source->org_stock_id)->first() : null;
            }

            if ($reason = $this->locationRefusalReason($location, $warehouse->id)) {
                return MobileOfflineOutcome::refused($reason, MobileOfflineOutcome::INSTRUCTION_PUT_BACK, $this->locationRefusalMessage($reason).' '.__('Put the goods back in :location.', ['location' => $source->location->code]), $requested);
            }

            if ($location->id === $source->location_id) {
                return MobileOfflineOutcome::refused(self::REASON_INVALID, MobileOfflineOutcome::INSTRUCTION_NONE, __('The source and the destination location must be different.'), $requested);
            }

            $resolved[] = ['row' => $row, 'location' => $location, 'quantity' => $target['quantity']];
        }

        $locked = $this->lockLocationOrgStocks(array_merge([$source->id], array_filter(array_map(fn (array $target) => $target['row']?->id, $resolved))));
        $source->setRawAttributes($locked->get($source->id)->getAttributes(), true);

        $sourceCounted = $context->countedSince($source);
        if (!$sourceCounted && $requested - (float) $source->quantity > self::TOLERANCE) {
            $context->requestStockCheck($source, __('Offline move of :quantity of :sko from :location was not recorded: Aiku showed only :stock there. Count the location.', [
                'quantity' => $this->quantityLabel($requested),
                'sko'      => $source->orgStock->code,
                'location' => $source->location->code,
                'stock'    => $this->quantityLabel((float) $source->quantity),
            ]));

            return MobileOfflineOutcome::refused('not_enough_stock', MobileOfflineOutcome::INSTRUCTION_NONE, __('Aiku shows only :stock in :location, so the move was not recorded. Leave the goods where they are: a stock check was asked for.', [
                'stock'    => $this->quantityLabel((float) $source->quantity),
                'location' => $source->location->code,
            ]), $requested);
        }

        $lastMovementId = $this->lastMovementId();

        $result = $this->inSavepoint(function () use ($resolved, $locked, $source, $sourceCounted, $context, $payload, $user) {
            $created       = [];
            $countedSince  = $sourceCounted;

            foreach ($resolved as $target) {
                $row = $target['row'] ? $locked->get($target['row']->id) : null;
                $targetCounted = false;

                if ($row) {
                    $targetCounted = $context->countedSince($row);
                } else {
                    $row       = StoreLocationOrgStock::make()->action($source->orgStock, $target['location'], [
                        'type'  => LocationStockTypeEnum::STORING->value,
                        'notes' => $source->notes,
                    ], user: $user);
                    $created[] = $row;
                }

                MoveOrgStockToOtherLocation::make()->action($source, $row, [
                    'quantity' => $target['quantity'],
                    'reason'   => $payload['reason'] ?? null,
                    'note'     => $payload['note'] ?? null,
                ], $user, $sourceCounted, $targetCounted);

                $countedSince = $countedSince || $targetCounted;
            }

            return ['created' => $created, 'counted_since' => $countedSince];
        }, $requested);

        if ($result instanceof MobileOfflineOutcome) {
            return $result;
        }

        foreach ($result['created'] as $row) {
            $context->recordCreated($row);
        }
        $this->recordMovementsSince($context, $source->org_stock_id, array_merge([$source->location_id], array_map(fn (array $target) => $target['location']->id, $resolved)), $lastMovementId);
        $this->rememberClientRequest($context, $payload);

        return MobileOfflineOutcome::applied($requested, $requested, $result['counted_since'], __('Moved :quantity from :location.', [
            'quantity' => $this->quantityLabel($requested),
            'location' => $source->location->code,
        ]));
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackRefusal($operation) === null;
    }

    /**
     * @throws ValidationException
     * @throws \Throwable
     */
    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        DB::transaction(function () use ($operation, $user) {
            $payload = $operation->payload;
            $source  = LocationOrgStock::find((int) ($payload['location_org_stock_id'] ?? 0));
            $targets = $this->rollbackTargets($operation, $source);

            if ($source) {
                $this->lockLocationOrgStocks(array_merge([$source->id], array_map(fn (array $target) => $target['row']->id, array_filter($targets, fn (array $target) => $target['row'] !== null))));
            }

            if ($refusal = $this->rollbackRefusal($operation)) {
                $this->refusedRollback($refusal);
            }

            $createdRowIds = $this->createdEffectIds($operation, new LocationOrgStock());

            foreach ($targets as $target) {
                $row = $target['row']->refresh();

                MoveOrgStockToOtherLocation::make()->action($row, $source->refresh(), ['quantity' => $target['quantity']], $user);

                $row->refresh();
                if (in_array($row->id, $createdRowIds, true) && abs((float) $row->quantity) <= self::TOLERANCE) {
                    DeleteLocationOrgStock::make()->action($row, $user);
                }
            }
        });
    }

    private function rollbackRefusal(MobileOfflineOperation $operation): ?string
    {
        if ($operation->counted_since) {
            return __('A location was counted after this move. Count it again instead.');
        }

        $source = LocationOrgStock::find((int) ($operation->payload['location_org_stock_id'] ?? 0));
        if (!$source) {
            return __('The location the stock came from no longer holds this SKO. Count instead.');
        }

        foreach ($this->rollbackTargets($operation, $source) as $target) {
            if (!$target['row'] || (float) $target['row']->quantity + self::TOLERANCE < $target['quantity']) {
                return __('The stock is no longer where it was moved to. Count instead.');
            }
        }

        return null;
    }

    /**
     * @return array<int, array{row: LocationOrgStock|null, quantity: float}>
     */
    private function rollbackTargets(MobileOfflineOperation $operation, ?LocationOrgStock $source): array
    {
        return array_map(function (array $target) use ($source) {
            $row = $target['location_org_stock_id']
                ? LocationOrgStock::find($target['location_org_stock_id'])
                : ($source ? LocationOrgStock::where('location_id', $target['location_id'])->where('org_stock_id', $source->org_stock_id)->first() : null);

            return ['row' => $row, 'quantity' => $target['quantity']];
        }, self::targetsOf($operation->payload ?? []));
    }

    /**
     * @param array<int, int> $locationIds
     * @return array<int, int>
     */
    private function existingRowsAtLocations(int $sourceId, array $locationIds): array
    {
        if ($locationIds === []) {
            return [];
        }

        return LocationOrgStock::whereIn('location_id', $locationIds)
            ->where('org_stock_id', LocationOrgStock::whereKey($sourceId)->select('org_stock_id'))
            ->pluck('id', 'location_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
