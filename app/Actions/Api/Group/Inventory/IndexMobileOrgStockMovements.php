<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\OrgStockMovement\UI\IndexOrgStockMovements;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Http\Resources\Inventory\OrgStockMovementsResource;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileOrgStockMovements
{
    use AsController;
    use WithMobileOrgStocks;

    /**
     * @param array{flow?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, OrgStock $orgStock, array $filters): LengthAwarePaginator
    {
        $this->useWebTableParameters(Arr::only($filters, ['per_page', 'page']));
        request()->merge(['elements' => empty($filters['flow']) ? [] : ['state' => $filters['flow']]]);

        return IndexOrgStockMovements::make()->handle($orgStock, onlyWarehouse: $warehouse);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $movements, OrgStock $orgStock): array
    {
        $listed      = collect($movements->items());
        $locationIds = OrgStockMovement::whereIn('id', $listed->pluck('id'))->pluck('location_id', 'id');

        return $listed->map(function (OrgStockMovement $movement) use ($locationIds, $orgStock) {
            $resource        = OrgStockMovementsResource::make($movement)->resolve();
            $locationId      = $locationIds->get($movement->id);
            $runningQuantity = $resource['running_quantity_org_stock'];

            return [
                'id'                         => $movement->id,
                'date'                       => $movement->date?->toIso8601String(),
                'type'                       => $movement->type->value,
                'type_label'                 => $resource['type_label'],
                'flow'                       => $movement->flow->value,
                ...$this->quantityFields('quantity', $movement->quantity, $orgStock->packed_in),
                'running_quantity_org_stock' => $runningQuantity === '' ? null : (float) $runningQuantity,
                'running_quantity_org_stock_label' => $runningQuantity === '' ? null : $this->stockFractionLabel((float) $runningQuantity, $orgStock->packed_in),
                'location'                   => $locationId ? ['id' => $locationId, 'code' => $movement->getAttribute('location_code')] : null,
                'reason'                     => $movement->reason?->value,
                'reason_label'               => $resource['reason_label'],
                'note'                       => self::plainNote($movement->note),
                'parent_type'                => $movement->parent_type,
                'parent_reference'           => $movement->getAttribute('parent_reference'),
                'user'                       => $this->userReference($movement->user),
            ];
        })->values()->all();
    }

    public static function plainNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $text = Str::squish(html_entity_decode(strip_tags($note), ENT_QUOTES | ENT_HTML5));

        return $text === '' ? null : $text;
    }

    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewInventory($user, $warehouse);
        $this->ensureOrgStockOfWarehouse($orgStock, $warehouse);

        $filters = Validator::make($request->query(), [
            'flow'     => ['sometimes', 'nullable', Rule::enum(OrgStockMovementFlowEnum::class)],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $movements = $this->handle($warehouse, $orgStock, $filters);

        return response()->json([
            'data' => $this->rows($movements, $orgStock),
            'meta' => $this->paginationMeta($movements),
        ]);
    }
}
