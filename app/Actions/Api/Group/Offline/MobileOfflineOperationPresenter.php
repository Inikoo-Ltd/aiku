<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\MobileOfflineOperationEffect;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobileOfflineOperationPresenter
{
    public const array RECORD_TYPES = [
        'DeliveryNote'       => 'delivery_note',
        'ReturnDeliveryNote' => 'return',
        'StockDelivery'      => 'stock_delivery',
        'Location'           => 'location',
        'OrgStock'           => 'org_stock',
    ];

    public function __construct(private readonly MobileOfflineEngine $engine)
    {
    }

    /**
     * @param array{operation: ?MobileOfflineOperation, status: string, sequence: int, expected_sequence: ?int} $outcome
     * @param array<int, array<int, array<string, mixed>>> $versions
     * @return array<string, mixed>
     */
    public function syncResult(array $outcome, array $versions): array
    {
        $operation = $outcome['operation'];
        if ($operation === null) {
            return [
                ...$this->emptyResult($outcome['sequence'], $outcome['status']),
                'message' => $outcome['status'] === 'missing_previous'
                    ? __('Send action :sequence first.', ['sequence' => $outcome['expected_sequence']])
                    : __('Already received.'),
            ];
        }

        $result = $this->result($operation, $versions);
        if ($outcome['status'] === 'already_done') {
            $result['status'] = 'already_done';
        }

        return $result;
    }

    /**
     * @param Collection<int, MobileOfflineOperation> $operations
     * @return array<int, array<int, array{location_org_stock_id: int, version: int, quantity: float}>>
     */
    public function versionsOf(Collection $operations): array
    {
        $operations = new EloquentCollection($operations->filter()->values()->all());
        if ($operations->isEmpty()) {
            return [];
        }

        $operations->loadMissing(['blockedBy:id,device_sequence', 'staffTask:id,reference', 'conflictingUser:id,contact_name,username']);

        $created = MobileOfflineOperationEffect::query()
            ->whereIn('mobile_offline_operation_id', $operations->pluck('id'))
            ->where('model_type', 'LocationOrgStock')
            ->get(['mobile_offline_operation_id', 'model_id'])
            ->groupBy('mobile_offline_operation_id');

        $idsByOperation = $operations->mapWithKeys(fn (MobileOfflineOperation $operation) => [
            $operation->id => collect($operation->touchedLocationOrgStockIds())
                ->merge($created->get($operation->id, collect())->pluck('model_id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ]);

        $rows = MobileOfflineEngine::locationRows($idsByOperation->flatten()->unique()->values()->all());

        return $idsByOperation->map(fn (array $ids) => collect($ids)
            ->filter(fn (int $id) => isset($rows[$id]))
            ->map(fn (int $id) => ['location_org_stock_id' => $id, ...$rows[$id]])
            ->values()
            ->all())->all();
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $versions
     * @return array<string, mixed>
     */
    public function result(MobileOfflineOperation $operation, array $versions): array
    {
        $answer = $operation->server_snapshot['answer'] ?? [];

        return [
            'sequence'            => $operation->device_sequence,
            'status'              => $operation->state,
            'operation_id'        => $operation->id,
            'operation'           => $operation->operation,
            'reason'              => $operation->outcome_reason,
            'instruction'         => $answer['instruction'] ?? MobileOfflineOutcome::INSTRUCTION_NONE,
            'message'             => $answer['message'] ?? null,
            'requested_quantity'  => $operation->requested_quantity,
            'applied_quantity'    => $operation->applied_quantity,
            'counted_since'       => (bool) $operation->counted_since,
            'task_reference'      => $operation->staffTask?->reference,
            'conflicting_user'    => $operation->conflictingUser ? [
                'id'   => $operation->conflictingUser->id,
                'name' => $operation->conflictingUser->contact_name ?? $operation->conflictingUser->username,
            ] : null,
            'blocked_by_sequence' => $operation->blockedBy?->device_sequence,
            'versions'            => $versions[$operation->id] ?? [],
        ];
    }

    /**
     * @param Collection<int, MobileOfflineOperation> $operations
     * @return array<int, array<string, mixed>>
     */
    public function results(Collection $operations): array
    {
        $versions = $this->versionsOf($operations);

        return $operations->map(fn (MobileOfflineOperation $operation) => $this->result($operation, $versions))->values()->all();
    }

    /**
     * @param Collection<int, MobileOfflineOperation> $operations
     * @return array<int, array<string, mixed>>
     */
    public function items(Collection $operations, User $viewer): array
    {
        $versions = $this->versionsOf($operations);
        $labels   = $this->labels($operations);

        return $operations->map(fn (MobileOfflineOperation $operation) => [
            ...$this->result($operation, $versions),
            'performed_at' => $operation->performed_at?->toIso8601ZuluString('millisecond'),
            'synced_at'    => $operation->synced_at?->toIso8601ZuluString('millisecond'),
            'state'        => $operation->state,
            'resolution'   => $operation->resolution,
            'labels'       => $labels[$operation->id] ?? ['title' => null, 'subtitle' => null, 'location_code' => null],
            'record'       => isset(self::RECORD_TYPES[$operation->parent_type]) && $operation->parent_id ? [
                'type' => self::RECORD_TYPES[$operation->parent_type],
                'id'   => (int) $operation->parent_id,
            ] : null,
            'resolutions'  => $this->engine->allowedResolutions($operation, $viewer),
            'can_rollback' => $this->engine->canRollback($operation, $viewer),
            'counted_rows' => $this->countedRows($operation),
        ])->values()->all();
    }

    /**
     * @return array<int, array{location_code: ?string, counted: ?float, quantity: ?float}>
     */
    private function countedRows(MobileOfflineOperation $operation): array
    {
        $rows = data_get($operation->server_snapshot, 'rows');
        if (!is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && array_key_exists('counted', $row))
            ->map(fn (array $row) => [
                'location_code' => $row['location_code'] ?? null,
                'counted'       => isset($row['counted']) ? (float) $row['counted'] : null,
                'quantity'      => isset($row['quantity']) ? (float) $row['quantity'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(int $sequence, string $status): array
    {
        return [
            'sequence'            => $sequence,
            'status'              => $status,
            'operation_id'        => null,
            'operation'           => null,
            'reason'              => null,
            'instruction'         => MobileOfflineOutcome::INSTRUCTION_NONE,
            'message'             => null,
            'requested_quantity'  => null,
            'applied_quantity'    => null,
            'counted_since'       => false,
            'task_reference'      => null,
            'conflicting_user'    => null,
            'blocked_by_sequence' => null,
            'versions'            => [],
        ];
    }

    /**
     * @param Collection<int, MobileOfflineOperation> $operations
     * @return array<int, array{title: ?string, subtitle: ?string, location_code: ?string}>
     */
    private function labels(Collection $operations): array
    {
        $idsOf = fn (string $type) => $operations->where('subject_type', $type)->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $deliveryNoteItems = DeliveryNoteItem::query()->with(['deliveryNote:id,reference', 'orgStock:id,code,name'])->findMany($idsOf('DeliveryNoteItem'), ['id', 'delivery_note_id', 'org_stock_id'])->keyBy('id');
        $lineNumbers       = $this->lineNumbers($deliveryNoteItems->pluck('delivery_note_id')->unique()->values()->all());
        $returnItems       = ReturnDeliveryNoteItem::query()->with(['returnDeliveryNote:id,reference', 'orgStock:id,code,name'])->findMany($idsOf('ReturnDeliveryNoteItem'), ['id', 'return_delivery_note_id', 'org_stock_id'])->keyBy('id');
        $stockDeliveryItems = StockDeliveryItem::query()->with(['stockDelivery:id,reference', 'orgStock:id,code,name'])->findMany($idsOf('StockDeliveryItem'), ['id', 'stock_delivery_id', 'org_stock_id'])->keyBy('id');
        $orgStocks          = OrgStock::query()->findMany($idsOf('OrgStock'), ['id', 'code', 'name'])->keyBy('id');

        $payloadLocationOrgStockIds = $operations->map(fn (MobileOfflineOperation $operation) => $operation->payload['location_org_stock_id'] ?? null)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);
        $locationOrgStocks          = LocationOrgStock::query()->with(['location:id,code', 'orgStock:id,code,name'])
            ->findMany($payloadLocationOrgStockIds->merge($idsOf('LocationOrgStock'))->unique()->values()->all(), ['id', 'location_id', 'org_stock_id'])
            ->keyBy('id');
        $locations = Location::query()->findMany(
            $operations->map(fn (MobileOfflineOperation $operation) => $operation->payload['location_id'] ?? null)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            ['id', 'code']
        )->keyBy('id');

        $skoLabel = fn (?OrgStock $orgStock) => $orgStock ? trim($orgStock->code.' '.$orgStock->name) : null;

        return $operations->mapWithKeys(function (MobileOfflineOperation $operation) use ($deliveryNoteItems, $lineNumbers, $returnItems, $stockDeliveryItems, $orgStocks, $locationOrgStocks, $locations, $skoLabel) {
            $payload          = $operation->payload ?? [];
            $locationOrgStock = is_numeric($payload['location_org_stock_id'] ?? null) ? $locationOrgStocks->get((int) $payload['location_org_stock_id']) : null;
            $locationCode     = $locationOrgStock?->location?->code
                ?? (is_numeric($payload['location_id'] ?? null) ? $locations->get((int) $payload['location_id'])?->code : null);
            $subjectId        = (int) $operation->subject_id;

            [$title, $subtitle] = match ($operation->subject_type) {
                'DeliveryNoteItem' => ($item = $deliveryNoteItems->get($subjectId)) ? [
                    __(':reference · line :line', ['reference' => $item->deliveryNote?->reference, 'line' => $lineNumbers[$item->id] ?? '?']),
                    $skoLabel($item->orgStock),
                ] : [null, null],
                'ReturnDeliveryNoteItem' => ($item = $returnItems->get($subjectId)) ? [$item->returnDeliveryNote?->reference, $skoLabel($item->orgStock)] : [null, null],
                'StockDeliveryItem'      => ($item = $stockDeliveryItems->get($subjectId)) ? [$item->stockDelivery?->reference, $skoLabel($item->orgStock)] : [null, null],
                'LocationOrgStock'       => ($item = $locationOrgStocks->get($subjectId)) ? [$item->location?->code, $skoLabel($item->orgStock)] : [null, null],
                'OrgStock'               => ($item = $orgStocks->get($subjectId)) ? [$item->code, $skoLabel($item)] : [null, null],
                default                  => [null, null],
            };

            if ($operation->subject_type === 'LocationOrgStock' && $locationCode === null) {
                $locationCode = $title;
            }

            return [$operation->id => ['title' => $title, 'subtitle' => $subtitle, 'location_code' => $locationCode]];
        })->all();
    }

    /**
     * @param array<int, int> $deliveryNoteIds
     * @return array<int, int>
     */
    private function lineNumbers(array $deliveryNoteIds): array
    {
        if ($deliveryNoteIds === []) {
            return [];
        }

        return DB::table('delivery_note_items')
            ->whereIn('delivery_note_id', $deliveryNoteIds)
            ->selectRaw('id, row_number() over (partition by delivery_note_id order by id) as line')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->id => (int) $row->line])
            ->all();
    }
}
