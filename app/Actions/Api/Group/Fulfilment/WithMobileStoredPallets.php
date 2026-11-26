<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

trait WithMobileStoredPallets
{
    use WithMobileFulfilment;

    public const int MAX_ID = 2147483647;
    public const int MAX_PER_PAGE = 100;
    public const int DEFAULT_PER_PAGE = 25;

    public static function canMovePallets(User $user, Warehouse $warehouse): bool
    {
        return static::canEditFulfilmentWarehouse($user, $warehouse)
            || ($warehouse->group_id === $user->group_id && $user->authTo("locations.$warehouse->id.edit"));
    }

    public static function canAuditPalletStoredItems(User $user, Warehouse $warehouse): bool
    {
        return $warehouse->group_id === $user->group_id && $user->authTo([
            "fulfilment.$warehouse->id.edit",
            "supervisor-fulfilment.$warehouse->id",
        ]);
    }

    public static function palletTypeLabel(PalletTypeEnum $type): string
    {
        return match ($type) {
            PalletTypeEnum::PALLET   => __('Pallet'),
            PalletTypeEnum::BOX      => __('Box'),
            PalletTypeEnum::OVERSIZE => __('Oversize'),
        };
    }

    protected function ensureCanViewPallets(User $user, Warehouse $warehouse): void
    {
        $this->ensureFulfilmentAccess($user, $warehouse, static::canViewFulfilment($user, $warehouse), __('You can not see the pallets of this warehouse.'));
    }

    protected function ensurePalletOfWarehouse(Pallet $pallet, Warehouse $warehouse): void
    {
        abort_unless($pallet->warehouse_id === $warehouse->id, 404);
    }

