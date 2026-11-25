<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-10h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileDeliveryNoteCounts
{
    use AsController;
    use WithMobileDeliveryNotes;
    use WithMobileModuleAccess;

    public const array STATE_BUCKETS = ['unassigned', 'queued', 'handling', 'handling_blocked', 'picked', 'packing', 'packed', 'finalised', 'dispatched'];

    /**
     * @param array<string, mixed> $filters
     * @return array{buckets: array<string, int>, channels: array<int, array{channel: string, label: string, buckets: array<string, int>}>}
     */
    public function handle(Warehouse $warehouse, User $user, array $filters = []): array
    {
        $this->useWebDeliveryNotesSearch($filters['search'] ?? null);

        $channels = [];
        foreach ($this->channelLabels() as $channel => $label) {
            $buckets = $this->bucketCounts($warehouse, $user, $channel, $filters);
            if ($buckets['in_warehouse'] + $buckets['dispatched'] > 0) {
                $channels[] = [
                    'channel' => $channel,
                    'label'   => $label,
                    'buckets' => $buckets,
                ];
            }
        }

        return [
            'buckets'  => $this->bucketCounts($warehouse, $user, 'all', $filters),
            'channels' => $channels,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    private function bucketCounts(Warehouse $warehouse, User $user, string $channel, array $filters): array
    {
        $query = $this->deliveryNotesQuery($warehouse, 'all', $channel, $filters['picker'] ?? null, $user);
        $this->whereListFilters($query, $warehouse, $filters);

        $stateCounts = $query
            ->reorder()
            ->toBase()
            ->select('delivery_notes.state')
            ->selectRaw('count(*) as number_delivery_notes')
            ->groupBy('delivery_notes.state')
            ->pluck('number_delivery_notes', 'state')
            ->map(fn ($count) => (int) $count);

        $buckets = [];
        foreach (self::STATE_BUCKETS as $state) {
            $buckets[$state] = $stateCounts->get($state, 0);
        }

        $buckets['in_warehouse'] = $stateCounts
            ->except([DeliveryNoteStateEnum::DISPATCHED->value, DeliveryNoteStateEnum::CANCELLED->value])
            ->sum();

        return $buckets;
    }

    /**
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            'b2b'                                => __('Wholesale'),
            'dropshipping'                       => __('Dropshipping'),
            'external'                           => __('Marketplaces'),
            'b2c'                                => __('B2C'),
            IndexDeliveryNotes::PARTNERS_CHANNEL => __('Partners'),
            'fulfilment'                         => __('Fulfilment'),
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(self::canViewGoodsOut($user, $warehouse), 403);

        $filters = Validator::make($request->query(), [
            'picker'  => ['sometimes', 'nullable', Rule::in(self::PICKER_FILTERS)],
            'search'  => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->dateRangeRules($request->query()),
            'overdue' => ['sometimes', 'boolean'],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, $user, $filters)]);
    }
}
