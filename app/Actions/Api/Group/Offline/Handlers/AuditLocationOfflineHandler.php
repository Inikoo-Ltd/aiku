<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AuditLocationOfflineHandler extends AuditOfflineHandler
{
    public function operation(): string
    {
        return 'audit_location';
    }

    /**
     * @return array<string, mixed>
     */
    protected function bodyRules(): array
    {
        return [
            'location_id'                    => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'counts'                         => ['required', 'array', 'min:1'],
            'counts.*'                       => ['required', 'array'],
            'counts.*.location_org_stock_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID, 'distinct'],
            'counts.*.quantity'              => ['required', 'numeric', 'gte:0'],
            'counts.*.reason'                => ['required', Rule::enum(OrgStockMovementReasonEnum::class)],
            'counts.*.note'                  => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{subject_type: string, subject_id: int, parent_type: ?string, parent_id: ?int}
     */
    public function subject(array $payload): array
    {
        return [
            'subject_type' => 'LocationOrgStock',
            'subject_id'   => (int) $payload['counts'][0]['location_org_stock_id'],
            'parent_type'  => 'Location',
            'parent_id'    => (int) $payload['location_id'],
        ];
    }

    protected function canAudit(User $user, Warehouse $warehouse): bool
    {
        return self::canViewLocations($user, $warehouse) && self::canEditStocks($user, $warehouse);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int> $ids
     * @return Collection<int, LocationOrgStock>|null
     */
    protected function countedLinks(array $payload, Warehouse $warehouse, array $ids): ?Collection
    {
        $location = Location::find((int) $payload['location_id']);
        if (!$location || $location->warehouse_id !== $warehouse->id) {
            return null;
        }

        $links = LocationOrgStock::with('location')->where('location_id', $location->id)->whereIn('id', $ids)->get()->keyBy('id');

        return $links->count() === count(array_unique($ids)) ? $links : null;
    }

    /**
     * @param array<int, array<string, mixed>> $counts
     * @param Collection<int, LocationOrgStock> $links
     * @return array<int, array{link: LocationOrgStock, data: array<string, mixed>}>
     */
    protected function audits(array $counts, Collection $links): array
    {
        return array_map(fn (array $count) => [
            'link' => $links->get((int) $count['location_org_stock_id']),
            'data' => Arr::only($count, ['quantity', 'reason', 'note']),
        ], $counts);
    }
}