    protected function ensureCanChangePallet(User $user, Warehouse $warehouse, Pallet $pallet, bool $allowed, string $refusal): void
    {
        $this->ensureFulfilmentAccess($user, $warehouse, $allowed || static::canViewFulfilment($user, $warehouse), $refusal);
        $this->ensurePalletOfWarehouse($pallet, $warehouse);
        abort_unless($allowed, 403, $refusal);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    protected function underFulfilmentLock(string $model, int $id, Closure $callback): mixed
    {
        try {
            return Cache::lock("mobile-fulfilment:$model:$id", 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            $this->refuseState(__('Someone else is updating this right now; try again.'));
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function palletLocationRules(): array
    {
        return [
            'location_id'   => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ID, 'required_without:location_code'],
            'location_code' => ['nullable', 'string', 'max:255', 'required_without:location_id'],
        ];
    }

    /**
     * @param array{location_id?: int|null, location_code?: string|null} $data
     *
     * @throws ValidationException
     */
    protected function palletLocation(Warehouse $warehouse, array $data): Location
    {
        $field    = empty($data['location_id']) ? 'location_code' : 'location_id';
        $location = $field === 'location_id'
            ? Location::withTrashed()->find($data['location_id'])
            : $this->locationByCode($warehouse, (string) $data['location_code']);

        $refusal = match (true) {
            !$location || $location->warehouse_id !== $warehouse->id || $location->trashed() => __('That location is not in this warehouse.'),
            $location->status !== LocationStatusEnum::OPERATIONAL                         => __('This location is not operational.'),
            !$location->allow_fulfilment                                                  => __('This location does not take pallets.'),
            default                                                                       => null,
        };

        if ($refusal) {
            throw ValidationException::withMessages([$field => $refusal]);
        }

        return $location;
    }

    private function locationByCode(Warehouse $warehouse, string $code): ?Location
    {
        $code   = trim($code);
        $search = fn (string $column) => Location::where('warehouse_id', $warehouse->id)
            ->whereRaw("lower($column) = lower(?)", [$code])
            ->orderBy('id')
            ->first();

        return $search('code') ?? $search('barcode');
    }

    /**
     * @param LengthAwarePaginator $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function palletRowRelations(): array
    {
        return ['fulfilmentCustomer.customer', 'location', 'palletReturn'];
    }

    /**
     * @param iterable<Pallet> $pallets
     * @return array<int, array<string, mixed>>
     */
    protected function palletRows(iterable $pallets): array
    {
        $reporters = $this->palletReporters($pallets);

        return collect($pallets)->map(fn (Pallet $pallet) => $this->palletRow($pallet, $reporters))->values()->all();
    }

    /**
     * @param iterable<Pallet> $pallets
     * @return Collection<int, User>
     */
    protected function palletReporters(iterable $pallets): Collection
    {
        $reporterIds = collect($pallets)
            ->filter(fn (Pallet $pallet) => $pallet->status === PalletStatusEnum::INCIDENT)
            ->map(fn (Pallet $pallet) => $pallet->incident_report->reporter_id ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($reporterIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $reporterIds)->get(['id', 'contact_name', 'username'])->keyBy('id');
    }

    /**
     * @param Collection<int, User> $reporters
     * @return array<string, mixed>
     */
    protected function palletRow(Pallet $pallet, Collection $reporters): array
    {
        return [
            'id'                  => $pallet->id,
            'reference'           => $pallet->reference,
            'customer_reference'  => $pallet->customer_reference,
            'type'                => $pallet->type->value,
            'type_label'          => static::palletTypeLabel($pallet->type),
            'state'               => $pallet->state->value,
            'state_label'         => PalletStateEnum::labels()[$pallet->state->value],
            'status'              => $pallet->status->value,
            'status_label'        => PalletStatusEnum::labels()[$pallet->status->value],
            'customer'            => $this->fulfilmentCustomerIdAndName($pallet->fulfilmentCustomer),
            'location'            => $pallet->location ? ['id' => $pallet->location->id, 'code' => $pallet->location->code] : null,
            'notes'               => $pallet->notes ?: null,
            'number_stored_items' => (int) ($pallet->pallet_stored_items_count ?? $pallet->palletStoredItems()->count()),
            'incident'            => $this->palletIncident($pallet, $reporters),
        ];
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    protected function fulfilmentCustomerIdAndName(?FulfilmentCustomer $fulfilmentCustomer): ?array
    {
        if (!$fulfilmentCustomer) {
            return null;
        }

        return [
            'id'   => $fulfilmentCustomer->id,
            'name' => $fulfilmentCustomer->customer?->name,
        ];
    }

    /**
     * @param Collection<int, User> $reporters
     * @return array<string, mixed>|null
     */
    protected function palletIncident(Pallet $pallet, Collection $reporters): ?array
    {
        if ($pallet->status !== PalletStatusEnum::INCIDENT) {
            return null;
        }

        $report     = (array) $pallet->incident_report;
        $reporterId = $report['reporter_id'] ?? null;
        $reporter   = $reporterId ? $reporters->get($reporterId) : null;
        $message    = $pallet->state === PalletStateEnum::OTHER_INCIDENT
            ? ($report['notes'] ?? null)
            : ($report['message'] ?? $report['notes'] ?? null);

        return [
            'state'       => $pallet->state->value,
            'state_label' => PalletStateEnum::labels()[$pallet->state->value],
            'message'     => $message ?: null,
            'reporter'    => $reporter ? ['id' => $reporter->id, 'name' => $reporter->contact_name ?? $reporter->username] : null,
            'date'        => $this->isoDateTime($report['date'] ?? $pallet->set_as_incident_at),
            'return'      => $pallet->palletReturn ? ['id' => $pallet->palletReturn->id, 'reference' => $pallet->palletReturn->reference] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function palletDetail(Warehouse $warehouse, Pallet $pallet, User $user): array
    {
        /** @var Pallet $pallet */
        $pallet = Pallet::with([...$this->palletRowRelations(), 'palletDelivery', 'palletStoredItems.storedItem'])
            ->withCount('palletStoredItems')
            ->findOrFail($pallet->id);

        $itemsStorage = (bool) $pallet->fulfilmentCustomer?->items_storage;
        $openAuditId  = $pallet->storedItemAudits()->where('state', StoredItemAuditStateEnum::IN_PROCESS)->orderBy('id')->value('id');

        return [
            ...$this->palletRow($pallet, $this->palletReporters([$pallet])),
            'delivery'          => $pallet->palletDelivery ? ['id' => $pallet->palletDelivery->id, 'reference' => $pallet->palletDelivery->reference] : null,
            'return'            => $pallet->palletReturn ? ['id' => $pallet->palletReturn->id, 'reference' => $pallet->palletReturn->reference] : null,
            'received_at'       => $this->isoDateTime($pallet->received_at),
            'booked_in_at'      => $this->isoDateTime($pallet->booked_in_at),
            'storing_at'        => $this->isoDateTime($pallet->storing_at),
            'items_storage'     => $itemsStorage,
            'stored_items'      => $itemsStorage ? $this->palletStoredItemRows($pallet) : [],
            'open_audit_id'     => $openAuditId,
            'available_actions' => $this->palletActions($warehouse, $pallet, $user, $itemsStorage, $openAuditId !== null),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function palletStoredItemRows(Pallet $pallet): array
    {
        return $pallet->palletStoredItems
            ->filter(fn (PalletStoredItem $palletStoredItem) => $palletStoredItem->storedItem !== null)
            ->sortBy(fn (PalletStoredItem $palletStoredItem) => [$palletStoredItem->storedItem->reference, $palletStoredItem->id])
            ->map(fn (PalletStoredItem $palletStoredItem) => [
                'id'          => $palletStoredItem->storedItem->id,
                'reference'   => $palletStoredItem->storedItem->reference,
                'name'        => $palletStoredItem->storedItem->name,
                'barcode'     => $palletStoredItem->storedItem->barcode,
                'quantity'    => (int) $palletStoredItem->quantity,
                'state'       => $palletStoredItem->state->value,
                'state_label' => PalletStoredItemStateEnum::labels()[$palletStoredItem->state->value],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function palletActions(Warehouse $warehouse, Pallet $pallet, User $user, bool $itemsStorage, bool $hasOpenAudit): array
    {
        if ($pallet->status === PalletStatusEnum::INCIDENT) {
            return static::canEditFulfilmentWarehouse($user, $warehouse) ? [$this->actionOption('back_to_storing', __('Set back to storing'))] : [];
        }

        if ($pallet->status !== PalletStatusEnum::STORING) {
            return [];
        }

        $actions = [];

        if (static::canMovePallets($user, $warehouse)) {
            $actions[] = $this->actionOption('move', __('Move'));
        }

        if (static::canEditFulfilmentWarehouse($user, $warehouse)) {
            $actions[] = $this->actionOption('damaged', __('Damaged'));
            $actions[] = $this->actionOption('lost', __('Lost'));
        }

        if ($itemsStorage && static::canAuditPalletStoredItems($user, $warehouse)) {
            $actions[] = $this->actionOption('audit', $hasOpenAudit ? __('Continue audit') : __('Audit stored items'));
        }

        return $actions;
    }

    protected function respondWithPallet(Warehouse $warehouse, Pallet $pallet, User $user, string $status, string $message): JsonResponse
    {
        return response()->json([
            'data'   => $this->palletDetail($warehouse, $pallet, $user),
            'result' => ['status' => $status, 'message' => $message],
        ]);
    }

    /**
     * @param EloquentCollection<int, Pallet> $pallets
     * @return EloquentCollection<int, Pallet>
     */
    protected function withPalletRowRelations(EloquentCollection $pallets): EloquentCollection
    {
        return $pallets->load($this->palletRowRelations())->loadCount('palletStoredItems');
    }
}
