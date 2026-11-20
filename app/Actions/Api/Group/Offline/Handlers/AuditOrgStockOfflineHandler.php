<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Inventory\AuditMobileOrgStock;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

class AuditOrgStockOfflineHandler extends AuditOfflineHandler
{
    public function operation(): string
    {
        return 'audit_org_stock';
    }

    /**
     * @return array<string, mixed>
     */
    protected function bodyRules(): array
    {
        return [
            'org_stock_id'                   => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'counts'                         => ['required', 'array', 'min:1'],
            'counts.*'                       => ['required', 'array'],
            'counts.*.location_org_stock_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID, 'distinct'],
            'counts.*.quantity'              => ['required', 'numeric', 'gte:0'],
            'counts.*.reason'                => ['sometimes', 'nullable', Rule::enum(OrgStockMovementReasonEnum::class)],
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
            'subject_type' => 'OrgStock',
            'subject_id'   => (int) $payload['org_stock_id'],
            'parent_type'  => 'OrgStock',
            'parent_id'    => (int) $payload['org_stock_id'],
        ];
    }

    protected function canAudit(User $user, Warehouse $warehouse): bool
    {
        return self::canViewInventory($user, $warehouse) && self::canEditStocks($user, $warehouse);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int> $ids
     * @return Collection<int, LocationOrgStock>|null
     */
    protected function countedLinks(array $payload, Warehouse $warehouse, array $ids): ?Collection
    {
        $orgStock = OrgStock::find((int) $payload['org_stock_id']);
        if (!$orgStock || $orgStock->organisation_id !== $warehouse->organisation_id) {
            return null;
        }

        $links = $this->warehouseLinks($orgStock, $warehouse)->whereIn('id', $ids)->with('location')->get()->keyBy('id');

        return $links->count() === count(array_unique($ids)) ? $links : null;
    }

    /**
     * @param array<int, array<string, mixed>> $counts
     * @param Collection<int, LocationOrgStock> $links
     * @return array<int, array{link: LocationOrgStock, data: array<string, mixed>}>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function audits(array $counts, Collection $links): array
    {
        return app(AuditMobileOrgStock::class)->auditsWithReasons($counts, $links);
    }
}
